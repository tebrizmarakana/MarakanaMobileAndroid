<?php
/**
 * Plugin Name: Marakana Playstation Hesab Satışı
 * Plugin URI: https://marakana.local/
 * Description: Playstation oyun hesablarının satışı, stok, müştəri, ödəniş və geniş axtarış idarəetməsi üçün professional Marakana plugin.
 * Version: 1.0.86
 * Author: Marakana
 * Text Domain: marakana-playstation-hesab-satisi
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Marakana_Playstation_Hesab_Satisi_100')) {
    final class Marakana_Playstation_Hesab_Satisi_100
    {
        const VERSION = '1.0.86';
        const DB_VERSION = '1.5.0';
        const OPTION_KEY = 'mara_account_sale_settings';
        const DB_OPTION_KEY = 'mara_account_sale_db_version';
        const LEGACY_IMPORT_OPTION_KEY = 'mara_account_sale_legacy_pdf_import_v54';
        const MOBILE_API_KEY_OPTION_KEY = 'mara_account_sale_mobile_api_key_v1';
        const CUSTOMER_CONTACTS_OPTION_KEY = 'mara_account_sale_customer_contacts_v1';
        const GAME_CATALOG_OPTION_KEY = 'mara_account_sale_game_catalog_v1';
        const GAME_RELATION_MIGRATION_OPTION_KEY = 'mara_account_sale_game_relation_migration_v1';

        private static $instance = null;

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

            add_action('admin_post_mara_account_sale_save', array($this, 'handle_save'));
            add_action('admin_post_mara_account_sale_delete', array($this, 'handle_delete'));
            add_action('admin_post_mara_account_sale_export_csv', array($this, 'handle_export_csv'));
            add_action('admin_post_mara_account_sale_save_settings', array($this, 'handle_save_settings'));
            add_action('admin_post_mara_account_sale_import_legacy', array($this, 'handle_import_legacy'));
            add_action('admin_post_mara_account_sale_repair_legacy', array($this, 'handle_repair_legacy'));
            add_action('admin_post_mara_account_sale_clear_database', array($this, 'handle_clear_database'));
            add_action('admin_post_mara_account_sale_import_pdf', array($this, 'handle_import_pdf'));
            add_action('admin_post_mara_account_sale_reset_import_pdf', array($this, 'handle_reset_import_pdf'));
            add_action('admin_post_mara_account_sale_regenerate_mobile_key', array($this, 'handle_regenerate_mobile_key'));

            add_action('admin_init', array($this, 'maybe_update_database'));
            add_action('init', array($this, 'maybe_update_database'));
            add_action('rest_api_init', array($this, 'register_mobile_rest_routes'));
        }

        public static function activate()
        {
            $self = self::instance();
            $self->create_or_update_table();
            if (!get_option(self::OPTION_KEY)) {
                add_option(self::OPTION_KEY, $self->default_settings());
            }
            update_option(self::DB_OPTION_KEY, self::DB_VERSION);
            $self->get_mobile_api_key();
        }

        public function maybe_update_database()
        {
            $current = get_option(self::DB_OPTION_KEY);
            if ($current !== self::DB_VERSION) {
                $this->create_or_update_table();
                update_option(self::DB_OPTION_KEY, self::DB_VERSION);
            }
        }

        private function table_name()
        {
            global $wpdb;
            return $wpdb->prefix . 'marakana_account_sales';
        }

        private function games_table_name()
        {
            global $wpdb;
            return $wpdb->prefix . 'marakana_account_games';
        }

        private function account_games_table_name()
        {
            global $wpdb;
            return $wpdb->prefix . 'marakana_account_sale_games';
        }

        private function capability()
        {
            return apply_filters('mara_account_sale_capability', 'manage_options');
        }

        private function default_settings()
        {
            return array(
                'currency' => 'AZN',
                'default_stock_status' => 'Satılıb',
                'default_payment_type' => 'Nağd',
                'phone_format_enabled' => '1',
            );
        }

        private function get_settings()
        {
            $settings = get_option(self::OPTION_KEY, array());
            return wp_parse_args(is_array($settings) ? $settings : array(), $this->default_settings());
        }

        private function create_or_update_table()
        {
            global $wpdb;
            $table = $this->table_name();
            $games_table = $this->games_table_name();
            $links_table = $this->account_games_table_name();
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                game_name TEXT NOT NULL,
                primary_game_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                account_type VARCHAR(20) NOT NULL,
                email VARCHAR(190) NOT NULL,
                account_password VARCHAR(255) NULL DEFAULT '',
                secret_code VARCHAR(255) NULL DEFAULT '',
                legacy_row_no BIGINT(20) UNSIGNED NULL DEFAULT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                console VARCHAR(10) NOT NULL,
                customer_name VARCHAR(190) NULL DEFAULT '',
                phone VARCHAR(30) NULL DEFAULT '',
                sale_date DATE NULL DEFAULT NULL,
                payment_type VARCHAR(20) NOT NULL,
                stock_status VARCHAR(30) NOT NULL,
                rental_duration_value INT(10) UNSIGNED NULL DEFAULT NULL,
                rental_duration_unit VARCHAR(10) NULL DEFAULT NULL,
                rental_started_at DATETIME NULL DEFAULT NULL,
                rental_ends_at DATETIME NULL DEFAULT NULL,
                deleted_at DATETIME NULL DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY primary_game_id (primary_game_id),
                KEY phone (phone),
                KEY stock_status (stock_status),
                KEY rental_ends_at (rental_ends_at),
                KEY deleted_at (deleted_at),
                KEY payment_type (payment_type),
                KEY sale_date (sale_date),
                KEY console (console),
                KEY account_type (account_type)
            ) {$charset_collate};";

            $games_sql = "CREATE TABLE {$games_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                game_name VARCHAR(190) NOT NULL,
                name_hash CHAR(32) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY name_hash (name_hash),
                KEY game_name (game_name)
            ) {$charset_collate};";

            $links_sql = "CREATE TABLE {$links_table} (
                account_id BIGINT(20) UNSIGNED NOT NULL,
                game_id BIGINT(20) UNSIGNED NOT NULL,
                sort_order INT(10) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY  (account_id,game_id),
                KEY game_id (game_id),
                KEY sort_order (sort_order)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            dbDelta($games_sql);
            dbDelta($links_sql);
            $this->repair_table_schema();
            $this->migrate_legacy_game_relations();
        }

        private function repair_table_schema()
        {
            global $wpdb;
            $table = $this->table_name();
            $wpdb->query("ALTER TABLE {$table} MODIFY customer_name VARCHAR(190) NULL DEFAULT ''");
            $wpdb->query("ALTER TABLE {$table} MODIFY phone VARCHAR(30) NULL DEFAULT ''");
            $wpdb->query("ALTER TABLE {$table} MODIFY sale_date DATE NULL DEFAULT NULL");
            $password_column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'account_password'");
            if (!$password_column) {
                $wpdb->query("ALTER TABLE {$table} ADD account_password VARCHAR(255) NULL DEFAULT '' AFTER email");
            } else {
                $wpdb->query("ALTER TABLE {$table} MODIFY account_password VARCHAR(255) NULL DEFAULT ''");
            }
            $secret_column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'secret_code'");
            if (!$secret_column) {
                $wpdb->query("ALTER TABLE {$table} ADD secret_code VARCHAR(255) NULL DEFAULT '' AFTER account_password");
            } else {
                $wpdb->query("ALTER TABLE {$table} MODIFY secret_code VARCHAR(255) NULL DEFAULT ''");
            }
            $legacy_row_column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'legacy_row_no'");
            if (!$legacy_row_column) {
                $wpdb->query("ALTER TABLE {$table} ADD legacy_row_no BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER secret_code");
            }
            $deleted_at_column = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'deleted_at'");
            if (!$deleted_at_column) {
                $wpdb->query("ALTER TABLE {$table} ADD deleted_at DATETIME NULL DEFAULT NULL AFTER rental_ends_at");
                $wpdb->query("ALTER TABLE {$table} ADD KEY deleted_at (deleted_at)");
            }
        }

        public function admin_menu()
        {
            add_menu_page(
                'Marakana Playstation Hesab Satışı',
                'Hesab Satışı',
                $this->capability(),
                'marakana-hesab-satisi',
                array($this, 'admin_page'),
                'dashicons-games',
                56
            );
        }

        public function register_frontend_assets()
        {
            wp_register_style(
                'mara-account-sale-style',
                plugin_dir_url(__FILE__) . 'assets/css/mara-account-sale.css',
                array(),
                self::VERSION
            );
            wp_register_script(
                'mara-account-sale-script',
                plugin_dir_url(__FILE__) . 'assets/js/mara-account-sale.js',
                array(),
                self::VERSION,
                true
            );
        }

        public function admin_enqueue_scripts($hook)
        {
            if ($hook !== 'toplevel_page_marakana-hesab-satisi') {
                return;
            }
            $this->enqueue_assets();
        }

        private function enqueue_assets()
        {
            if (!wp_style_is('mara-account-sale-style', 'registered')) {
                $this->register_frontend_assets();
            }
            wp_enqueue_style('mara-account-sale-style');
            wp_enqueue_script('mara-account-sale-script');
        }


        public function admin_page()
        {
            $this->enqueue_assets();
            echo $this->render_panel('admin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        private function purge_expired_trash()
        {
            global $wpdb;
            $table = $this->table_name();
            $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - (30 * DAY_IN_SECONDS));
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table} WHERE deleted_at IS NOT NULL AND deleted_at <= %s",
                $cutoff
            ));
            foreach (is_array($ids) ? $ids : array() as $id) {
                $id = absint($id);
                if ($id <= 0) continue;
                $this->delete_account_game_links($id);
                $wpdb->delete($table, array('id' => $id), array('%d'));
            }
            return is_array($ids) ? count($ids) : 0;
        }

        private function get_trash_records()
        {
            $this->purge_expired_trash();
            global $wpdb;
            $table = $this->table_name();
            $records = $wpdb->get_results("SELECT * FROM {$table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC, id DESC", ARRAY_A);
            if (!is_array($records)) return array();
            foreach ($records as &$record) {
                $record['stock_status'] = $this->normalize_stock_status_value(isset($record['stock_status']) ? $record['stock_status'] : '');
                $record['account_type'] = $this->normalize_legacy_account_type(isset($record['account_type']) ? $record['account_type'] : 'Online');
                $record['console'] = $this->normalize_legacy_console(isset($record['console']) ? $record['console'] : '');
                $this->hydrate_record_games($record);
            }
            unset($record);
            return $records;
        }

        private function get_records()
        {
            $this->purge_expired_trash();
            global $wpdb;
            $table = $this->table_name();
            $records = $wpdb->get_results("SELECT * FROM {$table} WHERE deleted_at IS NULL ORDER BY sale_date DESC, id DESC", ARRAY_A);
            if (!is_array($records)) {
                return array();
            }
            foreach ($records as &$record) {
                $record['stock_status'] = $this->normalize_stock_status_value(isset($record['stock_status']) ? $record['stock_status'] : '');
                $record['account_type'] = $this->normalize_legacy_account_type(isset($record['account_type']) ? $record['account_type'] : 'Online');
                $record['console'] = $this->normalize_legacy_console(isset($record['console']) ? $record['console'] : '');
                $this->hydrate_record_games($record);
            }
            unset($record);
            return $records;
        }

        private function get_saved_customer_contacts()
        {
            $contacts = get_option(self::CUSTOMER_CONTACTS_OPTION_KEY, array());
            return is_array($contacts) ? $contacts : array();
        }

        private function save_customer_contact($customer_name_raw, $phone_raw, &$error = '')
        {
            $customer_name = $this->normalize_name(sanitize_text_field((string) $customer_name_raw));
            if ($customer_name === '') {
                $error = 'Ad soyad boş ola bilməz.';
                return null;
            }
            if (!preg_match('/^[\p{L}\s]+$/u', $customer_name)) {
                $error = 'Ad soyad yalnız hərflərdən və boşluqdan ibarət olmalıdır.';
                return null;
            }

            $phone_error = '';
            $phone = $this->normalize_phone(sanitize_text_field((string) $phone_raw), $phone_error);
            if ($phone === '') {
                $error = $phone_error !== '' ? $phone_error : 'Telefon boş ola bilməz.';
                return null;
            }

            $contacts = $this->get_saved_customer_contacts();
            $key = md5($phone);
            $now = current_time('mysql');
            $created_at = isset($contacts[$key]['created_at']) ? (string) $contacts[$key]['created_at'] : $now;
            $contacts[$key] = array(
                'customer_name' => $customer_name,
                'phone' => $phone,
                'created_at' => $created_at,
                'updated_at' => $now,
            );
            update_option(self::CUSTOMER_CONTACTS_OPTION_KEY, $contacts, false);
            return $contacts[$key];
        }

        private function get_customer_rows()
        {
            global $wpdb;
            $table = $this->table_name();
            $rows = $wpdb->get_results(
                "SELECT phone,
                        MAX(customer_name) AS customer_name,
                        COUNT(*) AS game_count,
                        COALESCE(SUM(CASE WHEN stock_status = 'Satılıb' THEN price ELSE 0 END), 0) AS total_amount,
                        MAX(sale_date) AS last_sale_date,
                        MIN(created_at) AS first_account_created_at,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nağd' AND stock_status = 'Satılıb' THEN price ELSE 0 END), 0) AS cash_amount,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nisyə' AND stock_status = 'Satılıb' THEN price ELSE 0 END), 0) AS credit_amount,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nağd' AND stock_status = 'Satılıb' THEN 1 ELSE 0 END), 0) AS cash_count,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nisyə' AND stock_status = 'Satılıb' THEN 1 ELSE 0 END), 0) AS credit_count
                 FROM {$table}
                 WHERE deleted_at IS NULL AND phone <> '' AND stock_status = 'Satılıb'
                 GROUP BY phone",
                ARRAY_A
            );

            $merged = array();
            foreach (is_array($rows) ? $rows : array() as $row) {
                $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
                if ($phone === '') {
                    continue;
                }
                $row['contact_updated_at'] = '';
                $row['customer_created_at'] = isset($row['first_account_created_at']) ? (string) $row['first_account_created_at'] : '';
                $merged[$phone] = $row;
            }

            foreach ($this->get_saved_customer_contacts() as $contact) {
                if (!is_array($contact)) {
                    continue;
                }
                $phone = isset($contact['phone']) ? trim((string) $contact['phone']) : '';
                $name = isset($contact['customer_name']) ? trim((string) $contact['customer_name']) : '';
                if ($phone === '' || $name === '') {
                    continue;
                }
                $created_at = isset($contact['created_at']) ? (string) $contact['created_at'] : '';
                $updated_at = isset($contact['updated_at']) ? (string) $contact['updated_at'] : '';
                if (isset($merged[$phone])) {
                    $merged[$phone]['customer_name'] = $name;
                    $merged[$phone]['contact_updated_at'] = $updated_at;
                    if ($created_at !== '') {
                        $merged[$phone]['customer_created_at'] = $created_at;
                    }
                } else {
                    $merged[$phone] = array(
                        'phone' => $phone,
                        'customer_name' => $name,
                        'game_count' => 0,
                        'total_amount' => 0,
                        'last_sale_date' => '',
                        'first_account_created_at' => '',
                        'cash_amount' => 0,
                        'credit_amount' => 0,
                        'cash_count' => 0,
                        'credit_count' => 0,
                        'contact_updated_at' => $updated_at,
                        'customer_created_at' => $created_at,
                    );
                }
            }

            $customers = array_values($merged);
            usort($customers, function ($a, $b) {
                $a_created = isset($a['customer_created_at']) ? (string) $a['customer_created_at'] : '';
                $b_created = isset($b['customer_created_at']) ? (string) $b['customer_created_at'] : '';
                if ($a_created !== $b_created) {
                    if ($a_created === '') return 1;
                    if ($b_created === '') return -1;
                    return strcmp($b_created, $a_created);
                }
                $a_updated = isset($a['contact_updated_at']) ? (string) $a['contact_updated_at'] : '';
                $b_updated = isset($b['contact_updated_at']) ? (string) $b['contact_updated_at'] : '';
                if ($a_updated !== $b_updated) {
                    return strcmp($b_updated, $a_updated);
                }
                $a_sale = isset($a['last_sale_date']) ? (string) $a['last_sale_date'] : '';
                $b_sale = isset($b['last_sale_date']) ? (string) $b['last_sale_date'] : '';
                if ($a_sale !== $b_sale) {
                    return strcmp($b_sale, $a_sale);
                }
                return strcasecmp(
                    isset($a['customer_name']) ? (string) $a['customer_name'] : '',
                    isset($b['customer_name']) ? (string) $b['customer_name'] : ''
                );
            });
            return $customers;
        }

        private function split_game_names($value)
        {
            $value = trim((string) $value);
            if ($value === '') {
                return array();
            }
            $parts = preg_split('/\s*(?:,|;|\r?\n|\s+\+\s+|·|•)\s*/u', $value);
            $names = array();
            $seen = array();
            foreach ($parts as $part) {
                $name = trim((string) $part);
                if ($name === '') continue;
                $key = $this->game_name_key($name);
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $names[] = $name;
            }
            return $names;
        }

        private function normalize_game_names($value)
        {
            return implode(', ', $this->split_game_names($value));
        }

        private function game_name_key($value)
        {
            $value = trim((string) $value);
            return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        }

        private function game_name_hash($value)
        {
            return md5($this->game_name_key($value));
        }

        private function get_game_by_id($game_id)
        {
            global $wpdb;
            $game_id = absint($game_id);
            if ($game_id <= 0) return null;
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, game_name, created_at, updated_at FROM {$this->games_table_name()} WHERE id = %d", $game_id),
                ARRAY_A
            );
            return is_array($row) ? $row : null;
        }

        private function get_game_by_name($name)
        {
            global $wpdb;
            $name = trim((string) $name);
            if ($name === '') return null;
            $hash = $this->game_name_hash($name);
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT id, game_name, created_at, updated_at FROM {$this->games_table_name()} WHERE name_hash = %s", $hash),
                ARRAY_A
            );
            return is_array($row) ? $row : null;
        }

        private function ensure_game_record($name)
        {
            global $wpdb;
            $name = trim(sanitize_text_field((string) $name));
            if ($name === '') return 0;
            $existing = $this->get_game_by_name($name);
            if ($existing) return (int) $existing['id'];
            $now = current_time('mysql');
            $ok = $wpdb->insert(
                $this->games_table_name(),
                array(
                    'game_name' => $name,
                    'name_hash' => $this->game_name_hash($name),
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%s', '%s', '%s')
            );
            if ($ok === false) {
                $retry = $this->get_game_by_name($name);
                return $retry ? (int) $retry['id'] : 0;
            }
            return (int) $wpdb->insert_id;
        }

        private function normalize_game_ids($ids)
        {
            $out = array();
            $seen = array();
            if (!is_array($ids)) return $out;
            foreach ($ids as $id) {
                $id = absint($id);
                if ($id <= 0 || isset($seen[$id]) || !$this->get_game_by_id($id)) continue;
                $seen[$id] = true;
                $out[] = $id;
            }
            return $out;
        }

        private function game_ids_from_names($value)
        {
            $ids = array();
            foreach ($this->split_game_names($value) as $name) {
                $id = $this->ensure_game_record($name);
                if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
            }
            return $ids;
        }

        private function game_ids_from_input($input, $fallback_game_name = '')
        {
            $ids = array();
            if (is_array($input) && isset($input['game_ids']) && is_array($input['game_ids'])) {
                $ids = $this->normalize_game_ids($input['game_ids']);
            }
            if (empty($ids)) $ids = $this->game_ids_from_names($fallback_game_name);
            return $ids;
        }

        private function get_account_game_rows($account_id)
        {
            global $wpdb;
            $account_id = absint($account_id);
            if ($account_id <= 0) return array();
            $links = $this->account_games_table_name();
            $games = $this->games_table_name();
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT g.id, g.game_name, ag.sort_order
                     FROM {$links} ag
                     INNER JOIN {$games} g ON g.id = ag.game_id
                     WHERE ag.account_id = %d
                     ORDER BY ag.sort_order ASC, g.id ASC",
                    $account_id
                ),
                ARRAY_A
            );
            return is_array($rows) ? $rows : array();
        }

        private function refresh_account_game_cache($account_id)
        {
            global $wpdb;
            $account_id = absint($account_id);
            if ($account_id <= 0) return;
            $rows = $this->get_account_game_rows($account_id);
            $names = array();
            $first_id = null;
            foreach ($rows as $row) {
                if ($first_id === null) $first_id = (int) $row['id'];
                $names[] = (string) $row['game_name'];
            }
            $wpdb->update(
                $this->table_name(),
                array(
                    'game_name' => implode(', ', $names),
                    'primary_game_id' => $first_id,
                ),
                array('id' => $account_id),
                array('%s', '%d'),
                array('%d')
            );
        }

        private function sync_account_game_links($account_id, $game_ids = array(), $fallback_game_name = '')
        {
            global $wpdb;
            $account_id = absint($account_id);
            if ($account_id <= 0) return array();
            $game_ids = $this->normalize_game_ids($game_ids);
            if (empty($game_ids)) $game_ids = $this->game_ids_from_names($fallback_game_name);
            if (empty($game_ids)) return array();

            $links = $this->account_games_table_name();
            $wpdb->delete($links, array('account_id' => $account_id), array('%d'));
            $now = current_time('mysql');
            foreach ($game_ids as $order => $game_id) {
                $wpdb->insert(
                    $links,
                    array(
                        'account_id' => $account_id,
                        'game_id' => $game_id,
                        'sort_order' => (int) $order,
                        'created_at' => $now,
                    ),
                    array('%d', '%d', '%d', '%s')
                );
            }
            $this->refresh_account_game_cache($account_id);
            return $game_ids;
        }

        private function delete_account_game_links($account_id)
        {
            global $wpdb;
            $wpdb->delete($this->account_games_table_name(), array('account_id' => absint($account_id)), array('%d'));
        }

        private function cleanup_orphan_game_links()
        {
            global $wpdb;
            $links = $this->account_games_table_name();
            $sales = $this->table_name();
            $wpdb->query("DELETE ag FROM {$links} ag LEFT JOIN {$sales} s ON s.id = ag.account_id WHERE s.id IS NULL");
        }

        private function hydrate_record_games(&$record)
        {
            if (!is_array($record)) return;
            $account_id = isset($record['id']) ? absint($record['id']) : 0;
            if ($account_id <= 0) return;
            $rows = $this->get_account_game_rows($account_id);
            if (empty($rows) && !empty($record['game_name'])) {
                $this->sync_account_game_links($account_id, array(), $record['game_name']);
                $rows = $this->get_account_game_rows($account_id);
            }
            $ids = array();
            $names = array();
            $games = array();
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $name = (string) $row['game_name'];
                $ids[] = $id;
                $names[] = $name;
                $games[] = array('id' => $id, 'game_id' => $id, 'game_name' => $name);
            }
            if (!empty($names)) $record['game_name'] = implode(', ', $names);
            $record['game_ids'] = $ids;
            $record['games'] = $games;
            $record['primary_game_id'] = !empty($ids) ? $ids[0] : 0;
        }

        private function migrate_legacy_game_relations()
        {
            global $wpdb;
            $done = get_option(self::GAME_RELATION_MIGRATION_OPTION_KEY, '');
            if ($done === self::DB_VERSION) return;

            // Köhnə ayrıca oyun kataloqundakı adları yeni ID cədvəlinə köçür.
            $legacy_catalog = get_option(self::GAME_CATALOG_OPTION_KEY, array());
            if (is_array($legacy_catalog)) {
                foreach ($legacy_catalog as $entry) {
                    $name = is_array($entry) && isset($entry['game_name']) ? $entry['game_name'] : $entry;
                    $this->ensure_game_record($name);
                }
            }

            // Mövcud hesabların game_name mətnlərini game_id əlaqələrinə çevir.
            $rows = $wpdb->get_results("SELECT id, game_name FROM {$this->table_name()} ORDER BY id ASC", ARRAY_A);
            foreach (is_array($rows) ? $rows : array() as $row) {
                $account_id = isset($row['id']) ? absint($row['id']) : 0;
                if ($account_id <= 0) continue;
                if (empty($this->get_account_game_rows($account_id))) {
                    $this->sync_account_game_links($account_id, array(), isset($row['game_name']) ? $row['game_name'] : '');
                } else {
                    $this->refresh_account_game_cache($account_id);
                }
            }
            update_option(self::GAME_RELATION_MIGRATION_OPTION_KEY, self::DB_VERSION, false);
        }

        private function get_game_catalog()
        {
            $out = array();
            foreach ($this->get_game_name_rows() as $row) {
                $key = $this->game_name_key(isset($row['game_name']) ? $row['game_name'] : '');
                if ($key === '') continue;
                $out[$key] = array(
                    'id' => isset($row['id']) ? (int) $row['id'] : 0,
                    'game_name' => isset($row['game_name']) ? (string) $row['game_name'] : '',
                    'updated_at' => isset($row['last_used']) ? (string) $row['last_used'] : '',
                );
            }
            return $out;
        }

        private function save_game_catalog($catalog)
        {
            if (!is_array($catalog)) return;
            foreach ($catalog as $entry) {
                $name = is_array($entry) && isset($entry['game_name']) ? $entry['game_name'] : $entry;
                $this->ensure_game_record($name);
            }
        }

        private function remember_game_name($value)
        {
            foreach ($this->split_game_names($value) as $name) $this->ensure_game_record($name);
        }

        private function rename_game_name_everywhere($old_name, $new_name, &$error = '')
        {
            global $wpdb;
            $old_name = trim(sanitize_text_field((string) $old_name));
            $new_name = trim(sanitize_text_field((string) $new_name));
            if ($old_name === '' || $new_name === '') {
                $error = 'Köhnə və yeni oyun adı boş ola bilməz.';
                return false;
            }
            $this->migrate_legacy_game_relations();
            $old = $this->get_game_by_name($old_name);
            if (!$old) {
                $this->ensure_game_record($new_name);
                return 0;
            }
            $old_id = (int) $old['id'];
            $new = $this->get_game_by_name($new_name);
            $links = $this->account_games_table_name();
            $account_ids = $wpdb->get_col($wpdb->prepare("SELECT account_id FROM {$links} WHERE game_id = %d", $old_id));
            $account_ids = array_values(array_unique(array_map('absint', is_array($account_ids) ? $account_ids : array())));
            $now = current_time('mysql');

            if ($new && (int) $new['id'] !== $old_id) {
                $new_id = (int) $new['id'];
                foreach ($account_ids as $account_id) {
                    $sort_order = (int) $wpdb->get_var($wpdb->prepare(
                        "SELECT sort_order FROM {$links} WHERE account_id = %d AND game_id = %d",
                        $account_id,
                        $old_id
                    ));
                    $wpdb->query($wpdb->prepare(
                        "INSERT IGNORE INTO {$links} (account_id, game_id, sort_order, created_at) VALUES (%d, %d, %d, %s)",
                        $account_id,
                        $new_id,
                        $sort_order,
                        $now
                    ));
                }
                $wpdb->delete($links, array('game_id' => $old_id), array('%d'));
                $wpdb->delete($this->games_table_name(), array('id' => $old_id), array('%d'));
            } else {
                $updated = $wpdb->update(
                    $this->games_table_name(),
                    array(
                        'game_name' => $new_name,
                        'name_hash' => $this->game_name_hash($new_name),
                        'updated_at' => $now,
                    ),
                    array('id' => $old_id),
                    array('%s', '%s', '%s'),
                    array('%d')
                );
                if ($updated === false) {
                    $error = 'Oyun adı bazada yenilənmədi.';
                    return false;
                }
            }
            foreach ($account_ids as $account_id) $this->refresh_account_game_cache($account_id);
            return count($account_ids);
        }

        private function get_game_name_rows()
        {
            global $wpdb;
            $this->cleanup_orphan_game_links();
            $games = $this->games_table_name();
            $links = $this->account_games_table_name();
            $sales = $this->table_name();
            $rows = $wpdb->get_results(
                "SELECT g.id, g.game_name, g.updated_at,
                        COUNT(DISTINCT ag.account_id) AS use_count,
                        MAX(s.updated_at) AS last_used
                 FROM {$games} g
                 LEFT JOIN {$links} ag ON ag.game_id = g.id
                 LEFT JOIN {$sales} s ON s.id = ag.account_id
                 GROUP BY g.id, g.game_name, g.updated_at
                 ORDER BY COALESCE(MAX(s.updated_at), g.updated_at) DESC, g.game_name ASC",
                ARRAY_A
            );
            $out = array();
            foreach (is_array($rows) ? $rows : array() as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                $out[] = array(
                    'id' => $id,
                    'game_id' => $id,
                    'game_name' => isset($row['game_name']) ? (string) $row['game_name'] : '',
                    'use_count' => isset($row['use_count']) ? (int) $row['use_count'] : 0,
                    'last_used' => !empty($row['last_used']) ? (string) $row['last_used'] : (isset($row['updated_at']) ? (string) $row['updated_at'] : ''),
                );
            }
            return $out;
        }

        private function get_records_by_phone($phone)
        {
            global $wpdb;
            $table = $this->table_name();
            $records = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE deleted_at IS NULL AND phone = %s ORDER BY sale_date DESC, id DESC", $phone),
                ARRAY_A
            );
            foreach (is_array($records) ? $records : array() as &$record) $this->hydrate_record_games($record);
            unset($record);
            return is_array($records) ? $records : array();
        }

        private function calculate_stats($records)
        {
            $today = current_time('Y-m-d');
            $month = current_time('Y-m');
            $stats = array(
                'total' => count($records),
                'sold' => 0,
                'unsold' => 0,
                'rental' => 0,
                'rental_expiring' => 0,
                'rental_expired' => 0,
                'total_amount' => 0,
                'cash_amount' => 0,
                'credit_amount' => 0,
                'today_sold' => 0,
                'month_sold' => 0,
            );

            foreach ($records as $record) {
                $price = isset($record['price']) ? (float) $record['price'] : 0;
                if ($this->normalize_stock_status_value($record['stock_status']) === 'Satılıb') {
                    $stats['sold']++;
                    $stats['total_amount'] += $price;
                    if ($record['payment_type'] === 'Nağd') {
                        $stats['cash_amount'] += $price;
                    }
                    if ($record['payment_type'] === 'Nisyə') {
                        $stats['credit_amount'] += $price;
                    }
                    if ($record['sale_date'] === $today) {
                        $stats['today_sold']++;
                    }
                    if (strpos((string) $record['sale_date'], $month) === 0) {
                        $stats['month_sold']++;
                    }
                } elseif ($this->normalize_stock_status_value($record['stock_status']) === 'İcarə') {
                    $stats['rental']++;
                    $runtime = $this->rental_runtime_payload($record);
                    if ($runtime['rental_state'] === 'expired') $stats['rental_expired']++;
                    elseif ($runtime['rental_state'] === 'expiring') $stats['rental_expiring']++;
                } else {
                    $stats['unsold']++;
                }
            }

            return $stats;
        }

        private function format_price($price)
        {
            $settings = $this->get_settings();
            return number_format((float) $price, 2, '.', '') . ' ' . $settings['currency'];
        }

        private function normalize_name($name)
        {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
            if ($name === '') {
                return '';
            }
            if (function_exists('mb_strtoupper')) {
                return mb_strtoupper($name, 'UTF-8');
            }
            return strtoupper($name);
        }

        private function normalize_phone($phone, &$error = '')
        {
            $settings = $this->get_settings();
            $phone = trim((string) $phone);
            if ($phone === '') {
                return '';
            }

            if ($settings['phone_format_enabled'] !== '1') {
                return sanitize_text_field($phone);
            }

            $clean = preg_replace('/[^0-9\+]/', '', $phone);
            if (strpos($clean, '+') > 0) {
                $error = 'Telefon nömrəsində + işarəsi yalnız əvvəldə ola bilər.';
                return '';
            }

            if (strpos($clean, '+994') === 0 && strlen($clean) === 13) {
                $digits = substr($clean, 4);
                if (preg_match('/^\d{9}$/', $digits)) {
                    return '+994' . $digits;
                }
            }

            if (strpos($clean, '994') === 0 && strlen($clean) === 12) {
                $digits = substr($clean, 3);
                if (preg_match('/^\d{9}$/', $digits)) {
                    return '+994' . $digits;
                }
            }

            if (strpos($clean, '0') === 0 && strlen($clean) === 10) {
                $digits = substr($clean, 1);
                if (preg_match('/^\d{9}$/', $digits)) {
                    return '+994' . $digits;
                }
            }

            if (preg_match('/^\d{9}$/', $clean)) {
                return '+994' . $clean;
            }

            $error = 'Telefon nömrəsi düzgün deyil. Nümunə: 0705603030 və ya +994705603030.';
            return '';
        }

        private function validate_date($date)
        {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return false;
            }
            $parts = explode('-', $date);
            return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
        }

        private function normalize_rental_unit($value)
        {
            $value = strtolower(trim((string) $value));
            return in_array($value, array('hour', 'day'), true) ? $value : 'day';
        }

        private function rental_unit_label($unit)
        {
            return $this->normalize_rental_unit($unit) === 'hour' ? 'saat' : 'gün';
        }

        private function build_rental_fields($stock_status, $duration_value, $duration_unit, &$errors)
        {
            if ($stock_status !== 'İcarə') {
                return array(
                    'rental_duration_value' => null,
                    'rental_duration_unit' => null,
                    'rental_started_at' => null,
                    'rental_ends_at' => null,
                );
            }

            $duration_value = absint($duration_value);
            $duration_unit = $this->normalize_rental_unit($duration_unit);
            if ($duration_value <= 0) {
                $errors[] = 'İcarə seçilərsə icarə müddəti 0-dan böyük olmalıdır.';
                return array(
                    'rental_duration_value' => null,
                    'rental_duration_unit' => $duration_unit,
                    'rental_started_at' => null,
                    'rental_ends_at' => null,
                );
            }

            $seconds = $duration_value * ($duration_unit === 'hour' ? HOUR_IN_SECONDS : DAY_IN_SECONDS);
            $start_ts = current_time('timestamp');
            return array(
                'rental_duration_value' => $duration_value,
                'rental_duration_unit' => $duration_unit,
                'rental_started_at' => current_time('mysql'),
                'rental_ends_at' => date('Y-m-d H:i:s', $start_ts + $seconds),
            );
        }

        private function rental_runtime_payload($record)
        {
            $status = isset($record['stock_status']) ? $this->normalize_stock_status_value($record['stock_status']) : '';
            $ends_at = isset($record['rental_ends_at']) ? trim((string) $record['rental_ends_at']) : '';
            $value = isset($record['rental_duration_value']) ? (int) $record['rental_duration_value'] : 0;
            $unit = isset($record['rental_duration_unit']) ? $this->normalize_rental_unit($record['rental_duration_unit']) : 'day';
            $payload = array(
                'rental_duration_value' => $value,
                'rental_duration_unit' => $unit,
                'rental_duration_label' => $value > 0 ? ($value . ' ' . $this->rental_unit_label($unit)) : '',
                'rental_started_at' => isset($record['rental_started_at']) ? (string) $record['rental_started_at'] : '',
                'rental_ends_at' => $ends_at,
                'rental_remaining_seconds' => 0,
                'rental_state' => '',
                'rental_remaining_text' => '',
            );
            if ($status !== 'İcarə' || $ends_at === '') {
                return $payload;
            }

            try {
                $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
                $end = new DateTimeImmutable($ends_at, $tz);
                $now = new DateTimeImmutable('now', $tz);
                $remaining = $end->getTimestamp() - $now->getTimestamp();
            } catch (Exception $e) {
                $remaining = strtotime($ends_at) - current_time('timestamp');
            }
            $payload['rental_remaining_seconds'] = (int) $remaining;
            if ($remaining <= 0) {
                $payload['rental_state'] = 'expired';
                $payload['rental_remaining_text'] = 'Müddət bitib';
            } elseif ($remaining <= DAY_IN_SECONDS) {
                $payload['rental_state'] = 'expiring';
                $hours = (int) floor($remaining / HOUR_IN_SECONDS);
                $minutes = (int) floor(($remaining % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);
                $payload['rental_remaining_text'] = ($hours > 0 ? $hours . ' saat ' : '') . $minutes . ' dəq qalıb';
            } else {
                $days = (int) floor($remaining / DAY_IN_SECONDS);
                $hours = (int) floor(($remaining % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
                $payload['rental_state'] = 'active';
                $payload['rental_remaining_text'] = $days . ' gün' . ($hours > 0 ? ' ' . $hours . ' saat' : '') . ' qalıb';
            }
            return $payload;
        }

        private function preserve_existing_rental_window($id, &$data)
        {
            $id = absint($id);
            if ($id <= 0 || !is_array($data) || !isset($data['stock_status']) || $data['stock_status'] !== 'İcarə') {
                return;
            }
            global $wpdb;
            $existing = $wpdb->get_row($wpdb->prepare("SELECT stock_status, rental_duration_value, rental_duration_unit, rental_started_at, rental_ends_at FROM {$this->table_name()} WHERE id = %d", $id), ARRAY_A);
            if (!is_array($existing) || $this->normalize_stock_status_value(isset($existing['stock_status']) ? $existing['stock_status'] : '') !== 'İcarə') {
                return;
            }
            $same_value = (int) (isset($existing['rental_duration_value']) ? $existing['rental_duration_value'] : 0) === (int) (isset($data['rental_duration_value']) ? $data['rental_duration_value'] : 0);
            $same_unit = $this->normalize_rental_unit(isset($existing['rental_duration_unit']) ? $existing['rental_duration_unit'] : '') === $this->normalize_rental_unit(isset($data['rental_duration_unit']) ? $data['rental_duration_unit'] : '');
            if ($same_value && $same_unit && !empty($existing['rental_ends_at'])) {
                $data['rental_started_at'] = !empty($existing['rental_started_at']) ? $existing['rental_started_at'] : current_time('mysql');
                $data['rental_ends_at'] = $existing['rental_ends_at'];
            }
        }

        private function sort_rental_records($records)
        {
            usort($records, function ($a, $b) {
                $a_end = isset($a['rental_ends_at']) ? strtotime((string) $a['rental_ends_at']) : PHP_INT_MAX;
                $b_end = isset($b['rental_ends_at']) ? strtotime((string) $b['rental_ends_at']) : PHP_INT_MAX;
                if ($a_end === $b_end) {
                    return (isset($b['id']) ? (int) $b['id'] : 0) <=> (isset($a['id']) ? (int) $a['id'] : 0);
                }
                return $a_end <=> $b_end;
            });
            return $records;
        }

        private function sanitize_record_from_post(&$errors)
        {
            $allowed_types = array('Online', 'Universal', 'Offline');
            $allowed_consoles = array('PS4', 'PS5', 'PS4/PS5');
            $allowed_payments = array('Nağd', 'Nisyə');
            $allowed_stock = array('Satılıb', 'Satılmayıb', 'İcarə');

            $game_name = isset($_POST['game_name']) ? sanitize_text_field(wp_unslash($_POST['game_name'])) : '';
            $account_type = isset($_POST['account_type']) ? sanitize_text_field(wp_unslash($_POST['account_type'])) : '';
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $account_password = isset($_POST['account_password']) ? sanitize_text_field(wp_unslash($_POST['account_password'])) : '';
            $secret_code = isset($_POST['secret_code']) ? sanitize_text_field(wp_unslash($_POST['secret_code'])) : '';
            $price_raw = isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : '';
            $console = isset($_POST['console']) ? sanitize_text_field(wp_unslash($_POST['console'])) : '';
            $customer_name_raw = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
            $phone_raw = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
            $sale_date = isset($_POST['sale_date']) ? sanitize_text_field(wp_unslash($_POST['sale_date'])) : '';
            $payment_type = isset($_POST['payment_type']) ? sanitize_text_field(wp_unslash($_POST['payment_type'])) : '';
            $stock_status = isset($_POST['stock_status']) ? sanitize_text_field(wp_unslash($_POST['stock_status'])) : '';
            $rental_duration_value = isset($_POST['rental_duration_value']) ? absint($_POST['rental_duration_value']) : 0;
            $rental_duration_unit = isset($_POST['rental_duration_unit']) ? sanitize_text_field(wp_unslash($_POST['rental_duration_unit'])) : 'day';

            $game_name = $this->normalize_game_names($game_name);
            if ($game_name === '') $errors[] = 'Oyunun adı boş ola bilməz.';
            if (!in_array($account_type, $allowed_types, true)) $errors[] = 'Növ düzgün seçilməyib.';
            if ($email === '' || !is_email($email)) $errors[] = 'E-mail formatı düzgün deyil.';

            $price_clean = str_replace(',', '.', trim($price_raw));
            if ($price_clean === '' || !is_numeric($price_clean) || (float) $price_clean < 0) {
                $errors[] = 'Qiymət düzgün rəqəm olmalıdır və mənfi ola bilməz.';
                $price = 0;
            } else {
                $price = round((float) $price_clean, 2);
            }

            if (!in_array($console, $allowed_consoles, true)) $errors[] = 'Konsol düzgün seçilməyib.';
            if (!in_array($payment_type, $allowed_payments, true)) $errors[] = 'Ödəniş növü düzgün seçilməyib.';
            if (!in_array($stock_status, $allowed_stock, true)) $errors[] = 'Stok statusu düzgün seçilməyib.';

            if ($stock_status === 'Satılıb') {
                if (!$this->validate_date($sale_date)) $errors[] = 'Satılıb seçilərsə satış tarixi düzgün olmalıdır.';
            } elseif ($sale_date !== '' && !$this->validate_date($sale_date)) {
                $errors[] = 'Satış tarixi düzgün deyil.';
            }

            $customer_name = $this->normalize_name($customer_name_raw);
            if ($customer_name !== '' && !preg_match('/^[\p{L}\s]+$/u', $customer_name)) $errors[] = 'Ad soyad yalnız hərflərdən və boşluqdan ibarət olmalıdır.';

            $phone_error = '';
            $phone = $this->normalize_phone($phone_raw, $phone_error);
            if ($phone_raw !== '' && $phone === '' && $phone_error !== '') $errors[] = $phone_error;

            if ($stock_status === 'Satılıb' || $stock_status === 'İcarə') {
                if ($customer_name === '') $errors[] = ($stock_status === 'İcarə' ? 'İcarə' : 'Satılıb') . ' seçilərsə ad soyad məcburidir.';
                if ($phone === '') $errors[] = ($stock_status === 'İcarə' ? 'İcarə' : 'Satılıb') . ' seçilərsə telefon məcburidir.';
            }

            $rental = $this->build_rental_fields($stock_status, $rental_duration_value, $rental_duration_unit, $errors);
            return array_merge(array(
                'game_name' => $game_name,
                'account_type' => $account_type,
                'email' => $email,
                'account_password' => $account_password,
                'secret_code' => $secret_code,
                'price' => $price,
                'console' => $console,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'sale_date' => $stock_status === 'Satılıb' && $sale_date !== '' ? $sale_date : null,
                'payment_type' => $payment_type,
                'stock_status' => $stock_status,
            ), $rental);
        }

        private function pdf_unescape_string($value)
        {
            $value = preg_replace_callback('/\\([0-7]{1,3})/', function ($m) {
                return chr(octdec($m[1]));
            }, (string) $value);
            return str_replace(
                array('\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)', '\\\\'),
                array("\n", "\r", "\t", "\x08", "\x0C", '(', ')', '\\'),
                $value
            );
        }

        private function extract_pdf_text($path, &$error = '')
        {
            $error = '';
            $path = (string) $path;
            if ($path === '' || !is_readable($path)) {
                $error = 'PDF faylı oxunmur.';
                return '';
            }

            if (function_exists('shell_exec')) {
                $cmd = 'pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null';
                $out = @shell_exec($cmd);
                if (is_string($out) && strlen(trim($out)) > 20) {
                    return $out;
                }
            }

            $raw = @file_get_contents($path);
            if (!is_string($raw) || $raw === '') {
                $error = 'PDF faylı oxunmadı.';
                return '';
            }

            $chunks = array();
            if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
                foreach ($streams[1] as $stream) {
                    $decoded = $stream;
                    $unzipped = @gzuncompress($stream);
                    if ($unzipped === false && strlen($stream) > 2) {
                        $unzipped = @gzuncompress(substr($stream, 2));
                    }
                    if (is_string($unzipped) && $unzipped !== '') {
                        $decoded = $unzipped;
                    }

                    if (preg_match_all('/\((?:\\.|[^\\)])*\)\s*Tj/s', $decoded, $tj)) {
                        foreach ($tj[0] as $token) {
                            if (preg_match('/^\((.*)\)\s*Tj$/s', $token, $m)) {
                                $chunks[] = $this->pdf_unescape_string($m[1]);
                            }
                        }
                    }
                    if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $tjs)) {
                        foreach ($tjs[1] as $group) {
                            $line = '';
                            if (preg_match_all('/\((?:\\.|[^\\)])*\)/s', $group, $parts)) {
                                foreach ($parts[0] as $part) {
                                    $line .= $this->pdf_unescape_string(substr($part, 1, -1));
                                }
                            }
                            if (trim($line) !== '') {
                                $chunks[] = $line;
                            }
                        }
                    }
                }
            }

            $text = trim(implode("\n", $chunks));
            if ($text === '') {
                $error = 'PDF-dən mətn çıxarmaq mümkün olmadı. Mətn əsaslı PDF istifadə et.';
            }
            return $text;
        }

        private function normalize_import_header($value)
        {
            $value = function_exists('mb_strtolower') ? mb_strtolower((string) $value, 'UTF-8') : strtolower((string) $value);
            return strtr($value, array('ı'=>'i','ə'=>'e','ş'=>'s','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c','İ'=>'i','Ə'=>'e','Ş'=>'s','Ğ'=>'g','Ü'=>'u','Ö'=>'o','Ç'=>'c'));
        }

        private function repair_import_email($value)
        {
            $email = preg_replace('/\s+/u', '', (string) $value);
            $email = str_ireplace(array('@mailru', '@gmailcom', '@hotmailcom', '@outlookcom', '@yandexcom'), array('@mail.ru', '@gmail.com', '@hotmail.com', '@outlook.com', '@yandex.com'), $email);
            return sanitize_email($email);
        }

        private function parse_import_price($value)
        {
            $raw = strtoupper(preg_replace('/\s+/u', '', (string) $value));
            $raw = preg_replace('/[^0-9,\.]/', '', $raw);
            if ($raw === '') return 0;
            if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } elseif (strpos($raw, ',') !== false) {
                $raw = str_replace(',', '.', $raw);
            }
            return max(0, (float) $raw);
        }

        private function parse_import_date($value)
        {
            $raw = preg_replace('/\s+/u', '', (string) $value);
            if (preg_match('/^(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{4})$/', $raw, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            }
            if (preg_match('/^(\d{4})[\.\/-](\d{1,2})[\.\/-](\d{1,2})$/', $raw, $m)) {
                return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
            }
            return '';
        }

        private function normalize_import_phone_value($value)
        {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (strlen($digits) === 10 && strpos($digits, '0') === 0) return '+994' . substr($digits, 1);
            if (strlen($digits) === 9) return '+994' . $digits;
            if (strlen($digits) === 12 && strpos($digits, '994') === 0) return '+' . $digits;
            return trim((string) $value);
        }

        private function split_import_customer_and_phone($value)
        {
            $raw = trim(preg_replace('/\s+/u', ' ', (string) $value));
            if ($raw === '') return array('', '');
            if (preg_match('/((?:\+?994|0)[0-9\s]{8,20})$/u', $raw, $m, PREG_OFFSET_CAPTURE)) {
                $phone_raw = trim($m[1][0]);
                $name = trim(substr($raw, 0, $m[1][1]));
                return array($name, $this->normalize_import_phone_value($phone_raw));
            }
            return array($raw, '');
        }

        private function bbox_compact_text($items)
        {
            usort($items, function ($a, $b) {
                if (abs($a['y'] - $b['y']) > 0.5) return $a['y'] < $b['y'] ? -1 : 1;
                return $a['x'] < $b['x'] ? -1 : 1;
            });
            $out = '';
            foreach ($items as $item) $out .= preg_replace('/\s+/u', '', (string) $item['t']);
            return trim($out);
        }

        private function bbox_spaced_text($items)
        {
            usort($items, function ($a, $b) {
                if (abs($a['y'] - $b['y']) > 0.5) return $a['y'] < $b['y'] ? -1 : 1;
                return $a['x'] < $b['x'] ? -1 : 1;
            });
            $lines = array();
            $current_y = null;
            $current = array();
            foreach ($items as $item) {
                if ($current_y === null || abs($item['y'] - $current_y) < 1.0) {
                    $current[] = $item;
                    if ($current_y === null) $current_y = $item['y'];
                } else {
                    usort($current, function ($a, $b) { return $a['x'] < $b['x'] ? -1 : 1; });
                    $lines[] = implode(' ', array_map(function ($v) { return $v['t']; }, $current));
                    $current = array($item);
                    $current_y = $item['y'];
                }
            }
            if (!empty($current)) {
                usort($current, function ($a, $b) { return $a['x'] < $b['x'] ? -1 : 1; });
                $lines[] = implode(' ', array_map(function ($v) { return $v['t']; }, $current));
            }
            return trim(preg_replace('/\s+/u', ' ', implode(' ', $lines)));
        }

        private function parse_pdf_account_rows_bbox($path, &$error = '')
        {
            $error = '';
            if (!function_exists('shell_exec') || !class_exists('DOMDocument')) {
                $error = 'Dəqiq PDF import üçün serverdə pdftotext və PHP DOM dəstəyi tələb olunur.';
                return array();
            }
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (in_array('shell_exec', $disabled, true)) {
                $error = 'Dəqiq PDF import üçün shell_exec deaktiv edilməməlidir.';
                return array();
            }
            $tmp = function_exists('wp_tempnam') ? wp_tempnam('marakana-pdf-bbox.html') : tempnam(sys_get_temp_dir(), 'mara_pdf_');
            if (!$tmp) {
                $error = 'PDF üçün müvəqqəti fayl yaradıla bilmədi.';
                return array();
            }
            @unlink($tmp);
            $cmd = 'pdftotext -bbox-layout ' . escapeshellarg($path) . ' ' . escapeshellarg($tmp) . ' 2>&1';
            @shell_exec($cmd);
            if (!is_readable($tmp) || filesize($tmp) < 100) {
                @unlink($tmp);
                $error = 'pdftotext -bbox-layout PDF-ni oxuya bilmədi.';
                return array();
            }
            $xml = file_get_contents($tmp);
            @unlink($tmp);
            if ($xml === false || $xml === '') {
                $error = 'PDF koordinat məlumatı boş gəldi.';
                return array();
            }

            $dom = new DOMDocument();
            $old = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            libxml_use_internal_errors($old);
            if (!$loaded) {
                $error = 'PDF koordinat XML-i oxunmadı.';
                return array();
            }
            $xpath = new DOMXPath($dom);
            $pages = $xpath->query('//*[local-name()="page"]');
            if (!$pages || $pages->length === 0) {
                $error = 'PDF səhifələri tapılmadı.';
                return array();
            }

            $rows = array();
            foreach ($pages as $page_index => $page) {
                $word_nodes = $xpath->query('.//*[local-name()="word"]', $page);
                $words = array();
                $header_y = 0.0;
                foreach ($word_nodes as $word) {
                    $t = trim((string) $word->textContent);
                    if ($t === '') continue;
                    $x = (float) $word->getAttribute('xMin');
                    $y = (float) $word->getAttribute('yMin');
                    $words[] = array('x' => $x, 'y' => $y, 't' => $t);
                    if ($t === 'Sıra' && ($header_y <= 0 || $y < $header_y)) $header_y = $y;
                }
                if ($header_y <= 0) continue;

                $email_y = array();
                foreach ($words as $w) {
                    if ($w['x'] >= 223 && $w['x'] < 325 && $w['y'] > $header_y + 20) {
                        $email_y[number_format($w['y'], 3, '.', '')] = true;
                    }
                }
                $status_anchors = array();
                $type_anchors = array();
                foreach ($words as $w) {
                    $yk = number_format($w['y'], 3, '.', '');
                    if (!isset($email_y[$yk])) continue;
                    $norm = $this->normalize_import_header($w['t']);
                    if ($w['x'] >= 178 && $w['x'] < 223 && (strpos($norm, 'sat') === 0 || strpos($norm, 'ica') === 0)) {
                        $status_anchors[$yk] = $w['y'];
                    }
                    if ($w['x'] >= 155 && $w['x'] < 178 && preg_match('/^(on|un|of|off)$/i', $w['t'])) {
                        $type_anchors[$yk] = $w['y'];
                    }
                }
                foreach ($type_anchors as $yk => $y) {
                    foreach ($status_anchors as $sy) {
                        if (abs($y - $sy) <= 18) {
                            unset($type_anchors[$yk]);
                            break;
                        }
                    }
                }
                $anchors = array_values(array_merge($status_anchors, $type_anchors));
                sort($anchors, SORT_NUMERIC);
                if (empty($anchors)) continue;

                $page_end = 805.0;
                foreach ($words as $w) {
                    if ($w['t'] !== '1.0' || $w['y'] <= $header_y + 200) continue;
                    foreach ($words as $w2) {
                        if ($w2['t'] === 'Playstation' && abs($w2['y'] - $w['y']) < 1.0) {
                            $page_end = min($page_end, $w['y'] - 1.0);
                            break;
                        }
                    }
                }

                $id_words = array();
                foreach ($words as $w) {
                    if ($w['x'] >= 45 && $w['x'] < 80 && $w['y'] > $header_y + 20 && preg_match('/^\d{1,4}$/', $w['t'])) {
                        $id_words[] = array('y' => $w['y'], 'id' => (int) $w['t']);
                    }
                }
                usort($id_words, function ($a, $b) { return $a['y'] < $b['y'] ? -1 : 1; });

                $ranges = array(
                    'game_name' => array(80, 158, false),
                    'account_type_raw' => array(158, 178, true),
                    'stock_status_raw' => array(178, 223, true),
                    'email_raw' => array(223, 325, true),
                    'account_password' => array(325, 374, true),
                    'price_raw' => array(374, 400, true),
                    'console_raw' => array(400, 423, true),
                    'customer_raw' => array(423, 489, false),
                    'sale_date_raw' => array(489, 525, true),
                    'secret_code' => array(525, 999, true),
                );

                $anchor_count = count($anchors);
                for ($i = 0; $i < $anchor_count; $i++) {
                    $start_y = (float) $anchors[$i];
                    $nominal_end = $i + 1 < $anchor_count ? (float) $anchors[$i + 1] : $page_end;
                    $candidate_ids = array();
                    foreach ($id_words as $idw) {
                        if ($idw['y'] >= $start_y - 10 && $idw['y'] < $nominal_end) $candidate_ids[] = $idw;
                    }
                    $legacy_id = !empty($candidate_ids) ? (int) $candidate_ids[0]['id'] : 0;
                    $end_y = $nominal_end;
                    if (count($candidate_ids) > 1) $end_y = min($end_y, (float) $candidate_ids[1]['y'] - 6.0);

                    $bucket = array();
                    foreach ($ranges as $key => $range) $bucket[$key] = array();
                    foreach ($words as $w) {
                        if ($w['y'] < $start_y - 0.01 || $w['y'] >= $end_y - 0.01) continue;
                        foreach ($ranges as $key => $range) {
                            if ($w['x'] >= $range[0] && $w['x'] < $range[1]) {
                                $bucket[$key][] = $w;
                                break;
                            }
                        }
                    }

                    $raw = array();
                    foreach ($ranges as $key => $range) {
                        $raw[$key] = $range[2] ? $this->bbox_compact_text($bucket[$key]) : $this->bbox_spaced_text($bucket[$key]);
                    }
                    $email = $this->repair_import_email($raw['email_raw']);
                    $game_name = trim($raw['game_name']);
                    if ($game_name === '' || $email === '' || !is_email($email)) continue;
                    list($customer_name, $phone) = $this->split_import_customer_and_phone($raw['customer_raw']);
                    $sale_date = $this->parse_import_date($raw['sale_date_raw']);
                    $status = $raw['stock_status_raw'] === '' ? 'Satılmayıb' : $this->normalize_stock_status_value($raw['stock_status_raw']);
                    $rows[] = array(
                        'legacy_row_no' => $legacy_id,
                        'game_name' => $game_name,
                        'account_type' => $this->normalize_legacy_account_type($raw['account_type_raw']),
                        'email' => $email,
                        'account_password' => sanitize_text_field($raw['account_password']),
                        'secret_code' => sanitize_text_field($raw['secret_code']),
                        'price' => $this->parse_import_price($raw['price_raw']),
                        'console' => $this->normalize_legacy_console($raw['console_raw']),
                        'customer_name' => sanitize_text_field($customer_name),
                        'phone' => sanitize_text_field($phone),
                        'sale_date' => $sale_date,
                        'payment_type' => 'Nağd',
                        'stock_status' => $status,
                    );
                }
            }
            if (empty($rows)) $error = 'PDF-də uyğun hesab sətirləri tapılmadı.';
            return $rows;
        }

        private function parse_pdf_account_rows($text, &$error = '')
        {
            $error = '';
            $lines = preg_split('/\R/u', (string) $text);
            $rows = array();
            foreach ($lines as $line) {
                $line = trim(preg_replace('/[\x{00A0}\t]+/u', '  ', (string) $line));
                if ($line === '' || stripos($this->normalize_import_header($line), 'oyunun adi') !== false) {
                    continue;
                }
                if (!preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $line, $email_match, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $email = sanitize_email($email_match[0][0]);
                if ($email === '' || !is_email($email)) {
                    continue;
                }

                $cols = preg_split('/\s{2,}/u', $line);
                $cols = array_values(array_filter(array_map('trim', $cols), function ($v) { return $v !== ''; }));
                $email_index = -1;
                foreach ($cols as $idx => $col) {
                    if (strpos($col, '@') !== false) {
                        $email_index = $idx;
                        break;
                    }
                }

                $game_name = '';
                $account_type = 'Online';
                $console = '';
                $price = 0;
                $customer_name = '';
                $phone = '';
                $sale_date = '';
                $stock_status = 'Satılmayıb';

                if ($email_index >= 0) {
                    $before = array_slice($cols, 0, $email_index);
                    foreach ($before as $part) {
                        $candidate_type = $this->normalize_legacy_account_type($part);
                        $norm = $this->normalize_import_header($part);
                        if (in_array($norm, array('on','online','un','universal','unversal','off','offline','ofline','oflline'), true)) {
                            $account_type = $candidate_type;
                        } else {
                            $game_name = trim($game_name . ' ' . $part);
                        }
                    }
                    $after = array_slice($cols, $email_index + 1);
                } else {
                    $pos = $email_match[0][1];
                    $game_name = trim(substr($line, 0, $pos));
                    $after = preg_split('/\s{2,}/u', trim(substr($line, $pos + strlen($email_match[0][0]))));
                }

                foreach ($after as $part) {
                    $part = trim((string) $part);
                    if ($part === '') continue;
                    $norm = $this->normalize_import_header($part);
                    if ($console === '' && preg_match('/PS\s*4\s*\/\s*PS\s*5|PS\s*4|PS\s*5/i', $part, $m)) {
                        $console = $this->normalize_legacy_console($m[0]);
                        continue;
                    }
                    if ($sale_date === '' && preg_match('/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/', $part, $m)) {
                        $sale_date = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
                        continue;
                    }
                    if ($sale_date === '' && preg_match('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/', $part, $m)) {
                        $sale_date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                        continue;
                    }
                    if ($phone === '' && preg_match('/(?:\+?994)?0?\d{9}\b/', preg_replace('/\s+/', '', $part), $m)) {
                        $phone = $m[0];
                        continue;
                    }
                    if (strpos($norm, 'satilmayib') !== false || strpos($norm, 'satilmayan') !== false || $norm === 'unsold') {
                        $stock_status = 'Satılmayıb';
                        continue;
                    }
                    if (strpos($norm, 'satilib') !== false || $norm === 'sold') {
                        $stock_status = 'Satılıb';
                        continue;
                    }
                    if ($price <= 0 && preg_match('/^\d+(?:[\.,]\d{1,2})?$/', $part)) {
                        $price = (float) str_replace(',', '.', $part);
                        continue;
                    }
                    if ($customer_name === '' && preg_match('/^[\p{L}\s]+$/u', $part)) {
                        $customer_name = $part;
                    }
                }

                $game_name = trim(preg_replace('/\b(?:On|Online|Un|Universal|Unversal|Off|Offline)\b\s*$/iu', '', $game_name));
                if ($game_name === '') {
                    continue;
                }
                if ($stock_status === 'Satılmayıb') {
                    $customer_name = '';
                    $phone = '';
                    $sale_date = '';
                }

                $rows[] = array(
                    'game_name' => $game_name,
                    'account_type' => $account_type,
                    'email' => $email,
                    'price' => $price,
                    'console' => $console,
                    'customer_name' => $customer_name,
                    'phone' => $phone,
                    'sale_date' => $sale_date,
                    'payment_type' => 'Nağd',
                    'stock_status' => $stock_status,
                );
            }

            if (empty($rows)) {
                $error = 'PDF-də hesab sətrləri tapılmadı. PDF mətn əsaslı və sütunlu formatda olmalıdır.';
            }
            return $rows;
        }

        private function parse_client_pdf_bbox_pages($pages, &$error = '')
        {
            $error = '';
            if (!is_array($pages) || empty($pages)) {
                $error = 'Brauzer PDF məlumatını göndərmədi.';
                return array();
            }

            $rows = array();
            foreach ($pages as $page_index => $page_words) {
                if (!is_array($page_words) || empty($page_words)) continue;
                $words = array();
                $header_y = 0.0;
                foreach ($page_words as $word) {
                    if (!is_array($word)) continue;
                    $t = isset($word['t']) ? trim((string) $word['t']) : '';
                    if ($t === '') continue;
                    $x = isset($word['x']) ? (float) $word['x'] : 0.0;
                    $y = isset($word['y']) ? (float) $word['y'] : 0.0;
                    $words[] = array('x' => $x, 'y' => $y, 't' => $t);
                    if ($t === 'Sıra' && ($header_y <= 0 || $y < $header_y)) $header_y = $y;
                }
                if ($header_y <= 0) continue;

                $email_y = array();
                foreach ($words as $w) {
                    if ($w['x'] >= 223 && $w['x'] < 325 && $w['y'] > $header_y + 20) {
                        $email_y[number_format($w['y'], 3, '.', '')] = true;
                    }
                }
                $status_anchors = array();
                $type_anchors = array();
                foreach ($words as $w) {
                    $yk = number_format($w['y'], 3, '.', '');
                    if (!isset($email_y[$yk])) continue;
                    $norm = $this->normalize_import_header($w['t']);
                    if ($w['x'] >= 178 && $w['x'] < 223 && (strpos($norm, 'sat') === 0 || strpos($norm, 'ica') === 0)) {
                        $status_anchors[$yk] = $w['y'];
                    }
                    if ($w['x'] >= 155 && $w['x'] < 178 && preg_match('/^(on|un|of|off)$/i', $w['t'])) {
                        $type_anchors[$yk] = $w['y'];
                    }
                }
                foreach ($type_anchors as $yk => $y) {
                    foreach ($status_anchors as $sy) {
                        if (abs($y - $sy) <= 18) {
                            unset($type_anchors[$yk]);
                            break;
                        }
                    }
                }
                $anchors = array_values(array_merge($status_anchors, $type_anchors));
                sort($anchors, SORT_NUMERIC);
                if (empty($anchors)) continue;

                $page_end = 805.0;
                foreach ($words as $w) {
                    if ($w['t'] !== '1.0' || $w['y'] <= $header_y + 200) continue;
                    foreach ($words as $w2) {
                        if ($w2['t'] === 'Playstation' && abs($w2['y'] - $w['y']) < 1.0) {
                            $page_end = min($page_end, $w['y'] - 1.0);
                            break;
                        }
                    }
                }

                $id_words = array();
                foreach ($words as $w) {
                    if ($w['x'] >= 45 && $w['x'] < 80 && $w['y'] > $header_y + 20 && preg_match('/^\d{1,4}$/', $w['t'])) {
                        $id_words[] = array('y' => $w['y'], 'id' => (int) $w['t']);
                    }
                }
                usort($id_words, function ($a, $b) { return $a['y'] < $b['y'] ? -1 : 1; });

                $ranges = array(
                    'game_name' => array(80, 158, false),
                    'account_type_raw' => array(158, 178, true),
                    'stock_status_raw' => array(178, 223, true),
                    'email_raw' => array(223, 325, true),
                    'account_password' => array(325, 374, true),
                    'price_raw' => array(374, 400, true),
                    'console_raw' => array(400, 423, true),
                    'customer_raw' => array(423, 489, false),
                    'sale_date_raw' => array(489, 525, true),
                    'secret_code' => array(525, 999, true),
                );

                $anchor_count = count($anchors);
                for ($i = 0; $i < $anchor_count; $i++) {
                    $start_y = (float) $anchors[$i];
                    $nominal_end = $i + 1 < $anchor_count ? (float) $anchors[$i + 1] : $page_end;
                    $candidate_ids = array();
                    foreach ($id_words as $idw) {
                        if ($idw['y'] >= $start_y - 10 && $idw['y'] < $nominal_end) $candidate_ids[] = $idw;
                    }
                    $legacy_id = !empty($candidate_ids) ? (int) $candidate_ids[0]['id'] : 0;
                    $end_y = $nominal_end;
                    if (count($candidate_ids) > 1) $end_y = min($end_y, (float) $candidate_ids[1]['y'] - 6.0);

                    $bucket = array();
                    foreach ($ranges as $key => $range) $bucket[$key] = array();
                    foreach ($words as $w) {
                        if ($w['y'] < $start_y - 0.01 || $w['y'] >= $end_y - 0.01) continue;
                        foreach ($ranges as $key => $range) {
                            if ($w['x'] >= $range[0] && $w['x'] < $range[1]) {
                                $bucket[$key][] = $w;
                                break;
                            }
                        }
                    }

                    $raw = array();
                    foreach ($ranges as $key => $range) {
                        $raw[$key] = $range[2] ? $this->bbox_compact_text($bucket[$key]) : $this->bbox_spaced_text($bucket[$key]);
                    }
                    $email = $this->repair_import_email($raw['email_raw']);
                    $game_name = trim($raw['game_name']);
                    if ($game_name === '' || $email === '' || !is_email($email)) continue;
                    list($customer_name, $phone) = $this->split_import_customer_and_phone($raw['customer_raw']);
                    $sale_date = $this->parse_import_date($raw['sale_date_raw']);
                    $status = $raw['stock_status_raw'] === '' ? 'Satılmayıb' : $this->normalize_stock_status_value($raw['stock_status_raw']);
                    $rows[] = array(
                        'legacy_row_no' => $legacy_id,
                        'game_name' => $game_name,
                        'account_type' => $this->normalize_legacy_account_type($raw['account_type_raw']),
                        'email' => $email,
                        'account_password' => sanitize_text_field($raw['account_password']),
                        'secret_code' => sanitize_text_field($raw['secret_code']),
                        'price' => $this->parse_import_price($raw['price_raw']),
                        'console' => $this->normalize_legacy_console($raw['console_raw']),
                        'customer_name' => sanitize_text_field($customer_name),
                        'phone' => sanitize_text_field($phone),
                        'sale_date' => $sale_date,
                        'payment_type' => 'Nağd',
                        'stock_status' => $status,
                    );
                }
            }
            if (empty($rows)) $error = 'PDF-də uyğun hesab sətirləri tapılmadı. Brauzer PDF-ni oxudu, amma cədvəl formatı tanınmadı.';
            return $rows;
        }

        private function load_client_pdf_rows_from_post(&$error = '')
        {
            $error = '';
            if (empty($_POST['mara_client_pdf_bbox'])) return null;
            $raw = wp_unslash((string) $_POST['mara_client_pdf_bbox']);
            if (strlen($raw) > 8 * 1024 * 1024) {
                $error = 'Brauzerdən gələn PDF məlumatı həddən artıq böyükdür.';
                return array();
            }
            $pages = json_decode($raw, true);
            if (!is_array($pages)) {
                $error = 'Brauzerdən gələn PDF məlumatı oxunmadı.';
                return array();
            }
            return $this->parse_client_pdf_bbox_pages($pages, $error);
        }

        private function load_uploaded_pdf_rows($path, &$error = '')
        {
            $bbox_error = '';
            $rows = $this->parse_pdf_account_rows_bbox($path, $bbox_error);
            if (!empty($rows)) {
                $error = '';
                return $rows;
            }
            $text_error = '';
            $text = $this->extract_pdf_text($path, $text_error);
            if ($text === '') {
                $error = $bbox_error !== '' ? $bbox_error : $text_error;
                return array();
            }
            $rows = $this->parse_pdf_account_rows($text, $text_error);
            if (empty($rows)) {
                $error = $bbox_error !== '' ? $bbox_error . ' ' . $text_error : $text_error;
            }
            return $rows;
        }

        private function load_legacy_account_rows()
        {
            $php_path = plugin_dir_path(__FILE__) . 'data/old-base-playstation-accounts.php';
            if (is_readable($php_path)) {
                $rows = include $php_path;
                if (is_array($rows) && !empty($rows)) {
                    return $rows;
                }
            }

            $path = plugin_dir_path(__FILE__) . 'data/old-base-playstation-accounts.csv';
            if (!is_readable($path)) {
                return null;
            }

            $handle = fopen($path, 'r');
            if (!$handle) {
                return null;
            }

            $header = fgetcsv($handle, 0, ',');
            if (!is_array($header) || empty($header)) {
                fclose($handle);
                return null;
            }
            $header = array_map('trim', $header);
            $rows = array();
            while (($line = fgetcsv($handle, 0, ',')) !== false) {
                if (count($line) < count($header)) {
                    $line = array_pad($line, count($header), '');
                }
                $row = array_combine($header, array_slice($line, 0, count($header)));
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            fclose($handle);

            return $rows;
        }

        private function normalize_legacy_account_type($value)
        {
            $value = trim((string) $value);
            $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            if (in_array($lower, array('on', 'online'), true)) {
                return 'Online';
            }
            if (in_array($lower, array('un', 'universal', 'unversal'), true)) {
                return 'Universal';
            }
            if (in_array($lower, array('off', 'offline', 'ofline', 'oflline'), true)) {
                return 'Offline';
            }
            if (in_array($value, array('Online', 'Universal', 'Offline'), true)) {
                return $value;
            }
            return 'Online';
        }

        private function normalize_stock_status_value($value)
        {
            $value = trim((string) $value);
            if ($value === '') {
                return 'Satılmayıb';
            }

            $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
            $ascii = strtr($lower, array(
                'ı' => 'i',
                'İ' => 'i',
                'ə' => 'e',
                'Ə' => 'e',
                'ş' => 's',
                'Ş' => 's',
                'ğ' => 'g',
                'Ğ' => 'g',
                'ü' => 'u',
                'Ü' => 'u',
                'ö' => 'o',
                'Ö' => 'o',
                'ç' => 'c',
                'Ç' => 'c',
            ));

            if (strpos($lower, 'icarə') !== false || strpos($ascii, 'icare') !== false || strpos($ascii, 'rental') !== false) {
                return 'İcarə';
            }
            if (strpos($lower, 'satılmayıb') !== false || strpos($ascii, 'satilmayib') !== false || strpos($ascii, 'satilmayan') !== false || strpos($ascii, 'unsold') !== false) {
                return 'Satılmayıb';
            }
            if (strpos($lower, 'satılıb') !== false || strpos($ascii, 'satilib') !== false || strpos($ascii, 'sold') !== false) {
                return 'Satılıb';
            }

            return in_array($value, array('Satılıb', 'Satılmayıb', 'İcarə'), true) ? $value : 'Satılmayıb';
        }

        private function normalize_legacy_console($value)
        {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }
            $clean = strtoupper(str_replace(array(' ', '&', '+'), array('', '/', '/'), $value));
            if (strpos($clean, 'PS4') !== false && strpos($clean, 'PS5') !== false) {
                return 'PS4/PS5';
            }
            if (strpos($clean, 'PS5') !== false) {
                return 'PS5';
            }
            if (strpos($clean, 'PS4') !== false) {
                return 'PS4';
            }
            return sanitize_text_field((string) $value);
        }

        private function maybe_import_legacy_accounts($force = false, $allow_duplicates = false, $rows_override = null, $source_name = '')
        {
            $repair_mode = ($force === 'repair');
            if (!$force && get_option(self::LEGACY_IMPORT_OPTION_KEY)) {
                return array('inserted' => 0, 'inserted_sold' => 0, 'inserted_unsold' => 0, 'skipped' => 0, 'already_imported' => true, 'error' => '');
            }

            $legacy_rows = is_array($rows_override) ? $rows_override : $this->load_legacy_account_rows();
            if (!is_array($legacy_rows) || empty($legacy_rows)) {
                return array('inserted' => 0, 'inserted_sold' => 0, 'inserted_unsold' => 0, 'skipped' => 0, 'already_imported' => false, 'error' => 'Import məlumat faylı tapılmadı və ya oxunmadı.');
            }

            global $wpdb;
            $table = $this->table_name();
            $this->create_or_update_table();
            $this->repair_table_schema();
            $inserted = 0;
            $inserted_sold = 0;
            $inserted_unsold = 0;
            $skipped = 0;
            $skipped_sold = 0;
            $skipped_unsold = 0;
            $last_error = '';
            $now = current_time('mysql');
            $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
            $repair_deleted_signatures = array();
            $repair_deleted = 0;

            foreach ($legacy_rows as $row) {
                if (!is_array($row)) {
                    $skipped++;
                    continue;
                }

                $game_name = isset($row['game_name']) ? trim((string) $row['game_name']) : '';
                $email = isset($row['email']) ? sanitize_email((string) $row['email']) : '';
                if ($game_name === '' || $email === '' || !is_email($email)) {
                    $skipped++;
                    continue;
                }

                $account_type = $this->normalize_legacy_account_type(isset($row['account_type']) ? $row['account_type'] : 'Online');
                $stock_status = $this->normalize_stock_status_value(isset($row['stock_status']) ? $row['stock_status'] : 'Satılmayıb');

                $payment_type = isset($row['payment_type']) ? trim((string) $row['payment_type']) : 'Nağd';
                if (!in_array($payment_type, array('Nağd', 'Nisyə'), true)) {
                    $payment_type = 'Nağd';
                }

                $sale_date = isset($row['sale_date']) ? trim((string) $row['sale_date']) : '';
                if ($sale_date !== '' && !$this->validate_date($sale_date)) {
                    $sale_date = '';
                }

                $phone = isset($row['phone']) ? trim((string) $row['phone']) : '';
                $customer_name = isset($row['customer_name']) ? trim((string) $row['customer_name']) : '';

                $price = isset($row['price']) ? (float) str_replace(',', '.', (string) $row['price']) : 0;
                if ($price < 0) {
                    $price = 0;
                }

                $normalized_game_name = $this->normalize_game_names($game_name);
                $data = array(
                    'game_name' => $normalized_game_name,
                    'account_type' => $account_type,
                    'email' => $email,
                    'account_password' => isset($row['account_password']) ? sanitize_text_field((string) $row['account_password']) : '',
                    'secret_code' => isset($row['secret_code']) ? sanitize_text_field((string) $row['secret_code']) : '',
                    'legacy_row_no' => isset($row['legacy_row_no']) ? absint($row['legacy_row_no']) : null,
                    'price' => round($price, 2),
                    'console' => $this->normalize_legacy_console(isset($row['console']) ? $row['console'] : ''),
                    'customer_name' => $customer_name,
                    'phone' => $phone,
                    'sale_date' => $sale_date !== '' ? $sale_date : null,
                    'payment_type' => $payment_type,
                    'stock_status' => $stock_status,
                    'created_at' => $now,
                    'updated_at' => $now,
                );

                if ($repair_mode) {
                    $repair_signature = md5(strtolower($data['email']) . '|' . $data['account_type'] . '|' . $data['game_name'] . '|' . $data['phone'] . '|' . $sale_date);
                    if (!isset($repair_deleted_signatures[$repair_signature])) {
                        $deleted_for_row = $wpdb->query(
                            $wpdb->prepare(
                                "DELETE FROM {$table} WHERE email = %s AND account_type = %s AND game_name = %s AND COALESCE(phone, '') = %s AND COALESCE(sale_date, '') = %s",
                                $data['email'],
                                $data['account_type'],
                                $data['game_name'],
                                $data['phone'],
                                $sale_date
                            )
                        );
                        if ($deleted_for_row !== false) {
                            $repair_deleted += (int) $deleted_for_row;
                        }
                        $repair_deleted_signatures[$repair_signature] = true;
                    }
                }

                if (!$allow_duplicates) {
                    $duplicate = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NULL AND email = %s AND account_type = %s AND game_name = %s AND stock_status = %s AND COALESCE(phone, '') = %s AND COALESCE(sale_date, '') = %s",
                            $data['email'],
                            $data['account_type'],
                            $data['game_name'],
                            $data['stock_status'],
                            $data['phone'],
                            $sale_date
                        )
                    );

                    if ($duplicate > 0) {
                        $skipped++;
                        if ($stock_status === 'Satılıb') {
                            $skipped_sold++;
                        } else {
                            $skipped_unsold++;
                        }
                        continue;
                    }
                }

                $result = $wpdb->insert($table, $data, $formats);
                if ($result === false) {
                    $skipped++;
                    $last_error = $wpdb->last_error;
                    if ($stock_status === 'Satılıb') {
                        $skipped_sold++;
                    } else {
                        $skipped_unsold++;
                    }
                    continue;
                }
                $new_account_id = (int) $wpdb->insert_id;
                $this->sync_account_game_links($new_account_id, array(), isset($data['game_name']) ? $data['game_name'] : '');
                $inserted++;
                if ($stock_status === 'Satılıb') {
                    $inserted_sold++;
                } else {
                    $inserted_unsold++;
                }
            }

            $db_counts = $this->get_database_status_counts();
            update_option(self::LEGACY_IMPORT_OPTION_KEY, array(
                'source' => $source_name !== '' ? sanitize_file_name($source_name) : 'legacy-data',
                'source_rows' => count($legacy_rows),
                'inserted' => $inserted,
                'inserted_sold' => $inserted_sold,
                'inserted_unsold' => $inserted_unsold,
                'skipped' => $skipped,
                'skipped_sold' => $skipped_sold,
                'skipped_unsold' => $skipped_unsold,
                'repair_deleted' => $repair_deleted,
                'allow_duplicates' => $allow_duplicates ? 1 : 0,
                'db_total' => $db_counts['total'],
                'db_sold' => $db_counts['sold'],
                'db_unsold' => $db_counts['unsold'],
                'last_error' => $last_error,
                'mode' => $repair_mode ? 'repair' : 'import',
                'imported_at' => $now,
            ), false);

            return array(
                'inserted' => $inserted,
                'inserted_sold' => $inserted_sold,
                'inserted_unsold' => $inserted_unsold,
                'skipped' => $skipped,
                'skipped_sold' => $skipped_sold,
                'skipped_unsold' => $skipped_unsold,
                'repair_deleted' => $repair_deleted,
                'already_imported' => false,
                'db_counts' => $db_counts,
                'last_error' => $last_error,
                'error' => '',
            );
        }

        private function get_database_status_counts()
        {
            global $wpdb;
            $table = $this->table_name();
            $this->create_or_update_table();
            $counts = array('total' => 0, 'sold' => 0, 'unsold' => 0, 'rental' => 0, 'other' => 0);
            $rows = $wpdb->get_results("SELECT stock_status, COUNT(*) AS cnt FROM {$table} GROUP BY stock_status", ARRAY_A);
            if (!is_array($rows)) {
                return $counts;
            }
            foreach ($rows as $row) {
                $cnt = isset($row['cnt']) ? (int) $row['cnt'] : 0;
                $status = $this->normalize_stock_status_value(isset($row['stock_status']) ? $row['stock_status'] : '');
                $counts['total'] += $cnt;
                if ($status === 'Satılıb') {
                    $counts['sold'] += $cnt;
                } elseif ($status === 'Satılmayıb') {
                    $counts['unsold'] += $cnt;
                } elseif ($status === 'İcarə') {
                    $counts['rental'] += $cnt;
                } else {
                    $counts['other'] += $cnt;
                }
            }
            return $counts;
        }

        public function handle_repair_legacy()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_repair_legacy', 'mara_account_sale_repair_legacy_nonce');

            $this->create_or_update_table();
            $result = $this->maybe_import_legacy_accounts('repair');
            if (!is_array($result)) {
                $result = array('inserted' => 0, 'skipped' => 0, 'error' => 'Düzəltmə nəticəsi oxunmadı.');
            }
            if (!empty($result['error'])) {
                $this->redirect_with_message('error', 'settings', (string) $result['error']);
            }

            $this->redirect_with_message('legacy_repaired', 'settings');
        }


        private function clear_account_database()
        {
            global $wpdb;
            $table = $this->table_name();
            $games_table = $this->games_table_name();
            $links_table = $this->account_games_table_name();
            $this->create_or_update_table();

            // Tam təmizləmə: hesablar, Bundle əlaqələri və ayrıca oyun kataloqu birlikdə silinir.
            $deleted_links = $wpdb->query("DELETE FROM {$links_table}");
            $deleted = $wpdb->query("DELETE FROM {$table}");
            $deleted_games = $wpdb->query("DELETE FROM {$games_table}");
            if ($deleted === false || $deleted_links === false || $deleted_games === false) {
                return false;
            }

            $wpdb->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            $wpdb->query("ALTER TABLE {$games_table} AUTO_INCREMENT = 1");

            // Köhnə option-kataloqu da sil ki, sonrakı migration köhnə oyun adlarını geri qaytarmasın.
            delete_option(self::LEGACY_IMPORT_OPTION_KEY);
            delete_option(self::CUSTOMER_CONTACTS_OPTION_KEY);
            delete_option(self::GAME_CATALOG_OPTION_KEY);
            update_option(self::GAME_RELATION_MIGRATION_OPTION_KEY, self::DB_VERSION, false);
            return true;
        }

        public function handle_clear_database()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_clear_database', 'mara_account_sale_clear_database_nonce');

            if (!$this->clear_account_database()) {
                $this->redirect_with_message('error', 'settings', 'Baza silinərkən xəta baş verdi.');
            }

            $this->redirect_with_message('database_cleared', 'settings');
        }

        public function handle_import_pdf()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_import_pdf', 'mara_account_sale_import_pdf_nonce');

            if (!isset($_FILES['legacy_pdf']) || !is_array($_FILES['legacy_pdf'])) {
                $this->redirect_with_message('error', 'settings', 'PDF faylı seçilməyib.');
            }

            $file = $_FILES['legacy_pdf'];
            if (!empty($file['error'])) {
                $this->redirect_with_message('error', 'settings', 'PDF yüklənmədi. Faylı yenidən seç.');
            }

            $filename = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '';
            $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($tmp_name === '' || !is_uploaded_file($tmp_name) || $extension !== 'pdf') {
                $this->redirect_with_message('error', 'settings', 'Yalnız PDF faylı yüklə.');
            }

            $clear_before_import = (isset($_POST['clear_before_import']) && $_POST['clear_before_import'] === '1');
            $parse_error = '';
            $pdf_rows = $this->load_client_pdf_rows_from_post($parse_error);
            if ($pdf_rows === null) {
                $pdf_rows = $this->load_uploaded_pdf_rows($tmp_name, $parse_error);
            }
            if (empty($pdf_rows)) {
                $this->redirect_with_message('error', 'settings', $parse_error !== '' ? $parse_error : 'PDF məlumatları oxunmadı. Baza dəyişdirilmədi.');
            }
            if ($clear_before_import) {
                if (!$this->clear_account_database()) {
                    $this->redirect_with_message('error', 'settings', 'İmportdan əvvəl baza silinmədi.');
                }
            }

            $this->create_or_update_table();
            $result = $this->maybe_import_legacy_accounts(true, $clear_before_import, $pdf_rows, $filename);
            if (!is_array($result)) {
                $result = array('inserted' => 0, 'skipped' => 0, 'error' => 'PDF import nəticəsi oxunmadı.');
            }
            if (!empty($result['error'])) {
                $this->redirect_with_message('error', 'settings', (string) $result['error']);
            }

            $inserted = isset($result['inserted']) ? (int) $result['inserted'] : 0;
            if ($inserted <= 0) {
                $this->redirect_with_message('legacy_import_no_new', 'settings');
            }

            $this->redirect_with_message('pdf_imported', 'settings');
        }


        public function handle_reset_import_pdf()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_reset_import_pdf', 'mara_account_sale_reset_import_pdf_nonce');

            if (!isset($_FILES['legacy_pdf_reset']) || !is_array($_FILES['legacy_pdf_reset'])) {
                $this->redirect_with_message('error', 'settings', 'PDF faylı seçilməyib.');
            }

            $file = $_FILES['legacy_pdf_reset'];
            if (!empty($file['error'])) {
                $this->redirect_with_message('error', 'settings', 'PDF yüklənmədi. Faylı yenidən seç.');
            }

            $filename = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '';
            $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($tmp_name === '' || !is_uploaded_file($tmp_name) || $extension !== 'pdf') {
                $this->redirect_with_message('error', 'settings', 'Yalnız PDF faylı yüklə.');
            }

            $parse_error = '';
            $pdf_rows = $this->load_client_pdf_rows_from_post($parse_error);
            if ($pdf_rows === null) {
                $pdf_rows = $this->load_uploaded_pdf_rows($tmp_name, $parse_error);
            }
            if (empty($pdf_rows)) {
                $this->redirect_with_message('error', 'settings', $parse_error !== '' ? $parse_error : 'PDF məlumatları oxunmadı. Baza dəyişdirilmədi.');
            }
            if (!$this->clear_account_database()) {
                $this->redirect_with_message('error', 'settings', 'Baza silinmədi.');
            }

            $this->create_or_update_table();
            $result = $this->maybe_import_legacy_accounts(true, true, $pdf_rows, $filename);
            if (!is_array($result)) {
                $this->redirect_with_message('error', 'settings', 'Tam PDF import nəticəsi oxunmadı.');
            }
            if (!empty($result['error'])) {
                $this->redirect_with_message('error', 'settings', (string) $result['error']);
            }

            $counts = isset($result['db_counts']) && is_array($result['db_counts']) ? $result['db_counts'] : $this->get_database_status_counts();
            if ((int) $counts['unsold'] <= 0) {
                $last_error = isset($result['last_error']) ? (string) $result['last_error'] : '';
                $this->redirect_with_message('error', 'settings', 'Satılmayıb hesabları importdan sonra 0 göründü. SQL xəta: ' . $last_error);
            }

            $this->redirect_with_message('pdf_reset_imported', 'settings');
        }

        private function maybe_create_auto_universal_account($data, $base_formats, $now)
        {
            if (!isset($data['account_type']) || $data['account_type'] !== 'Online') {
                return false;
            }

            global $wpdb;
            $table = $this->table_name();
            $existing = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE email = %s AND account_type = %s",
                    $data['email'],
                    'Universal'
                )
            );

            if ($existing > 0) {
                return false;
            }

            $universal = $data;
            $universal['account_type'] = 'Universal';
            $universal['customer_name'] = '';
            $universal['phone'] = '';
            $universal['sale_date'] = null;
            $universal['stock_status'] = 'Satılmayıb';
            $universal['rental_duration_value'] = null;
            $universal['rental_duration_unit'] = null;
            $universal['rental_started_at'] = null;
            $universal['rental_ends_at'] = null;
            $universal['created_at'] = $now;
            $universal['updated_at'] = $now;

            $inserted = $wpdb->insert(
                $table,
                $universal,
                array_merge($base_formats, array('%s', '%s'))
            );
            if ($inserted !== false) {
                $universal_id = (int) $wpdb->insert_id;
                $this->sync_account_game_links($universal_id, array(), isset($universal['game_name']) ? $universal['game_name'] : '');
                return true;
            }
            return false;
        }

        /**
         * Eyni e-mailə bağlı Online / Universal / Offline hesablar bir PSN hesabının
         * fərqli satış növləridir. Bu hesablardan HƏR HANSINDA oyun siyahısı dəyişəndə
         * digər növlərin oyun siyahısını da eyni vəziyyətə gətirir.
         *
         * Bu qayda həm oyun əlavə etməyə, həm də Bundle-dan oyun çıxarmağa aiddir.
         * Qiymət, status, müştəri, telefon, satış tarixi, məxfi kod və digər sahələrə toxunmur.
         */
        private function sync_games_to_email_siblings($source_id, $source_email, $game_ids, $game_name, $now)
        {
            $source_email = sanitize_email((string) $source_email);
            $game_ids = $this->normalize_game_ids($game_ids);
            $normalized_game_name = $this->normalize_game_names($game_name);
            if ($source_email === '' || $normalized_game_name === '') {
                return array('count' => 0, 'types' => array());
            }

            global $wpdb;
            $table = $this->table_name();
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, account_type FROM {$table}
                     WHERE email = %s
                       AND id <> %d
                       AND deleted_at IS NULL
                       AND account_type IN ('Online', 'Universal', 'Offline')",
                    $source_email,
                    absint($source_id)
                ),
                ARRAY_A
            );

            if (!is_array($rows) || empty($rows)) {
                return array('count' => 0, 'types' => array());
            }

            $count = 0;
            $types = array();
            foreach ($rows as $row) {
                $target_id = isset($row['id']) ? absint($row['id']) : 0;
                if ($target_id <= 0) continue;

                $updated = $wpdb->update(
                    $table,
                    array(
                        'game_name' => $normalized_game_name,
                        'updated_at' => $now,
                    ),
                    array('id' => $target_id),
                    array('%s', '%s'),
                    array('%d')
                );
                if ($updated === false) continue;

                $this->sync_account_game_links($target_id, $game_ids, $normalized_game_name);
                $count++;
                $type = isset($row['account_type']) ? (string) $row['account_type'] : '';
                if ($type !== '' && !in_array($type, $types, true)) $types[] = $type;
            }

            return array('count' => $count, 'types' => $types);
        }

        public function handle_save()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_save', 'mara_account_sale_nonce');

            global $wpdb;
            $table = $this->table_name();
            $this->create_or_update_table();
            $this->repair_table_schema();
            $errors = array();
            $data = $this->sanitize_record_from_post($errors);
            $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
            $before_record = $id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, email, account_type, game_name FROM {$table} WHERE id = %d", $id), ARRAY_A) : null;
            $return_tab = isset($_POST['mara_return_tab']) ? sanitize_key(wp_unslash($_POST['mara_return_tab'])) : 'accounts';
            if (empty($errors) && $id > 0) {
                $this->preserve_existing_rental_window($id, $data);
            }

            if (!empty($errors)) {
                $this->redirect_with_message('error', $return_tab, implode(' ', $errors));
            }

            $now = current_time('mysql');
            $base_formats = array('%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s');

            if ($id > 0) {
                $data['updated_at'] = $now;
                $updated = $wpdb->update(
                    $table,
                    $data,
                    array('id' => $id),
                    array_merge($base_formats, array('%s')),
                    array('%d')
                );
                if ($updated === false) {
                    $this->redirect_with_message('error', $return_tab, 'Düzəliş zamanı xəta baş verdi.');
                }
                $admin_game_ids = $this->game_ids_from_names(isset($data['game_name']) ? $data['game_name'] : '');
                $this->sync_account_game_links($id, $admin_game_ids, isset($data['game_name']) ? $data['game_name'] : '');
                if (is_array($before_record)) {
                    $before_games = $this->normalize_game_names(isset($before_record['game_name']) ? $before_record['game_name'] : '');
                    $after_games = $this->normalize_game_names(isset($data['game_name']) ? $data['game_name'] : '');
                    if ($before_games !== $after_games) {
                        $this->sync_games_to_email_siblings(
                            $id,
                            isset($data['email']) ? $data['email'] : (isset($before_record['email']) ? $before_record['email'] : ''),
                            $admin_game_ids,
                            $after_games,
                            $now
                        );
                    }
                }
                $this->redirect_with_message('updated', 'accounts');
            }

            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $inserted = $wpdb->insert($table, $data, array_merge($base_formats, array('%s', '%s')));
            if ($inserted === false) {
                $this->redirect_with_message('error', $return_tab, 'Yadda saxlanma zamanı xəta baş verdi.');
            }

            $id = (int) $wpdb->insert_id;
            $this->sync_account_game_links($id, array(), isset($data['game_name']) ? $data['game_name'] : '');
            $auto_universal_created = $this->maybe_create_auto_universal_account($data, $base_formats, $now);
            $this->redirect_with_message($auto_universal_created ? 'saved_auto_universal' : 'saved', 'accounts');
        }

        public function handle_delete()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
            if ($id <= 0) {
                $this->redirect_with_message('error', 'accounts', 'Silinəcək hesab tapılmadı.');
            }
            check_admin_referer('mara_account_sale_delete_' . $id);
            global $wpdb;
            $now = current_time('mysql');
            $deleted = $wpdb->update(
                $this->table_name(),
                array('deleted_at' => $now, 'updated_at' => $now),
                array('id' => $id),
                array('%s', '%s'),
                array('%d')
            );
            if ($deleted === false) {
                $this->redirect_with_message('error', 'accounts', 'Zibil qutusuna köçürmə zamanı xəta baş verdi.');
            }
            $this->redirect_with_message('deleted', 'accounts');
        }

        public function handle_import_legacy()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_import_legacy', 'mara_account_sale_import_legacy_nonce');

            $this->create_or_update_table();
            $result = $this->maybe_import_legacy_accounts(true);
            if (!is_array($result)) {
                $result = array('inserted' => 0, 'skipped' => 0, 'error' => 'Import nəticəsi oxunmadı.');
            }
            if (!empty($result['error'])) {
                $this->redirect_with_message('error', 'settings', (string) $result['error']);
            }

            $inserted = isset($result['inserted']) ? (int) $result['inserted'] : 0;
            $this->redirect_with_message($inserted > 0 ? 'legacy_imported' : 'legacy_import_no_new', 'settings');
        }

        private function get_mobile_api_key()
        {
            $key = trim((string) get_option(self::MOBILE_API_KEY_OPTION_KEY, ''));
            if ($key === '') {
                $key = wp_generate_password(48, false, false);
                update_option(self::MOBILE_API_KEY_OPTION_KEY, $key, false);
            }
            return $key;
        }

        private function regenerate_mobile_api_key()
        {
            $key = wp_generate_password(48, false, false);
            update_option(self::MOBILE_API_KEY_OPTION_KEY, $key, false);
            return $key;
        }

        public function handle_regenerate_mobile_key()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_regenerate_mobile_key', 'mara_account_sale_regenerate_mobile_key_nonce');
            $this->regenerate_mobile_api_key();
            $this->redirect_with_message('mobile_key_regenerated', 'settings');
        }

        public function register_mobile_rest_routes()
        {
            $namespace = 'marakana-account-sales/v1';

            register_rest_route($namespace, '/ping', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_mobile_ping'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/overview', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_mobile_overview'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/customer', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_mobile_customer'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/customer/save', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_save_customer'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/save', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_save'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/create-accounts', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_create_accounts'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/game/save', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_save_game'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/delete', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_delete'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/trash', array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'rest_mobile_trash'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/trash/restore', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_trash_restore'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/trash/delete', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_trash_delete'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
            register_rest_route($namespace, '/settings', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_save_settings'),
                'permission_callback' => array($this, 'rest_mobile_permission'),
            ));
        }

        public function rest_mobile_permission($request)
        {
            if (current_user_can($this->capability())) {
                return true;
            }
            $expected = $this->get_mobile_api_key();
            $provided = trim((string) $request->get_header('x-marakana-account-key'));
            if ($expected !== '' && $provided !== '' && hash_equals($expected, $provided)) {
                return true;
            }
            return new WP_Error(
                'mara_account_sale_mobile_forbidden',
                'Hesab Satışı mobil API açarı düzgün deyil.',
                array('status' => 401)
            );
        }

        private function mobile_record_payload($record)
        {
            if (is_array($record)) $this->hydrate_record_games($record);
            $runtime = $this->rental_runtime_payload(is_array($record) ? $record : array());
            return array_merge(array(
                'id' => isset($record['id']) ? (int) $record['id'] : 0,
                'game_name' => isset($record['game_name']) ? (string) $record['game_name'] : '',
                'primary_game_id' => isset($record['primary_game_id']) ? (int) $record['primary_game_id'] : 0,
                'game_ids' => isset($record['game_ids']) && is_array($record['game_ids']) ? array_values(array_map('intval', $record['game_ids'])) : array(),
                'games' => isset($record['games']) && is_array($record['games']) ? $record['games'] : array(),
                'account_type' => isset($record['account_type']) ? (string) $record['account_type'] : '',
                'email' => isset($record['email']) ? (string) $record['email'] : '',
                'account_password' => isset($record['account_password']) ? (string) $record['account_password'] : '',
                'secret_code' => isset($record['secret_code']) ? (string) $record['secret_code'] : '',
                'legacy_row_no' => isset($record['legacy_row_no']) ? (int) $record['legacy_row_no'] : 0,
                'price' => isset($record['price']) ? (float) $record['price'] : 0,
                'price_formatted' => $this->format_price(isset($record['price']) ? $record['price'] : 0),
                'console' => isset($record['console']) ? (string) $record['console'] : '',
                'customer_name' => isset($record['customer_name']) ? (string) $record['customer_name'] : '',
                'phone' => isset($record['phone']) ? (string) $record['phone'] : '',
                'sale_date' => isset($record['sale_date']) && $record['sale_date'] !== null ? (string) $record['sale_date'] : '',
                'payment_type' => isset($record['payment_type']) ? (string) $record['payment_type'] : '',
                'stock_status' => isset($record['stock_status']) ? $this->normalize_stock_status_value($record['stock_status']) : 'Satılmayıb',
                'rental_duration_value' => isset($record['rental_duration_value']) ? (int) $record['rental_duration_value'] : 0,
                'rental_duration_unit' => isset($record['rental_duration_unit']) ? (string) $record['rental_duration_unit'] : '',
                'rental_started_at' => isset($record['rental_started_at']) ? (string) $record['rental_started_at'] : '',
                'rental_ends_at' => isset($record['rental_ends_at']) ? (string) $record['rental_ends_at'] : '',
                'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : '',
                'updated_at' => isset($record['updated_at']) ? (string) $record['updated_at'] : '',
                'deleted_at' => isset($record['deleted_at']) && $record['deleted_at'] !== null ? (string) $record['deleted_at'] : '',
                'trash_days_remaining' => (!empty($record['deleted_at'])) ? max(0, 30 - (int) floor((current_time('timestamp') - strtotime($record['deleted_at'])) / DAY_IN_SECONDS)) : 0,
            ), $runtime);
        }

        private function mobile_customer_payload($customer)
        {
            return array(
                'customer_name' => isset($customer['customer_name']) ? (string) $customer['customer_name'] : '',
                'phone' => isset($customer['phone']) ? (string) $customer['phone'] : '',
                'game_count' => isset($customer['game_count']) ? (int) $customer['game_count'] : 0,
                'total_amount' => isset($customer['total_amount']) ? (float) $customer['total_amount'] : 0,
                'total_amount_formatted' => $this->format_price(isset($customer['total_amount']) ? $customer['total_amount'] : 0),
                'cash_amount' => isset($customer['cash_amount']) ? (float) $customer['cash_amount'] : 0,
                'cash_amount_formatted' => $this->format_price(isset($customer['cash_amount']) ? $customer['cash_amount'] : 0),
                'credit_amount' => isset($customer['credit_amount']) ? (float) $customer['credit_amount'] : 0,
                'credit_amount_formatted' => $this->format_price(isset($customer['credit_amount']) ? $customer['credit_amount'] : 0),
                'cash_count' => isset($customer['cash_count']) ? (int) $customer['cash_count'] : 0,
                'credit_count' => isset($customer['credit_count']) ? (int) $customer['credit_count'] : 0,
                'last_sale_date' => isset($customer['last_sale_date']) ? (string) $customer['last_sale_date'] : '',
                'customer_created_at' => isset($customer['customer_created_at']) ? (string) $customer['customer_created_at'] : '',
            );
        }

        public function rest_mobile_ping($request)
        {
            return rest_ensure_response(array(
                'ok' => true,
                'plugin' => 'Marakana Playstation Hesab Satışı',
                'version' => self::VERSION,
            ));
        }

        public function rest_mobile_overview($request)
        {
            $all_records = $this->get_records();
            $records = $all_records;
            $section = sanitize_key((string) $request->get_param('section'));
            if ($section === 'sold') {
                $records = array_values(array_filter($records, array($this, 'is_sold_record')));
            } elseif ($section === 'unsold') {
                $records = array_values(array_filter($records, array($this, 'is_unsold_record')));
            } elseif ($section === 'rental') {
                $records = $this->sort_rental_records(array_values(array_filter($records, array($this, 'is_rental_record'))));
            } elseif ($section === 'customers' || $section === 'settings') {
                $records = array();
            }

            $search = sanitize_text_field((string) $request->get_param('search'));
            if ($search !== '') {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($search, 'UTF-8') : strtolower($search);
                $records = array_values(array_filter($records, function ($record) use ($needle) {
                    $haystack = implode(' ', array(
                        isset($record['game_name']) ? $record['game_name'] : '',
                        isset($record['email']) ? $record['email'] : '',
                        isset($record['account_type']) ? $record['account_type'] : '',
                        isset($record['console']) ? $record['console'] : '',
                        isset($record['customer_name']) ? $record['customer_name'] : '',
                        isset($record['phone']) ? $record['phone'] : '',
                        isset($record['sale_date']) ? $record['sale_date'] : '',
                        isset($record['payment_type']) ? $record['payment_type'] : '',
                        isset($record['stock_status']) ? $record['stock_status'] : '',
                    ));
                    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
                    return strpos($haystack, $needle) !== false;
                }));
            }

            $customers = array_map(array($this, 'mobile_customer_payload'), $this->get_customer_rows());
            $games = $this->get_game_name_rows();
            $settings = $this->get_settings();

            return rest_ensure_response(array(
                'ok' => true,
                'records' => array_map(array($this, 'mobile_record_payload'), $records),
                'stats' => $this->calculate_stats($all_records),
                'customers' => $customers,
                'game_names' => $games,
                'game_storage' => 'id_relation_v1',
                'settings' => $settings,
                'currency' => isset($settings['currency']) ? $settings['currency'] : 'AZN',
            ));
        }

        public function rest_mobile_customer($request)
        {
            $phone = sanitize_text_field((string) $request->get_param('phone'));
            if ($phone === '') {
                return new WP_Error('mara_account_sale_mobile_phone_required', 'Telefon boş ola bilməz.', array('status' => 400));
            }
            $records = $this->get_records_by_phone($phone);
            return rest_ensure_response(array(
                'ok' => true,
                'phone' => $phone,
                'records' => array_map(array($this, 'mobile_record_payload'), is_array($records) ? $records : array()),
            ));
        }

        public function rest_mobile_save_customer($request)
        {
            $input = $request->get_json_params();
            if (!is_array($input)) {
                $input = array();
            }
            $error = '';
            $saved = $this->save_customer_contact(
                isset($input['customer_name']) ? $input['customer_name'] : '',
                isset($input['phone']) ? $input['phone'] : '',
                $error
            );
            if (!is_array($saved)) {
                return new WP_Error(
                    'mara_account_sale_mobile_customer_validation',
                    $error !== '' ? $error : 'Müştəri yadda saxlanmadı.',
                    array('status' => 400)
                );
            }

            $customer = array(
                'customer_name' => $saved['customer_name'],
                'phone' => $saved['phone'],
                'game_count' => 0,
                'total_amount' => 0,
                'last_sale_date' => '',
                'cash_amount' => 0,
                'credit_amount' => 0,
                'cash_count' => 0,
                'credit_count' => 0,
            );
            foreach ($this->get_customer_rows() as $row) {
                if (isset($row['phone']) && (string) $row['phone'] === (string) $saved['phone']) {
                    $customer = $row;
                    break;
                }
            }

            return rest_ensure_response(array(
                'ok' => true,
                'customer' => $this->mobile_customer_payload($customer),
            ));
        }

        private function sanitize_mobile_record_data($input, &$errors)
        {
            $input = is_array($input) ? $input : array();
            $allowed_types = array('Online', 'Universal', 'Offline');
            $allowed_consoles = array('PS4', 'PS5', 'PS4/PS5');
            $allowed_payments = array('Nağd', 'Nisyə');
            $allowed_stock = array('Satılıb', 'Satılmayıb', 'İcarə');

            $game_name = $this->normalize_game_names(isset($input['game_name']) ? sanitize_text_field((string) $input['game_name']) : '');
            $account_type = isset($input['account_type']) ? sanitize_text_field((string) $input['account_type']) : '';
            $email = isset($input['email']) ? sanitize_email((string) $input['email']) : '';
            $secret_code = isset($input['secret_code']) ? sanitize_text_field((string) $input['secret_code']) : '';
            $price_raw = isset($input['price']) ? sanitize_text_field((string) $input['price']) : '';
            $console = isset($input['console']) ? sanitize_text_field((string) $input['console']) : '';
            $customer_name_raw = isset($input['customer_name']) ? sanitize_text_field((string) $input['customer_name']) : '';
            $phone_raw = isset($input['phone']) ? sanitize_text_field((string) $input['phone']) : '';
            $sale_date = isset($input['sale_date']) ? sanitize_text_field((string) $input['sale_date']) : '';
            $payment_type = isset($input['payment_type']) ? sanitize_text_field((string) $input['payment_type']) : '';
            $stock_status = isset($input['stock_status']) ? sanitize_text_field((string) $input['stock_status']) : '';
            $rental_duration_value = isset($input['rental_duration_value']) ? absint($input['rental_duration_value']) : 0;
            $rental_duration_unit = isset($input['rental_duration_unit']) ? sanitize_text_field((string) $input['rental_duration_unit']) : 'day';

            if ($game_name === '') $errors[] = 'Oyunun adı boş ola bilməz.';
            if (!in_array($account_type, $allowed_types, true)) $errors[] = 'Növ düzgün seçilməyib.';
            if ($email === '' || !is_email($email)) $errors[] = 'E-mail formatı düzgün deyil.';

            $price_clean = str_replace(',', '.', trim($price_raw));
            if ($price_clean === '' || !is_numeric($price_clean) || (float) $price_clean < 0) {
                $errors[] = 'Qiymət düzgün rəqəm olmalıdır və mənfi ola bilməz.';
                $price = 0;
            } else {
                $price = round((float) $price_clean, 2);
            }

            if (!in_array($console, $allowed_consoles, true)) $errors[] = 'Konsol düzgün seçilməyib.';
            if (!in_array($payment_type, $allowed_payments, true)) $errors[] = 'Ödəniş növü düzgün seçilməyib.';
            if (!in_array($stock_status, $allowed_stock, true)) $errors[] = 'Stok statusu düzgün seçilməyib.';

            if ($stock_status === 'Satılıb') {
                if (!$this->validate_date($sale_date)) $errors[] = 'Satılıb seçilərsə satış tarixi düzgün olmalıdır.';
            } elseif ($sale_date !== '' && !$this->validate_date($sale_date)) {
                $errors[] = 'Satış tarixi düzgün deyil.';
            }

            $customer_name = $this->normalize_name($customer_name_raw);
            if ($customer_name !== '' && !preg_match('/^[\p{L}\s]+$/u', $customer_name)) $errors[] = 'Ad soyad yalnız hərflərdən və boşluqdan ibarət olmalıdır.';

            $phone_error = '';
            $phone = $this->normalize_phone($phone_raw, $phone_error);
            if ($phone_raw !== '' && $phone === '' && $phone_error !== '') $errors[] = $phone_error;

            if ($stock_status === 'Satılıb' || $stock_status === 'İcarə') {
                if ($customer_name === '') $errors[] = ($stock_status === 'İcarə' ? 'İcarə' : 'Satılıb') . ' seçilərsə ad soyad məcburidir.';
                if ($phone === '') $errors[] = ($stock_status === 'İcarə' ? 'İcarə' : 'Satılıb') . ' seçilərsə telefon məcburidir.';
            }

            $rental = $this->build_rental_fields($stock_status, $rental_duration_value, $rental_duration_unit, $errors);
            return array_merge(array(
                'game_name' => $game_name,
                'account_type' => $account_type,
                'email' => $email,
                'secret_code' => $secret_code,
                'price' => $price,
                'console' => $console,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'sale_date' => $stock_status === 'Satılıb' && $sale_date !== '' ? $sale_date : null,
                'payment_type' => $payment_type,
                'stock_status' => $stock_status,
            ), $rental);
        }

        public function rest_mobile_save($request)
        {
            global $wpdb;
            $this->create_or_update_table();
            $this->repair_table_schema();
            $input = $request->get_json_params();
            if (!is_array($input)) $input = array();
            $errors = array();
            $data = $this->sanitize_mobile_record_data($input, $errors);
            if (!empty($errors)) {
                return new WP_Error('mara_account_sale_mobile_validation', implode(' ', $errors), array('status' => 400));
            }

            if (!empty($data['customer_name']) && !empty($data['phone'])) {
                $contact_error = '';
                $this->save_customer_contact($data['customer_name'], $data['phone'], $contact_error);
            }

            $table = $this->table_name();
            $id = isset($input['id']) ? absint($input['id']) : 0;
            $before_record = $id > 0 ? $wpdb->get_row($wpdb->prepare("SELECT id, email, account_type, game_name FROM {$table} WHERE id = %d", $id), ARRAY_A) : null;
            if ($id > 0) {
                // Köhnə mobil versiya və Sat / İcarə axını secret_code göndərməsə, mövcud dəyəri silmə.
                if (!array_key_exists('secret_code', $input)) {
                    $existing_secret = $wpdb->get_var($wpdb->prepare("SELECT secret_code FROM {$table} WHERE id = %d", $id));
                    $data['secret_code'] = $existing_secret === null ? '' : (string) $existing_secret;
                }
                $this->preserve_existing_rental_window($id, $data);
            }
            $now = current_time('mysql');
            $base_formats = array('%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s');
            $auto_universal_created = false;

            if ($id > 0) {
                $data['updated_at'] = $now;
                $updated = $wpdb->update(
                    $table,
                    $data,
                    array('id' => $id),
                    array_merge($base_formats, array('%s')),
                    array('%d')
                );
                if ($updated === false) {
                    return new WP_Error('mara_account_sale_mobile_update_failed', 'Düzəliş zamanı xəta baş verdi.', array('status' => 500));
                }
            } else {
                $data['created_at'] = $now;
                $data['updated_at'] = $now;
                $inserted = $wpdb->insert($table, $data, array_merge($base_formats, array('%s', '%s')));
                if ($inserted === false) {
                    return new WP_Error('mara_account_sale_mobile_insert_failed', 'Yadda saxlanma zamanı xəta baş verdi.', array('status' => 500));
                }
                $id = (int) $wpdb->insert_id;
            }

            $game_ids = $this->game_ids_from_input($input, isset($data['game_name']) ? $data['game_name'] : '');
            $this->sync_account_game_links($id, $game_ids, isset($data['game_name']) ? $data['game_name'] : '');
            $bundle_sync = array('count' => 0, 'types' => array());
            if ($id > 0 && is_array($before_record)) {
                $before_games = $this->normalize_game_names(isset($before_record['game_name']) ? $before_record['game_name'] : '');
                $after_games = $this->normalize_game_names(isset($data['game_name']) ? $data['game_name'] : '');
                if ($before_games !== $after_games) {
                    $bundle_sync = $this->sync_games_to_email_siblings(
                        $id,
                        isset($data['email']) ? $data['email'] : (isset($before_record['email']) ? $before_record['email'] : ''),
                        $game_ids,
                        $after_games,
                        $now
                    );
                }
            }
            if (empty($input['id'])) {
                $auto_universal_created = $this->maybe_create_auto_universal_account($data, $base_formats, $now);
            }
            $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
            return rest_ensure_response(array(
                'ok' => true,
                'id' => $id,
                'record' => is_array($record) ? $this->mobile_record_payload($record) : null,
                'auto_universal_created' => $auto_universal_created ? 1 : 0,
                'bundle_synced_records' => isset($bundle_sync['count']) ? (int) $bundle_sync['count'] : 0,
                'bundle_synced_types' => isset($bundle_sync['types']) && is_array($bundle_sync['types']) ? array_values($bundle_sync['types']) : array(),
            ));
        }

        public function rest_mobile_save_game($request)
        {
            $input = $request->get_json_params();
            if (!is_array($input)) {
                $input = array();
            }
            $old_name = isset($input['old_name']) ? trim(sanitize_text_field((string) $input['old_name'])) : '';
            $new_name = isset($input['new_name']) ? trim(sanitize_text_field((string) $input['new_name'])) : '';
            if ($new_name === '' && isset($input['game_name'])) {
                $new_name = trim(sanitize_text_field((string) $input['game_name']));
            }
            if ($new_name === '') {
                return new WP_Error('mara_account_sale_mobile_game_required', 'Oyun adı boş ola bilməz.', array('status' => 400));
            }

            $updated_count = 0;
            if ($old_name !== '') {
                $error = '';
                $updated = $this->rename_game_name_everywhere($old_name, $new_name, $error);
                if ($updated === false) {
                    return new WP_Error(
                        'mara_account_sale_mobile_game_rename_failed',
                        $error !== '' ? $error : 'Oyun adı yenilənmədi.',
                        array('status' => 500)
                    );
                }
                $updated_count = (int) $updated;
            } else {
                $this->remember_game_name($new_name);
            }

            $saved_game = $this->get_game_by_name($new_name);
            return rest_ensure_response(array(
                'ok' => true,
                'game_id' => $saved_game ? (int) $saved_game['id'] : 0,
                'game_name' => $new_name,
                'old_name' => $old_name,
                'updated_records' => $updated_count,
                'game_names' => $this->get_game_name_rows(),
            ));
        }

        public function rest_mobile_create_accounts($request)
        {
            global $wpdb;
            $this->create_or_update_table();
            $this->repair_table_schema();
            $input = $request->get_json_params();
            if (!is_array($input)) $input = array();

            $types = isset($input['account_types']) && is_array($input['account_types']) ? $input['account_types'] : array();
            $allowed_types = array('Online', 'Universal', 'Offline');
            $selected_types = array();
            foreach ($types as $type) {
                $type = sanitize_text_field((string) $type);
                if (in_array($type, $allowed_types, true) && !in_array($type, $selected_types, true)) {
                    $selected_types[] = $type;
                }
            }
            if (empty($selected_types)) {
                return new WP_Error('mara_account_sale_mobile_types_required', 'Ən azı bir hesab növü seçilməlidir.', array('status' => 400));
            }

            $table = $this->table_name();
            $now = current_time('mysql');
            $formats = array('%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s');
            $created_ids = array();
            $created_types = array();
            $wpdb->query('START TRANSACTION');

            foreach ($selected_types as $type) {
                $row_input = array(
                    'game_name' => isset($input['game_name']) ? $input['game_name'] : '',
                    'account_type' => $type,
                    'email' => isset($input['email']) ? $input['email'] : '',
                    'secret_code' => isset($input['secret_code']) ? $input['secret_code'] : '',
                    'price' => isset($input['price']) ? $input['price'] : '',
                    'console' => isset($input['console']) ? $input['console'] : '',
                    'customer_name' => '',
                    'phone' => '',
                    'sale_date' => '',
                    'payment_type' => 'Nağd',
                    'stock_status' => 'Satılmayıb',
                );
                $errors = array();
                $data = $this->sanitize_mobile_record_data($row_input, $errors);
                if (!empty($errors)) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('mara_account_sale_mobile_create_validation', implode(' ', $errors), array('status' => 400));
                }
                $data['customer_name'] = '';
                $data['phone'] = '';
                $data['sale_date'] = null;
                $data['payment_type'] = 'Nağd';
                $data['stock_status'] = 'Satılmayıb';
                $data['created_at'] = $now;
                $data['updated_at'] = $now;

                $inserted = $wpdb->insert($table, $data, $formats);
                if ($inserted === false) {
                    $wpdb->query('ROLLBACK');
                    return new WP_Error('mara_account_sale_mobile_create_failed', 'Hesab yaradılarkən xəta baş verdi.', array('status' => 500));
                }
                $new_account_id = (int) $wpdb->insert_id;
                $game_ids = $this->game_ids_from_input($input, isset($data['game_name']) ? $data['game_name'] : '');
                $this->sync_account_game_links($new_account_id, $game_ids, isset($data['game_name']) ? $data['game_name'] : '');
                $created_ids[] = $new_account_id;
                $created_types[] = $type;
            }

            $wpdb->query('COMMIT');
            return rest_ensure_response(array(
                'ok' => true,
                'created_count' => count($created_ids),
                'created_ids' => $created_ids,
                'created_types' => $created_types,
                'stock_status' => 'Satılmayıb',
            ));
        }

        public function rest_mobile_delete($request)
        {
            global $wpdb;
            $input = $request->get_json_params();
            $id = is_array($input) && isset($input['id']) ? absint($input['id']) : 0;
            if ($id <= 0) {
                return new WP_Error('mara_account_sale_mobile_delete_id', 'Silinəcək hesab tapılmadı.', array('status' => 400));
            }
            $now = current_time('mysql');
            $deleted = $wpdb->update(
                $this->table_name(),
                array('deleted_at' => $now, 'updated_at' => $now),
                array('id' => $id, 'deleted_at' => null),
                array('%s', '%s'),
                array('%d', '%s')
            );
            if ($deleted === false) {
                return new WP_Error('mara_account_sale_mobile_delete_failed', 'Zibil qutusuna köçürmə zamanı xəta baş verdi.', array('status' => 500));
            }
            return rest_ensure_response(array('ok' => true, 'id' => $id, 'deleted_at' => $now, 'trash_days' => 30));
        }

        public function rest_mobile_trash($request)
        {
            $records = $this->get_trash_records();
            return rest_ensure_response(array(
                'ok' => true,
                'retention_days' => 30,
                'records' => array_map(array($this, 'mobile_record_payload'), $records),
            ));
        }

        public function rest_mobile_trash_restore($request)
        {
            global $wpdb;
            $input = $request->get_json_params();
            $id = is_array($input) && isset($input['id']) ? absint($input['id']) : 0;
            if ($id <= 0) {
                return new WP_Error('mara_account_sale_mobile_restore_id', 'Geri qaytarılacaq hesab tapılmadı.', array('status' => 400));
            }
            $now = current_time('mysql');
            $restored = $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name()} SET deleted_at = NULL, updated_at = %s WHERE id = %d AND deleted_at IS NOT NULL",
                $now,
                $id
            ));
            if ($restored === false) {
                return new WP_Error('mara_account_sale_mobile_restore_failed', 'Hesab geri qaytarılmadı.', array('status' => 500));
            }
            return rest_ensure_response(array('ok' => true, 'id' => $id));
        }

        public function rest_mobile_trash_delete($request)
        {
            global $wpdb;
            $input = $request->get_json_params();
            $id = is_array($input) && isset($input['id']) ? absint($input['id']) : 0;
            if ($id <= 0) {
                return new WP_Error('mara_account_sale_mobile_trash_delete_id', 'Silinəcək hesab tapılmadı.', array('status' => 400));
            }
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name()} WHERE id = %d AND deleted_at IS NOT NULL",
                $id
            ));
            if ($exists <= 0) {
                return new WP_Error('mara_account_sale_mobile_trash_delete_missing', 'Hesab zibil qutusunda tapılmadı.', array('status' => 404));
            }
            $this->delete_account_game_links($id);
            $deleted = $wpdb->delete($this->table_name(), array('id' => $id), array('%d'));
            if ($deleted === false) {
                return new WP_Error('mara_account_sale_mobile_trash_delete_failed', 'Hesab birdəfəlik silinmədi.', array('status' => 500));
            }
            return rest_ensure_response(array('ok' => true, 'id' => $id));
        }

        public function rest_mobile_save_settings($request)
        {
            $input = $request->get_json_params();
            if (!is_array($input)) $input = array();
            $currency = isset($input['currency']) ? sanitize_text_field((string) $input['currency']) : 'AZN';
            $default_stock_status = isset($input['default_stock_status']) ? sanitize_text_field((string) $input['default_stock_status']) : 'Satılıb';
            $default_payment_type = isset($input['default_payment_type']) ? sanitize_text_field((string) $input['default_payment_type']) : 'Nağd';
            $phone_format_enabled = !empty($input['phone_format_enabled']) ? '1' : '0';

            if (!in_array($default_stock_status, array('Satılıb', 'Satılmayıb'), true)) $default_stock_status = 'Satılıb';
            if (!in_array($default_payment_type, array('Nağd', 'Nisyə'), true)) $default_payment_type = 'Nağd';
            if ($currency === '') $currency = 'AZN';

            $settings = array(
                'currency' => $currency,
                'default_stock_status' => $default_stock_status,
                'default_payment_type' => $default_payment_type,
                'phone_format_enabled' => $phone_format_enabled,
            );
            update_option(self::OPTION_KEY, $settings);
            return rest_ensure_response(array('ok' => true, 'settings' => $settings));
        }

        public function handle_save_settings()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_save_settings', 'mara_account_sale_settings_nonce');

            $currency = isset($_POST['currency']) ? sanitize_text_field(wp_unslash($_POST['currency'])) : 'AZN';
            $default_stock_status = isset($_POST['default_stock_status']) ? sanitize_text_field(wp_unslash($_POST['default_stock_status'])) : 'Satılıb';
            $default_payment_type = isset($_POST['default_payment_type']) ? sanitize_text_field(wp_unslash($_POST['default_payment_type'])) : 'Nağd';
            $phone_format_enabled = isset($_POST['phone_format_enabled']) ? '1' : '0';

            if (!in_array($default_stock_status, array('Satılıb', 'Satılmayıb'), true)) {
                $default_stock_status = 'Satılıb';
            }
            if (!in_array($default_payment_type, array('Nağd', 'Nisyə'), true)) {
                $default_payment_type = 'Nağd';
            }
            $currency = $currency !== '' ? $currency : 'AZN';

            update_option(self::OPTION_KEY, array(
                'currency' => $currency,
                'default_stock_status' => $default_stock_status,
                'default_payment_type' => $default_payment_type,
                'phone_format_enabled' => $phone_format_enabled,
            ));

            $this->redirect_with_message('settings', 'settings');
        }

        public function handle_export_csv()
        {
            if (!current_user_can($this->capability())) {
                wp_die(esc_html__('Bu əməliyyat üçün icazəniz yoxdur.', 'marakana-playstation-hesab-satisi'));
            }
            check_admin_referer('mara_account_sale_export_csv');

            $records = $this->get_records();
            $filename = 'marakana-hesab-satisi-' . current_time('Y-m-d-His') . '.csv';

            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, array('ID', 'Oyunun adı', 'Növ', 'E-mail', 'Qiymət', 'Konsol', 'Ad soyad', 'Telefon', 'Satış tarixi', 'Ödəniş növü', 'Stok', 'Yaradıldı', 'Yeniləndi'));
            foreach ($records as $record) {
                fputcsv($out, array(
                    $record['id'],
                    $record['game_name'],
                    $record['account_type'],
                    $record['email'],
                    $record['price'],
                    $record['console'],
                    $record['customer_name'],
                    $record['phone'],
                    $record['sale_date'],
                    $record['payment_type'],
                    $record['stock_status'],
                    $record['created_at'],
                    $record['updated_at'],
                ));
            }
            fclose($out);
            exit;
        }

        private function redirect_with_message($message, $tab = 'accounts', $error = '')
        {
            $referer = wp_get_referer();
            if (!$referer) {
                $referer = admin_url('admin.php?page=marakana-hesab-satisi');
            }
            $referer = remove_query_arg(array('mara_account_sale_msg', 'mara_account_sale_error', 'mara_tab'), $referer);
            $args = array(
                'mara_account_sale_msg' => $message,
                'mara_tab' => $tab,
            );
            if ($error !== '') {
                $args['mara_account_sale_error'] = rawurlencode($error);
            }
            wp_safe_redirect(add_query_arg($args, $referer));
            exit;
        }

        private function message_html()
        {
            if (!isset($_GET['mara_account_sale_msg'])) {
                return '';
            }
            $msg = sanitize_key(wp_unslash($_GET['mara_account_sale_msg']));
            $texts = array(
                'saved' => 'Hesab satışı uğurla əlavə edildi.',
                'saved_auto_universal' => 'Online hesab əlavə edildi və həmin e-mail üçün Universal versiya tarixsiz, müştərisiz və Satılmayıb kimi yaradıldı.',
                'updated' => 'Hesab məlumatları yeniləndi.',
                'deleted' => 'Hesab silindi.',
                'settings' => 'Ayarlar yadda saxlandı.',
                'legacy_imported' => 'Köhnə baza import edildi. Satılıb və Satılmayıb bölünməsi qorundu.',
                'legacy_repaired' => 'Köhnə baza yenidən düz import edildi: Satılıb/Satılmayıb ayrımı yeniləndi.',
                'legacy_import_no_new' => 'Köhnə bazada əlavə ediləcək yeni hesab tapılmadı. Mövcud hesablar təkrar yazılmadı.',
                'database_cleared' => 'Baza və oyun kataloqu tam silindi. İndi sıfırdan davam edə bilərsən.',
                'pdf_imported' => 'PDF import edildi. Oyun, növ, status, e-mail, şifrə, qiymət, konsol, müştəri, telefon, satış tarixi və məxfi kod qorundu.',
                'pdf_reset_imported' => 'PDF əvvəl tam oxundu, sonra baza yeniləndi. PDF-dəki əsas hesab sahələri və köhnə sıra nömrəsi qorundu.',
                'mobile_key_regenerated' => 'Mobil APK üçün API açarı yeniləndi.',
                'error' => 'Əməliyyat tamamlanmadı.',
            );
            $class = $msg === 'error' ? 'mara-account-sale-notice-error' : 'mara-account-sale-notice-success';
            $text = isset($texts[$msg]) ? $texts[$msg] : '';
            if ($msg === 'error' && isset($_GET['mara_account_sale_error'])) {
                $text .= ' ' . sanitize_text_field(rawurldecode(wp_unslash($_GET['mara_account_sale_error'])));
            }
            if ($text === '') {
                return '';
            }
            return '<div class="mara-account-sale-notice ' . esc_attr($class) . '" data-mara-account-sale-notice="1">' . esc_html($text) . '</div>';
        }

        private function render_panel($context = 'admin')
        {
            if (!is_user_logged_in() || !current_user_can($this->capability())) {
                return '<div class="mara-account-sale-wrap"><div class="mara-account-sale-locked"><h3>Hesab Satışı paneli</h3><p>Bu paneldən istifadə etmək üçün icazəli istifadəçi kimi daxil olun.</p></div></div>';
            }

            $records = $this->get_records();
            $stats = $this->calculate_stats($records);
            $customers = $this->get_customer_rows();
            $game_names = $this->get_game_name_rows();
            $settings = $this->get_settings();
            $active_tab = isset($_GET['mara_tab']) ? sanitize_key(wp_unslash($_GET['mara_tab'])) : 'accounts';
            if (!in_array($active_tab, array('accounts', 'new', 'customers', 'sold', 'unsold', 'rental', 'settings'), true)) {
                $active_tab = 'accounts';
            }

            ob_start();
            ?>
            <div class="mara-account-sale-wrap" data-active-tab="<?php echo esc_attr($active_tab); ?>">
                <?php echo $this->message_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <?php echo $this->render_quick_search_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

                <div class="mara-account-sale-tabs" role="tablist">
                    <?php
                    $tabs = array(
                        'accounts' => 'Hesablar',
                        'new' => 'Yeni',
                        'customers' => 'Müştərilər',
                        'sold' => 'Satılanlar',
                        'unsold' => 'Satılmayanlar',
                        'rental' => 'İcarə',
                        'settings' => 'Ayarlar',
                    );
                    foreach ($tabs as $key => $label) :
                        ?>
                        <button type="button" class="mara-account-sale-tab" data-mara-tab-open="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></button>
                    <?php endforeach; ?>
                </div>

                <section class="mara-account-sale-section" data-mara-tab="accounts">
                    <?php echo $this->render_records_table($records, 'accounts-table', 'Bütün hesablar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <section class="mara-account-sale-section" data-mara-tab="new">
                    <div class="mara-account-sale-card mara-account-sale-card-form">
                        <div class="mara-account-sale-card-head">
                            <h2>Yeni hesab satışı əlavə et</h2>
                            <p>Satılıb, Satılmayıb və İcarə statusları dəstəklənir. İcarə üçün müştəri və müddət qeyd edilir.</p>
                        </div>
                        <?php echo $this->render_form(null, $settings, 'new', $customers, $game_names); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </section>

                <section class="mara-account-sale-section" data-mara-tab="customers">
                    <?php echo $this->render_customers($customers); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <section class="mara-account-sale-section" data-mara-tab="sold">
                    <?php echo $this->render_records_table(array_values(array_filter($records, array($this, 'is_sold_record'))), 'sold-table', 'Satılan hesablar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <section class="mara-account-sale-section" data-mara-tab="unsold">
                    <?php echo $this->render_records_table(array_values(array_filter($records, array($this, 'is_unsold_record'))), 'unsold-table', 'Satılmayan hesablar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <section class="mara-account-sale-section" data-mara-tab="rental">
                    <?php echo $this->render_records_table($this->sort_rental_records(array_values(array_filter($records, array($this, 'is_rental_record')))), 'rental-table', 'İcarədə olan hesablar'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <section class="mara-account-sale-section" data-mara-tab="settings">
                    <?php echo $this->render_stats($stats); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php echo $this->render_settings($settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <?php echo $this->render_detail_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->render_customer_modal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->render_edit_modal($settings, $customers, $game_names); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->render_customer_picker_modal($customers); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php echo $this->render_game_picker_modal($game_names); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php
            return ob_get_clean();
        }

        public function is_sold_record($record)
        {
            return isset($record['stock_status']) && $this->normalize_stock_status_value($record['stock_status']) === 'Satılıb';
        }

        public function is_unsold_record($record)
        {
            return isset($record['stock_status']) && $this->normalize_stock_status_value($record['stock_status']) === 'Satılmayıb';
        }

        public function is_rental_record($record)
        {
            return isset($record['stock_status']) && $this->normalize_stock_status_value($record['stock_status']) === 'İcarə';
        }

        private function export_button_html()
        {
            $url = wp_nonce_url(
                admin_url('admin-post.php?action=mara_account_sale_export_csv'),
                'mara_account_sale_export_csv'
            );
            return '<a class="mara-account-sale-btn mara-account-sale-btn-light" href="' . esc_url($url) . '">Export CSV</a>';
        }

        private function render_stats($stats)
        {
            $items = array(
                array('Hesablar', $stats['total']),
                array('Satılan', $stats['sold']),
                array('Satılmayan', $stats['unsold']),
                array('İcarə', $stats['rental']),
                array('Bitməyə yaxın', $stats['rental_expiring']),
                array('Müddəti bitən', $stats['rental_expired']),
                array('Cəmi satış', $this->format_price($stats['total_amount'])),
                array('Bugün satılan', $stats['today_sold']),
                array('Bu ay satılan', $stats['month_sold']),
            );
            ob_start();
            ?>
            <div class="mara-account-sale-stats mara-account-sale-stats-footer" aria-label="Göstəricilər">
                <?php foreach ($items as $item) : ?>
                    <div class="mara-account-sale-stat-card">
                        <span><?php echo esc_html($item[0]); ?></span>
                        <strong><?php echo esc_html($item[1]); ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_quick_search_bar()
        {
            ob_start();
            ?>
            <div class="mara-account-sale-quick-search" data-mara-quick-search>
                <div class="mara-account-sale-quick-search-row">
                    <input id="mara-account-sale-global-search" type="search" placeholder="Oyun adı, e-mail, ad soyad, telefon, PS4/PS5 və ya stok yaz..." data-global-search-input autocomplete="off">
                    <button type="button" class="mara-account-sale-btn mara-account-sale-btn-outline" data-global-search-clear>Təmizlə</button>
                </div>
                <div class="mara-account-sale-quick-search-note" data-global-search-count></div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_filter_panel($target_table, $scope)
        {
            ob_start();
            ?>
            <div class="mara-account-sale-card mara-account-sale-filter" data-target-table="<?php echo esc_attr($target_table); ?>">
                <div class="mara-account-sale-card-head mara-account-sale-filter-head">
                    <div>
                        <h2>Ətraflı axtarış / filter</h2>
                        <p>Oyun, müştəri, telefon, stok, tarix və qiymət üzrə süzgəc.</p>
                    </div>
                    <button type="button" class="mara-account-sale-btn mara-account-sale-btn-outline" data-filter-reset>Filterləri təmizlə</button>
                </div>
                <div class="mara-account-sale-filter-grid">
                    <label>Oyunun adı<input type="text" data-filter-field="game" placeholder="FIFA 25, GTA V..."></label>
                    <label>E-mail<input type="text" data-filter-field="email" placeholder="email@example.com"></label>
                    <label>Ad soyad<input type="text" data-filter-field="customer" placeholder="CAN EMRE"></label>
                    <label>Telefon<input type="text" data-filter-field="phone" placeholder="0705603030"></label>
                    <label>Konsol
                        <select data-filter-field="console">
                            <option value="">Hamısı</option>
                            <option value="PS4">PS4</option>
                            <option value="PS5">PS5</option>
                            <option value="PS4/PS5">PS4/PS5</option>
                        </select>
                    </label>
                    <label>Növ
                        <select data-filter-field="type">
                            <option value="">Hamısı</option>
                            <option value="Online">Online</option>
                            <option value="Universal">Universal</option>
                            <option value="Offline">Offline</option>
                        </select>
                    </label>
                    <label>Stok
                        <select data-filter-field="stock">
                            <option value="">Hamısı</option>
                            <option value="Satılıb">Satılıb</option>
                            <option value="Satılmayıb">Satılmayıb</option>
                            <option value="İcarə">İcarə</option>
                        </select>
                    </label>
                    <label>Başlanğıc tarixi<input type="date" data-filter-field="date_from"></label>
                    <label>Son tarix<input type="date" data-filter-field="date_to"></label>
                    <label>Minimum qiymət<input type="number" min="0" step="0.01" data-filter-field="price_min"></label>
                    <label>Maksimum qiymət<input type="number" min="0" step="0.01" data-filter-field="price_max"></label>
                </div>
                <div class="mara-account-sale-filter-actions">
                    <button type="button" class="mara-account-sale-btn mara-account-sale-btn-primary" data-filter-apply>Axtar</button>
                    <span class="mara-account-sale-filter-count" data-filter-count></span>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function badge($type, $text)
        {
            $class = 'mara-account-sale-badge mara-account-sale-badge-' . sanitize_html_class($type);
            return '<span class="' . esc_attr($class) . '">' . esc_html($text) . '</span>';
        }

        private function render_records_table($records, $table_id, $title)
        {
            $is_accounts_table = ($table_id === 'accounts-table');
            $is_sold_table = ($table_id === 'sold-table');
            $is_unsold_table = ($table_id === 'unsold-table');
            $is_rental_table = ($table_id === 'rental-table');
            $card_classes = 'mara-account-sale-card mara-account-sale-table-card';
            $table_classes = 'mara-account-sale-table';
            if ($is_accounts_table || $is_sold_table || $is_unsold_table || $is_rental_table) {
                $card_classes .= ' mara-account-sale-mobile-compact-card';
                $table_classes .= ' mara-account-sale-mobile-compact-table';
            }
            if ($is_accounts_table) {
                $card_classes .= ' mara-account-sale-accounts-flat-card';
            }
            if ($is_sold_table) {
                $card_classes .= ' mara-account-sale-sold-flat-card';
            }
            if ($is_unsold_table) {
                $card_classes .= ' mara-account-sale-unsold-flat-card';
            }
            if ($is_rental_table) {
                $card_classes .= ' mara-account-sale-rental-flat-card';
            }

            ob_start();
            ?>
            <div class="<?php echo esc_attr($card_classes); ?>">
                <?php if (!$is_accounts_table) : ?>
                    <div class="mara-account-sale-card-head mara-account-sale-table-head">
                        <div>
                            <h2><?php echo esc_html($title); ?> (<span data-table-visible-count="<?php echo esc_attr($table_id); ?>"><?php echo esc_html(count($records)); ?></span>)</h2>
                        </div>
                    </div>
                <?php else : ?>
                    <span class="mara-account-sale-visually-hidden" data-table-visible-count="<?php echo esc_attr($table_id); ?>"><?php echo esc_html(count($records)); ?></span>
                <?php endif; ?>
                <div class="mara-account-sale-table-scroll">
                    <table class="<?php echo esc_attr($table_classes); ?>" id="<?php echo esc_attr($table_id); ?>">
                        <thead>
                            <tr>
                                <th>Oyunun adı</th>
                                <th>E-mail</th>
                                <th>Növ</th>
                                <th>Konsol</th>
                                <th>Qiymət</th>
                                <th>Ad soyad</th>
                                <th>Telefon</th>
                                <th>Satış tarixi</th>
                                <th>Stok</th>
                                <?php if ($is_rental_table) : ?><th>İcarə müddəti</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($records)) : ?>
                            <tr class="mara-account-sale-empty-row"><td colspan="<?php echo $is_rental_table ? 10 : 9; ?>">Məlumat yoxdur.</td></tr>
                        <?php else : ?>
                            <?php foreach ($records as $record) :
                                $record_json = wp_json_encode($this->record_for_json($record));
                                ?>
                                <tr class="mara-account-sale-record-row"
                                    data-record="<?php echo esc_attr($record_json); ?>"
                                    data-game="<?php echo esc_attr(strtolower($record['game_name'])); ?>"
                                    data-email="<?php echo esc_attr(strtolower($record['email'])); ?>"
                                    data-customer="<?php echo esc_attr(strtolower($record['customer_name'])); ?>"
                                    data-phone="<?php echo esc_attr(preg_replace('/\D+/', '', $record['phone'])); ?>"
                                    data-console="<?php echo esc_attr($record['console']); ?>"
                                    data-type="<?php echo esc_attr($record['account_type']); ?>"
                                    data-stock="<?php echo esc_attr($record['stock_status']); ?>"
                                    data-date="<?php echo esc_attr($record['sale_date'] ?: ''); ?>"
                                    data-price="<?php echo esc_attr((float) $record['price']); ?>">
                                    <td data-label="Oyunun adı"><strong><?php echo esc_html($record['game_name']); ?></strong></td>
                                    <td data-label="E-mail" class="mara-account-sale-email-cell"><?php echo esc_html($record['email']); ?></td>
                                    <td data-label="Növ"><?php echo $this->badge('type', $record['account_type']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td data-label="Konsol"><?php echo $this->badge(strtolower($record['console']), $record['console']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td data-label="Qiymət"><?php echo esc_html($this->format_price($record['price'])); ?></td>
                                    <td data-label="Ad soyad"><?php echo esc_html($record['customer_name'] ?: '—'); ?></td>
                                    <td data-label="Telefon"><?php echo $this->phone_link($record['phone']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td data-label="Satış tarixi"><?php echo esc_html($record['sale_date'] ?: '—'); ?></td>
                                    <?php $normalized_status = $this->normalize_stock_status_value($record['stock_status']); ?>
                                    <td data-label="Stok"><?php echo $this->badge($normalized_status === 'Satılıb' ? 'sold' : ($normalized_status === 'İcarə' ? 'rental' : 'unsold'), $normalized_status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <?php if ($is_rental_table) : $rental_runtime = $this->rental_runtime_payload($record); ?>
                                        <td data-label="İcarə müddəti"><strong><?php echo esc_html($rental_runtime['rental_remaining_text'] ?: '—'); ?></strong><?php if (!empty($rental_runtime['rental_ends_at'])) : ?><br><small><?php echo esc_html($rental_runtime['rental_ends_at']); ?></small><?php endif; ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function record_for_json($record)
        {
            $record = array_merge($record, $this->rental_runtime_payload($record));
            $record['price_formatted'] = $this->format_price($record['price']);
            $record['whatsapp_phone'] = preg_replace('/\D+/', '', $record['phone']);
            $record['delete_url'] = wp_nonce_url(
                admin_url('admin-post.php?action=mara_account_sale_delete&id=' . absint($record['id'])),
                'mara_account_sale_delete_' . absint($record['id'])
            );
            return $record;
        }

        private function phone_link($phone)
        {
            if (!$phone) {
                return '—';
            }
            return esc_html($phone);
        }

        private function render_form($record, $settings, $return_tab, $customers = array(), $game_names = array())
        {
            $record = wp_parse_args(is_array($record) ? $record : array(), array(
                'id' => 0,
                'game_name' => '',
                'account_type' => 'Online',
                'email' => '',
                'account_password' => '',
                'secret_code' => '',
                'price' => '',
                'console' => 'PS5',
                'customer_name' => '',
                'phone' => '',
                'sale_date' => current_time('Y-m-d'),
                'payment_type' => $settings['default_payment_type'],
                'stock_status' => $settings['default_stock_status'],
                'rental_duration_value' => '',
                'rental_duration_unit' => 'day',
                'rental_started_at' => '',
                'rental_ends_at' => '',
            ));

            ob_start();
            ?>
            <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-account-sale-form>
                <input type="hidden" name="action" value="mara_account_sale_save">
                <input type="hidden" name="id" value="<?php echo esc_attr(absint($record['id'])); ?>" data-form-field="id">
                <input type="hidden" name="mara_return_tab" value="<?php echo esc_attr($return_tab); ?>">
                <?php wp_nonce_field('mara_account_sale_save', 'mara_account_sale_nonce'); ?>

                <div class="mara-account-sale-form-grid">
                    <label>Oyunun adı *
                        <input type="text" name="game_name" required placeholder="FIFA 25, FC 26, GTA V" value="<?php echo esc_attr($record['game_name']); ?>" data-form-field="game_name" data-game-picker-input autocomplete="off">
                        <small class="mara-account-sale-game-help">Bir neçə oyun seçmək üçün xanaya klik edib oyunları ardıcıl seç.</small>
                        <div class="mara-account-sale-game-selected" data-game-selected-list hidden></div>
                    </label>
                    <label>Növ *
                        <select name="account_type" required data-form-field="account_type">
                            <option value="Online" <?php selected($record['account_type'], 'Online'); ?>>Online</option>
                            <option value="Universal" <?php selected($record['account_type'], 'Universal'); ?>>Universal</option>
                            <option value="Offline" <?php selected($record['account_type'], 'Offline'); ?>>Offline</option>
                        </select>
                    </label>
                    <label>E-mail *
                        <input type="email" name="email" required placeholder="example@mail.com" value="<?php echo esc_attr($record['email']); ?>" data-form-field="email">
                    </label>
                    <label>Şifrə
                        <input type="text" name="account_password" placeholder="Hesab şifrəsi" value="<?php echo esc_attr(isset($record['account_password']) ? $record['account_password'] : ''); ?>" data-form-field="account_password" autocomplete="off">
                    </label>
                    <label>Məxfi kod
                        <input type="text" name="secret_code" placeholder="Məxfi kod" value="<?php echo esc_attr($record['secret_code']); ?>" data-form-field="secret_code" autocomplete="off">
                    </label>
                    <label>Qiymət *
                        <input type="text" name="price" required inputmode="decimal" placeholder="35.50" value="<?php echo esc_attr($record['price']); ?>" class="mara-account-sale-price-input" data-form-field="price">
                    </label>
                    <label>Konsol *
                        <select name="console" required data-form-field="console">
                            <option value="PS4" <?php selected($record['console'], 'PS4'); ?>>PS4</option>
                            <option value="PS5" <?php selected($record['console'], 'PS5'); ?>>PS5</option>
                            <option value="PS4/PS5" <?php selected($record['console'], 'PS4/PS5'); ?>>PS4/PS5</option>
                        </select>
                    </label>
                    <label>Ad soyad
                        <input type="text" name="customer_name" placeholder="CAN EMRE" value="<?php echo esc_attr($record['customer_name']); ?>" class="mara-account-sale-name-input" data-form-field="customer_name" data-customer-picker-input autocomplete="off">
                    </label>
                    <label>Telefon
                        <input type="text" name="phone" inputmode="tel" placeholder="0705603030" value="<?php echo esc_attr($record['phone']); ?>" class="mara-account-sale-phone-input" data-form-field="phone">
                    </label>
                    <label>Satış tarixi *
                        <input type="date" name="sale_date" value="<?php echo esc_attr($record['sale_date']); ?>" data-form-field="sale_date">
                    </label>
                    <input type="hidden" name="payment_type" value="<?php echo esc_attr(in_array($record['payment_type'], array('Nağd', 'Nisyə'), true) ? $record['payment_type'] : 'Nağd'); ?>" data-form-field="payment_type">
                    <label>Stok / status *
                        <select name="stock_status" required data-form-field="stock_status" data-stock-select>
                            <option value="Satılıb" <?php selected($record['stock_status'], 'Satılıb'); ?>>Satılıb</option>
                            <option value="Satılmayıb" <?php selected($record['stock_status'], 'Satılmayıb'); ?>>Satılmayıb</option>
                            <option value="İcarə" <?php selected($record['stock_status'], 'İcarə'); ?>>İcarə</option>
                        </select>
                    </label>
                    <div class="mara-account-sale-rental-fields" data-rental-fields <?php echo $record['stock_status'] === 'İcarə' ? '' : 'hidden'; ?>>
                        <label>İcarə müddəti *
                            <input type="number" min="1" step="1" name="rental_duration_value" value="<?php echo esc_attr((int) $record['rental_duration_value'] > 0 ? (int) $record['rental_duration_value'] : ''); ?>" data-form-field="rental_duration_value" data-rental-duration-value>
                        </label>
                        <label>Müddət vahidi *
                            <select name="rental_duration_unit" data-form-field="rental_duration_unit" data-rental-duration-unit>
                                <option value="hour" <?php selected($record['rental_duration_unit'], 'hour'); ?>>Saat</option>
                                <option value="day" <?php selected($record['rental_duration_unit'], 'day'); ?>>Gün</option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="mara-account-sale-form-note" data-stock-note>Satılıb seçilərsə ad soyad və telefon məcburidir.</div>
                <div class="mara-account-sale-form-actions">
                    <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-primary">Yadda saxla</button>
                    <button type="reset" class="mara-account-sale-btn mara-account-sale-btn-outline">Təmizlə</button>
                </div>
            </form>
            <?php
            return ob_get_clean();
        }

        private function render_customers($customers)
        {
            ob_start();
            ?>
            <div class="mara-account-sale-card mara-account-sale-table-card mara-account-sale-customers-flat-card">
                <div class="mara-account-sale-card-head">
                    <div>
                        <h2>Müştərilər</h2>
                        <p>Eyni telefon nömrəsinə görə avtomatik qruplaşdırılır.</p>
                    </div>
                </div>
                <div class="mara-account-sale-table-scroll">
                    <table class="mara-account-sale-table mara-account-sale-customer-table mara-account-sale-customer-compact-table" id="customers-table">
                        <thead>
                            <tr>
                                <th>Ad soyad</th>
                                <th>Telefon</th>
                                <th>Aldığı oyun sayı</th>
                                <th>Ümumi alış məbləği</th>
                                <th>Son alış tarixi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)) : ?>
                                <tr><td colspan="5">Müştəri məlumatı yoxdur.</td></tr>
                            <?php else : ?>
                                <?php foreach ($customers as $customer) :
                                    $games = $this->get_records_by_phone($customer['phone']);
                                    $payload = array(
                                        'customer_name' => $customer['customer_name'],
                                        'phone' => $customer['phone'],
                                        'game_count' => (int) $customer['game_count'],
                                        'total_amount' => $this->format_price($customer['total_amount']),
                                        'cash_amount' => $this->format_price($customer['cash_amount']),
                                        'credit_amount' => $this->format_price($customer['credit_amount']),
                                        'cash_count' => (int) $customer['cash_count'],
                                        'credit_count' => (int) $customer['credit_count'],
                                        'last_sale_date' => $customer['last_sale_date'],
                                        'games' => array_map(array($this, 'record_for_json'), $games),
                                    );
                                    ?>
                                    <tr class="mara-account-sale-customer-row" data-customer="<?php echo esc_attr(wp_json_encode($payload)); ?>">
                                        <td data-label="Ad soyad"><strong><?php echo esc_html($customer['customer_name']); ?></strong></td>
                                        <td data-label="Telefon"><?php echo $this->phone_link($customer['phone']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                        <td data-label="Aldığı oyun sayı"><?php echo esc_html((int) $customer['game_count']); ?></td>
                                        <td data-label="Ümumi alış məbləği"><?php echo esc_html($this->format_price($customer['total_amount'])); ?></td>
                                        <td data-label="Son alış tarixi"><?php echo esc_html($customer['last_sale_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_settings($settings)
        {
            $db_counts = $this->get_database_status_counts();
            $table_name = $this->table_name();
            $last_import = get_option(self::LEGACY_IMPORT_OPTION_KEY, array());
            ob_start();
            ?>
            <div class="mara-account-sale-settings-nav" aria-label="Ayarlar bölmələri">
                <a class="mara-account-sale-btn mara-account-sale-btn-light" href="#mara-general-settings">Ümumi ayarlar</a>
                <a class="mara-account-sale-btn mara-account-sale-btn-light" href="#mara-mobile-api">Mobil API</a>
                <a class="mara-account-sale-btn mara-account-sale-btn-light" href="#mara-pdf-import">PDF import</a>
                <a class="mara-account-sale-btn mara-account-sale-btn-light" href="#mara-database-tools">Bazanı təmizlə</a>
            </div>

            <div class="mara-account-sale-card mara-account-sale-card-form" id="mara-general-settings">
                <div class="mara-account-sale-card-head">
                    <div>
                        <h2>Ümumi ayarlar</h2>
                        <p>Panel yalnız WordPress admin daxilində idarə olunur.</p>
                    </div>
                </div>
                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mara_account_sale_save_settings">
                    <input type="hidden" name="default_payment_type" value="<?php echo esc_attr(isset($settings['default_payment_type']) ? $settings['default_payment_type'] : 'Nağd'); ?>">
                    <?php wp_nonce_field('mara_account_sale_save_settings', 'mara_account_sale_settings_nonce'); ?>
                    <div class="mara-account-sale-form-grid">
                        <label>Pul vahidi
                            <input type="text" name="currency" value="<?php echo esc_attr($settings['currency']); ?>" placeholder="AZN">
                        </label>
                        <label>Default stok statusu
                            <select name="default_stock_status">
                                <option value="Satılıb" <?php selected($settings['default_stock_status'], 'Satılıb'); ?>>Satılıb</option>
                                <option value="Satılmayıb" <?php selected($settings['default_stock_status'], 'Satılmayıb'); ?>>Satılmayıb</option>
                            </select>
                        </label>
                        <label class="mara-account-sale-check-label">
                            <input type="checkbox" name="phone_format_enabled" value="1" <?php checked($settings['phone_format_enabled'], '1'); ?>>
                            Telefon formatlama aktivdir
                        </label>
                    </div>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-primary">Ayarları saxla</button>
                    </div>
                </form>
            </div>

            <div class="mara-account-sale-card mara-account-sale-card-form" id="mara-mobile-api">
                <div class="mara-account-sale-card-head">
                    <div>
                        <h2>Mobil APK bağlantısı</h2>
                        <p>Marakana Mobile → Hesab Satışı bu REST API açarı ilə eyni WordPress bazasına qoşulur.</p>
                    </div>
                </div>
                <div class="mara-account-sale-form-grid">
                    <label>WordPress ünvanı
                        <input type="text" readonly value="<?php echo esc_attr(home_url('/')); ?>">
                    </label>
                    <label>Mobil API açarı
                        <input type="text" readonly value="<?php echo esc_attr($this->get_mobile_api_key()); ?>" onclick="this.select();">
                    </label>
                </div>
                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Mobil API açarı yenilənsin? Köhnə açarla qoşulan telefonlarda yeni açarı yazmaq lazım olacaq.');">
                    <input type="hidden" name="action" value="mara_account_sale_regenerate_mobile_key">
                    <?php wp_nonce_field('mara_account_sale_regenerate_mobile_key', 'mara_account_sale_regenerate_mobile_key_nonce'); ?>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-outline">API açarını yenilə</button>
                    </div>
                </form>
                <p class="mara-account-sale-help">API ünvanı: <code><?php echo esc_html(rest_url('marakana-account-sales/v1/')); ?></code></p>
            </div>

            <div class="mara-account-sale-card mara-account-sale-card-form" id="mara-pdf-import">
                <div class="mara-account-sale-card-head">
                    <div>
                        <h2>PDF import</h2>
                        <p>Seçilən PDF faylı həqiqətən oxunur və içindəki hesab sətrləri bazaya əlavə edilir.</p>
                        <p><strong>Hazırkı baza:</strong> Cəmi <?php echo esc_html($db_counts['total']); ?> / Satılıb <?php echo esc_html($db_counts['sold']); ?> / Satılmayıb <?php echo esc_html($db_counts['unsold']); ?> / İcarə <?php echo esc_html(isset($db_counts['rental']) ? $db_counts['rental'] : 0); ?></p>
                        <?php if (is_array($last_import) && !empty($last_import)) : ?>
                            <p><strong>Son import:</strong> <?php echo esc_html(isset($last_import['source']) ? $last_import['source'] : '—'); ?> — əlavə edildi <?php echo esc_html(isset($last_import['inserted']) ? (int) $last_import['inserted'] : 0); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <form class="mara-account-sale-form mara-account-sale-tool-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-mara-pdf-import-form="1" data-mara-pdf-confirm="PDF içəri aktarılsın?">
                    <input type="hidden" name="action" value="mara_account_sale_import_pdf">
                    <input type="hidden" name="mara_client_pdf_bbox" value="" data-mara-client-pdf-bbox>
                    <?php wp_nonce_field('mara_account_sale_import_pdf', 'mara_account_sale_import_pdf_nonce'); ?>
                    <div class="mara-account-sale-tool-grid">
                        <label>PDF faylı seç
                            <input type="file" name="legacy_pdf" accept="application/pdf,.pdf" required data-mara-pdf-file>
                        </label>
                        <label class="mara-account-sale-check-label">
                            <input type="checkbox" name="clear_before_import" value="1">
                            Əvvəl hesab bazasını təmizlə, sonra import et
                        </label>
                    </div>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-primary">PDF import et</button>
                    </div>
                    <p class="mara-account-sale-help">PDF brauzerdə oxunur; serverdə pdftotext və PHP DOM tələb olunmur. Şifrə, məxfi kod, status, müştəri və köhnə sıra nömrəsi qorunur.</p>
                    <p class="mara-account-sale-help" data-mara-pdf-status hidden></p>
                </form>

                <form class="mara-account-sale-form mara-account-sale-tool-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" data-mara-pdf-import-form="1" data-mara-pdf-confirm="Baza təmizlənsin və PDF sıfırdan tam yüklənsin?">
                    <input type="hidden" name="action" value="mara_account_sale_reset_import_pdf">
                    <input type="hidden" name="mara_client_pdf_bbox" value="" data-mara-client-pdf-bbox>
                    <?php wp_nonce_field('mara_account_sale_reset_import_pdf', 'mara_account_sale_reset_import_pdf_nonce'); ?>
                    <div class="mara-account-sale-tool-grid">
                        <label>PDF faylı seç
                            <input type="file" name="legacy_pdf_reset" accept="application/pdf,.pdf" required data-mara-pdf-file>
                        </label>
                    </div>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-warning">Bazanı təmizlə + PDF tam yüklə</button>
                    </div>
                    <p class="mara-account-sale-help" data-mara-pdf-status hidden></p>
                </form>
            </div>

            <div class="mara-account-sale-card mara-account-sale-card-form mara-account-sale-danger-zone" id="mara-database-tools">
                <div class="mara-account-sale-card-head">
                    <div>
                        <h2>Bazanı təmizlə</h2>
                        <p><strong>SQL cədvəli:</strong> <code><?php echo esc_html($table_name); ?></code></p>
                        <p>Bu əməliyyat hesabları, hesab–oyun əlaqələrini, oyun kataloqunu və ayrıca yaradılmış müştəri kontaktlarını tam silir. Plugin ayarları və Mobil API açarı qalır.</p>
                    </div>
                </div>
                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Diqqət! Bütün hesab bazası, oyun adları və müştəri kontaktları silinəcək. Davam edilsin?');">
                    <input type="hidden" name="action" value="mara_account_sale_clear_database">
                    <?php wp_nonce_field('mara_account_sale_clear_database', 'mara_account_sale_clear_database_nonce'); ?>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-danger">Bazanı tam təmizlə</button>
                    </div>
                </form>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_detail_modal()
        {
            ob_start();
            ?>
            <div class="mara-account-sale-modal" id="maraAccountSaleDetailModal" aria-hidden="true">
                <div class="mara-account-sale-modal-backdrop" data-modal-close></div>
                <div class="mara-account-sale-modal-box" role="dialog" aria-modal="true">
                    <button type="button" class="mara-account-sale-modal-close" data-modal-close>×</button>
                    <div class="mara-account-sale-modal-head">
                        <span class="mara-account-sale-kicker">Hesab detalı</span>
                        <h2 data-detail-title>—</h2>
                    </div>
                    <div class="mara-account-sale-detail-grid" data-detail-grid></div>
                    <div class="mara-account-sale-modal-actions">
                        <button type="button" class="mara-account-sale-btn mara-account-sale-btn-primary" data-detail-edit>Düzəliş et</button>
                        <a class="mara-account-sale-btn mara-account-sale-btn-warning" href="#" target="_blank" rel="noopener" data-detail-whatsapp>WhatsApp</a>
                        <button type="button" class="mara-account-sale-btn mara-account-sale-btn-outline" data-detail-print>Satış qəbzi</button>
                        <a class="mara-account-sale-btn mara-account-sale-btn-danger" href="#" data-detail-delete data-confirm="Bu hesab silinsin?">Sil</a>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_customer_modal()
        {
            ob_start();
            ?>
            <div class="mara-account-sale-modal" id="maraAccountSaleCustomerModal" aria-hidden="true">
                <div class="mara-account-sale-modal-backdrop" data-modal-close></div>
                <div class="mara-account-sale-modal-box mara-account-sale-modal-box-wide" role="dialog" aria-modal="true">
                    <button type="button" class="mara-account-sale-modal-close" data-modal-close>×</button>
                    <div class="mara-account-sale-modal-head">
                        <span class="mara-account-sale-kicker">Müştəri detalı</span>
                        <h2 data-customer-title>—</h2>
                    </div>
                    <div class="mara-account-sale-detail-grid" data-customer-summary></div>
                    <div class="mara-account-sale-table-scroll">
                        <table class="mara-account-sale-table">
                            <thead>
                                <tr>
                                    <th>Oyunun adı</th>
                                    <th>E-mail</th>
                                    <th>Növ</th>
                                </tr>
                            </thead>
                            <tbody data-customer-games></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_customer_picker_modal($customers)
        {
            ob_start();
            ?>
            <div class="mara-account-sale-modal" id="maraAccountSaleCustomerPickerModal" aria-hidden="true">
                <div class="mara-account-sale-modal-backdrop" data-modal-close></div>
                <div class="mara-account-sale-modal-box mara-account-sale-customer-picker-box" role="dialog" aria-modal="true">
                    <button type="button" class="mara-account-sale-modal-close" data-modal-close>×</button>
                    <div class="mara-account-sale-modal-head">
                        <span class="mara-account-sale-kicker">Müştəri seç</span>
                        <h2>Əvvəlki müştərilər</h2>
                    </div>
                    <div class="mara-account-sale-customer-picker-search">
                        <input type="search" placeholder="Ad soyad və ya telefon yaz..." data-customer-picker-search autocomplete="off">
                    </div>
                    <div class="mara-account-sale-customer-picker-list" data-customer-picker-list>
                        <?php if (empty($customers)) : ?>
                            <div class="mara-account-sale-customer-picker-empty">Müştəri məlumatı yoxdur.</div>
                        <?php else : ?>
                            <?php foreach ($customers as $customer) :
                                $customer_name = isset($customer['customer_name']) ? $customer['customer_name'] : '';
                                $phone = isset($customer['phone']) ? $customer['phone'] : '';
                                $search = strtolower($customer_name . ' ' . $phone . ' ' . preg_replace('/\D+/', '', $phone));
                                ?>
                                <button type="button"
                                    class="mara-account-sale-customer-picker-row"
                                    data-customer-pick-row
                                    data-customer-name="<?php echo esc_attr($customer_name); ?>"
                                    data-customer-phone="<?php echo esc_attr($phone); ?>"
                                    data-customer-search="<?php echo esc_attr($search); ?>">
                                    <strong><?php echo esc_html($customer_name); ?></strong>
                                    <span><?php echo esc_html($phone); ?></span>
                                    <small><?php echo esc_html((int) $customer['game_count']); ?> alış · <?php echo esc_html($this->format_price($customer['total_amount'])); ?></small>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mara-account-sale-customer-picker-empty" data-customer-picker-no-results hidden>Nəticə tapılmadı.</div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_game_picker_modal($game_names)
        {
            ob_start();
            ?>
            <div class="mara-account-sale-modal" id="maraAccountSaleGamePickerModal" aria-hidden="true">
                <div class="mara-account-sale-modal-backdrop" data-modal-close></div>
                <div class="mara-account-sale-modal-box mara-account-sale-customer-picker-box mara-account-sale-game-picker-box" role="dialog" aria-modal="true">
                    <button type="button" class="mara-account-sale-modal-close" data-modal-close>×</button>
                    <div class="mara-account-sale-modal-head">
                        <span class="mara-account-sale-kicker">Oyun seç</span>
                        <h2>Yadda saxlanan oyun adları</h2>
                    </div>
                    <div class="mara-account-sale-customer-picker-search mara-account-sale-game-picker-search">
                        <input type="search" placeholder="Oyun adı yaz..." data-game-picker-search autocomplete="off">
                    </div>
                    <div class="mara-account-sale-customer-picker-list mara-account-sale-game-picker-list" data-game-picker-list>
                        <?php if (empty($game_names)) : ?>
                            <div class="mara-account-sale-customer-picker-empty">Yadda saxlanmış oyun adı yoxdur.</div>
                        <?php else : ?>
                            <?php foreach ($game_names as $game_row) :
                                $game_name = isset($game_row['game_name']) ? $game_row['game_name'] : '';
                                if ($game_name === '') {
                                    continue;
                                }
                                $search = strtolower($game_name);
                                ?>
                                <button type="button"
                                    class="mara-account-sale-customer-picker-row mara-account-sale-game-picker-row"
                                    data-game-pick-row
                                    data-game-name="<?php echo esc_attr($game_name); ?>"
                                    data-game-search="<?php echo esc_attr($search); ?>">
                                    <strong><?php echo esc_html($game_name); ?></strong>
                                    <span><?php echo esc_html((int) $game_row['use_count']); ?> dəfə istifadə olunub</span>
                                </button>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="mara-account-sale-customer-picker-empty mara-account-sale-game-picker-empty" data-game-picker-no-results hidden>
                        <span data-game-picker-empty-text>Nəticə tapılmadı.</span>
                        <button type="button" class="mara-account-sale-btn mara-account-sale-btn-primary mara-account-sale-game-add-new" data-game-add-new>Yeni oyun</button>
                    </div>
                    <div class="mara-account-sale-game-picker-actions">
                        <button type="button" class="mara-account-sale-btn mara-account-sale-btn-primary" data-game-picker-done>Hazır</button>
                        <button type="button" class="mara-account-sale-btn mara-account-sale-btn-outline" data-game-picker-clear>Təmizlə</button>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        private function render_edit_modal($settings, $customers = array(), $game_names = array())
        {
            ob_start();
            ?>
            <div class="mara-account-sale-modal" id="maraAccountSaleEditModal" aria-hidden="true">
                <div class="mara-account-sale-modal-backdrop" data-modal-close></div>
                <div class="mara-account-sale-modal-box mara-account-sale-modal-box-wide" role="dialog" aria-modal="true">
                    <button type="button" class="mara-account-sale-modal-close" data-modal-close>×</button>
                    <div class="mara-account-sale-modal-head">
                        <span class="mara-account-sale-kicker">Düzəliş</span>
                        <h2>Hesab məlumatlarını dəyiş</h2>
                    </div>
                    <?php echo $this->render_form(array('id' => 0), $settings, 'accounts', $customers, $game_names); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}

Marakana_Playstation_Hesab_Satisi_100::instance();
register_activation_hook(__FILE__, array('Marakana_Playstation_Hesab_Satisi_100', 'activate'));
