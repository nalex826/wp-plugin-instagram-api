<?php
/**
 * WP Custom Instagrams Class
 */
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/* Check if Class Exists. */
if (! class_exists('Instagrams')) {
    class Instagrams
    {
        // Class properties
        public $hash  = '';
        public $json  = '';
        public $limit = 10;

        // Method to load Instagram feed
        public function load_instagram_feed()
        {
            $data          = [];
            $results_array = json_decode($this->json, true);

            // Check if the JSON data is valid
            if (! empty($results_array['graphql'])) {
                // Loop through the JSON data
                for ($i = $this->limit; $i >= 0; $i--) {
                    if (array_key_exists($i, $results_array['graphql']['user']['edge_owner_to_timeline_media']['edges'])) {
                        $latest_array = $results_array['graphql']['user']['edge_owner_to_timeline_media']['edges'][$i]['node'];
                        $url          = $this->saveImg($latest_array['thumbnail_src'], $latest_array['id']);

                        // Prepare data for database insertion
                        $newPosting = [
                            'pubdate'      => $latest_array['taken_at_timestamp'],
                            'instagram_id' => $latest_array['id'],
                            'link'         => 'https://www.instagram.com/p/' . $latest_array['shortcode'],
                            'caption'      => (! empty($latest_array['title'])) ? $latest_array['title'] : '',
                            'image'        => $url,
                            'count'        => $latest_array['edge_media_to_comment']['count'],
                            'count2'       => (isset($latest_array['video_view_count'])) ? $latest_array['video_view_count'] : $latest_array['edge_liked_by']['count'],
                            'status'       => 0,
                        ];
                        array_push($data, $newPosting);
                    }
                }

                // Write data to the database
                $this->insta_write($data);

                // Display success notice
                add_action('admin_notices', function ($messages) {
                    ?>
<div class="notice notice-success">
  <p>Instagram Feed has been processed.</p>
</div>
<?php
                });
            } else {
                // Display error notice
                add_action('admin_notices', function ($messages) {
                    ?>
<div class="notice notice-error">
  <p>Instagram Feed failed to be processed.</p>
</div>
<?php
                });
            }
        }

        // Method to save image locally
        private function saveImg($img, $id)
        {
            $parts    = explode('?', $img);
            $pathinfo = pathinfo($parts[0]);
            $url      = $id . '.' . $pathinfo['extension'];
            $folder   = ABSPATH . '/wp-content/uploads/instagram';
            if (! is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
            $filepath = $folder . '/' . $url;
            $image    = imagecreatefromjpeg($img);
            imagejpeg($image, $filepath, 75);
            imagedestroy($image);

            return '/wp-content/uploads/instagram/' . $url;
        }

        // Method to insert data into the database
        private function insta_write($data = null)
        {
            global $wpdb;
            if (empty($data)) {
                return null;
            }
            $table_name = $wpdb->prefix . 'instagram_feeds';
            foreach ($data as $entry) {
                // Use replace method to handle duplicate entries
                $wpdb->replace($table_name, $entry, ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d']);
            }

            return 'passed';
        }
    }
}
?>