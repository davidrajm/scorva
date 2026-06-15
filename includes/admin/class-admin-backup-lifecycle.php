<?php

declare(strict_types=1);

namespace ProjectReviews\Admin;

use ProjectReviews\Rest_Bootstrap;
use ProjectReviews\Services\PluginSettings;
use ProjectReviews\Services\SmtpService;

final class Admin_Backup_Lifecycle
{
    private const NONCE_ACTION = 'scorva_lifecycle_save';
    private const NONCE_FIELD  = '_scorva_lifecycle_nonce';

    public static function render_page(): void
    {
        if (!current_user_can(PR_CAP_MANAGE_SETTINGS)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'scorva'));
        }

        $saved    = false;
        $save_err = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[self::NONCE_FIELD])) {
            if (!check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD)) {
                $save_err = __('Security check failed.', 'scorva');
            } else {
                $enabled = !empty($_POST['pr_delete_data_on_uninstall']);
                PluginSettings::set_delete_data_on_uninstall($enabled);
                $saved = true;
            }
        }

        $delete_on_uninstall = PluginSettings::delete_data_on_uninstall();
        $smtp_svc            = new SmtpService();
        ?>
        <div class="scorva-admin-page">
            <div class="scorva-admin-page__accent-bar"></div>

            <?php self::render_page_header(); ?>
            <?php self::render_full_backup_card(); ?>
            <?php self::render_session_backups_card(); ?>
            <?php self::render_system_status_card($smtp_svc); ?>
            <?php self::render_lifecycle_card(); ?>
            <?php self::render_danger_zone_card($delete_on_uninstall, $saved, $save_err); ?>
        </div>

        <div id="scorva-toast" class="scorva-toast" aria-live="polite"></div>

        <script>
        (function () {
            var REST_NONCE = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
            var BACKUP_URL = <?php echo wp_json_encode(rest_url(Rest_Bootstrap::NAMESPACE . '/backup/download')); ?>;
            var SESSIONS_URL = <?php echo wp_json_encode(rest_url(Rest_Bootstrap::NAMESPACE . '/sessions')); ?>;
            var RESET_URL = <?php echo wp_json_encode(rest_url(Rest_Bootstrap::NAMESPACE . '/admin/reset')); ?>;
            var SESSION_BACKUP_BASE = <?php echo wp_json_encode(rest_url(Rest_Bootstrap::NAMESPACE . '/sessions/')); ?>;

            /* ── Full Backup ──────────────────────────────────────── */
            var backupBtn    = document.getElementById('scorva-bl-backup-btn');
            var backupErr    = document.getElementById('scorva-bl-backup-err');

            if (backupBtn) {
                backupBtn.addEventListener('click', function () {
                    backupBtn.disabled = true;
                    backupBtn.innerHTML = '<svg class="scorva-btn-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" /></svg> Preparing…';
                    if (backupErr) { backupErr.style.display = 'none'; backupErr.textContent = ''; }

                    fetch(BACKUP_URL, {
                        credentials: 'same-origin',
                        headers: { 'X-WP-Nonce': REST_NONCE },
                    })
                    .then(function (r) {
                        if (!r.ok) {
                            return r.json().then(function (p) {
                                throw new Error((p && p.message) || <?php echo wp_json_encode(__('Backup failed.', 'scorva')); ?>);
                            });
                        }
                        var disp = r.headers.get('Content-Disposition') || '';
                        var m = disp.match(/filename="([^"]+)"/);
                        var filename = m ? m[1] : 'scorva-backup.zip';
                        return r.blob().then(function (b) { return { blob: b, filename: filename }; });
                    })
                    .then(function (res) {
                        var url = URL.createObjectURL(res.blob);
                        var a = document.createElement('a');
                        a.href = url; a.download = res.filename;
                        document.body.appendChild(a); a.click(); a.remove();
                        URL.revokeObjectURL(url);
                    })
                    .catch(function (err) {
                        if (backupErr) {
                            backupErr.textContent = err.message || <?php echo wp_json_encode(__('Backup failed.', 'scorva')); ?>;
                            backupErr.style.display = '';
                        }
                    })
                    .finally(function () {
                        backupBtn.disabled = false;
                        backupBtn.innerHTML = <?php echo wp_json_encode(
                            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> '
                            . __('Download Full Backup', 'scorva')
                        ); ?>;
                    });
                });
            }

            /* ── Session Backups table ────────────────────────────── */
            var sessionTable  = document.getElementById('scorva-bl-sessions-tbody');
            var sessionFilter = document.getElementById('scorva-bl-session-filter');
            var paginationEl  = document.getElementById('scorva-bl-pagination');
            var PAGE_SIZE     = 10;
            var allSessions   = [];
            var filteredSessions = [];
            var currentPage   = 1;

            function renderPage() {
                if (!sessionTable) { return; }
                var start = (currentPage - 1) * PAGE_SIZE;
                var slice = filteredSessions.slice(start, start + PAGE_SIZE);
                if (slice.length === 0) {
                    sessionTable.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--pr-color-text-muted);padding:20px 0">' + <?php echo wp_json_encode(__('No sessions found.', 'scorva')); ?> + '</td></tr>';
                } else {
                    sessionTable.innerHTML = slice.map(function (s) {
                        var statusLabel = s.status === 'closed'
                            ? '<span class="scorva-chip scorva-chip--closed">' + <?php echo wp_json_encode(__('Closed', 'scorva')); ?> + '</span>'
                            : s.status === 'active'
                                ? '<span class="scorva-chip scorva-chip--active">' + <?php echo wp_json_encode(__('Active', 'scorva')); ?> + '</span>'
                                : '<span class="scorva-chip scorva-chip--draft">' + <?php echo wp_json_encode(__('Draft', 'scorva')); ?> + '</span>';
                        var dlUrl = SESSION_BACKUP_BASE + s.id + '/backup/download?_wpnonce=' + encodeURIComponent(REST_NONCE);
                        var prog = s.program ? escHtml(s.program) : '<span style="color:var(--pr-color-text-muted)">—</span>';
                        return '<tr><td>' + escHtml(s.title || '') + '</td><td>' + prog + '</td><td>' + statusLabel + '</td>'
                             + '<td><a href="' + escHtml(dlUrl) + '" class="scorva-btn scorva-btn--icon-only scorva-bl-session-dl" title="' + <?php echo wp_json_encode(__('Download session backup', 'scorva')); ?> + '" aria-label="' + <?php echo wp_json_encode(__('Download session backup', 'scorva')); ?> + '">'
                             + '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'
                             + '</a></td></tr>';
                    }).join('');
                }
                if (paginationEl) {
                    var total = filteredSessions.length;
                    var pages = Math.ceil(total / PAGE_SIZE);
                    if (pages <= 1) {
                        paginationEl.style.display = 'none';
                    } else {
                        paginationEl.style.display = '';
                        var from = start + 1;
                        var to = Math.min(start + PAGE_SIZE, total);
                        document.getElementById('scorva-bl-page-info').textContent = from + '–' + to + ' ' + <?php echo wp_json_encode(__('of', 'scorva')); ?> + ' ' + total;
                        document.getElementById('scorva-bl-prev').disabled = currentPage <= 1;
                        document.getElementById('scorva-bl-next').disabled = currentPage >= pages;
                    }
                }
            }

            function applyFilter() {
                var q = sessionFilter ? sessionFilter.value.toLowerCase().trim() : '';
                filteredSessions = q
                    ? allSessions.filter(function (s) { return (s.title || '').toLowerCase().indexOf(q) !== -1; })
                    : allSessions.slice();
                currentPage = 1;
                renderPage();
            }

            if (sessionTable) {
                fetch(SESSIONS_URL, {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': REST_NONCE },
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    allSessions = Array.isArray(data) ? data : [];
                    applyFilter();
                })
                .catch(function () {
                    sessionTable.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--pr-color-danger);padding:20px 0">' + <?php echo wp_json_encode(__('Could not load sessions.', 'scorva')); ?> + '</td></tr>';
                });
            }

            if (sessionFilter) {
                sessionFilter.addEventListener('input', applyFilter);
            }

            var prevBtn = document.getElementById('scorva-bl-prev');
            var nextBtn = document.getElementById('scorva-bl-next');
            if (prevBtn) { prevBtn.addEventListener('click', function () { currentPage--; renderPage(); }); }
            if (nextBtn) { nextBtn.addEventListener('click', function () { currentPage++; renderPage(); }); }

            /* ── Copy support info ────────────────────────────────── */
            var copyBtn = document.getElementById('scorva-bl-copy-support');
            if (copyBtn) {
                copyBtn.addEventListener('click', function () {
                    var rows = document.querySelectorAll('#scorva-bl-status-grid [data-label]');
                    var lines = [];
                    rows.forEach(function (el) {
                        lines.push(el.dataset.label + ': ' + el.dataset.value);
                    });
                    var text = lines.join('\n');
                    var restore = copyBtn.textContent;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                            copyBtn.textContent = <?php echo wp_json_encode("✓ " . __('Copied!', 'scorva')); ?>;
                            setTimeout(function () { copyBtn.textContent = restore; }, 2000);
                        });
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                        document.body.appendChild(ta); ta.select();
                        try { document.execCommand('copy'); } catch (e) {}
                        ta.remove();
                        copyBtn.textContent = <?php echo wp_json_encode("✓ " . __('Copied!', 'scorva')); ?>;
                        setTimeout(function () { copyBtn.textContent = restore; }, 2000);
                    }
                });
            }

            /* ── Uninstall warning expand ─────────────────────────── */
            var uninstallToggle  = document.getElementById('scorva-bl-uninstall-toggle');
            var uninstallWarning = document.getElementById('scorva-bl-uninstall-warning');
            function syncWarning() {
                if (!uninstallWarning) { return; }
                if (uninstallToggle && uninstallToggle.checked) {
                    uninstallWarning.style.display = '';
                } else {
                    uninstallWarning.style.display = 'none';
                }
            }
            if (uninstallToggle) {
                uninstallToggle.addEventListener('change', syncWarning);
                syncWarning();
            }

            /* ── Reset section ────────────────────────────────────── */
            var resetInput = document.getElementById('scorva-bl-reset-input');
            var resetBtn   = document.getElementById('scorva-bl-reset-btn');
            var resetErr   = document.getElementById('scorva-bl-reset-err');

            function syncReset() {
                if (!resetBtn || !resetInput) { return; }
                var enabled = resetInput.value === 'RESET';
                resetBtn.disabled = !enabled;
                resetBtn.style.opacity = enabled ? '1' : '0.4';
                resetBtn.style.cursor  = enabled ? 'pointer' : 'not-allowed';
            }
            if (resetInput) {
                resetInput.addEventListener('input', syncReset);
                syncReset();
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    if (resetInput && resetInput.value !== 'RESET') { return; }
                    if (!window.confirm(<?php echo wp_json_encode(__('This will permanently delete all Scorva data. This cannot be undone. Proceed?', 'scorva')); ?>)) {
                        return;
                    }
                    resetBtn.disabled = true;
                    resetBtn.textContent = <?php echo wp_json_encode(__('Resetting…', 'scorva')); ?>;
                    if (resetErr) { resetErr.style.display = 'none'; }

                    fetch(RESET_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': REST_NONCE,
                        },
                        body: JSON.stringify({ confirmation: 'RESET' }),
                    })
                    .then(function (r) { return r.json().then(function (p) { return { ok: r.ok, payload: p }; }); })
                    .then(function (res) {
                        if (!res.ok) {
                            throw new Error((res.payload && res.payload.message) || <?php echo wp_json_encode(__('Reset failed.', 'scorva')); ?>);
                        }
                        window.location.href = window.location.href + (window.location.href.indexOf('?') === -1 ? '?' : '&') + '_scorva_reset=1';
                    })
                    .catch(function (err) {
                        resetBtn.disabled = false;
                        syncReset();
                        if (resetErr) {
                            resetErr.textContent = err.message;
                            resetErr.style.display = '';
                        }
                    });
                });
            }

            /* ── Utility ──────────────────────────────────────────── */
            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            /* ── Save-status flash ────────────────────────────────── */
            <?php if ($saved): ?>
            var toast = document.getElementById('scorva-toast');
            if (toast) {
                toast.textContent = <?php echo wp_json_encode(__('Settings saved.', 'scorva')); ?>;
                toast.classList.add('scorva-toast--show');
                setTimeout(function () { toast.classList.remove('scorva-toast--show'); }, 3000);
            }
            <?php elseif ($save_err !== ''): ?>
            var saveErrEl = document.getElementById('scorva-bl-lifecycle-save-err');
            if (saveErrEl) {
                saveErrEl.textContent = <?php echo wp_json_encode($save_err); ?>;
                saveErrEl.style.display = '';
            }
            <?php endif; ?>
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
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 8v13H3V8"/>
                        <rect x="1" y="3" width="22" height="5" rx="1"/>
                        <path d="M10 12h4"/>
                    </svg>
                </span>
                <div>
                    <h1 class="scorva-page-header__title">
                        <?php esc_html_e('Backup & Lifecycle', 'scorva'); ?>
                    </h1>
                    <p class="scorva-page-header__subtitle">
                        <?php esc_html_e('Export your data, review lifecycle behaviour, and manage uninstall settings.', 'scorva'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_full_backup_card(): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 8v13H3V8"/><rect x="1" y="3" width="22" height="5" rx="1"/>
                    <path d="M10 12h4"/>
                </svg>
                <?php esc_html_e('Full Backup', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <p class="scorva-field-hint" style="margin-bottom:16px">
                    <?php esc_html_e('Packages all sessions, marks, plugin options, and Excel reports into a single ZIP archive.', 'scorva'); ?>
                </p>
                <button type="button" id="scorva-bl-backup-btn" class="scorva-btn scorva-btn--primary scorva-btn--lg">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    <?php esc_html_e('Download Full Backup', 'scorva'); ?>
                </button>
                <p id="scorva-bl-backup-err" class="scorva-field-hint" style="color:var(--pr-color-danger);margin-top:10px;display:none"></p>
            </div>
        </div>
        <?php
    }

    private static function render_session_backups_card(): void
    {
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M3 9h18M3 15h18M9 3v18"/>
                </svg>
                <?php esc_html_e('Session Backups', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <div class="scorva-bl-filter-row">
                    <input type="search" id="scorva-bl-session-filter" class="scorva-input scorva-bl-filter-input"
                           placeholder="<?php esc_attr_e('Filter sessions…', 'scorva'); ?>"
                           aria-label="<?php esc_attr_e('Filter sessions by name', 'scorva'); ?>">
                </div>
                <div class="scorva-bl-table-wrap">
                    <table class="scorva-bl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Session', 'scorva'); ?></th>
                                <th><?php esc_html_e('Programme', 'scorva'); ?></th>
                                <th><?php esc_html_e('Status', 'scorva'); ?></th>
                                <th><?php esc_html_e('Export', 'scorva'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="scorva-bl-sessions-tbody">
                            <tr>
                                <td colspan="4" style="text-align:center;color:var(--pr-color-text-muted);padding:20px 0">
                                    <?php esc_html_e('Loading…', 'scorva'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="scorva-bl-pagination" class="scorva-bl-pagination" style="display:none">
                    <button type="button" id="scorva-bl-prev" class="scorva-btn scorva-btn--secondary scorva-btn--sm">
                        &larr; <?php esc_html_e('Prev', 'scorva'); ?>
                    </button>
                    <span id="scorva-bl-page-info" class="scorva-bl-page-info"></span>
                    <button type="button" id="scorva-bl-next" class="scorva-btn scorva-btn--secondary scorva-btn--sm">
                        <?php esc_html_e('Next', 'scorva'); ?> &rarr;
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    private static function render_system_status_card(SmtpService $smtp_svc): void
    {
        $plugin_ver  = defined('PR_PLUGIN_VERSION') ? PR_PLUGIN_VERSION : '—';
        $db_ver      = (string) get_option('pr_db_version', '—');
        $caps_ver    = (string) get_option('pr_caps_version', '—');
        $rewrite_ver = (string) get_option('pr_rewrite_version', '—');
        $php_ver     = PHP_VERSION;
        $wp_ver      = get_bloginfo('version');

        $smtp_ok    = $smtp_svc->is_configured();
        $smtp_host  = trim((string) ($smtp_svc->get_settings()['host'] ?? ''));
        $smtp_label = $smtp_ok
            ? sprintf(/* translators: %s: SMTP hostname */ __('Configured via %s', 'scorva'), $smtp_host)
            : __('Not configured', 'scorva');

        $cells = [
            ['label' => __('Plugin Version', 'scorva'),       'value' => $plugin_ver],
            ['label' => __('DB Schema Version', 'scorva'),    'value' => $db_ver ?: '—'],
            ['label' => __('PHP Version', 'scorva'),          'value' => $php_ver],
            ['label' => __('WordPress Version', 'scorva'),    'value' => $wp_ver],
            ['label' => __('Capabilities Version', 'scorva'), 'value' => $caps_ver ?: '—'],
            ['label' => __('Rewrite Version', 'scorva'),      'value' => $rewrite_ver ?: '—'],
        ];
        ?>
        <div class="scorva-card">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <?php esc_html_e('System Status', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">
                <p class="scorva-field-hint" style="margin-bottom:12px">
                    <?php esc_html_e('Include this information when reporting issues.', 'scorva'); ?>
                </p>
                <div class="scorva-status-grid" id="scorva-bl-status-grid">
                    <?php foreach ($cells as $cell): ?>
                        <div class="scorva-status-grid__cell"
                             data-label="<?php echo esc_attr($cell['label']); ?>"
                             data-value="<?php echo esc_attr($cell['value']); ?>">
                            <div class="scorva-status-grid__label"><?php echo esc_html($cell['label']); ?></div>
                            <div class="scorva-status-grid__value"><?php echo esc_html($cell['value']); ?></div>
                        </div>
                    <?php endforeach; ?>

                    <div class="scorva-status-grid__cell scorva-status-grid__cell--full"
                         data-label="<?php esc_attr_e('SMTP', 'scorva'); ?>"
                         data-value="<?php echo esc_attr($smtp_label); ?>">
                        <div class="scorva-status-grid__label"><?php esc_html_e('SMTP', 'scorva'); ?></div>
                        <div class="scorva-status-grid__value scorva-status-grid__service">
                            <span class="scorva-status-dot <?php echo $smtp_ok ? 'scorva-status-dot--ok' : 'scorva-status-dot--off'; ?>"></span>
                            <?php echo esc_html($smtp_label); ?>
                        </div>
                    </div>
                </div>
                <button type="button" id="scorva-bl-copy-support" class="scorva-btn scorva-btn--ghost" style="margin-top:12px">
                    <?php esc_html_e('Copy support info', 'scorva'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    private static function render_lifecycle_card(): void
    {
        $steps = [
            [
                'color' => '#1a7f37',
                'title' => __('Activation', 'scorva'),
                'desc'  => __('Routes and capabilities are restored. Database schema is upgraded if needed.', 'scorva'),
                'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>',
            ],
            [
                'color' => '#9a6700',
                'title' => __('Deactivation', 'scorva'),
                'desc'  => __('Rewrite rules are flushed. All sessions, marks, and options are retained.', 'scorva'),
                'icon'  => '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>',
            ],
            [
                'color' => '#cf222e',
                'title' => __('Deletion (uninstall)', 'scorva'),
                'desc'  => __('Data is preserved unless the toggle in the Danger Zone is ON. WordPress user accounts are never deleted.', 'scorva'),
                'icon'  => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>',
            ],
        ];
        ?>
        <details class="scorva-card scorva-card--collapsible" open>
            <summary class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="23 4 23 10 17 10"/>
                    <polyline points="1 20 1 14 7 14"/>
                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                </svg>
                <?php esc_html_e('Plugin Lifecycle', 'scorva'); ?>
                <svg class="scorva-card__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </summary>
            <div class="scorva-card__body scorva-card__body--no-pad">
                <?php foreach ($steps as $i => $step): ?>
                    <div class="scorva-lifecycle-step<?php echo $i < count($steps) - 1 ? '' : ' scorva-lifecycle-step--last'; ?>">
                        <div class="scorva-lifecycle-step__dot" style="color:<?php echo esc_attr($step['color']); ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" aria-hidden="true">
                                <?php echo $step['icon']; // phpcs:ignore -- safe SVG path ?>
                            </svg>
                        </div>
                        <div class="scorva-lifecycle-step__body">
                            <strong class="scorva-lifecycle-step__title" style="color:<?php echo esc_attr($step['color']); ?>">
                                <?php echo esc_html($step['title']); ?>
                            </strong>
                            <p class="scorva-lifecycle-step__desc">
                                <?php echo esc_html($step['desc']); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php
    }

    private static function render_danger_zone_card(bool $delete_on_uninstall, bool $saved, string $save_err): void
    {
        unset($saved, $save_err); // handled globally via toast / JS
        ?>
        <div class="scorva-card scorva-card--danger-zone">
            <div class="scorva-card__header">
                <svg class="scorva-card__header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                     style="color:var(--pr-color-danger)">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <?php esc_html_e('Danger Zone', 'scorva'); ?>
            </div>
            <div class="scorva-card__body">

                <!-- Uninstall toggle -->
                <form method="post" id="scorva-bl-lifecycle-form">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                    <div class="scorva-bl-toggle-row">
                        <div class="scorva-bl-toggle-row__text">
                            <strong><?php esc_html_e('Delete all data on uninstall', 'scorva'); ?></strong>
                            <p class="scorva-field-hint">
                                <?php esc_html_e('When enabled, deleting the plugin from the WordPress plugins screen will permanently remove all Scorva tables and options.', 'scorva'); ?>
                            </p>
                        </div>
                        <div class="scorva-bl-toggle-row__controls">
                            <label class="scorva-toggle" aria-label="<?php esc_attr_e('Delete all data on uninstall', 'scorva'); ?>">
                                <input type="checkbox" id="scorva-bl-uninstall-toggle"
                                       name="pr_delete_data_on_uninstall" value="1"
                                       <?php checked($delete_on_uninstall); ?>>
                                <span class="scorva-toggle__track"></span>
                            </label>
                            <button type="submit" class="scorva-btn scorva-btn--secondary scorva-btn--sm">
                                <?php esc_html_e('Save', 'scorva'); ?>
                            </button>
                        </div>
                    </div>
                    <p id="scorva-bl-lifecycle-save-err" class="scorva-field-hint" style="color:var(--pr-color-danger);display:none"></p>
                </form>

                <!-- Uninstall warning (shown when toggle is ON) -->
                <div id="scorva-bl-uninstall-warning" class="scorva-bl-uninstall-warning" style="display:none">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    <span>
                        <?php esc_html_e('Danger: If this plugin is deleted from the WordPress plugins screen, all Scorva tables, marks, sessions, and options will be permanently removed. WordPress user accounts are never deleted by Scorva.', 'scorva'); ?>
                    </span>
                </div>

                <!-- Reset section -->
                <div class="scorva-bl-reset-section">
                    <p class="scorva-label" style="margin-bottom:8px">
                        <?php esc_html_e('Type RESET to enable the reset button:', 'scorva'); ?>
                    </p>
                    <div class="scorva-bl-reset-row">
                        <input type="text" id="scorva-bl-reset-input" class="scorva-input"
                               placeholder="<?php esc_attr_e('Type RESET', 'scorva'); ?>"
                               autocomplete="off" spellcheck="false">
                        <button type="button" id="scorva-bl-reset-btn"
                                class="scorva-btn scorva-btn--danger-fill" disabled style="opacity:.4;cursor:not-allowed">
                            <?php esc_html_e('Reset all data', 'scorva'); ?>
                        </button>
                    </div>
                    <p id="scorva-bl-reset-err" class="scorva-field-hint" style="color:var(--pr-color-danger);margin-top:8px;display:none"></p>
                    <p class="scorva-field-hint" style="margin-top:8px">
                        <?php esc_html_e('This truncates all Scorva tables and clears plugin options. WordPress user accounts are never deleted. This action is logged.', 'scorva'); ?>
                    </p>
                </div>

            </div>
        </div>
        <?php
    }
}
