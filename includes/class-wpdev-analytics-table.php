<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WPDev_Analytics_Table extends WP_List_Table
{
    private array $post_id_cache = [];
    private string $date_from = '';
    private string $date_to = '';
    private string $search_target_id = '';
    private string $search_page_url = '';

    public function __construct(array $args = [])
    {
        parent::__construct(['singular' => 'Salinan', 'plural' => 'Salinan', 'ajax' => false]);
        
        $this->date_from = !empty($args['date_from']) ? sanitize_text_field($args['date_from']) : '';
        $this->date_to = !empty($args['date_to']) ? sanitize_text_field($args['date_to']) : '';
        $this->search_target_id = !empty($args['search_target_id']) ? sanitize_text_field($args['search_target_id']) : '';
        $this->search_page_url = !empty($args['search_page_url']) ? sanitize_text_field($args['search_page_url']) : '';
    }

    protected function display_tablenav($which)
    {
        if ('top' === $which) {
            ?>
            <div class="tablenav top">
                <form method="get" class="wpdev-table-search-form">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
                    <input type="hidden" name="date_from" value="<?php echo esc_attr($this->date_from); ?>" />
                    <input type="hidden" name="date_to" value="<?php echo esc_attr($this->date_to); ?>" />
                    
                    <p class="search-box">
                        <label class="screen-reader-text" for="wpdev-search-target-input">Cari Target ID:</label>
                        <input type="search" id="wpdev-search-target-input" name="s_target" value="<?php echo esc_attr($this->search_target_id); ?>" placeholder="Cari Target ID...">
                        
                        <label class="screen-reader-text" for="wpdev-search-page-input">Cari URL Halaman:</label>
                        <input type="search" id="wpdev-search-page-input" name="s_page" value="<?php echo esc_attr($this->search_page_url); ?>" placeholder="Cari URL Halaman...">
                        
                        <input type="submit" id="search-submit" class="button" value="Cari">
                    </p>
                </form>
                <?php $this->pagination($which); ?>
                <br class="clear">
            </div>
            <?php
        } else {
            parent::display_tablenav($which);
        }
    }

    // --- PERUBAHAN DI FUNGSI INI ---
    public function get_columns(): array
    {
        return [
            'time'          => 'Waktu',
            'target_id'     => 'Elemen (Target ID)',
            'page_url'      => 'Halaman Sumber',
            'post_type'     => 'Jenis Konten',
            'user_email'    => 'User',
            'user_ip_hash'  => 'IP Hash', // Dipindahkan ke sebelah kanan User
            'user_group'    => 'Grup Pengguna',
            'user_agent'    => 'Perangkat',
            'operating_system' => 'OS',
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'time'       => ['time', true],
            'target_id'  => ['target_id', false],
            'user_email' => ['user_email', false],
            'user_group' => ['user_group', false],
            'operating_system' => ['operating_system', false]
        ];
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'time':
                return date_i18n(get_option('date_format') . ' H:i:s', strtotime($item['time']));
                
            case 'target_id':
                return '<code>' . esc_html($item['target_id']) . '</code>';
            
            case 'page_url':
                $url_path = (string) parse_url($item['page_url'], PHP_URL_PATH);
                $display_text = trim($url_path, '/');
                if (empty($display_text)) {
                    $display_text = 'Homepage';
                }
                $query_string = (string) parse_url($item['page_url'], PHP_URL_QUERY);
                if (!empty($query_string)) {
                    $display_text .= '?' . $query_string;
                }
                return '<a href="' . esc_url($item['page_url']) . '" target="_blank" title="' . esc_attr($item['page_url']) . '">' . esc_html($display_text) . '</a>';
            
            case 'post_type':
                return $this->get_content_type_label($item['page_url']);

            case 'user_email':
                if ($item['user_email'] === 'Guest') return '<em>Guest</em>';
                return '<a href="mailto:' . esc_attr($item['user_email']) . '">' . esc_html($item['user_email']) . '</a>';
            
            case 'user_group':
                if (empty($item['user_group']) || $item['user_group'] === 'N/A') return '<em>N/A</em>';
                return '<strong>' . esc_html($item['user_group']) . '</strong>';

            case 'user_ip_hash':
                return '<span title="' . esc_attr($item['user_ip_hash']) . '">' . substr($item['user_ip_hash'], 0, 12) . '...</span>';
            
            case 'user_agent':
                return $this->get_device_icon($item['user_agent']);

            case 'operating_system':
                return '<strong>' . esc_html($item['operating_system']) . '</strong>';
            
            default:
                return print_r($item, true);
        }
    }

    private function validate_date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    public function prepare_items()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpdev_copy_analytics';

        $per_page = 50;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $where_clauses = [];
        $params = [];

        if (!empty($this->date_from) && $this->validate_date($this->date_from)) {
            $where_clauses[] = 'DATE(time) >= %s';
            $params[] = $this->date_from;
        }
        if (!empty($this->date_to) && $this->validate_date($this->date_to)) {
            $where_clauses[] = 'DATE(time) <= %s';
            $params[] = $this->date_to;
        }
        if (!empty($this->search_target_id)) {
            $where_clauses[] = 'target_id LIKE %s';
            $params[] = '%' . $wpdb->esc_like($this->search_target_id) . '%';
        }
        if (!empty($this->search_page_url)) {
            $where_clauses[] = 'page_url LIKE %s';
            $params[] = '%' . $wpdb->esc_like($this->search_page_url) . '%';
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $total_items_query = "SELECT COUNT(id) FROM {$table_name} {$where_sql}";
        $total_items = (int) $wpdb->get_var($wpdb->prepare($total_items_query, $params));
        
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;
        
        $data_params = $params;
        $data_params[] = $per_page;
        $data_params[] = $offset;

        $orderby = isset($_REQUEST['orderby']) && in_array($_REQUEST['orderby'], array_keys($this->get_sortable_columns())) ? sanitize_key($_REQUEST['orderby']) : 'time';
        $order = isset($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), ['ASC', 'DESC']) ? strtoupper(sanitize_key($_REQUEST['order'])) : 'DESC';

        $data_query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $this->items = $wpdb->get_results($wpdb->prepare($data_query, $data_params), ARRAY_A);
    }

    private function get_post_id_from_url(string $url): int
    {
        if (isset($this->post_id_cache[$url])) {
            return $this->post_id_cache[$url];
        }
        $post_id = url_to_postid($url);
        $this->post_id_cache[$url] = $post_id;
        return $post_id;
    }

    private function get_content_type_label(string $page_url): string
    {
        $post_id = $this->get_post_id_from_url($page_url);
        if ($post_id > 0) {
            $post_type_object = get_post_type_object(get_post_type($post_id));
            if ($post_type_object) {
                return esc_html($post_type_object->labels->singular_name);
            }
        }
        return '<em>Non-Singular</em>';
    }

    private function get_device_icon(string $user_agent): string
    {
        $device = 'Desktop';
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $user_agent)) {
            $device = 'Tablet';
        } elseif (preg_match('/(mobi|ipod|iphone|kindle|opera mini|blackberry|palm|windows ce|netfront|fennec|hiptop|phone|samsung|htc|lg|motorola|nokia)/i', $user_agent)) {
            $device = 'Mobile';
        }
        $icon_class = 'dashicons dashicons-desktop';
        if ($device === 'Tablet') {
            $icon_class = 'dashicons dashicons-tablet';
        } elseif ($device === 'Mobile') {
            $icon_class = 'dashicons dashicons-smartphone';
        }
        return '<span title="' . esc_attr($user_agent) . '"><i class="' . $icon_class . '"></i> ' . esc_html($device) . '</span>';
    }
}