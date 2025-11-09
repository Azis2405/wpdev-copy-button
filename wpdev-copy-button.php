<?php
/**
 * Plugin Name:       WPDev Copy Button
 * Description:       Menambahkan tombol untuk menyalin teks dengan analitik pelacakan.
 * Version:           2.9.3
 * Author:            WP Developer
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpdev-copy-button
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// DEFINISIKAN VERSI UNTUK MANAJEMEN ASET
define('WPDEV_COPY_BUTTON_VERSION', '2.9.3');

register_activation_hook(__FILE__, 'wpdev_copy_button_activate');

final class WPDev_Copy_Button
{
    private static $instance;
    private array $options;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
        $this->options = get_option('wpdev_copy_options', $this->get_default_options());
        $this->load_dependencies();

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_plugin_settings']);
        add_action('admin_post_wpdev_delete_analytics', [$this, 'handle_delete_analytics_data']);
        
        add_action('admin_post_wpdev_export_csv', [$this, 'handle_export_csv']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        if (!empty($this->options['enable_plugin'])) {
            add_shortcode('tombol_salin', [$this, 'render_copy_button_shortcode']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
            add_action('wp_ajax_wpdev_track_copy', [$this, 'handle_ajax_tracking']);
            add_action('wp_ajax_nopriv_wpdev_track_copy', [$this, 'handle_ajax_tracking']);
        }
    }

    private function get_default_options(): array
    {
        return [
            'enable_plugin' => 'on',
            'success_duration' => 2000,
            'disable_on_copy' => 'on',
            'ignored_roles' => ['administrator' => 'on'],
        ];
    }

    private function load_dependencies(): void
    {
        require_once plugin_dir_path(__FILE__) . 'includes/class-wpdev-helpers.php';
        if (is_admin()) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-wpdev-analytics-table.php';
        }
    }

    public function enqueue_frontend_assets(): void
    {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css', [], '6.5.2');
        wp_enqueue_style('wpdev-copy-style', plugin_dir_url(__FILE__) . 'css/wpdev-copy-style.css', [], WPDEV_COPY_BUTTON_VERSION);
        wp_enqueue_script('wpdev-copy-handler', plugin_dir_url(__FILE__) . 'js/wpdev-copy-handler.js', [], WPDEV_COPY_BUTTON_VERSION, true);

        wp_localize_script('wpdev-copy-handler', 'wpdev_copy_settings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wpdev_copy_nonce'),
            'duration' => (int) $this->options['success_duration'],
            'disable_on_copy' => !empty($this->options['disable_on_copy']),
        ]);
    }
    
    public function enqueue_admin_assets(string $hook): void
    {
        $current_screen = get_current_screen();
        if (!$current_screen) return;
        
        if ($current_screen->id === 'toplevel_page_wpdev-copy-analytics') {
            wp_enqueue_style('wpdev-admin-style', plugin_dir_url(__FILE__) . 'css/wpdev-admin-style.css', [], WPDEV_COPY_BUTTON_VERSION);
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
            wp_enqueue_script('wpdev-admin-charts', plugin_dir_url(__FILE__) . 'js/wpdev-admin-charts.js', ['chart-js'], WPDEV_COPY_BUTTON_VERSION, true);
            
            $date_from = !empty($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
            $date_to = !empty($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

            wp_localize_script('wpdev-admin-charts', 'wpdev_chart_data', [
                'topPagesData'      => $this->prepare_data_for_top_pages_chart($date_from, $date_to),
                'topUserGroupsData' => $this->prepare_data_for_top_user_groups_chart($date_from, $date_to),
                'topTaxonomiesData' => $this->prepare_data_for_top_taxonomies_chart($date_from, $date_to),
                'deviceData'        => $this->prepare_data_for_device_bar_chart($date_from, $date_to),
            ]);
        }
        
        $settings_pages = ['wpdev-copy_page_wpdev-copy-settings', 'wpdev-copy_page_wpdev-copy-shortcodes'];
        if (in_array($current_screen->id, $settings_pages)) {
             wp_enqueue_style('wpdev-admin-style', plugin_dir_url(__FILE__) . 'css/wpdev-admin-style.css', [], WPDEV_COPY_BUTTON_VERSION);
            wp_enqueue_script('wpdev-copy-handler', plugin_dir_url(__FILE__) . 'js/wpdev-copy-handler.js', [], WPDEV_COPY_BUTTON_VERSION, true);
            wp_localize_script('wpdev-copy-handler', 'wpdev_copy_settings', [
                'duration' => 2000,
                'disable_on_copy' => true
            ]);
        }
    }

    public function add_admin_menu(): void
    {
        add_menu_page('WPDev Copy Analytics', 'WPDev Copy', 'manage_options', 'wpdev-copy-analytics', [$this, 'render_analytics_page'], 'dashicons-chart-bar', 25 );
        add_submenu_page('wpdev-copy-analytics', 'Statistics', 'Statistics', 'manage_options', 'wpdev-copy-analytics', [$this, 'render_analytics_page'] );
        add_submenu_page('wpdev-copy-analytics', 'Pengaturan Tombol Salin', 'Pengaturan', 'manage_options', 'wpdev-copy-settings', [$this, 'render_settings_page']);
        add_submenu_page('wpdev-copy-analytics', 'Shortcode', 'Shortcode', 'manage_options', 'wpdev-copy-shortcodes', [$this, 'render_shortcodes_page']);
    }

    public function register_plugin_settings(): void
    {
        register_setting('wpdev_copy_button_options_group', 'wpdev_copy_options', [$this, 'sanitize_settings']);
        add_settings_section('wpdev_general_section', 'Pengaturan Umum & Perilaku', null, 'wpdev-copy-settings');
        add_settings_field('enable_plugin', 'Aktifkan Plugin', [$this, 'render_field_checkbox'], 'wpdev-copy-settings', 'wpdev_general_section', ['id' => 'enable_plugin']);
        add_settings_field('success_duration', 'Durasi Status Sukses (ms)', [$this, 'render_field_number'], 'wpdev-copy-settings', 'wpdev_general_section', ['id' => 'success_duration']);
        add_settings_field('disable_on_copy', 'Nonaktifkan Tombol Setelah Salin', [$this, 'render_field_checkbox'], 'wpdev-copy-settings', 'wpdev_general_section', ['id' => 'disable_on_copy']);
        add_settings_section('wpdev_analytics_section', 'Pengaturan Analitik', null, 'wpdev-copy-settings');
        add_settings_field('ignored_roles', 'Abaikan Peran Pengguna', [$this, 'render_field_roles_multicheck'], 'wpdev-copy-settings', 'wpdev_analytics_section', ['id' => 'ignored_roles']);
        add_settings_section('wpdev_data_section', 'Manajemen Data', null, 'wpdev-copy-settings');
        add_settings_field('delete_data', 'Hapus Data Analitik', [$this, 'render_field_delete_button'], 'wpdev-copy-settings', 'wpdev_data_section');
    }

    public function render_settings_page(): void
    {
        ?>
        <div class="wrap">
            <h1>Pengaturan WPDev Copy Button</h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpdev_copy_button_options_group');
                // Render only general and analytics sections (not data management)
                $this->do_settings_sections_exclude('wpdev-copy-settings', ['wpdev_data_section']);
                submit_button();
                ?>
            </form>

            <!-- Data Management Section (outside form) -->
            <h2>Manajemen Data</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">Hapus Data Analitik</th>
                        <td><?php $this->render_field_delete_button(); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function do_settings_sections_exclude(string $page, array $exclude_sections = []): void
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (!isset($wp_settings_sections[$page])) {
            return;
        }

        foreach ((array) $wp_settings_sections[$page] as $section) {
            if (in_array($section['id'], $exclude_sections, true)) {
                continue;
            }

            if ($section['title']) {
                echo "<h2>{$section['title']}</h2>\n";
            }

            if ($section['callback']) {
                call_user_func($section['callback'], $section);
            }

            if (!isset($wp_settings_fields) || !isset($wp_settings_fields[$page]) || !isset($wp_settings_fields[$page][$section['id']])) {
                continue;
            }

            echo '<table class="form-table" role="presentation">';
            do_settings_fields($page, $section['id']);
            echo '</table>';
        }
    }
    
    public function render_shortcodes_page(): void
    {
        ?>
        <div class="wrap">
            <h1>Shortcode</h1>
            <div class="wpdev-shortcode-guide">
                <h2>Cara Menggunakan Shortcode</h2>
                <p>Gunakan shortcode di bawah ini di dalam editor postingan atau halaman Anda untuk menampilkan tombol salin. Pastikan elemen target Anda memiliki `id` yang sesuai.</p>
                
                <div class="shortcode-example">
                    <h3>Shortcode Dasar</h3>
                    <p>Menampilkan tombol salin standar yang menargetkan elemen dengan `id="contoh-id-1"`.</p>
                    <div class="shortcode-display">
                        <pre id="sc-1">[tombol_salin target_id="contoh-id-1"]</pre>
                        <button class="button wpdev-copy-btn" data-target-id="sc-1"><span class="wpdev-btn-text">Salin</span></button>
                    </div>
                </div>

                <div class="shortcode-example">
                    <h3>Shortcode dengan Teks Kustom</h3>
                    <p>Mengubah teks default tombol menjadi "Salin Kode Ini".</p>
                    <div class="shortcode-display">
                        <pre id="sc-2">[tombol_salin target_id="contoh-id-2" text="Salin Kode Ini"]</pre>
                        <button class="button wpdev-copy-btn" data-target-id="sc-2"><span class="wpdev-btn-text">Salin</span></button>
                    </div>
                </div>

                <div class="shortcode-example">
                    <h3>Shortcode dengan Ikon (Font Awesome)</h3>
                    <p>Menambahkan ikon di sebelah kiri teks. Pastikan tema Anda memuat Font Awesome.</p>
                    <div class="shortcode-display">
                        <pre id="sc-3">[tombol_salin target_id="contoh-id-3" text="Salin" icon="fa-solid fa-clipboard"]</pre>
                        <button class="button wpdev-copy-btn" data-target-id="sc-3"><span class="wpdev-btn-text">Salin</span></button>
                    </div>
                </div>
                
                <hr>
                
                <h3>Contoh Implementasi Lengkap</h3>
                <p>Berikut adalah contoh bagaimana Anda bisa menyusun HTML dan shortcode bersamaan di editor teks Anda.</p>
                
                <div class="shortcode-display">
                    <pre id="sc-4"><?php 
                    echo "\n";
                    echo esc_html('<p id="kutipan-penting">Ini adalah teks penting yang akan disalin oleh pengguna.</p>');
                    echo "\n\n";
                    echo "\n";
                    echo esc_html('[tombol_salin target_id="kutipan-penting" text="Salin Kutipan"]');
                    ?></pre>
                     <button class="button wpdev-copy-btn" data-target-id="sc-4"><span class="wpdev-btn-text">Salin</span></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_analytics_page(): void
    {
        $date_from = !empty($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = !empty($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search_target_id = !empty($_GET['s_target']) ? sanitize_text_field($_GET['s_target']) : '';
        $search_page_url = !empty($_GET['s_page']) ? sanitize_text_field($_GET['s_page']) : '';

        $export_url = add_query_arg([
            'action' => 'wpdev_export_csv',
            '_wpnonce' => wp_create_nonce('wpdev_export_nonce'),
            'date_from' => $date_from,
            'date_to' => $date_to,
            's_target' => $search_target_id,
            's_page' => $search_page_url,
        ], admin_url('admin-post.php'));

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">WPDEV COPY STATISTICS </h1>';
        printf('<a href="%s" class="page-title-action">Ekspor ke CSV</a>', esc_url($export_url));
        
        echo '<hr class="wp-header-end">';
        
        ?>
        <form method="get" class="wpdev-filter-form">
            <input type="hidden" name="page" value="wpdev-copy-analytics" />
            <input type="hidden" name="s_target" value="<?php echo esc_attr($search_target_id); ?>" />
            <input type="hidden" name="s_page" value="<?php echo esc_attr($search_page_url); ?>" />
            
            <div class="filter-item">
                <label for="date_from">Dari:</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>">
            </div>
            <div class="filter-item">
                <label for="date_to">Sampai:</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>">
            </div>
            <div class="filter-item">
                <?php submit_button('Filter Tanggal', 'primary', 'filter_action', false); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpdev-copy-analytics')); ?>" class="button">Reset Semua Filter</a>
            </div>
        </form>
        <?php

        echo '<div class="wpdev-charts-wrapper">';
        echo '  <div class="wpdev-chart-container"><canvas id="wpdevTopPagesChart"></canvas></div>';
        echo '  <div class="wpdev-chart-container"><canvas id="wpdevTopTaxonomiesChart"></canvas></div>';
        echo '  <div class="wpdev-chart-container"><canvas id="wpdevTopUserGroupsChart"></canvas></div>';
        echo '  <div class="wpdev-chart-container"><canvas id="wpdevDeviceChart"></canvas></div>';
        echo '</div>';

        $analytics_table = new WPDev_Analytics_Table([
            'date_from' => $date_from, 
            'date_to' => $date_to,
            'search_target_id' => $search_target_id,
            'search_page_url' => $search_page_url,
        ]);
        $analytics_table->prepare_items();
        $analytics_table->display();
        echo '</div>';
    }

    public function handle_export_csv(): void
    {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpdev_export_nonce')) {
            wp_die('Aksi tidak valid atau nonce salah.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Anda tidak memiliki izin untuk melakukan aksi ini.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        
        $date_from = !empty($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $date_to = !empty($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search_target_id = !empty($_GET['s_target']) ? sanitize_text_field($_GET['s_target']) : '';
        $search_page_url = !empty($_GET['s_page']) ? sanitize_text_field($_GET['s_page']) : '';
        
        $where_clauses = [];
        $params = [];
        
        if (!empty($date_from) && $this->validate_date($date_from)) {
            $where_clauses[] = 'DATE(time) >= %s';
            $params[] = $date_from;
        }
        if (!empty($date_to) && $this->validate_date($date_to)) {
            $where_clauses[] = 'DATE(time) <= %s';
            $params[] = $date_to;
        }
        if (!empty($search_target_id)) {
            $where_clauses[] = 'target_id LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search_target_id) . '%';
        }
        if (!empty($search_page_url)) {
            $where_clauses[] = 'page_url LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search_page_url) . '%';
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY time DESC";
        $data = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);

        $filename = 'wpdev-copy-analytics-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        $output = fopen('php://output', 'w');

        fputcsv($output, [
            'Waktu', 'Elemen (Target ID)', 'Halaman Sumber', 'Jenis Konten', 
            'Kategori/Taksonomi', 'User', 'Grup Pengguna', 'IP Hash', 'Perangkat', 'OS', 'User Agent'
        ]);
        
        if (!empty($data)) {
            $post_id_cache = [];

            foreach ($data as $row) {
                $post_id = 0;
                if (!isset($post_id_cache[$row['page_url']])) {
                    $post_id_cache[$row['page_url']] = url_to_postid($row['page_url']);
                }
                $post_id = $post_id_cache[$row['page_url']];
                
                $post_type_label = 'Non-Singular';
                $terms_list = 'N/A';

                if ($post_id > 0) {
                    $post_type_obj = get_post_type_object(get_post_type($post_id));
                    if($post_type_obj) $post_type_label = $post_type_obj->labels->singular_name;
                    
                    $taxonomies = get_object_taxonomies(get_post_type($post_id));
                    $all_terms = [];
                    foreach ($taxonomies as $taxonomy) {
                        $terms = get_the_terms($post_id, $taxonomy);
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $all_terms = array_merge($all_terms, wp_list_pluck($terms, 'name'));
                        }
                    }
                    if (!empty($all_terms)) $terms_list = implode(', ', array_unique($all_terms));
                }

                $device = 'Desktop';
                if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $row['user_agent'])) $device = 'Tablet';
                elseif (preg_match('/(mobi|ipod|iphone|kindle|opera mini|blackberry|palm)/i', $row['user_agent'])) $device = 'Mobile';
                
                fputcsv($output, [
                    $row['time'],
                    $row['target_id'],
                    $row['page_url'],
                    $post_type_label,
                    $terms_list,
                    $row['user_email'],
                    $row['user_group'],
                    $row['user_ip_hash'],
                    $device,
                    $row['operating_system'],
                    $row['user_agent'],
                ]);
            }
        }

        fclose($output);
        exit;
    }
    
    public function render_field_checkbox(array $args): void {
        $id = $args['id'];
        $checked = !empty($this->options[$id]) ? 'checked' : '';
        printf('<label for="%s"><input type="checkbox" id="%s" name="wpdev_copy_options[%s]" %s /> Aktif</label>', esc_attr($id), esc_attr($id), esc_attr($id), $checked);
    }

    public function render_field_number(array $args): void {
        $id = $args['id'];
        $value = $this->options[$id] ?? 2000;
        printf('<input type="number" id="%s" name="wpdev_copy_options[%s]" value="%d" class="small-text" />', esc_attr($id), esc_attr($id), (int)$value);
    }

    public function render_field_roles_multicheck(array $args): void {
        $id = $args['id'];
        $saved_roles = $this->options[$id] ?? [];
        $all_roles = wp_roles()->get_names();
        
        foreach ($all_roles as $role => $name) {
            $checked = isset($saved_roles[$role]) ? 'checked' : '';
            printf(
                '<label style="display: block; margin-bottom: 5px;"><input type="checkbox" name="wpdev_copy_options[%s][%s]" %s /> %s</label>',
                esc_attr($id), esc_attr($role), $checked, esc_html($name)
            );
        }
        echo '<p class="description">Tindakan dari peran pengguna yang dicentang tidak akan dilacak.</p>';
    }

    public function render_field_delete_button(): void {
        $delete_url = add_query_arg([
            'action' => 'wpdev_delete_analytics',
            '_wpnonce' => wp_create_nonce('wpdev_delete_analytics_nonce')
        ], admin_url('admin-post.php'));
        
        printf(
            '<a href="%s" class="button button-danger" onclick="return confirm(\'PERINGATAN: Anda akan menghapus semua data analitik. Tindakan ini tidak dapat diurungkan. Lanjutkan?\');">Hapus Semua Data Analitik</a>',
            esc_url($delete_url)
        );
        echo '<p class="description">Gunakan tombol ini untuk menghapus seluruh catatan dari tabel analitik plugin.</p>';
    }

    public function sanitize_settings(array $input): array
    {
        $new_input = [];
        $defaults = $this->get_default_options();

        $new_input['enable_plugin'] = isset($input['enable_plugin']) ? 'on' : '';
        $new_input['disable_on_copy'] = isset($input['disable_on_copy']) ? 'on' : '';
        $new_input['success_duration'] = isset($input['success_duration']) ? absint($input['success_duration']) : $defaults['success_duration'];
        
        $new_input['ignored_roles'] = [];
        if (!empty($input['ignored_roles']) && is_array($input['ignored_roles'])) {
            foreach (array_keys($input['ignored_roles']) as $role) {
                $new_input['ignored_roles'][sanitize_key($role)] = 'on';
            }
        }
        
        return $new_input;
    }

    public function handle_delete_analytics_data(): void
    {
        // Verify nonce -gunakan $_REQUEST untuk lebih robust
        $nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : '';

        if (empty($nonce) || !wp_verify_nonce($nonce, 'wpdev_delete_analytics_nonce')) {
            wp_die('Aksi tidak valid atau nonce salah. Silakan refresh halaman dan coba lagi.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Anda tidak memiliki izin untuk melakukan aksi ini.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        $wpdb->query("TRUNCATE TABLE {$table_name}");

        add_settings_error('wpdev_copy_options', 'data_deleted', 'Semua data analitik berhasil dihapus.', 'success');
        set_transient('settings_errors', get_settings_errors(), 30);

        wp_safe_redirect(admin_url('admin.php?page=wpdev-copy-settings&settings-updated=true'));
        exit;
    }
    
    public function render_copy_button_shortcode(array $atts): string
    {
        $attributes = shortcode_atts(['target_id' => '', 'text' => 'Salin', 'icon' => ''], $atts, 'tombol_salin');
        $target_id = sanitize_html_class($attributes['target_id']);
        if (empty($target_id)) { return ''; }
        $button_text = esc_attr($attributes['text']);
        $icon_class = esc_attr($attributes['icon']);
        $icon_html = !empty($icon_class) ? sprintf('<i class="%s"></i>', $icon_class) : '';
        return sprintf(
            '<button class="wpdev-copy-btn" data-target-id="%s" data-original-text="%s">%s<span class="wpdev-btn-text">%s</span></button>',
            esc_attr($target_id), $button_text, $icon_html, $button_text
        );
    }
    
    private function validate_date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    private function get_date_where_clause(string $date_from, string $date_to): array
    {
        $where_clauses = [];
        $params = [];

        if (!empty($date_from) && $this->validate_date($date_from)) {
            $where_clauses[] = 'DATE(time) >= %s';
            $params[] = $date_from;
        }

        if (!empty($date_to) && $this->validate_date($date_to)) {
            $where_clauses[] = 'DATE(time) <= %s';
            $params[] = $date_to;
        }

        if (empty($where_clauses)) {
            return ['', []];
        }

        return ['WHERE ' . implode(' AND ', $where_clauses), $params];
    }
    
    private function prepare_data_for_device_bar_chart(string $date_from = '', string $date_to = ''): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        [$where_clause, $params] = $this->get_date_where_clause($date_from, $date_to);
        
        $query = "SELECT user_agent FROM {$table_name} {$where_clause}";
        $results = $wpdb->get_results($wpdb->prepare($query, $params), ARRAY_A);
        
        $counts = ['Desktop' => 0, 'Tablet' => 0, 'Mobile' => 0];

        foreach ($results as $row) {
            $user_agent = $row['user_agent'];
            if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $user_agent)) {
                $counts['Tablet']++;
            } elseif (preg_match('/(mobi|ipod|iphone|kindle|opera mini|blackberry|palm|windows ce|netfront|fennec|hiptop|phone|samsung|htc|lg|motorola|nokia)/i', $user_agent)) {
                $counts['Mobile']++;
            } else {
                $counts['Desktop']++;
            }
        }
        return ['labels' => array_keys($counts), 'values' => array_values($counts)];
    }
    
    private function prepare_data_for_top_pages_chart(string $date_from = '', string $date_to = ''): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        [$where_clause, $params] = $this->get_date_where_clause($date_from, $date_to);
        
        $query = "SELECT page_url, COUNT(id) as count FROM {$table_name} {$where_clause} GROUP BY page_url ORDER BY count DESC LIMIT 10";
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        $labels = [];
        $values = [];
        $full_paths = [];

        foreach ($results as $row) {
            $page_url = $row->page_url;

            $url_path = (string) parse_url($page_url, PHP_URL_PATH);
            $full_path = trim($url_path, '/');
            if (empty($full_path)) {
                $full_path = 'Homepage';
            }
            $full_paths[] = $full_path;

            $label = $full_path;
            if (mb_strlen($label) > 20) {
                $label = mb_substr($label, 0, 20) . '...';
            }

            $labels[] = $label;
            $values[] = (int)$row->count;
        }
        
        return ['labels' => $labels, 'values' => $values, 'full_paths' => $full_paths];
    }
    
    // --- PERUBAHAN DI FUNGSI INI ---
    private function prepare_data_for_top_user_groups_chart(string $date_from = '', string $date_to = ''): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        [$where_clause, $params] = $this->get_date_where_clause($date_from, $date_to);
        
        $base_query = "SELECT user_group, COUNT(id) as count FROM {$table_name}";
        
        $conditions = "user_group IS NOT NULL AND user_group != 'N/A' AND user_group != ''";
        if (!empty($where_clause)) {
            $where_clause_stripped = substr($where_clause, 6);
            $conditions = "({$where_clause_stripped}) AND " . $conditions;
        }

        $query = "{$base_query} WHERE {$conditions} GROUP BY user_group ORDER BY count DESC LIMIT 10";
        $results = $wpdb->get_results($wpdb->prepare($query, $params));

        $labels = [];      // Untuk label yang dipotong
        $values = [];
        $full_labels = []; // Untuk label versi lengkap

        foreach ($results as $row) {
            $full_label = $row->user_group;
            $full_labels[] = $full_label;

            // Logika untuk memotong label jika lebih dari 10 karakter
            $label = $full_label;
            if (mb_strlen($label) > 10) {
                $label = mb_substr($label, 0, 10) . '...';
            }
            $labels[] = $label;
            
            $values[] = (int)$row->count;
        }
        
        // Kembalikan ketiga array agar bisa digunakan di JavaScript
        return ['labels' => $labels, 'values' => $values, 'full_labels' => $full_labels];
    }

    private function prepare_data_for_top_taxonomies_chart(string $date_from = '', string $date_to = ''): array
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        [$where_clause, $params] = $this->get_date_where_clause($date_from, $date_to);

        $query = "SELECT page_url FROM {$table_name} {$where_clause}";
        $results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        if (empty($results)) {
            return ['labels' => [], 'values' => []];
        }
        $term_counts = [];
        $post_id_cache = [];
        foreach ($results as $row) {
            $url = $row->page_url;
            if (!isset($post_id_cache[$url])) {
                $post_id_cache[$url] = url_to_postid($url);
            }
            $post_id = $post_id_cache[$url];
            if ($post_id === 0) continue;
            $taxonomies = get_object_taxonomies(get_post_type($post_id));
            foreach ($taxonomies as $taxonomy) {
                $terms = get_the_terms($post_id, $taxonomy);
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if (!isset($term_counts[$term->name])) {
                            $term_counts[$term->name] = 0;
                        }
                        $term_counts[$term->name]++;
                    }
                }
            }
        }
        arsort($term_counts);
        $top_terms = array_slice($term_counts, 0, 10, true);
        return [
            'labels' => array_keys($top_terms),
            'values' => array_values($top_terms)
        ];
    }
    
    public function handle_ajax_tracking(): void
    {
        if (empty($this->options['enable_plugin'])) { wp_die(); }

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_roles = (array) $user->roles;
            $ignored_roles = $this->options['ignored_roles'] ?? [];
            foreach ($user_roles as $role) {
                if (isset($ignored_roles[$role])) {
                    wp_die();
                }
            }
        }

        check_ajax_referer('wpdev_copy_nonce', 'nonce');
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
        
        $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip_hash = hash('sha256', $user_ip);
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $operating_system = WPDev_Helpers::get_os_from_user_agent($user_agent);
        $user_email = 'Guest';
        $user_group = 'N/A';

        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            if ($current_user instanceof WP_User) {
                $user_email = $current_user->user_email;
                $sejoli_group = WPDev_Helpers::get_sejoli_user_group($current_user->ID);
                if ($sejoli_group !== null) {
                    $user_group = $sejoli_group['name'];
                }
            }
        }

        $target_id = isset($_POST['target_id']) ? sanitize_text_field($_POST['target_id']) : '';
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';

        $wpdb->insert($table_name, [ 
            'time' => current_time('mysql'), 
            'target_id' => $target_id, 
            'page_url' => $page_url, 
            'user_email' => $user_email, 
            'user_ip_hash' => $ip_hash, 
            'user_agent' => $user_agent, 
            'user_group' => $user_group,
            'operating_system' => $operating_system 
        ]);

        wp_die();
    }
}

function wpdev_copy_button_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpdev_copy_analytics';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        target_id varchar(255) NOT NULL,
        page_url text NOT NULL,
        user_email varchar(255) NOT NULL,
        user_ip_hash varchar(64) NOT NULL,
        user_agent text NOT NULL,
        user_group varchar(255) DEFAULT 'N/A' NOT NULL,
        operating_system VARCHAR(100) DEFAULT 'Unknown' NOT NULL,
        PRIMARY KEY  (id),
        KEY time (time),
        KEY target_id (target_id(191)),
        KEY operating_system (operating_system)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (false === get_option('wpdev_copy_options')) {
        $default_options = [
            'enable_plugin' => 'on',
            'success_duration' => 2000,
            'disable_on_copy' => 'on',
            'ignored_roles' => ['administrator' => 'on'],
        ];
        add_option('wpdev_copy_options', $default_options);
    }
}

WPDev_Copy_Button::get_instance();