<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Capabilities;
use ProjectReviews\Rest_Bootstrap;

final class Admin_Role_Editor
{
    private const PER_PAGE = 20;

    public static function render_page(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'scorva'));
        }

        $page    = max(1, (int) ($_GET['paged'] ?? 1));
        $offset  = ($page - 1) * self::PER_PAGE;

        $scorva_roles = [
            Capabilities::ROLE_COORDINATOR,
            Capabilities::ROLE_HOD,
            Capabilities::ROLE_REVIEWER,
        ];

        $all_assigned = get_users([
            'role__in' => $scorva_roles,
            'fields'   => 'ID',
        ]);
        $total = is_array($all_assigned) ? count($all_assigned) : 0;
        $pages = max(1, (int) ceil($total / self::PER_PAGE));

        $users = get_users([
            'role__in' => $scorva_roles,
            'number'   => self::PER_PAGE,
            'offset'   => $offset,
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ]);

        $rest_url = rest_url(Rest_Bootstrap::NAMESPACE . '/admin/users');
        $nonce    = wp_create_nonce('wp_rest');
        ?>
        <div class="scorva-admin-page">
            <div class="scorva-admin-page__accent-bar"></div>

            <?php self::render_page_header(); ?>

            <?php self::render_assign_card(); ?>

            <?php self::render_assignments_card($users, $total, $page, $pages); ?>

            <?php self::render_matrix_card(); ?>
        </div>

        <div id="scorva-toast" class="scorva-toast" aria-live="polite"></div>

        <script>
        (function () {
            var REST_URL  = <?php echo wp_json_encode($rest_url); ?>;
            var NONCE     = <?php echo wp_json_encode($nonce); ?>;
            var ROLES     = <?php echo wp_json_encode(self::role_label_map()); ?>;

            /* ── helpers ─────────────────────────────────── */
            function apiFetch(url, opts) {
                opts = opts || {};
                opts.credentials = 'same-origin';
                opts.headers = Object.assign({ 'X-WP-Nonce': NONCE }, opts.headers || {});
                return fetch(url, opts).then(function (r) {
                    return r.json().then(function (body) {
                        if (!r.ok) throw new Error(body.message || 'Request failed');
                        return body;
                    });
                });
            }

            function showToast(msg, type) {
                var el = document.getElementById('scorva-toast');
                if (!el) return;
                el.textContent = msg;
                el.className = 'scorva-toast scorva-toast--' + (type || 'success') + ' scorva-toast--visible';
                clearTimeout(el._timer);
                el._timer = setTimeout(function () { el.className = 'scorva-toast'; }, 3200);
            }

            function roleBadgeHtml(role) {
                if (!role) return '<span class="scorva-role-badge scorva-role-badge--none">None</span>';
                var label = ROLES[role] || role;
                var cls   = role.replace('project_reviews_', '');
                return '<span class="scorva-role-badge scorva-role-badge--' + cls + '">' + label + '</span>';
            }

            function roleSelectHtml(currentRole, userId) {
                var opts = '<option value="">None</option>';
                Object.keys(ROLES).forEach(function (r) {
                    opts += '<option value="' + r + '"' + (currentRole === r ? ' selected' : '') + '>' + ROLES[r] + '</option>';
                });
                return '<select class="scorva-inline-role-select" data-user="' + userId + '">' + opts + '</select>';
            }

            /* ── user search ─────────────────────────────── */
            var searchInput   = document.getElementById('scorva-user-search');
            var searchResults = document.getElementById('scorva-search-results');
            var assignRole    = document.getElementById('scorva-assign-role');
            var assignBtn     = document.getElementById('scorva-assign-btn');
            var searchTimer;

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(searchTimer);
                    var q = searchInput.value.trim();
                    if (q.length < 2) { searchResults.innerHTML = ''; searchResults.hidden = true; return; }
                    searchTimer = setTimeout(function () { doSearch(q); }, 280);
                });

                document.addEventListener('click', function (e) {
                    if (!searchResults.contains(e.target) && e.target !== searchInput) {
                        searchResults.hidden = true;
                    }
                });
            }

            function doSearch(q) {
                searchResults.innerHTML = '<div class="scorva-search-loading">Searching…</div>';
                searchResults.hidden = false;
                apiFetch(REST_URL + '?search=' + encodeURIComponent(q))
                    .then(function (data) {
                        if (!data.length) {
                            searchResults.innerHTML = '<div class="scorva-search-empty">No WordPress users match your search.</div>';
                            return;
                        }
                        searchResults.innerHTML = data.map(function (u) {
                            return '<div class="scorva-search-row" data-id="' + u.id + '" data-name="' + escHtml(u.name) + '" data-role="' + (u.scorva_role || '') + '">' +
                                '<span class="scorva-avatar scorva-avatar--' + (u.scorva_role ? u.scorva_role.replace('project_reviews_', '') : 'none') + '">' + escHtml(initials(u.name)) + '</span>' +
                                '<span class="scorva-search-row__info"><span class="scorva-search-row__name">' + escHtml(u.name) + '</span> <span class="scorva-search-row__email">' + escHtml(u.email) + '</span></span>' +
                                (u.scorva_role ? roleBadgeHtml(u.scorva_role) : '') +
                                '<button type="button" class="button button-small scorva-add-btn">Add</button>' +
                                '</div>';
                        }).join('');
                    })
                    .catch(function (err) {
                        searchResults.innerHTML = '<div class="scorva-search-empty">' + escHtml(err.message) + '</div>';
                    });
            }

            if (searchResults) {
                searchResults.addEventListener('click', function (e) {
                    var btn = e.target.closest('.scorva-add-btn');
                    if (!btn) return;
                    var row = btn.closest('.scorva-search-row');
                    var uid  = row.dataset.id;
                    var name = row.dataset.name;
                    var role = row.dataset.role || '';
                    openAssignPanel(uid, name, role);
                });
            }

            var assignPanel  = document.getElementById('scorva-assign-panel');
            var assignLabel  = document.getElementById('scorva-assign-label');
            var assignSelect = document.getElementById('scorva-assign-role');
            var assignSave   = document.getElementById('scorva-assign-save');
            var assignCancel = document.getElementById('scorva-assign-cancel');
            var _currentUid;

            function openAssignPanel(uid, name, currentRole) {
                _currentUid = uid;
                if (assignLabel) assignLabel.textContent = 'Assign role to ' + name;
                if (assignSelect) assignSelect.value = currentRole || '';
                if (assignPanel) assignPanel.hidden = false;
                searchResults.hidden = true;
                searchInput.value = name;
            }

            if (assignSave) {
                assignSave.addEventListener('click', function () {
                    saveRole(_currentUid, assignSelect ? assignSelect.value || null : null, function () {
                        if (assignPanel) assignPanel.hidden = true;
                        searchInput.value = '';
                        showToast('Role saved. Reload to see updated table.', 'success');
                    });
                });
            }

            if (assignCancel) {
                assignCancel.addEventListener('click', function () {
                    if (assignPanel) assignPanel.hidden = true;
                    searchInput.value = '';
                });
            }

            /* ── role table inline actions ───────────────── */
            var table = document.getElementById('scorva-role-table');
            if (table) {
                table.addEventListener('click', function (e) {
                    var editBtn   = e.target.closest('.scorva-row-edit');
                    var saveBtn   = e.target.closest('.scorva-row-save');
                    var cancelBtn = e.target.closest('.scorva-row-cancel');
                    var removeBtn = e.target.closest('.scorva-row-remove');
                    var confirmBtn = e.target.closest('.scorva-row-confirm-remove');
                    var abortBtn  = e.target.closest('.scorva-row-abort-remove');

                    var row = e.target.closest('tr');
                    if (!row) return;
                    var uid = row.dataset.userId;

                    if (editBtn) {
                        var cell = row.querySelector('.scorva-role-cell');
                        var currentRole = row.dataset.role || '';
                        cell.innerHTML = roleSelectHtml(currentRole, uid) +
                            ' <button type="button" class="scorva-icon-btn scorva-row-save" title="Save">&#10003;</button>' +
                            ' <button type="button" class="scorva-icon-btn scorva-row-cancel" title="Cancel">&#10005;</button>';
                        editBtn.style.display = 'none';
                        row.querySelector('.scorva-row-remove').style.display = 'none';
                    }

                    if (saveBtn) {
                        var select = row.querySelector('.scorva-inline-role-select');
                        var newRole = select ? select.value || null : null;
                        saveRole(uid, newRole, function (savedRole) {
                            row.dataset.role = savedRole || '';
                            var cell = row.querySelector('.scorva-role-cell');
                            cell.innerHTML = roleBadgeHtml(savedRole || null);
                            restoreRowActions(row);
                            showToast('Role updated.', 'success');
                        });
                    }

                    if (cancelBtn) {
                        var currentRole2 = row.dataset.role || '';
                        var cell2 = row.querySelector('.scorva-role-cell');
                        cell2.innerHTML = roleBadgeHtml(currentRole2 || null);
                        restoreRowActions(row);
                    }

                    if (removeBtn) {
                        var actionsCell = row.querySelector('.scorva-actions-cell');
                        actionsCell.innerHTML =
                            '<span class="scorva-remove-confirm-text">Remove role?</span> ' +
                            '<button type="button" class="button button-small scorva-row-confirm-remove">Yes, remove</button> ' +
                            '<button type="button" class="scorva-icon-btn scorva-row-abort-remove">&#10005;</button>';
                    }

                    if (confirmBtn) {
                        saveRole(uid, null, function () {
                            row.remove();
                            showToast('Role removed.', 'success');
                        });
                    }

                    if (abortBtn) {
                        restoreRowActions(row);
                    }
                });
            }

            function restoreRowActions(row) {
                var actionsCell = row.querySelector('.scorva-actions-cell');
                actionsCell.innerHTML =
                    '<button type="button" class="scorva-icon-btn scorva-row-edit" title="Edit role">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                    '</button> ' +
                    '<button type="button" class="scorva-icon-btn scorva-icon-btn--danger scorva-row-remove" title="Remove role">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
                    '</button>';
            }

            function saveRole(uid, role, cb) {
                apiFetch(REST_URL + '/' + uid + '/role', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role: role }),
                })
                .then(function (data) { if (cb) cb(data.role || null); })
                .catch(function (err) { showToast(err.message, 'error'); });
            }

            /* ── matrix toggle ───────────────────────────── */
            var matrixDetails = document.getElementById('scorva-matrix-details');
            if (matrixDetails) {
                matrixDetails.querySelector('summary').addEventListener('click', function () {
                    var chevron = this.querySelector('.scorva-chevron');
                    if (chevron) chevron.style.transform = matrixDetails.open ? '' : 'rotate(180deg)';
                });
            }

            /* ── utils ───────────────────────────────────── */
            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }
            function initials(name) {
                return name.trim().split(/\s+/).map(function (w) { return w[0] || ''; }).slice(0, 2).join('').toUpperCase();
            }
        }());
        </script>
        <?php
    }

    private static function render_page_header(): void
    {
        ?>
        <div class="scorva-page-header">
            <div class="scorva-page-header__title-group">
                <span class="scorva-page-header__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <div>
                    <h1 class="scorva-page-header__title">
                        <?php esc_html_e('Role Editor', 'scorva'); ?>
                    </h1>
                    <p class="scorva-page-header__subtitle">
                        <?php esc_html_e('Assign Scorva roles to WordPress users and view their access rights.', 'scorva'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_assign_card(): void
    {
        $role_options = self::role_label_map();
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <?php esc_html_e('Assign a User', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <div class="scorva-search-wrap">
                    <div class="scorva-search-field">
                        <svg class="scorva-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input id="scorva-user-search" type="text" class="scorva-search-input"
                            placeholder="<?php esc_attr_e('Search by name or email…', 'scorva'); ?>"
                            autocomplete="off" />
                    </div>
                    <div id="scorva-search-results" class="scorva-search-results" hidden></div>
                </div>

                <div id="scorva-assign-panel" class="scorva-assign-panel" hidden>
                    <p id="scorva-assign-label" class="scorva-assign-panel__label"></p>
                    <select id="scorva-assign-role" class="scorva-select">
                        <option value=""><?php esc_html_e('None', 'scorva'); ?></option>
                        <?php foreach ($role_options as $role_slug => $role_label) : ?>
                            <option value="<?php echo esc_attr($role_slug); ?>">
                                <?php echo esc_html($role_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button id="scorva-assign-save" type="button" class="button button-primary">
                        <?php esc_html_e('Save', 'scorva'); ?>
                    </button>
                    <button id="scorva-assign-cancel" type="button" class="button">
                        <?php esc_html_e('Cancel', 'scorva'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param \WP_User[] $users
     */
    private static function render_assignments_card(array $users, int $total, int $page, int $pages): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <?php esc_html_e('Current Role Assignments', 'scorva'); ?>
            </div>
            <div class="scorva-card__body scorva-card__body--no-pad">
                <?php if (empty($users)) : ?>
                    <div class="scorva-empty-state">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <p class="scorva-empty-state__title">
                            <?php esc_html_e('No roles assigned yet', 'scorva'); ?>
                        </p>
                        <p class="scorva-empty-state__body">
                            <?php esc_html_e('Start by searching for a user above.', 'scorva'); ?>
                        </p>
                    </div>
                <?php else : ?>
                    <table id="scorva-role-table" class="scorva-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('User', 'scorva'); ?></th>
                                <th><?php esc_html_e('Email', 'scorva'); ?></th>
                                <th><?php esc_html_e('Role', 'scorva'); ?></th>
                                <th><?php esc_html_e('Actions', 'scorva'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user) : ?>
                                <?php
                                $scorva_role = self::get_scorva_role($user);
                                $role_slug   = $scorva_role ?: '';
                                $role_class  = $role_slug ? str_replace('project_reviews_', '', $role_slug) : 'none';
                                $initials    = self::initials($user->display_name);
                                ?>
                                <tr data-user-id="<?php echo esc_attr((string) $user->ID); ?>"
                                    data-role="<?php echo esc_attr($role_slug); ?>">
                                    <td>
                                        <div class="scorva-user-cell">
                                            <span class="scorva-avatar scorva-avatar--<?php echo esc_attr($role_class); ?>">
                                                <?php echo esc_html($initials); ?>
                                            </span>
                                            <?php echo esc_html($user->display_name); ?>
                                        </div>
                                    </td>
                                    <td class="scorva-text-muted"><?php echo esc_html($user->user_email); ?></td>
                                    <td class="scorva-role-cell">
                                        <?php echo self::role_badge_html($scorva_role); ?>
                                    </td>
                                    <td class="scorva-actions-cell">
                                        <button type="button" class="scorva-icon-btn scorva-row-edit" title="<?php esc_attr_e('Edit role', 'scorva'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button type="button" class="scorva-icon-btn scorva-icon-btn--danger scorva-row-remove" title="<?php esc_attr_e('Remove role', 'scorva'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6l-1 14H6L5 6"/>
                                                <path d="M10 11v6"/><path d="M14 11v6"/>
                                                <path d="M9 6V4h6v2"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($pages > 1) : ?>
                        <div class="scorva-pagination">
                            <span class="scorva-pagination__summary">
                                <?php
                                $from = (($page - 1) * self::PER_PAGE) + 1;
                                $to   = min($page * self::PER_PAGE, $total);
                                printf(
                                    /* translators: 1: from, 2: to, 3: total */
                                    esc_html__('Showing %1$d–%2$d of %3$d', 'scorva'),
                                    $from,
                                    $to,
                                    $total
                                );
                                ?>
                            </span>
                            <div class="scorva-pagination__links">
                                <?php if ($page > 1) : ?>
                                    <a href="<?php echo esc_url(self::page_url($page - 1)); ?>" class="button">&laquo; <?php esc_html_e('Prev', 'scorva'); ?></a>
                                <?php endif; ?>
                                <?php for ($p = 1; $p <= $pages; $p++) : ?>
                                    <?php if ($p === $page) : ?>
                                        <span class="scorva-pagination__current"><?php echo esc_html((string) $p); ?></span>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url(self::page_url($p)); ?>" class="button button-small"><?php echo esc_html((string) $p); ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $pages) : ?>
                                    <a href="<?php echo esc_url(self::page_url($page + 1)); ?>" class="button"><?php esc_html_e('Next', 'scorva'); ?> &raquo;</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_matrix_card(): void
    {
        $all_caps     = Capabilities::all();
        $role_caps    = Capabilities::role_caps();
        $cap_meta     = self::capability_meta();
        ?>
        <div class="scorva-card">
            <details id="scorva-matrix-details" class="scorva-matrix-details">
                <summary class="scorva-card__header scorva-matrix-summary">
                    <svg class="scorva-card__header-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <?php esc_html_e('Capability Reference', 'scorva'); ?>
                    <svg class="scorva-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:auto;transition:transform .2s;">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </summary>
                <div class="scorva-card__body scorva-card__body--no-pad">
                    <div class="scorva-matrix-wrap">
                        <table class="scorva-matrix-table">
                            <thead>
                                <tr>
                                    <th class="scorva-matrix-cap-col"><?php esc_html_e('Capability', 'scorva'); ?></th>
                                    <th><?php echo self::role_badge_html(Capabilities::ROLE_COORDINATOR); ?></th>
                                    <th><?php echo self::role_badge_html(Capabilities::ROLE_HOD); ?></th>
                                    <th><?php echo self::role_badge_html(Capabilities::ROLE_REVIEWER); ?></th>
                                    <th><?php echo self::role_badge_html('administrator'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_caps as $cap) : ?>
                                    <?php $meta = $cap_meta[$cap] ?? ['label' => $cap, 'desc' => '', 'icon' => null]; ?>
                                    <tr>
                                        <td class="scorva-matrix-cap-col">
                                            <div class="scorva-cap-label">
                                                <?php if (!empty($meta['icon'])) : ?>
                                                    <span class="scorva-cap-icon"><?php echo $meta['icon']; ?></span>
                                                <?php endif; ?>
                                                <span>
                                                    <span class="scorva-cap-label__name"><?php echo esc_html($meta['label']); ?></span>
                                                    <?php if (!empty($meta['desc'])) : ?>
                                                        <br><span class="scorva-cap-label__desc"><?php echo esc_html($meta['desc']); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <?php
                                        $columns = [
                                            Capabilities::ROLE_COORDINATOR => $role_caps[Capabilities::ROLE_COORDINATOR] ?? [],
                                            Capabilities::ROLE_HOD         => $role_caps[Capabilities::ROLE_HOD] ?? [],
                                            Capabilities::ROLE_REVIEWER    => $role_caps[Capabilities::ROLE_REVIEWER] ?? [],
                                            'administrator'                => $all_caps,
                                        ];
                                        foreach ($columns as $caps) :
                                            $has = in_array($cap, $caps, true);
                                        ?>
                                            <td class="scorva-matrix-cell">
                                                <?php if ($has) : ?>
                                                    <svg class="scorva-matrix-check" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                <?php else : ?>
                                                    <span class="scorva-matrix-dash">&mdash;</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>
        </div>
        <?php
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private static function get_scorva_role(\WP_User $user): ?string
    {
        $scorva_roles = [
            Capabilities::ROLE_COORDINATOR,
            Capabilities::ROLE_HOD,
            Capabilities::ROLE_REVIEWER,
        ];

        foreach ($scorva_roles as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return $role;
            }
        }

        return null;
    }

    private static function role_badge_html(?string $role): string
    {
        if ($role === null || $role === '') {
            return '<span class="scorva-role-badge scorva-role-badge--none">' . esc_html__('None', 'scorva') . '</span>';
        }

        $labels = [
            Capabilities::ROLE_COORDINATOR => __('Coordinator', 'scorva'),
            Capabilities::ROLE_HOD         => __('HOD', 'scorva'),
            Capabilities::ROLE_REVIEWER    => __('Reviewer', 'scorva'),
            'administrator'                => __('Admin', 'scorva'),
        ];

        $label = $labels[$role] ?? ucfirst(str_replace(['project_reviews_', '_'], ['', ' '], $role));
        $class = str_replace('project_reviews_', '', $role);

        return '<span class="scorva-role-badge scorva-role-badge--' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    /**
     * @return array<string, string>
     */
    private static function role_label_map(): array
    {
        return [
            Capabilities::ROLE_COORDINATOR => __('Coordinator', 'scorva'),
            Capabilities::ROLE_HOD         => __('HOD', 'scorva'),
            Capabilities::ROLE_REVIEWER    => __('Reviewer', 'scorva'),
        ];
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $chars = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $chars .= mb_strtoupper(mb_substr($w, 0, 1));
        }

        return $chars ?: '?';
    }

    private static function page_url(int $p): string
    {
        return add_query_arg('paged', $p, admin_url('admin.php?page=scorva'));
    }

    /**
     * @return array<string, array{label: string, desc: string, icon: string}>
     */
    private static function capability_meta(): array
    {
        return [
            PR_CAP_MANAGE_SESSIONS  => ['label' => __('Manage sessions', 'scorva'),    'desc' => __('Create and configure review projects', 'scorva'),         'icon' => self::svg_icon('calendar')],
            PR_CAP_UPLOAD_STUDENTS  => ['label' => __('Upload students', 'scorva'),    'desc' => __('Import student rosters', 'scorva'),                       'icon' => self::svg_icon('upload')],
            PR_CAP_MANAGE_PANELS    => ['label' => __('Manage panels', 'scorva'),      'desc' => __('Create and configure review panels', 'scorva'),            'icon' => self::svg_icon('squares')],
            PR_CAP_ASSIGN_REVIEWERS => ['label' => __('Assign reviewers', 'scorva'),   'desc' => __('Add reviewers to panels', 'scorva'),                      'icon' => self::svg_icon('user-plus')],
            PR_CAP_CONFIGURE_WEIGHTS => ['label' => __('Configure weights', 'scorva'), 'desc' => __('Set review and reviewer weights', 'scorva'),               'icon' => self::svg_icon('scale')],
            PR_CAP_CONFIRM_RUBRICS  => ['label' => __('Confirm rubrics', 'scorva'),    'desc' => __('Open marking for a review', 'scorva'),                    'icon' => self::svg_icon('check-badge')],
            PR_CAP_ENTER_MARKS      => ['label' => __('Enter marks', 'scorva'),        'desc' => __('Submit scores as a reviewer', 'scorva'),                  'icon' => self::svg_icon('pencil')],
            PR_CAP_OVERRIDE_MARKS   => ['label' => __('Override marks', 'scorva'),     'desc' => __('Adjust reviewer scores with audit trail', 'scorva'),       'icon' => self::svg_icon('shield-check')],
            PR_CAP_VIEW_REPORTS     => ['label' => __('View reports', 'scorva'),       'desc' => __('Read session and panel reports', 'scorva'),                'icon' => self::svg_icon('chart-bar')],
            PR_CAP_CLOSE_SESSION    => ['label' => __('Close session', 'scorva'),      'desc' => __('Sign off on a completed review project', 'scorva'),        'icon' => self::svg_icon('lock-closed')],
            PR_CAP_MANAGE_SETTINGS  => ['label' => __('Manage settings', 'scorva'),   'desc' => __('Access Scorva admin pages', 'scorva'),                    'icon' => self::svg_icon('cog')],
        ];
    }

    private static function svg_icon(string $name): string
    {
        $icons = [
            'calendar'    => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            'upload'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
            'squares'     => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
            'user-plus'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
            'scale'       => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="3" x2="12" y2="21"/><path d="M5 21h14"/><path d="M5 6l7-3 7 3"/><path d="M5 10l-2 5h4l-2-5z"/><path d="M19 10l-2 5h4l-2-5z"/></svg>',
            'check-badge' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/><polyline points="9 12 11 14 15 10"/></svg>',
            'pencil'      => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            'shield-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
            'chart-bar'   => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>',
            'lock-closed' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            'cog'         => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        ];

        return $icons[$name] ?? '';
    }
}
