<?php
/**
 * Plugin Name: Marakana Playstation Hesab Satışı
 * Plugin URI: https://marakana.local/
 * Description: Playstation oyun hesablarının satışı, stok, müştəri, ödəniş və geniş axtarış idarəetməsi üçün professional Marakana plugin.
 * Version: 1.0.72
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
        const VERSION = '1.0.72';
        const DB_VERSION = '1.0.4';
        const OPTION_KEY = 'mara_account_sale_settings';
        const DB_OPTION_KEY = 'mara_account_sale_db_version';
        const LEGACY_IMPORT_OPTION_KEY = 'mara_account_sale_legacy_pdf_import_v54';
        const MOBILE_API_KEY_OPTION_KEY = 'mara_account_sale_mobile_api_key_v1';
        const CUSTOMER_CONTACTS_OPTION_KEY = 'mara_account_sale_customer_contacts_v1';

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
            add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
            add_shortcode('ps_hesab_satisi', array($this, 'shortcode'));

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
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                game_name TEXT NOT NULL,
                account_type VARCHAR(20) NOT NULL,
                email VARCHAR(190) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                console VARCHAR(10) NOT NULL,
                customer_name VARCHAR(190) NULL DEFAULT '',
                phone VARCHAR(30) NULL DEFAULT '',
                sale_date DATE NULL DEFAULT NULL,
                payment_type VARCHAR(20) NOT NULL,
                stock_status VARCHAR(30) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY phone (phone),
                KEY stock_status (stock_status),
                KEY payment_type (payment_type),
                KEY sale_date (sale_date),
                KEY console (console),
                KEY account_type (account_type)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            $this->repair_table_schema();
        }

        private function repair_table_schema()
        {
            global $wpdb;
            $table = $this->table_name();
            $wpdb->query("ALTER TABLE {$table} MODIFY customer_name VARCHAR(190) NULL DEFAULT ''");
            $wpdb->query("ALTER TABLE {$table} MODIFY phone VARCHAR(30) NULL DEFAULT ''");
            $wpdb->query("ALTER TABLE {$table} MODIFY sale_date DATE NULL DEFAULT NULL");
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

        public function shortcode($atts = array())
        {
            $this->enqueue_assets();
            return $this->render_panel('frontend');
        }

        public function admin_page()
        {
            $this->enqueue_assets();
            echo $this->render_panel('admin'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        private function get_records()
        {
            global $wpdb;
            $table = $this->table_name();
            $records = $wpdb->get_results("SELECT * FROM {$table} ORDER BY sale_date DESC, id DESC", ARRAY_A);
            if (!is_array($records)) {
                return array();
            }
            foreach ($records as &$record) {
                $record['stock_status'] = $this->normalize_stock_status_value(isset($record['stock_status']) ? $record['stock_status'] : '');
                $record['account_type'] = $this->normalize_legacy_account_type(isset($record['account_type']) ? $record['account_type'] : 'Online');
                $record['console'] = $this->normalize_legacy_console(isset($record['console']) ? $record['console'] : '');
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
                        COALESCE(SUM(CASE WHEN payment_type = 'Nağd' AND stock_status = 'Satılıb' THEN price ELSE 0 END), 0) AS cash_amount,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nisyə' AND stock_status = 'Satılıb' THEN price ELSE 0 END), 0) AS credit_amount,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nağd' AND stock_status = 'Satılıb' THEN 1 ELSE 0 END), 0) AS cash_count,
                        COALESCE(SUM(CASE WHEN payment_type = 'Nisyə' AND stock_status = 'Satılıb' THEN 1 ELSE 0 END), 0) AS credit_count
                 FROM {$table}
                 WHERE phone <> '' AND stock_status = 'Satılıb'
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
                $updated_at = isset($contact['updated_at']) ? (string) $contact['updated_at'] : '';
                if (isset($merged[$phone])) {
                    $merged[$phone]['customer_name'] = $name;
                    $merged[$phone]['contact_updated_at'] = $updated_at;
                } else {
                    $merged[$phone] = array(
                        'phone' => $phone,
                        'customer_name' => $name,
                        'game_count' => 0,
                        'total_amount' => 0,
                        'last_sale_date' => '',
                        'cash_amount' => 0,
                        'credit_amount' => 0,
                        'cash_count' => 0,
                        'credit_count' => 0,
                        'contact_updated_at' => $updated_at,
                    );
                }
            }

            $customers = array_values($merged);
            usort($customers, function ($a, $b) {
                $a_sale = isset($a['last_sale_date']) ? (string) $a['last_sale_date'] : '';
                $b_sale = isset($b['last_sale_date']) ? (string) $b['last_sale_date'] : '';
                if ($a_sale !== $b_sale) {
                    if ($a_sale === '') return 1;
                    if ($b_sale === '') return -1;
                    return strcmp($b_sale, $a_sale);
                }
                $a_updated = isset($a['contact_updated_at']) ? (string) $a['contact_updated_at'] : '';
                $b_updated = isset($b['contact_updated_at']) ? (string) $b['contact_updated_at'] : '';
                if ($a_updated !== $b_updated) {
                    return strcmp($b_updated, $a_updated);
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

            $parts = preg_split('/\s*(?:,|;|\r?\n|\s+\+\s+|·)\s*/u', $value);
            $names = array();
            $seen = array();

            foreach ($parts as $part) {
                $name = trim((string) $part);
                if ($name === '') {
                    continue;
                }
                $key = function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $names[] = $name;
            }

            return $names;
        }

        private function normalize_game_names($value)
        {
            $names = $this->split_game_names($value);
            return implode(', ', $names);
        }

        private function get_game_name_rows()
        {
            global $wpdb;
            $table = $this->table_name();
            $rows = $wpdb->get_results(
                "SELECT game_name, updated_at
                 FROM {$table}
                 WHERE game_name <> ''
                 ORDER BY updated_at DESC",
                ARRAY_A
            );

            $games = array();
            foreach ($rows as $row) {
                $last_used = isset($row['updated_at']) ? $row['updated_at'] : '';
                foreach ($this->split_game_names(isset($row['game_name']) ? $row['game_name'] : '') as $game_name) {
                    $key = function_exists('mb_strtolower') ? mb_strtolower($game_name, 'UTF-8') : strtolower($game_name);
                    if (!isset($games[$key])) {
                        $games[$key] = array(
                            'game_name' => $game_name,
                            'use_count' => 0,
                            'last_used' => $last_used,
                        );
                    }
                    $games[$key]['use_count']++;
                    if ($last_used > $games[$key]['last_used']) {
                        $games[$key]['last_used'] = $last_used;
                    }
                }
            }

            $games = array_values($games);
            usort($games, function ($a, $b) {
                if ($a['last_used'] === $b['last_used']) {
                    return strcasecmp($a['game_name'], $b['game_name']);
                }
                return strcmp($b['last_used'], $a['last_used']);
            });

            return $games;
        }

        private function get_records_by_phone($phone)
        {
            global $wpdb;
            $table = $this->table_name();
            return $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE phone = %s ORDER BY sale_date DESC, id DESC", $phone),
                ARRAY_A
            );
        }

        private function calculate_stats($records)
        {
            $today = current_time('Y-m-d');
            $month = current_time('Y-m');
            $stats = array(
                'total' => count($records),
                'sold' => 0,
                'unsold' => 0,
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

        private function sanitize_record_from_post(&$errors)
        {
            $allowed_types = array('Online', 'Universal', 'Offline');
            $allowed_consoles = array('PS4', 'PS5', 'PS4/PS5');
            $allowed_payments = array('Nağd', 'Nisyə');
            $allowed_stock = array('Satılıb', 'Satılmayıb');

            $game_name = isset($_POST['game_name']) ? sanitize_text_field(wp_unslash($_POST['game_name'])) : '';
            $account_type = isset($_POST['account_type']) ? sanitize_text_field(wp_unslash($_POST['account_type'])) : '';
            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $price_raw = isset($_POST['price']) ? sanitize_text_field(wp_unslash($_POST['price'])) : '';
            $console = isset($_POST['console']) ? sanitize_text_field(wp_unslash($_POST['console'])) : '';
            $customer_name_raw = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
            $phone_raw = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
            $sale_date = isset($_POST['sale_date']) ? sanitize_text_field(wp_unslash($_POST['sale_date'])) : '';
            $payment_type = isset($_POST['payment_type']) ? sanitize_text_field(wp_unslash($_POST['payment_type'])) : '';
            $stock_status = isset($_POST['stock_status']) ? sanitize_text_field(wp_unslash($_POST['stock_status'])) : '';

            $game_name = $this->normalize_game_names($game_name);
            if ($game_name === '') {
                $errors[] = 'Oyunun adı boş ola bilməz.';
            }

            if (!in_array($account_type, $allowed_types, true)) {
                $errors[] = 'Növ düzgün seçilməyib.';
            }

            if ($email === '' || !is_email($email)) {
                $errors[] = 'E-mail formatı düzgün deyil.';
            }

            $price_clean = str_replace(',', '.', trim($price_raw));
            if ($price_clean === '' || !is_numeric($price_clean) || (float) $price_clean < 0) {
                $errors[] = 'Qiymət düzgün rəqəm olmalıdır və mənfi ola bilməz.';
                $price = 0;
            } else {
                $price = round((float) $price_clean, 2);
            }

            if (!in_array($console, $allowed_consoles, true)) {
                $errors[] = 'Konsol düzgün seçilməyib.';
            }

            if (!in_array($payment_type, $allowed_payments, true)) {
                $errors[] = 'Ödəniş növü düzgün seçilməyib.';
            }

            if (!in_array($stock_status, $allowed_stock, true)) {
                $errors[] = 'Stok statusu düzgün seçilməyib.';
            }

            if ($stock_status === 'Satılıb') {
                if (!$this->validate_date($sale_date)) {
                    $errors[] = 'Satılıb seçilərsə satış tarixi düzgün olmalıdır.';
                }
            } elseif ($sale_date !== '' && !$this->validate_date($sale_date)) {
                $errors[] = 'Satış tarixi düzgün deyil.';
            }

            $customer_name = $this->normalize_name($customer_name_raw);
            if ($customer_name !== '' && !preg_match('/^[\p{L}\s]+$/u', $customer_name)) {
                $errors[] = 'Ad soyad yalnız hərflərdən və boşluqdan ibarət olmalıdır.';
            }

            $phone_error = '';
            $phone = $this->normalize_phone($phone_raw, $phone_error);
            if ($phone_raw !== '' && $phone === '' && $phone_error !== '') {
                $errors[] = $phone_error;
            }

            if ($stock_status === 'Satılıb') {
                if ($customer_name === '') {
                    $errors[] = 'Satılıb seçilərsə ad soyad məcburidir.';
                }
                if ($phone === '') {
                    $errors[] = 'Satılıb seçilərsə telefon məcburidir.';
                }
            }

            return array(
                'game_name' => $game_name,
                'account_type' => $account_type,
                'email' => $email,
                'price' => $price,
                'console' => $console,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'sale_date' => $sale_date !== '' ? $sale_date : null,
                'payment_type' => $payment_type,
                'stock_status' => $stock_status,
            );
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

            if (strpos($lower, 'satılmayıb') !== false || strpos($ascii, 'satilmayib') !== false || strpos($ascii, 'satilmayan') !== false || strpos($ascii, 'icare') !== false || strpos($ascii, 'icarə') !== false || strpos($ascii, 'unsold') !== false) {
                return 'Satılmayıb';
            }
            if (strpos($lower, 'satılıb') !== false || strpos($ascii, 'satilib') !== false || strpos($ascii, 'sold') !== false) {
                return 'Satılıb';
            }

            return in_array($value, array('Satılıb', 'Satılmayıb'), true) ? $value : 'Satılmayıb';
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

        private function maybe_import_legacy_accounts($force = false, $allow_duplicates = false)
        {
            $repair_mode = ($force === 'repair');
            if (!$force && get_option(self::LEGACY_IMPORT_OPTION_KEY)) {
                return array('inserted' => 0, 'inserted_sold' => 0, 'inserted_unsold' => 0, 'skipped' => 0, 'already_imported' => true, 'error' => '');
            }

            $legacy_rows = $this->load_legacy_account_rows();
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
            $formats = array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');
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
                if ($stock_status === 'Satılmayıb') {
                    $phone = '';
                    $customer_name = '';
                    $sale_date = '';
                }

                $price = isset($row['price']) ? (float) str_replace(',', '.', (string) $row['price']) : 0;
                if ($price < 0) {
                    $price = 0;
                }

                $normalized_game_name = $this->normalize_game_names($game_name);
                $data = array(
                    'game_name' => $normalized_game_name,
                    'account_type' => $account_type,
                    'email' => $email,
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
                            "SELECT COUNT(*) FROM {$table} WHERE email = %s AND account_type = %s AND game_name = %s AND stock_status = %s AND COALESCE(phone, '') = %s AND COALESCE(sale_date, '') = %s",
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
                $inserted++;
                if ($stock_status === 'Satılıb') {
                    $inserted_sold++;
                } else {
                    $inserted_unsold++;
                }
            }

            $db_counts = $this->get_database_status_counts();
            update_option(self::LEGACY_IMPORT_OPTION_KEY, array(
                'source' => 'PlayStation_hesablar_temizlenmis(1).pdf',
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
            $counts = array('total' => 0, 'sold' => 0, 'unsold' => 0, 'other' => 0);
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
            $this->create_or_update_table();
            $deleted = $wpdb->query("DELETE FROM {$table}");
            if ($deleted === false) {
                return false;
            }
            $wpdb->query("ALTER TABLE {$table} AUTO_INCREMENT = 1");
            delete_option(self::LEGACY_IMPORT_OPTION_KEY);
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
            if ($clear_before_import) {
                if (!$this->clear_account_database()) {
                    $this->redirect_with_message('error', 'settings', 'İmportdan əvvəl baza silinmədi.');
                }
            }

            $this->create_or_update_table();
            $result = $this->maybe_import_legacy_accounts(true, $clear_before_import);
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

            if (!$this->clear_account_database()) {
                $this->redirect_with_message('error', 'settings', 'Baza silinmədi.');
            }

            $this->create_or_update_table();
            $result = $this->maybe_import_legacy_accounts(true, true);
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
            $universal['created_at'] = $now;
            $universal['updated_at'] = $now;

            $inserted = $wpdb->insert(
                $table,
                $universal,
                array_merge($base_formats, array('%s', '%s'))
            );

            return $inserted !== false;
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
            $return_tab = isset($_POST['mara_return_tab']) ? sanitize_key(wp_unslash($_POST['mara_return_tab'])) : 'accounts';

            if (!empty($errors)) {
                $this->redirect_with_message('error', $return_tab, implode(' ', $errors));
            }

            $now = current_time('mysql');
            $base_formats = array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s');

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
                $this->redirect_with_message('updated', 'accounts');
            }

            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $inserted = $wpdb->insert($table, $data, array_merge($base_formats, array('%s', '%s')));
            if ($inserted === false) {
                $this->redirect_with_message('error', $return_tab, 'Yadda saxlanma zamanı xəta baş verdi.');
            }

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
            $deleted = $wpdb->delete($this->table_name(), array('id' => $id), array('%d'));
            if ($deleted === false) {
                $this->redirect_with_message('error', 'accounts', 'Silmə zamanı xəta baş verdi.');
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
            register_rest_route($namespace, '/delete', array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'rest_mobile_delete'),
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
            return array(
                'id' => isset($record['id']) ? (int) $record['id'] : 0,
                'game_name' => isset($record['game_name']) ? (string) $record['game_name'] : '',
                'account_type' => isset($record['account_type']) ? (string) $record['account_type'] : '',
                'email' => isset($record['email']) ? (string) $record['email'] : '',
                'price' => isset($record['price']) ? (float) $record['price'] : 0,
                'price_formatted' => $this->format_price(isset($record['price']) ? $record['price'] : 0),
                'console' => isset($record['console']) ? (string) $record['console'] : '',
                'customer_name' => isset($record['customer_name']) ? (string) $record['customer_name'] : '',
                'phone' => isset($record['phone']) ? (string) $record['phone'] : '',
                'sale_date' => isset($record['sale_date']) && $record['sale_date'] !== null ? (string) $record['sale_date'] : '',
                'payment_type' => isset($record['payment_type']) ? (string) $record['payment_type'] : '',
                'stock_status' => isset($record['stock_status']) ? $this->normalize_stock_status_value($record['stock_status']) : 'Satılmayıb',
                'created_at' => isset($record['created_at']) ? (string) $record['created_at'] : '',
                'updated_at' => isset($record['updated_at']) ? (string) $record['updated_at'] : '',
            );
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
            $allowed_stock = array('Satılıb', 'Satılmayıb');

            $game_name = $this->normalize_game_names(isset($input['game_name']) ? sanitize_text_field((string) $input['game_name']) : '');
            $account_type = isset($input['account_type']) ? sanitize_text_field((string) $input['account_type']) : '';
            $email = isset($input['email']) ? sanitize_email((string) $input['email']) : '';
            $price_raw = isset($input['price']) ? sanitize_text_field((string) $input['price']) : '';
            $console = isset($input['console']) ? sanitize_text_field((string) $input['console']) : '';
            $customer_name_raw = isset($input['customer_name']) ? sanitize_text_field((string) $input['customer_name']) : '';
            $phone_raw = isset($input['phone']) ? sanitize_text_field((string) $input['phone']) : '';
            $sale_date = isset($input['sale_date']) ? sanitize_text_field((string) $input['sale_date']) : '';
            $payment_type = isset($input['payment_type']) ? sanitize_text_field((string) $input['payment_type']) : '';
            $stock_status = isset($input['stock_status']) ? sanitize_text_field((string) $input['stock_status']) : '';

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
            if ($customer_name !== '' && !preg_match('/^[\p{L}\s]+$/u', $customer_name)) {
                $errors[] = 'Ad soyad yalnız hərflərdən və boşluqdan ibarət olmalıdır.';
            }

            $phone_error = '';
            $phone = $this->normalize_phone($phone_raw, $phone_error);
            if ($phone_raw !== '' && $phone === '' && $phone_error !== '') $errors[] = $phone_error;

            if ($stock_status === 'Satılıb') {
                if ($customer_name === '') $errors[] = 'Satılıb seçilərsə ad soyad məcburidir.';
                if ($phone === '') $errors[] = 'Satılıb seçilərsə telefon məcburidir.';
            }

            return array(
                'game_name' => $game_name,
                'account_type' => $account_type,
                'email' => $email,
                'price' => $price,
                'console' => $console,
                'customer_name' => $customer_name,
                'phone' => $phone,
                'sale_date' => $sale_date !== '' ? $sale_date : null,
                'payment_type' => $payment_type,
                'stock_status' => $stock_status,
            );
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
            $now = current_time('mysql');
            $base_formats = array('%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s');
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
                $auto_universal_created = $this->maybe_create_auto_universal_account($data, $base_formats, $now);
            }

            $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
            return rest_ensure_response(array(
                'ok' => true,
                'id' => $id,
                'record' => is_array($record) ? $this->mobile_record_payload($record) : null,
                'auto_universal_created' => $auto_universal_created ? 1 : 0,
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
            $deleted = $wpdb->delete($this->table_name(), array('id' => $id), array('%d'));
            if ($deleted === false) {
                return new WP_Error('mara_account_sale_mobile_delete_failed', 'Silmə zamanı xəta baş verdi.', array('status' => 500));
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
                'database_cleared' => 'Baza tam silindi. İndi PDF import edə bilərsən.',
                'pdf_imported' => 'PDF import edildi. Satılıb və Satılmayıb düzgün bölündü.',
                'pdf_reset_imported' => 'Baza silindi və PDF tam yükləndi. SQL saylarında Satılıb/Satılmayıb ayrıca görünməlidir.',
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

        private function render_panel($context = 'frontend')
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
            if (!in_array($active_tab, array('accounts', 'new', 'customers', 'sold', 'unsold', 'settings'), true)) {
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
                            <p>Satılmayıb seçilərsə müştəri məlumatları boş saxlanıla bilər.</p>
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


                <section class="mara-account-sale-section" data-mara-tab="settings">
                    <?php echo $this->render_settings($settings); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>

                <?php echo $this->render_stats($stats); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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
                array('Cəmi', $this->format_price($stats['total_amount'])),
                array('Nağd', $this->format_price($stats['cash_amount'])),
                array('Nisyə', $this->format_price($stats['credit_amount'])),
                array('Bugün', $stats['today_sold']),
                array('Bu ay', $stats['month_sold']),
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
                    <input id="mara-account-sale-global-search" type="search" placeholder="Oyun adı, e-mail, ad soyad, telefon, PS4/PS5, ödəniş və ya stok yaz..." data-global-search-input autocomplete="off">
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
                        <p>Oyun, müştəri, telefon, stok, ödəniş, tarix və qiymət üzrə süzgəc.</p>
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
                    <label>Ödəniş
                        <select data-filter-field="payment">
                            <option value="">Hamısı</option>
                            <option value="Nağd">Nağd</option>
                            <option value="Nisyə">Nisyə</option>
                        </select>
                    </label>
                    <label>Stok
                        <select data-filter-field="stock">
                            <option value="">Hamısı</option>
                            <option value="Satılıb">Satılıb</option>
                            <option value="Satılmayıb">Satılmayıb</option>
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
            $card_classes = 'mara-account-sale-card mara-account-sale-table-card';
            $table_classes = 'mara-account-sale-table';
            if ($is_accounts_table || $is_sold_table || $is_unsold_table) {
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
                                <th>Ödəniş</th>
                                <th>Stok</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($records)) : ?>
                            <tr class="mara-account-sale-empty-row"><td colspan="10">Məlumat yoxdur.</td></tr>
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
                                    data-payment="<?php echo esc_attr($record['payment_type']); ?>"
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
                                    <td data-label="Ödəniş"><?php echo $this->badge($record['payment_type'] === 'Nisyə' ? 'credit' : 'cash', $record['payment_type']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                    <td data-label="Stok"><?php echo $this->badge($this->normalize_stock_status_value($record['stock_status']) === 'Satılıb' ? 'sold' : 'unsold', $record['stock_status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
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
                'price' => '',
                'console' => 'PS5',
                'customer_name' => '',
                'phone' => '',
                'sale_date' => current_time('Y-m-d'),
                'payment_type' => $settings['default_payment_type'],
                'stock_status' => $settings['default_stock_status'],
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
                    <label>Ödəniş növü *
                        <select name="payment_type" required data-form-field="payment_type">
                            <option value="Nağd" <?php selected($record['payment_type'], 'Nağd'); ?>>Nağd</option>
                            <option value="Nisyə" <?php selected($record['payment_type'], 'Nisyə'); ?>>Nisyə</option>
                        </select>
                    </label>
                    <label>Stok *
                        <select name="stock_status" required data-form-field="stock_status" data-stock-select>
                            <option value="Satılıb" <?php selected($record['stock_status'], 'Satılıb'); ?>>Satılıb</option>
                            <option value="Satılmayıb" <?php selected($record['stock_status'], 'Satılmayıb'); ?>>Satılmayıb</option>
                        </select>
                    </label>
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
            <div class="mara-account-sale-card mara-account-sale-card-form">
                <div class="mara-account-sale-card-head">
                    <h2>Ayarlar</h2>
                    <p>Shortcode: <code>[ps_hesab_satisi]</code></p>
                </div>
                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="mara_account_sale_save_settings">
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
                        <label>Default ödəniş növü
                            <select name="default_payment_type">
                                <option value="Nağd" <?php selected($settings['default_payment_type'], 'Nağd'); ?>>Nağd</option>
                                <option value="Nisyə" <?php selected($settings['default_payment_type'], 'Nisyə'); ?>>Nisyə</option>
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

            <div class="mara-account-sale-card mara-account-sale-card-form" style="margin-top:16px;">
                <div class="mara-account-sale-card-head">
                    <h2>Mobil APK bağlantısı</h2>
                    <p>Marakana Mobile tətbiqində Admin → Hesab Satışı bölməsi bu REST API açarı ilə WordPress bazasına qoşulur.</p>
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

            <div class="mara-account-sale-card mara-account-sale-card-form" style="margin-top:16px;">
                <div class="mara-account-sale-card-head">
                    <h2>Baza və PDF import</h2>
                    <p>Köhnə PDF bazası: 726 hesab. Satılıb: 501, Satılmayıb: 225. On/Un/Off növləri Online/Universal/Offline kimi əlavə olunur.</p>
                    <p><strong>SQL cədvəli:</strong> <code><?php echo esc_html($table_name); ?></code></p>
                    <p><strong>Hazırkı SQL sayı:</strong> Cəmi <?php echo esc_html($db_counts['total']); ?> / Satılıb <?php echo esc_html($db_counts['sold']); ?> / Satılmayıb <?php echo esc_html($db_counts['unsold']); ?></p>
                    <?php if (is_array($last_import) && !empty($last_import)) : ?>
                        <p><strong>Son import:</strong> əlavə edildi <?php echo esc_html(isset($last_import['inserted']) ? (int) $last_import['inserted'] : 0); ?> — Satılıb <?php echo esc_html(isset($last_import['inserted_sold']) ? (int) $last_import['inserted_sold'] : 0); ?> / Satılmayıb <?php echo esc_html(isset($last_import['inserted_unsold']) ? (int) $last_import['inserted_unsold'] : 0); ?><?php echo !empty($last_import['last_error']) ? ' — SQL xəta: ' . esc_html($last_import['last_error']) : ''; ?></p>
                    <?php endif; ?>
                </div>

                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" onsubmit="return confirm('Baza tam silinsin və PDF sıfırdan tam yüklənsin?');">
                    <input type="hidden" name="action" value="mara_account_sale_reset_import_pdf">
                    <?php wp_nonce_field('mara_account_sale_reset_import_pdf', 'mara_account_sale_reset_import_pdf_nonce'); ?>
                    <div class="mara-account-sale-form-grid">
                        <label>PDF faylı seç
                            <input type="file" name="legacy_pdf_reset" accept="application/pdf,.pdf" required>
                        </label>
                    </div>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-primary">Bazanı sil + PDF tam yüklə</button>
                    </div>
                    <p class="mara-account-sale-help">Əsas istifadə olunacaq düymə budur. Əvvəl bütün hesab bazasını silir, sonra PDF bazanı tam yükləyir və təkrar sətirləri də saxlayır.</p>
                </form>

                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" onsubmit="return confirm('PDF içəri aktarılsın? Əgər aşağıdakı silmə seçimini işarələsən, əvvəlcə bütün hesab bazası silinəcək.');" style="margin-top:12px;">
                    <input type="hidden" name="action" value="mara_account_sale_import_pdf">
                    <?php wp_nonce_field('mara_account_sale_import_pdf', 'mara_account_sale_import_pdf_nonce'); ?>
                    <div class="mara-account-sale-form-grid">
                        <label>PDF faylı seç
                            <input type="file" name="legacy_pdf" accept="application/pdf,.pdf" required>
                        </label>
                        <label class="mara-account-sale-check-label">
                            <input type="checkbox" name="clear_before_import" value="1">
                            Əvvəl bazanı tam sil, sonra PDF bazanı yüklə
                        </label>
                    </div>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-primary">PDF içəri aktar</button>
                    </div>
                    <p class="mara-account-sale-help">PDF faylını seçib import et. Satılmayıb olanlarda müştəri, telefon və satış tarixi boş saxlanılır.</p>
                </form>

                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Diqqət! Bütün hesab bazası silinəcək. Davam edilsin?');" style="margin-top:12px;">
                    <input type="hidden" name="action" value="mara_account_sale_clear_database">
                    <?php wp_nonce_field('mara_account_sale_clear_database', 'mara_account_sale_clear_database_nonce'); ?>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-danger">Bazanı tam sil</button>
                    </div>
                    <p class="mara-account-sale-help">Bu düymə yalnız hesab satış bazasını silir. Plugin ayarları qalır.</p>
                </form>

                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Köhnə baza import edilsin? Mövcud hesablar təkrar yazılmayacaq.');" style="margin-top:12px;">
                    <input type="hidden" name="action" value="mara_account_sale_import_legacy">
                    <?php wp_nonce_field('mara_account_sale_import_legacy', 'mara_account_sale_import_legacy_nonce'); ?>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-outline">Hazır köhnə bazanı import et</button>
                    </div>
                    <p class="mara-account-sale-help">PDF seçmədən hazır köhnə bazanı əlavə etmək üçün istifadə et.</p>
                </form>

                <form class="mara-account-sale-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Səhv düşən köhnə baza silinib yenidən düzgün bölünsün? Satılıb/Satılmayıb CSV-yə görə yenilənəcək.');" style="margin-top:12px;">
                    <input type="hidden" name="action" value="mara_account_sale_repair_legacy">
                    <?php wp_nonce_field('mara_account_sale_repair_legacy', 'mara_account_sale_repair_legacy_nonce'); ?>
                    <div class="mara-account-sale-form-actions">
                        <button type="submit" class="mara-account-sale-btn mara-account-sale-btn-outline">Səhv importu düzəlt</button>
                    </div>
                    <p class="mara-account-sale-help">Əgər əvvəlki importda hamısı Satılıb kimi düşübsə və ya Satılmayıb siyahısı boşdursa, bu düyməni bas.</p>
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
