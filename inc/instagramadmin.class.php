<?php
/**
 * WP Custom Instagram Admin Class
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/* Check if Class Exists. */
if (! class_exists('InstagramAdmin')) {
    class InstagramAdmin
    {
        public $name;
        public $prefix;
        public $title;
        public $slug;

        public function __construct()
        {
            $this->name   = 'instagram';
            $this->prefix = 'instagram_';
            $this->title  = 'Instagram Feed';
            $this->slug   = str_replace('_', '-', $this->name);
            if (is_admin()) { // admin actions
                add_action('admin_menu', [$this, 'instagram_add_admin_page']); // Corrected function name
                add_action('admin_init', [$this, 'instagram_add_custom_settings']); // Corrected function name
                // Create a handler for the AJAX toolbar requests.
                add_action('wp_ajax_feed_delete_cache', [$this, 'delete_cache']); // Made the method non-static
            }
        }

        public function instagram_add_admin_page()
        {
            add_menu_page(
                __($this->title, 'textdomain'),
                __($this->title, 'textdomain'),
                'manage_options',
                $this->slug,
                [$this, 'instagram_render_settings_page'], // Corrected function name
                'dashicons-instagram',
                50
            );
        }

        public function instagram_add_custom_settings()
        {
            add_settings_section(
                $this->prefix . 'settings',
                '',
                [$this, 'instagram_settings_callback'],
                $this->slug . '-api-settings'
            );
            register_setting(
                $this->prefix . 'instagram_settings',
                $this->prefix . 'insta_api_keys'
            );
        }

        public function instagram_settings_callback()
        {
            settings_fields($this->prefix . 'instagram_settings');
            $this->instagram_api_settings_callback();
        }

        public function instagram_api_settings_callback()
        {
            $insta_api_options = get_option($this->prefix . 'insta_api_keys');
            ?>
<p>
  <input type="button" name="wipe-feed-cache" id="wipe-feed-cache" class="button button-primary" value="Clear Instagram Cache">
  <?php if (! empty($insta_api_options['hash']) && ! empty($insta_api_options['json'])) { ?>
  <a href="/wp-admin/admin.php?page=instagram&action=populate"><input type="button" name="get-instagram" id="get-instagram" class="button button-primary" value="Get Latest Instagram"></a>
  <?php } else { ?>
  <a href="https://www.instagram.com/<?php echo esc_attr($insta_api_options['hash']); ?>?__a=1" target="_blank"><input type="button" name="json-instagram" id="json-instagram" class="button button-primary" value="Get JSON"></a>
  <?php } ?>
</p>
<div id="wipe-message"></div>
<hr>
<h2>Instagram API Settings</h2>
<div class="form-wrap" style="max-width: 500px;">
  <div class="form-field">
    <label style="display:inline" for="<?php echo esc_attr($this->prefix); ?>insta_api_keys[enable]">Enable Instagram </label>
    <input name="<?php echo esc_attr($this->prefix); ?>insta_api_keys[enable]" type="checkbox" <?php echo (isset($insta_api_options['enable']) && $insta_api_options['enable']) ? 'checked' : ''; ?> />
  </div>
  <div class="form-field">
    <label for="<?php echo esc_attr($this->prefix); ?>insta_api_keys[hash]">Instagram Hash Tag</label>
    <input name="<?php echo esc_attr($this->prefix); ?>insta_api_keys[hash]" type="text" value="<?php echo esc_attr($insta_api_options['hash']); ?>" />
  </div>
  <div class="form-field">
    <label for="<?php echo esc_attr($this->prefix); ?>insta_api_keys[limit]">Instagram Limit</label>
    <input name="<?php echo esc_attr($this->prefix); ?>insta_api_keys[limit]" type="text" value="<?php echo esc_attr($insta_api_options['limit']); ?>" />
  </div>
  <div class="form-field">
    <label for="<?php echo esc_attr($this->prefix); ?>insta_api_keys[likes]">Instagram Likes</label>
    <input name="<?php echo esc_attr($this->prefix); ?>insta_api_keys[likes]" type="text" value="<?php echo esc_attr($insta_api_options['likes']); ?>" />
  </div>
  <div class="form-field">
    <label for="<?php echo esc_attr($this->prefix); ?>insta_api_keys[json]">Instagram JSON Required</label>
    <textarea name="<?php echo esc_attr($this->prefix); ?>insta_api_keys[json]"><?php echo (! empty($insta_api_options['json'])) ? esc_textarea($insta_api_options['json']) : ''; ?></textarea>
  </div>
  <input id="action-instagram" name="action_instagram" type="hidden" value="" />
</div>
<?php
        }

        public function instagram_render_settings_page()
        {
            ?>
<div class="wrap">
  <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
  <form method="post" action="options.php">
    <?php
                    do_settings_sections($this->slug . '-api-settings');
            submit_button('Save Instagram Settings', 'primary', 'submit');
            ?>
  </form>
  <hr>
</div>
<hr>
<?php
            $listTable = new Instagram_List_Table();
            $_count    = $listTable->prepare_items();
            ?>
<h1><?php echo esc_html(get_admin_page_title()); ?></h1>
<div class="wrap">
  <form id="page-filter" method="post">
    <?php $listTable->display(); ?>
  </form>
</div>
<?php
        }

        // Handle Delete Cache Table
        public function delete_cache()
        {
            global $wpdb;
            if (current_user_can('manage_options')) {
                $table_name = $wpdb->prefix . 'instagram_feeds';
                $querystr   = $wpdb->prepare('TRUNCATE TABLE %s', $table_name);
                $wpdb->query($querystr);
                $this->wipe_directory();
                $insta_api_options         = get_option($this->prefix . 'insta_api_keys');
                $insta_api_options['json'] = '';
                update_option($this->prefix . 'insta_api_keys', $insta_api_options);
            }
            wp_die();
        }

        // Handle Delete Directory
        private function wipe_directory()
        {
            $filepath = ABSPATH . '/wp-content/uploads/instagram/*';
            $files    = glob($filepath);
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    if (! class_exists('WP_List_Table')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }

    class Instagram_List_Table extends WP_List_Table
    {
        public $prefix;
        public $table;

        public function __construct()
        {
            global $wpdb, $status, $page;
            $this->table = $wpdb->prefix . 'instagram_feeds';
            // Set parent defaults
            parent::__construct([
                'singular'  => 'instagram',     // singular name of the listed records
                'plural'    => 'instagrams',    // plural name of the listed records
                'ajax'      => false,        // does this table support ajax?
            ]);
        }

        public function process_bulk_action()
        {
            global $wpdb;
            $action = $this->current_action();
            if ('disable' == $action && ! empty($_REQUEST['cb_action'])) {
                foreach ($_REQUEST['cb_action'] as $post_id) {
                    $wpdb->update($this->table, ['status' => 1], ['id' => $post_id], ['%d'], ['%d']);
                }
            } elseif ('enable' == $action && ! empty($_REQUEST['cb_action'])) {
                foreach ($_REQUEST['cb_action'] as $post_id) {
                    $wpdb->update($this->table, ['status' => 0], ['id' => $post_id], ['%d'], ['%d']);
                }
            } elseif ('delete' == $action && ! empty($_REQUEST['cb_action'])) {
                foreach ($_REQUEST['cb_action'] as $post_id) {
                    $wpdb->delete($this->table, ['id' => $post_id], ['%d']);
                }
            }
        }

        public function column_default($item, $column_name)
        {
            switch ($column_name) {
                case 'pubdate':
                    return date_i18n(get_option('date_format'), $item->{$column_name});
                case 'caption':
                    return wp_trim_words($item->{$column_name}, 25);
                case 'image':
                    return '<img style="width: 90px; height: auto;" src="' . esc_url($item->{$column_name}) . '"/>';
                case 'link':
                    return '<a href="' . esc_url($item->{$column_name}) . '" target="_blank">' . esc_url($item->{$column_name}) . '</a>';
                case 'status':
                    return (1 == $item->{$column_name}) ? '<span style="color: red;">Disabled</span>' : '<span style="color: green;">Enabled</span>';
                default:
                    return $item->{$column_name};
            }
        }

        public function get_bulk_actions()
        {
            $actions = [
                'disable'   => __('Disable'),
                'enable'    => __('Enable'),
                'delete'    => __('Delete'),
            ];

            return $actions;
        }

        public function column_cb($item)
        {
            return sprintf(
                '<input type="checkbox" name="cb_action[]" value="%s" />',
                $item->id
            );
        }

        public function get_columns()
        {
            $columns = [
                'cb'              => '<input type="checkbox" />',
                'image'           => 'Image',
                'pubdate'         => 'Date',
                'instagram_id'    => 'Instagram ID',
                'link'            => 'link',
                'count'           => 'Comments',
                'count2'          => 'Likes',
                'caption'         => 'Caption',
                'status'          => 'Status',
            ];

            return $columns;
        }

        public function get_sortable_columns()
        {
            $sortable_columns = [
                'pubdate'      => ['pubdate', false],
                'status'       => ['status', false],
            ];

            return $sortable_columns;
        }

        public function prepare_items()
        {
            global $wpdb;
            $per_page = 30;
            $columns  = $this->get_columns();
            $hidden   = $dataQry = [];
            $query    = '';
            $this->process_bulk_action();
            $sortable              = $this->get_sortable_columns();
            $this->_column_headers = [$columns, $hidden, $sortable];
            $searchcol             = [
                'instagram_id',
            ];
            if (! empty($_POST['s'])) {
                foreach ($searchcol as $col) {
                    $dataQry[] = $wpdb->prepare('%s LIKE %s', $col, '%' . trim($_POST['s']) . '%');
                }
                $query = ' WHERE (' . implode(' OR ', $dataQry) . ')';
            }
            $querystr   = $wpdb->prepare('SELECT * FROM %s', $this->table);
            $data       = $wpdb->get_results($querystr);
            usort($data, 'insta_usort_reorder');
            if (! empty($data)) {
                $current_page = $this->get_pagenum();
                $total_items  = count($data);
                $data         = array_slice($data, (($current_page - 1) * $per_page), $per_page);
                $this->items  = $data;
                $this->set_pagination_args([
                    'total_items' => $total_items,                  // WE have to calculate the total number of items
                    'per_page'    => $per_page,                     // WE have to determine how many items to show on a page
                    'total_pages' => ceil($total_items / $per_page),   // WE have to calculate the total number of pages
                ]);

                return $total_items;
            }

            return null;
        }
    }
}

function insta_usort_reorder($a, $b)
{
    $orderby = (! empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'pubdate'; // If no sort, default to title
    $order   = (! empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'desc'; // If no order, default to asc
    $result  = strcmp($a->{$orderby}, $b->{$orderby}); // Determine sort order

    return ('asc' === $order) ? $result : -$result; // Send final sort direction to usort
}
?>