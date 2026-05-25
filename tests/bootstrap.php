<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('PR_UNIT_TEST')) {
    define('PR_UNIT_TEST', true);
}

if (!defined('PR_PLUGIN_DIR')) {
    define('PR_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('PR_PLUGIN_FILE')) {
    define('PR_PLUGIN_FILE', dirname(__DIR__) . '/project-reviews.php');
}

if (!defined('PR_PLUGIN_VERSION')) {
    define('PR_PLUGIN_VERSION', '0.1.0');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}


/** @var array<string, WP_Role> */
$GLOBALS['pr_test_roles'] = [];

/** @var array<string, mixed> */
$GLOBALS['pr_test_options'] = [];

/** @var array<string, array{value: mixed, expires: int}> */
$GLOBALS['pr_test_transients'] = [];

/** @var int */
$GLOBALS['pr_test_current_user_id'] = 0;

/** @var array<string, bool> */
$GLOBALS['pr_test_user_caps'] = [];

/** @var string|null */
$GLOBALS['pr_test_rest_nonce'] = null;

/** @var list<array<string, mixed>> */
$GLOBALS['pr_test_registered_routes'] = [];

/** @var array<string, string> */
$GLOBALS['pr_test_rewrite_rules'] = [];

/** @var string|false */
$GLOBALS['pr_test_query_var'] = '';

/** @var bool */
$GLOBALS['pr_test_auth_redirect_called'] = false;

/** @var string|null */
$GLOBALS['pr_test_template_included'] = null;

/** @var string|null */
$GLOBALS['pr_test_template_app'] = null;

/** @var list<string> */
$GLOBALS['pr_test_dequeued_styles'] = [];

/** @var list<string> */
$GLOBALS['pr_test_dequeued_scripts'] = [];

/** @var list<int> */
$GLOBALS['pr_test_created_user_ids'] = [];

/** @var list<object> */
$GLOBALS['pr_test_users'] = [];

/** @var list<array{priority: int, callback: callable}> */
$GLOBALS['pr_test_wp_enqueue_callbacks'] = [];

/** @var array<string, list<array{priority: int, callback: callable, accepted_args: int}>> */
$GLOBALS['pr_test_filters'] = [];

/** @var list<array{code: int, headers: array<string, string>}> */
$GLOBALS['pr_test_http_headers'] = [];

/** @var list<array{handle: string, src: string, deps: list<string>, ver: string|false}> */
$GLOBALS['pr_test_enqueued_styles'] = [];

/** @var list<array{handle: string, src: string, deps: list<string>, ver: string|false, in_footer: bool}> */
$GLOBALS['pr_test_enqueued_scripts'] = [];

/** @var list<array{handle: string, object_name: string, data: array<string, mixed>}> */
$GLOBALS['pr_test_localized_scripts'] = [];

/** @var array<int, object{term_id: int, name: string, slug: string}> */
$GLOBALS['pr_test_nav_menus'] = [];

/** @var array<int, list<object{ID: int, url: string, type: string, title: string}>> */
$GLOBALS['pr_test_nav_menu_items'] = [];

/** @var array<string, int> */
$GLOBALS['pr_test_nav_menu_locations'] = [];

/** @var array<string, string> */
$GLOBALS['pr_test_registered_nav_menus'] = [];

/** @var int */
$GLOBALS['pr_test_nav_menu_id_seq'] = 1;

/** @var int */
$GLOBALS['pr_test_nav_menu_item_id_seq'] = 1;

/** @var array<string, mixed> */
$GLOBALS['pr_test_theme_mods'] = [];

/** @var list<string> */
$GLOBALS['pr_test_dbdelta_calls'] = [];

/** @var bool */
$GLOBALS['pr_test_exit_called'] = false;

/** @var string|null */
$GLOBALS['pr_test_redirect_url'] = null;

/** @var bool */
$GLOBALS['pr_test_workspace_denied'] = false;

/** @var string|null */
$GLOBALS['pr_test_wp_die_message'] = null;

/** @var object{queue: list<string>} */
$GLOBALS['pr_test_wp_styles'] = (object) ['queue' => []];

/** @var object{queue: list<string>} */
$GLOBALS['pr_test_wp_scripts'] = (object) ['queue' => []];

if (!class_exists('WP_Role', false)) {
    final class WP_Role
    {
        /** @var array<string, bool> */
        public array $capabilities = [];

        public function add_cap(string $cap): void
        {
            $this->capabilities[$cap] = true;
        }

        public function remove_cap(string $cap): void
        {
            unset($this->capabilities[$cap]);
        }

        public function has_cap(string $cap): bool
        {
            return !empty($this->capabilities[$cap]);
        }
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file): string
    {
        return dirname((string) $file) . '/';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback): void
    {
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback): void
    {
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(): void
    {
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        if ($hook === 'init' || $hook === 'rest_api_init') {
            $callback();
        }

        if ($hook === 'wp_enqueue_scripts') {
            $GLOBALS['pr_test_wp_enqueue_callbacks'][] = [
                'priority' => $priority,
                'callback' => $callback,
            ];
        }
    }
}

if (!function_exists('pr_test_run_wp_enqueue_scripts')) {
    function pr_test_run_wp_enqueue_scripts(): void
    {
        $callbacks = $GLOBALS['pr_test_wp_enqueue_callbacks'] ?? [];
        usort(
            $callbacks,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
        );
        foreach ($callbacks as $item) {
            $item['callback']();
        }
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url(string $path = '', $plugin = ''): string
    {
        return 'https://example.test/wp-content/plugins/project-reviews/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false): void
    {
        $GLOBALS['pr_test_enqueued_styles'][] = [
            'handle' => $handle,
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
        ];
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(
        string $handle,
        string $src = '',
        array $deps = [],
        $ver = false,
        bool $in_footer = false
    ): void {
        $GLOBALS['pr_test_enqueued_scripts'][] = [
            'handle' => $handle,
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'in_footer' => $in_footer,
        ];
    }
}

if (!function_exists('wp_localize_script')) {
    /**
     * @param array<string, mixed> $data
     */
    function wp_localize_script(string $handle, string $object_name, array $data): void
    {
        $GLOBALS['pr_test_localized_scripts'][] = [
            'handle' => $handle,
            'object_name' => $object_name,
            'data' => $data,
        ];
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        if ($action === 'wp_rest') {
            return (string) ($GLOBALS['pr_test_rest_nonce'] ?? 'test-rest-nonce');
        }

        return 'nonce-' . $action;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        if ($hook === 'query_vars') {
            return;
        }

        $GLOBALS['pr_test_filters'][$hook][] = [
            'priority' => $priority,
            'callback' => $callback,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        $callbacks = $GLOBALS['pr_test_filters'][$hook] ?? [];
        usort(
            $callbacks,
            static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']
        );

        foreach ($callbacks as $item) {
            $accepted = (int) $item['accepted_args'];
            if ($accepted <= 0) {
                $value = $item['callback']($value);
                continue;
            }

            $invoke = array_merge([$value], array_slice($args, 0, $accepted - 1));
            $value = $item['callback'](...$invoke);
        }

        return $value;
    }
}

if (!function_exists('status_header')) {
    function status_header(int $code, string $description = ''): void
    {
        unset($description);
        $GLOBALS['pr_test_http_headers'][] = [
            'code' => $code,
            'headers' => [],
        ];
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9._\-]/', '-', $filename) ?? '';

        return trim($filename, '-');
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(string $regex, string $query, string $after = 'bottom'): void
    {
        $GLOBALS['pr_test_rewrite_rules'][$regex] = $query;
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var(string $key, $default = '')
    {
        if ($key === 'pr_app') {
            $value = $GLOBALS['pr_test_query_var'] ?? '';

            return $value === '' ? $default : $value;
        }

        return $default;
    }
}

if (!function_exists('auth_redirect')) {
    function auth_redirect(): void
    {
        $GLOBALS['pr_test_auth_redirect_called'] = true;
    }
}

if (!function_exists('wp_dequeue_style')) {
    function wp_dequeue_style(string $handle): void
    {
        $GLOBALS['pr_test_dequeued_styles'][] = $handle;
    }
}

if (!function_exists('wp_deregister_style')) {
    function wp_deregister_style(string $handle): void
    {
    }
}

if (!function_exists('wp_dequeue_script')) {
    function wp_dequeue_script(string $handle): void
    {
        $GLOBALS['pr_test_dequeued_scripts'][] = $handle;
    }
}

if (!function_exists('wp_deregister_script')) {
    function wp_deregister_script(string $handle): void
    {
    }
}

if (!function_exists('get_option')) {
    function get_option(string $key, $default = false)
    {
        return $GLOBALS['pr_test_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, $value): bool
    {
        $GLOBALS['pr_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        unset($GLOBALS['pr_test_options'][$key]);

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $transient)
    {
        $key = '_transient_' . $transient;
        $entry = $GLOBALS['pr_test_transients'][$key] ?? null;
        if (!is_array($entry)) {
            return false;
        }

        if (($entry['expires'] ?? 0) > 0 && $entry['expires'] < time()) {
            unset($GLOBALS['pr_test_transients'][$key]);

            return false;
        }

        return $entry['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $transient, $value, int $expiration = 0): bool
    {
        $key = '_transient_' . $transient;
        $GLOBALS['pr_test_transients'][$key] = [
            'value' => $value,
            'expires' => $expiration > 0 ? time() + $expiration : 0,
        ];

        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        unset($GLOBALS['pr_test_transients']['_transient_' . $transient]);

        return true;
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool
    {
        if (is_dir($target)) {
            return true;
        }

        return mkdir($target, 0777, true);
    }
}

if (!function_exists('wp_json_encode')) {
    /**
     * @param mixed $data
     */
    function wp_json_encode($data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('count_users')) {
    /**
     * @param array<string, mixed> $args
     * @return array<string, int>
     */
    function count_users(array $args = []): array
    {
        $role = (string) ($args['role'] ?? '');
        $count = 0;
        foreach ($GLOBALS['pr_test_users'] as $user) {
            if ($role === '' || in_array($role, (array) ($user->roles ?? []), true)) {
                ++$count;
            }
        }

        return ['total_users' => $count];
    }
}

if (!class_exists('WP_Roles', false)) {
    class WP_Roles
    {
        /** @var array<string, array<string, mixed>> */
        public array $roles = [];
    }
}

if (!function_exists('wp_roles')) {
    function wp_roles(): WP_Roles
    {
        $roles = new WP_Roles();
        foreach ($GLOBALS['pr_test_roles'] as $role_id => $wp_role) {
            $roles->roles[(string) $role_id] = [
                'capabilities' => $wp_role->capabilities,
            ];
        }

        return $roles;
    }
}

if (!function_exists('remove_role')) {
    function remove_role(string $role): void
    {
        unset($GLOBALS['pr_test_roles'][$role]);
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta(string $sql): void
    {
        $GLOBALS['pr_test_dbdelta_calls'][] = $sql;
    }
}

if (!function_exists('get_role')) {
    function get_role(string $role): ?WP_Role
    {
        return $GLOBALS['pr_test_roles'][$role] ?? null;
    }
}

if (!class_exists('WP_Error', false)) {
    class WP_Error
    {
        public string $code;
        public string $message;
        /** @var array<string, mixed> */
        public array $data;

        /**
         * @param array<string, mixed> $data
         */
        public function __construct(string $code = '', string $message = '', array $data = [])
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /**
         * @return array<string, mixed>
         */
        public function get_error_data(?string $key = null)
        {
            if ($key === null) {
                return $this->data;
            }

            return $this->data[$key] ?? null;
        }
    }
}

if (!class_exists('WP_REST_Request', false)) {
    class WP_REST_Request
    {
        /** @var array<string, string> */
        private array $headers = [];

        /** @var array<string, mixed> */
        private array $params = [];

        /** @var array<string, mixed>|null */
        private ?array $json_params = null;

        public function set_header(string $name, string $value): void
        {
            $this->headers[strtolower($name)] = $value;
        }

        public function get_header(string $name): ?string
        {
            return $this->headers[strtolower($name)] ?? null;
        }

        /**
         * @param array<string, mixed> $params
         */
        public function set_params(array $params): void
        {
            $this->params = $params;
        }

        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, $value): void
        {
            $this->params[$key] = $value;
        }

        /**
         * @param array<string, mixed>|null $json
         */
        public function set_json_params(?array $json): void
        {
            $this->json_params = $json;
        }

        /**
         * @return array<string, mixed>|null
         */
        public function get_json_params(): ?array
        {
            return $this->json_params;
        }
    }
}

if (!class_exists('WP_REST_Server', false)) {
    class WP_REST_Server
    {
    }
}

if (!class_exists('WP_REST_Response', false)) {
    class WP_REST_Response
    {
        /** @var mixed */
        private $data;

        private int $status;

        /** @var array<string, string> */
        private array $headers = [];

        /**
         * @param mixed $data
         */
        public function __construct($data = null, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        /**
         * @return array<string, string>
         */
        public function get_headers(): array
        {
            return $this->headers;
        }

        public function header(string $name, string $value): void
        {
            $this->headers[$name] = $value;
        }
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return (string) $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return htmlspecialchars((string) __($text, $domain), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return ($GLOBALS['pr_test_current_user_id'] ?? 0) > 0;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool
    {
        if (!is_user_logged_in()) {
            return false;
        }

        return !empty($GLOBALS['pr_test_user_caps'][$cap]);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action): bool
    {
        if ($action !== 'wp_rest') {
            return false;
        }

        $expected = $GLOBALS['pr_test_rest_nonce'] ?? null;

        return $expected !== null && hash_equals((string) $expected, $nonce);
    }
}

if (!function_exists('register_rest_route')) {
    /**
     * @param array<string, mixed> $route_args
     */
    function register_rest_route(string $namespace, string $route, array $route_args, bool $override = false): bool
    {
        $GLOBALS['pr_test_registered_routes'][] = [
            'namespace' => $namespace,
            'route' => $route,
            'args' => $route_args,
        ];

        return true;
    }
}

/** @var array<int, object{ID: int, user_login: string, user_email: string, display_name: string, roles: list<string>}> */
$GLOBALS['pr_test_users'] = [];

/** @var list<array{to: string, subject: string, message: string, headers: list<string>}> */
$GLOBALS['pr_test_sent_mail'] = [];

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test' . $path;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect(string $location, int $status = 302): void
    {
        unset($status);
        $GLOBALS['pr_test_redirect_url'] = $location;
        $GLOBALS['pr_test_exit_called'] = true;
    }
}

if (!function_exists('wp_logout_url')) {
    function wp_logout_url(string $redirect = ''): string
    {
        $query = $redirect !== '' ? '?redirect_to=' . rawurlencode($redirect) : '';

        return home_url('/wp-login.php?action=logout') . $query;
    }
}

if (!function_exists('wp_die')) {
    /**
     * @param array<string, mixed>|string $args
     */
    function wp_die($message = '', $title = '', $args = ''): void
    {
        unset($title, $args);
        $GLOBALS['pr_test_wp_die_message'] = is_string($message) ? $message : '';
        $GLOBALS['pr_test_workspace_denied'] = true;
        $GLOBALS['pr_test_exit_called'] = true;
    }
}

if (!function_exists('wp_login_url')) {
    function wp_login_url(): string
    {
        return home_url('/wp-login.php');
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user(string $username, bool $strict = false): string
    {
        unset($strict);

        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($username)) ?? '';
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}

if (!function_exists('wp_rand')) {
    function wp_rand(int $min = 0, int $max = 0): int
    {
        if ($max <= $min) {
            return $min;
        }

        return random_int($min, $max);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special = true, bool $extra = false): string
    {
        unset($special, $extra);

        return substr(bin2hex(random_bytes($length)), 0, $length);
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by(string $field, $value)
    {
        foreach ($GLOBALS['pr_test_users'] as $user) {
            if ($field === 'id' || $field === 'ID') {
                if ((int) $user->ID === (int) $value) {
                    return $user;
                }
            } elseif ($field === 'email' && strcasecmp((string) $user->user_email, (string) $value) === 0) {
                return $user;
            } elseif ($field === 'login' && (string) $user->user_login === (string) $value) {
                return $user;
            }
        }

        return false;
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata(int $user_id)
    {
        $user = get_user_by('id', $user_id);

        return $user === false ? null : $user;
    }
}

if (!function_exists('get_users')) {
    /**
     * @param array<string, mixed> $args
     * @return list<object>
     */
    function get_users(array $args = []): array
    {
        $search = (string) ($args['search'] ?? '');
        $needle = trim($search, '*');
        $role = (string) ($args['role'] ?? '');
        $meta_key = (string) ($args['meta_key'] ?? '');
        $meta_value = (string) ($args['meta_value'] ?? '');
        $meta_compare = (string) ($args['meta_compare'] ?? '=');

        $users = array_values($GLOBALS['pr_test_users']);
        $filtered = array_values(array_filter(
            $users,
            static function (object $user) use ($needle, $role, $meta_key, $meta_value, $meta_compare): bool {
                if ($role !== '' && !in_array($role, (array) ($user->roles ?? []), true)) {
                    return false;
                }

                if ($meta_key !== '') {
                    $meta = $GLOBALS['pr_test_user_meta'][(int) $user->ID] ?? [];
                    if ($meta_compare === 'EXISTS') {
                        if (!array_key_exists($meta_key, $meta)) {
                            return false;
                        }
                    } elseif ((string) ($meta[$meta_key] ?? '') !== $meta_value) {
                        return false;
                    }
                }

                if ($needle === '') {
                    return true;
                }

                foreach (['user_login', 'user_email', 'display_name'] as $field) {
                    if (stripos((string) ($user->{$field} ?? ''), $needle) !== false) {
                        return true;
                    }
                }

                return false;
            }
        ));

        return array_slice($filtered, 0, (int) ($args['number'] ?? 20));
    }
}

if (!class_exists('Pr_Test_User', false)) {
    final class Pr_Test_User
    {
        public int $ID;

        public string $user_login;

        public string $user_email;

        public string $display_name;

        /** @var list<string> */
        public array $roles = [];

        public function has_cap(string $cap): bool
        {
            unset($cap);

            return in_array('administrator', $this->roles, true)
                || in_array('project_reviews_coordinator', $this->roles, true);
        }

        public function add_role(string $role): void
        {
            if (!in_array($role, $this->roles, true)) {
                $this->roles[] = $role;
            }
        }
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user(string $username, string $password, string $email)
    {
        unset($password);
        if (get_user_by('email', $email) !== false) {
            return new WP_Error('existing_user_email', 'Email exists');
        }

        $id = count($GLOBALS['pr_test_users']) + 1;
        $user = new Pr_Test_User();
        $user->ID = $id;
        $user->user_login = $username;
        $user->user_email = $email;
        $user->display_name = $username;
        $GLOBALS['pr_test_users'][$id] = $user;

        return $id;
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user(int $user_id): bool
    {
        unset($GLOBALS['pr_test_users'][$user_id], $GLOBALS['pr_test_user_meta'][$user_id]);
        global $pr_test_created_user_ids;
        if (isset($pr_test_created_user_ids)) {
            $pr_test_created_user_ids = array_values(
                array_filter($pr_test_created_user_ids, static fn (int $id): bool => $id !== $user_id)
            );
        }

        return true;
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type(): string
    {
        return (string) ($GLOBALS['pr_test_environment_type'] ?? 'local');
    }
}

if (!function_exists('wp_update_user')) {
    /**
     * @param array<string, mixed> $userdata
     */
    function wp_update_user(array $userdata): int
    {
        $id = (int) ($userdata['ID'] ?? 0);
        $user = get_user_by('id', $id);
        if ($user === false) {
            return 0;
        }
        if (isset($userdata['display_name'])) {
            $user->display_name = (string) $userdata['display_name'];
        }

        return $id;
    }
}

if (!function_exists('wp_set_password')) {
    function wp_set_password(string $password, int $user_id): void
    {
        unset($password);
        $GLOBALS['pr_test_user_passwords'][ $user_id ] = true;
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta(int $user_id, string $key, $value): bool
    {
        $GLOBALS['pr_test_user_meta'][ $user_id ][ $key ] = $value;

        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false)
    {
        $meta = $GLOBALS['pr_test_user_meta'][ $user_id ] ?? [];
        if ($key === '') {
            return $meta;
        }

        if (!array_key_exists($key, $meta)) {
            return $single ? '' : [];
        }

        return $single ? $meta[ $key ] : [$meta[ $key ]];
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta(int $user_id, string $key, $meta_value = ''): bool
    {
        unset($meta_value);
        unset($GLOBALS['pr_test_user_meta'][ $user_id ][ $key ]);

        return true;
    }
}

if (!function_exists('wp_mail')) {
    /**
     * @param list<string> $headers
     */
    function wp_mail(string $to, string $subject, string $message, array $headers = []): bool
    {
        $GLOBALS['pr_test_sent_mail'][] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        ];

        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return (int) ($GLOBALS['pr_test_current_user_id'] ?? 0);
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            $guest = new Pr_Test_User();
            $guest->ID = 0;
            $guest->user_login = '';
            $guest->user_email = '';
            $guest->display_name = '';

            return $guest;
        }

        $user = get_userdata($user_id);
        if ($user !== null) {
            return $user;
        }

        $fallback = new Pr_Test_User();
        $fallback->ID = $user_id;
        $fallback->user_login = 'user' . $user_id;
        $fallback->user_email = 'user' . $user_id . '@example.test';
        $fallback->display_name = 'User ' . $user_id;

        return $fallback;
    }
}

if (!function_exists('user_can')) {
    function user_can($user, string $cap): bool
    {
        $user_id = is_numeric($user) ? (int) $user : (int) ($user->ID ?? 0);

        if (!empty($GLOBALS['pr_test_user_caps_by_user'][$user_id][$cap])) {
            return true;
        }

        if ($user_id > 0 && $user_id === get_current_user_id()) {
            return !empty($GLOBALS['pr_test_user_caps'][$cap]);
        }

        return false;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return home_url('/wp-admin/' . ltrim($path, '/'));
    }
}

if (!function_exists('add_query_arg')) {
    /**
     * @param array<string, string>|string $key
     * @param string|int|null $value
     */
    function add_query_arg($key, $value = null, $url = null): string
    {
        if (is_array($key)) {
            $url = $value ?? '';
            $result = (string) $url;
            foreach ($key as $arg_key => $arg_value) {
                $result = add_query_arg((string) $arg_key, (string) $arg_value, $result);
            }

            return $result;
        }

        $url = (string) ($url ?? '');
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool
    {
        return !empty($GLOBALS['pr_test_doing_ajax']);
    }
}

if (!function_exists('add_role')) {
    /**
     * @param array<string, bool> $capabilities
     */
    function add_role(string $role, string $display_name, array $capabilities = []): ?WP_Role
    {
        $wp_role = new WP_Role();
        foreach ($capabilities as $cap => $grant) {
            if ($grant) {
                $wp_role->add_cap((string) $cap);
            }
        }

        $GLOBALS['pr_test_roles'][$role] = $wp_role;

        return $wp_role;
    }
}

if (!class_exists('WP_Error', false)) {
    final class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public mixed $data = null
        ) {
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_create_nav_menu')) {
    function wp_create_nav_menu(string $menu_name)
    {
        $id = (int) ($GLOBALS['pr_test_nav_menu_id_seq'] ?? 1);
        $GLOBALS['pr_test_nav_menu_id_seq'] = $id + 1;
        $slug = function_exists('sanitize_title') ? sanitize_title($menu_name) : 'menu-' . $id;
        $menu = (object) [
            'term_id' => $id,
            'name' => $menu_name,
            'slug' => $slug,
        ];
        $GLOBALS['pr_test_nav_menus'][$id] = $menu;
        $GLOBALS['pr_test_nav_menu_items'][$id] = [];

        return $id;
    }
}

if (!function_exists('wp_get_nav_menus')) {
    /**
     * @return list<object{term_id: int, name: string, slug: string}>
     */
    function wp_get_nav_menus(): array
    {
        return array_values($GLOBALS['pr_test_nav_menus'] ?? []);
    }
}

if (!function_exists('wp_update_nav_menu_item')) {
    function wp_update_nav_menu_item(int $menu_id, int $menu_item_id, array $args)
    {
        if ($menu_id <= 0) {
            return new WP_Error('invalid_menu', 'Invalid menu');
        }

        $items = &$GLOBALS['pr_test_nav_menu_items'][$menu_id];
        if (!is_array($items)) {
            $items = [];
        }

        $url = (string) ($args['menu-item-url'] ?? '');
        $title = (string) ($args['menu-item-title'] ?? '');
        $type = (string) ($args['menu-item-type'] ?? 'custom');

        if ($menu_item_id > 0) {
            foreach ($items as $item) {
                if ((int) ($item->ID ?? 0) === $menu_item_id) {
                    $item->url = $url;
                    $item->title = $title;
                    $item->type = $type;

                    return $menu_item_id;
                }
            }
        }

        $new_id = (int) ($GLOBALS['pr_test_nav_menu_item_id_seq'] ?? 1);
        $GLOBALS['pr_test_nav_menu_item_id_seq'] = $new_id + 1;
        $items[] = (object) [
            'ID' => $new_id,
            'url' => $url,
            'type' => $type,
            'title' => $title,
        ];

        return $new_id;
    }
}

if (!function_exists('wp_get_nav_menu_items')) {
    /**
     * @return list<object{ID: int, url: string, type: string, title: string}>|false
     */
    function wp_get_nav_menu_items(int $menu_id)
    {
        $items = $GLOBALS['pr_test_nav_menu_items'][$menu_id] ?? [];

        return is_array($items) ? $items : false;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post(int $post_id, bool $force = false): bool
    {
        unset($force);
        foreach ($GLOBALS['pr_test_nav_menu_items'] as $menu_id => $items) {
            if (!is_array($items)) {
                continue;
            }
            $GLOBALS['pr_test_nav_menu_items'][$menu_id] = array_values(
                array_filter(
                    $items,
                    static fn (object $item): bool => (int) ($item->ID ?? 0) !== $post_id
                )
            );
        }

        return true;
    }
}

if (!function_exists('get_nav_menu_locations')) {
    /**
     * @return array<string, int>
     */
    function get_nav_menu_locations(): array
    {
        $mods = $GLOBALS['pr_test_theme_mods']['nav_menu_locations'] ?? null;
        if (is_array($mods)) {
            return $mods;
        }

        return $GLOBALS['pr_test_nav_menu_locations'] ?? [];
    }
}

if (!function_exists('set_theme_mod')) {
    function set_theme_mod(string $name, mixed $value): bool
    {
        $GLOBALS['pr_test_theme_mods'][$name] = $value;
        if ($name === 'nav_menu_locations' && is_array($value)) {
            $GLOBALS['pr_test_nav_menu_locations'] = $value;
        }

        return true;
    }
}

if (!function_exists('get_theme_mod')) {
    function get_theme_mod(string $name, mixed $default = false): mixed
    {
        return $GLOBALS['pr_test_theme_mods'][$name] ?? $default;
    }
}

if (!function_exists('get_registered_nav_menus')) {
    /**
     * @return array<string, string>
     */
    function get_registered_nav_menus(): array
    {
        $menus = $GLOBALS['pr_test_registered_nav_menus'] ?? [];

        return is_array($menus) ? $menus : [];
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url(string $actionurl, string $action = '-1', string $name = '_wpnonce'): string
    {
        return add_query_arg($name, 'test-nonce-' . $action, $actionurl);
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer(string $action = '-1', string $query_arg = '_wpnonce'): void
    {
        unset($action, $query_arg);
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
