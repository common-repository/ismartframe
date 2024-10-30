<?php

/**
 *
 * @package   ismartframe
 * @link      https://ismartframe.com
 * @license   GPL-2.0-or-later
 *
 * Plugin Name: iSmartFrame
 * Plugin URI:  https://ismartframe.com
 * Description: Official Wordpress plugin to manage iSmartFrame cache.
 * Version:     1.2
 * Requires at least: 5.6
 * Requires PHP: 7.2
 * Author:      iSmartFrame
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ismartframe
 *
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Define a global constant for logging
define('ISFWP_PLUGIN_LOGGING_ENABLED', false);

class ISFWP_ISmartFrame {

    protected $api_url;
    protected $api_key;
    protected $purge_timeout;
    protected $last_purge;
    protected $plugin_url;
    protected $plugin_version;
    protected $debug;

    public function __construct() {
        /**
         *
         * Initialize plugin check
         *
         */

        //Get the plugin wp global data
        if (!function_exists('get_plugin_data') || !function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        //Set Plugin URL
        $this->plugin_url = plugin_dir_url(__FILE__);
        $plugin_data = get_plugin_data(__FILE__);
        $this->plugin_version = $plugin_data['Version'];
        $this->purge_timeout = 5; //minutes
        $this->last_purge = null;

        //Get options from Isf Wordpress page
        $options = get_option('isfwp_ismartframe_settings');

        $this->api_url = "https://app.ismartframe.com/api/v1/cache/purge";

        if (is_array($options)) {
            $this->debug = $options['debug'] ?? false;
            $this->api_key = $options['api_key'] ?? '';
            $this->last_purge = $options['last_purge'] ?? null;
        } else {
            $this->api_key = '';
            $this->debug = false;
            $this->last_purge = null;
        }

        /**
         *
         * Enqueue function
         *
         */

        // Hook into post updates
        add_action('wp_after_insert_post', [$this, 'isfwp_trigger_save_post'], 10, 3);

        // Hook into taxonomy term changes (creation, edit, delete)
        // add_action('created_term', [$this, 'isfwp_trigger_purge_on_taxonomy_change'], 10, 3);
        add_action('edited_term', [$this, 'isfwp_trigger_purge_on_taxonomy_change'], 10, 3);
        add_action('delete_term', [$this, 'isfwp_trigger_purge_on_taxonomy_change'], 10, 3);

        add_action('init', [$this, 'isfwp_enqueue_assets']);

        add_action('wp_ajax_isfwp_verify_api_key', [$this, 'isfwp_verify_api_key']);
        add_action('wp_ajax_isfwp_purge_by_pattern', [$this, 'isfwp_purge_by_pattern']);
        add_action('wp_ajax_isfwp_purge_by_url', [$this, 'isfwp_purge_by_url']);
        add_action('admin_bar_menu', [$this, 'isfwp_custom_admin_menu_bar'], 1000);
        add_action('enqueue_block_editor_assets', [$this, 'isfwp_add_purge_by_url_button_gutenberg']);
        add_action('add_meta_boxes', [$this, 'isfwp_add_purge_by_url_button_classic']);
        add_action('wp_ajax_isfwp_purge_by_url_edit', [$this, 'isfwp_purge_by_url_edit']);
    }

    /**
     *
     * Function called when the plugin is unistalled. Clear the plugin settings.
     *
     */
    public static function isfwp_plugin_uninstall() {
        // Clear plugin options when uninstall
        isfwp_write_log('[DEBUG] Plugin uninstalled, clearing plugin settings...');
        delete_option('isfwp_ismartframe_settings');
    }

    /**
     *
     * Add new voice 'Purge cache' in Admin Bar
     *
     */
    function isfwp_custom_admin_menu_bar() {
        global $wp_admin_bar;

        if (!is_super_admin() || !is_admin_bar_showing() || is_admin()) return;
        // Add Parent Menu
        $argsParent = array(
            'id' => 'purgeCacheByUrl',
            'title' => '<span class="ab-icon dashicons dashicons-performance"></span> Purge from cache this page',
            'href' => false
        );
        $wp_admin_bar->add_menu($argsParent);
    }

    /**
     *
     * Add new voice 'Purge cache' in Admin Bar
     *
     */
    function isfwp_add_purge_by_url_button_gutenberg() {

        if (!in_array($GLOBALS['pagenow'], array('post.php'))) {
            return;
        }
        wp_enqueue_script(
            'my-plugin-custom-button',
            plugin_dir_url(__FILE__) . 'assets/js/purgeUrlButton.js',
            array('wp-plugins', 'wp-blocks', 'wp-element', 'wp-edit-post', 'wp-data'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/purgeUrlButton.js'),
            true
        );

        // Localize the script with translated strings
        wp_localize_script('my-plugin-custom-button', 'isfwp_object_strings', [
            'purge_from_cache' => __('Purge from cache this page', 'ismartframe'),
            'confirm_purge_message' => __('Are you sure you want to purge cache for this page?', 'ismartframe'),
            'yes' => __('Yes', 'ismartframe'),
        ]);
    }

    function isfwp_add_purge_by_url_button_classic() {
        if (!in_array($GLOBALS['pagenow'], array('post.php'))) {
            return;
        }
        add_meta_box(
            'my_custom_meta_box_id',                // Unique ID
            'Purge from cache this page',           // Box title
            [$this, 'isfwp_purge_by_url_metabox_html'],   // Content callback, must be of type callable
            'post',                                 // Post type
            'side',                                 // Context (where the box appears)
            'default',                              // Priority (default is 'default')
            array('__back_compat_meta_box' => true)
        );
    }

    function isfwp_purge_by_url_metabox_html() {
        global $post; // Access global post object

        $post_id = $post->ID; // Retrieve the current post ID
    ?>
        <div class="message"></div>
        <p><?php echo esc_html(__('Are you sure you want to purge from cache this page?', 'ismartframe')); ?></p>
        <button id="purge-cache-by-url-edit-classic" class="button" data-post-id="<?php echo esc_attr($post_id); ?>">
            <?php echo esc_html(__('Yes', 'ismartframe')); ?>
        </button>
        <div class="spinner"></div>
<?php
    }

    /**
     *
     * Isf plugin trigger log
     *
     * @param $operationType
     * @param $result
     * @param $object
     * @param $userId
     * @param $username
     * @param $isError
     * @return void
     */
    private function isf_log($operationType, $result, $object, $userId = null, $username = null, $isError = false) {
        // Format the message to include all required details
        $status = $isError ? 'KO' : 'OK';
        $code = $isError ? ($result['http_code'] ?? 'Unknown Error Code') : '200';
        $message = $isError ? ($result['message'] ?? 'Unknown error occurred.') : '';

        $userId = $userId ?: 'N/A';
        $username = $username ?: 'N/A';

        $log_entry = sprintf(
            "[%s] Operation: %s | Status: %s (Code: %s)%s | User ID: %s (%s) | Object: %s",
            gmdate('Y-m-d H:i:s'),
            $operationType,
            $status,
            $code,
            $message ? " | Message: $message" : '',
            $userId,
            $username,
            wp_json_encode($object, JSON_UNESCAPED_SLASHES)
        );

        // Set the log file path
        $filename = plugin_dir_path(__DIR__) . 'ismartframe/ismartframe_activity.log';

        // Initialize the WP_Filesystem
        global $wp_filesystem;
        if (! function_exists('WP_Filesystem')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }

        if (WP_Filesystem()) {
            // Read existing content
            $existing_content = '';
            if ($wp_filesystem->exists($filename)) {
                $existing_content = $wp_filesystem->get_contents($filename);
            }

            // Prepend the new log entry
            $new_content = $log_entry . "\n" . $existing_content;

            // Write the updated content back to the file
            $wp_filesystem->put_contents($filename, $new_content, FS_CHMOD_FILE);
        } else {
            error_log('WP_Filesystem initialization failed.');
        }
    }


    /**
     *
     * Trigger purge by tag at save/update post
     *
     * @param $new
     * @param $old
     * @param $post
     * @return void
     */
    public function isfwp_trigger_save_post($post_id, $post, $update) {
        // Initial Logging
        isfwp_write_log('[DEBUG] isfwp_trigger_save_post');
        // isfwp_write_log("[DEBUG] New status: $new_status, Old status: $old_status, Post ID: {$post->ID}");
        isfwp_write_log("[DEBUG] Post ID: {$post_id}, Post Type: {$post->post_type}");

        if (!$update) {
            // This is a new post, not an update
            // isfwp_write_log('[DEBUG] Skipping, not an update!');
            return;
        }

        // Check if the post is a revision, draft, or auto-save
        if (wp_is_post_revision($post->ID) || wp_is_post_autosave($post->ID) || $post->post_status === 'draft') {
            // isfwp_write_log('[DEBUG] Skipping revision, draft, or auto-save');
            return;
        }

        // Limit to specific post statuses
        if (!in_array($post->post_status, ['publish', 'future']) || $post->post_status === 'trash') {
            // isfwp_write_log('[DEBUG] Skipping not publish, future, ors trash');
            return;
        }

        // Get all post types and filter out unwanted types (like 'attachment')
        $supported_types = array_diff(get_post_types(), ['attachment']);

        // Check if the post type is supported
        if (!in_array($post->post_type, $supported_types)) {
            // isfwp_write_log('[DEBUG] Unsupported post type: ' . $post->post_type);
            return;
        }

        // Check if the status change warrants a purge
        if (!in_array($post->post_status, ['publish', 'future']) || $post->post_status === 'trash') {
            // isfwp_write_log('[DEBUG] No purge required for this status change');
            return;
        }

        try {
            if (!empty($this->api_key)) {
                isfwp_write_log("[DEBUG] Initializing Purge by Tag on post update, Post ID: {$post->ID}");

                // Collect tags (post ID, category IDs, taxonomy IDs)
                $object_ids = ['p' . $post->ID];
                $object_ids = array_merge($object_ids, isfwp_get_category_and_taxonomy_ids($post->ID));
                isfwp_write_log('[DEBUG] Tags to purge: ' . wp_json_encode($object_ids));

                // Prepare log data
                $operationType = 'TAG';
                $currentUserId = get_current_user_id();
                $currentUserName = wp_get_current_user()->display_name;

                // Prepare request body
                $data = ['tag' => $object_ids];

                // API URL
                $api_key = sanitize_text_field($this->api_key);
                $apiUrl = esc_url_raw($this->api_url . "/tag?api_key={$api_key}");

                // Send purge request
                $response = $this->make_curl_request($apiUrl, $data);

                if ($response['error']) {
                    isfwp_write_log('[DEBUG] ERROR CURL RESPONSE');
                    isfwp_write_log('API Response: ' . wp_json_encode($response));

                    // LOG RESULT
                    $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName, true);
                } else {
                    isfwp_write_log('[DEBUG] SUCCESS CURL RESPONSE');
                    isfwp_write_log('[DEBUG] API Response: ' . wp_json_encode($response));
                    isfwp_write_log('[INFO] Tag purge requested successfully');

                    // LOG RESULT
                    $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName);
                }

                isfwp_write_log('Purge operation complete.');
            }
        } catch (Exception $e) {
            isfwp_write_log('[ERROR] Exception: ' . $e->getMessage());
        }
    }

    /**
     *
     * Trigger purge by tag at save/update taaxonomy
     *
     * @param $term_id
     * @param $tt_id
     * @param $taxonomy
     * @return void
     */
    public function isfwp_trigger_purge_on_taxonomy_change($term_id, $tt_id, $taxonomy) {
        // Log the event
        isfwp_write_log("[DEBUG] Taxonomy changed: $taxonomy, Term ID: $term_id");

        try {
            if (!empty($this->api_key)) {
                isfwp_write_log('[DEBUG] Initializing Purge by Tag for Taxonomy');

                // Determine the appropriate prefix based on the taxonomy name
                if ($taxonomy === 'category') {
                    $prefix = 'c';
                } elseif ($taxonomy === 'post_tag') {
                    $prefix = 't';
                } else {
                    $prefix = $taxonomy[0];
                }

                // Collect the tag based on the term ID
                $object_ids = [$prefix . $term_id];
                isfwp_write_log('[DEBUG] Tags to purge: ' . wp_json_encode($object_ids));

                // Prepare log data
                $operationType = 'TAG';
                $currentUserId = get_current_user_id();
                $currentUserName = wp_get_current_user()->display_name;

                // Prepare request body
                $data = ['tag' => $object_ids];

                // API URL
                $apiUrl = $this->api_url . "/tag?api_key={$this->api_key}";

                // Send purge request
                $response = $this->make_curl_request($apiUrl, $data);

                if ($response['error']) {
                    isfwp_write_log('[DEBUG] ERROR CURL RESPONSE');
                    isfwp_write_log('API Response: ' . wp_json_encode($response));

                    // LOG RESULT
                    $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName, true);
                } else {
                    isfwp_write_log('[DEBUG] SUCCESS CURL RESPONSE');
                    isfwp_write_log('[DEBUG] API Response: ' . wp_json_encode($response));
                    isfwp_write_log('[INFO] Tag purge requested successfully');

                    // LOG RESULT
                    $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName);
                }
                isfwp_write_log('Purge operation complete.');
            }
        } catch (Exception $e) {
            isfwp_write_log('[ERROR] Exception: ' . $e->getMessage());
        }
    }

    public function isfwp_enqueue_assets() {
        wp_enqueue_style('isf-wordpress-plugin-style', plugins_url('assets/css/style.css', __FILE__), [], $this->plugin_version);
        wp_enqueue_script('isf-script', plugins_url('assets/js/script.js', __FILE__), ['jquery'], $this->plugin_version, true);

        $strings = [
            // API KEY
            'no_api_key' => __('Please enter an API key to continue.', 'ismartframe'),
            'api_key_verified' => __('API key successfully verified and saved.', 'ismartframe'),
            'error_validating_key' => __('Error validating API key. Please check your API key and try again.', 'ismartframe'),
            'error_validating_key_retry' => __('Error validating API key. Please retry again later.', 'ismartframe'),
            // PURGE CACHE
            'cache_purged_success' => __('Cache successfully purged for this page.', 'ismartframe'),
            'cache_purge_failed' => __('Cache purged unsuccessfully, please try again later.', 'ismartframe'),
            'error_purging_cache' => __('Error purging cache for this page. Please check your API key and try again later.', 'ismartframe'),
            'rate_limit_exceeded' => __('Error purging cache for this page. Rate limit exceeded. Please try again later.', 'ismartframe'),
            'general_purge_error' => __('Error purging cache for this page. Please retry again later.', 'ismartframe'),
            // PURGE BY PATTERN
            // Translators: %s is the domain name for which the cache purge was requested.
            'cache_pattern_purged_success' => __('Purge cache successfully requested for domain <b>%s</b>. <br>This operation may take a few minutes to propagate across all data centers.', 'ismartframe'),
            'error_clearing_cache' => __('Error clearing cache. Please retry again later.', 'ismartframe'),
            'invalid_data_error' => __('Error clearing cache. The given data was invalid.', 'ismartframe'),
            'invalid_regex_error' => __('Error clearing cache. The regex pattern is not valid.', 'ismartframe'),
            // Translators: %1$s is the number of minutes, %2$s is the timestamp of the last cache reset request.
            'wait_for_cache_reset' => __('Please wait <b>%1$s minutes</b> before requesting a new cache reset. <br> Last cache reset request: %2$s', 'ismartframe'),
        ];

        wp_localize_script('isf-script', 'isfwp_object', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('isfwp_nonce'),
            'strings' => $strings
        ]);
    }

    private function make_curl_request($url, $data) {
        // Set up the arguments for the POST request
        $args = [
            'body'        => wp_json_encode($data),
            'headers'     => [
                'Content-Type' => 'application/json',
            ],
            'method'      => 'POST',
            'data_format' => 'body',
            'sslverify' => false,
        ];

        // Make the HTTP POST request using wp_remote_post
        $response = wp_remote_post($url, $args);

        // Log the response (for debugging purposes)
        isfwp_write_log(wp_json_encode($response));

        // Check for errors in the request
        if (is_wp_error($response)) {
            return [
                'error' => true,
                'message' => 'HTTP request error: ' . $response->get_error_message(),
                'http_code' => wp_remote_retrieve_response_code($response)
            ];
        }

        // Get the response body and HTTP status code
        $response_body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);

        // Decode the JSON response
        $response_data = json_decode($response_body, true);

        // Check if the request was successful and if the expected data exists
        if ($http_code != 200 || !isset($response_data['result']) || $response_data['result'] !== true) {
            return [
                'error' => true,
                'message' => $response_data['error']['message'] ?? 'Unknown error.',
                'http_code' => $http_code
            ];
        }

        // Return the success response
        return ['error' => false, 'data' => $response_data];
    }

    function isfwp_verify_api_key() {

        isfwp_write_log('[DEBUG] Verify API');

        if (!check_ajax_referer('isfwp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 400);
            wp_die();
        }

        try {

            if (!isset($_POST['api_key']) || $_POST['api_key'] == '') {
                isfwp_write_log('[DEBUG] Missing API KEY parameter');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
            }

            $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
            $data = ['url' => [
                "/testApiKey"
            ]];

            // Prepare log data
            $operationType = 'APIKEY';
            $currentUserId = get_current_user_id();
            $currentUserName = wp_get_current_user()->display_name;

            isfwp_write_log('[DEBUG] api key: ' . $api_key);

            $apiUrl = $this->api_url . "?api_key={$api_key}";

            $response = $this->make_curl_request($apiUrl, $data);

            if ($response['error'] || !isset($response['data']['domain'])) {
                isfwp_write_log('[DEBUG] ERROR CURL RESPONSE');

                // LOG RESULT
                $this->isf_log($operationType, $response, [], $currentUserId, $currentUserName, true);

                wp_send_json_error(['message' => $response['message']], $response['http_code'] ?? 500);
            } else {
                isfwp_write_log('[DEBUG] SUCCESS CURL RESPONSE');
                isfwp_write_log($response['data']);

                $siteDomain = wp_parse_url(get_site_url(), PHP_URL_HOST);
                $responseDomain = $response['data']['domain'];

                $checkDomain = $siteDomain === $responseDomain;
                isfwp_write_log($checkDomain ? "[DEBUG] DOMAIN $siteDomain MATCH" : "[DEBUG] DOMAIN $siteDomain NOT MATCH");

                if ($checkDomain) {
                    // Get the current options
                    $existing_options = get_option('isfwp_ismartframe_settings');

                    // Update api_key option
                    $existing_options['api_key'] = $api_key;

                    // Save the updated options
                    update_option('isfwp_ismartframe_settings', $existing_options);

                    // LOG RESULT
                    $this->isf_log($operationType, $response, [], $currentUserId, $currentUserName);

                    wp_send_json_success(['checkDomain' => $checkDomain]);
                }

                // wp_send_json_success(['checkDomain' => false]);
                wp_send_json_error([
                    'message' => 'Domain not match.',
                ], 401);
            }
        } catch (Exception $e) {
            isfwp_write_log('[ERROR] Exception: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'An error occurred while checking API key. Please try again later.',
            ], 500);
        }

        wp_die();
    }

    function isfwp_purge_by_pattern() {

        isfwp_write_log('[DEBUG] isfwp_purge_by_pattern');

        if (!check_ajax_referer('isfwp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 400);
            wp_die();
        }

        try {

            if (empty($this->api_key)) {
                isfwp_write_log('[DEBUG] Missing parameters');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
            }

            // Check purge timeout
            $remaining_time = $this->purge_remaining_time();

            if ($remaining_time > 0) {
                $last_purge = $this->last_purge;
                $remaining_minutes = ceil($remaining_time / 60);
                $formatted_last_purge = ($last_purge) ? gmdate('Y-m-d H:i', $last_purge) : null;
                wp_send_json_error([
                    'message' => 'Purge cooldown in progress',
                    'last_purge' => $formatted_last_purge,
                    'remaining_minutes' => $remaining_minutes,
                ], 429);

                wp_die();
            }

            $api_key = sanitize_text_field($this->api_key);
            $pattern = "(.*?)";
            $data = ['pattern' => $pattern];

            isfwp_write_log('[DEBUG] Pattern: ' . $pattern);

            // Prepare log data
            $operationType = 'PATTERN';
            $currentUserId = get_current_user_id();
            $currentUserName = wp_get_current_user()->display_name;

            $apiUrl = $this->api_url . "/pattern?api_key={$api_key}";

            $response = $this->make_curl_request($apiUrl, $data);

            if ($response['error']) {
                isfwp_write_log('[DEBUG] ERROR CURL RESPONSE');

                // LOG RESULT
                $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName, true);

                wp_send_json_error(['message' => $response['message']], $response['http_code'] ?? 500);
            } else {
                isfwp_write_log('[DEBUG] SUCCESS CURL RESPONSE');
                $this->last_purge = time();
                // update_option('my_plugin_last_purge', $this->last_purge);

                $existing_options = get_option('isfwp_ismartframe_settings');

                // Update api_key option
                $existing_options['last_purge'] = $this->last_purge;

                // Save the updated options
                update_option('isfwp_ismartframe_settings', $existing_options);

                isfwp_write_log($response['data']);

                // LOG RESULT
                $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName);

                wp_send_json_success($response['data']);
            }
        } catch (Exception $e) {
            isfwp_write_log('[ERROR] Exception: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'An error occurred while checking API key. Please try again later.',
            ], 500);
        }

        wp_die();
    }

    function isfwp_purge_by_url() {
        isfwp_write_log('[DEBUG] isfwp_purge_by_url');

        if (!check_ajax_referer('isfwp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 400);
            wp_die();
        }

        try {

            // Check if all data needed are present
            if (empty($this->api_key)) {
                isfwp_write_log('[DEBUG] Missing parameters');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
            }

            // Check if 'urls_to_purge' is provided and sanitize it
            if (!isset($_POST['urls_to_purge'])) {
                isfwp_write_log('[DEBUG] Missing data: field "urls_to_purge" is missing');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
                wp_die();
            }

            // Sanitize and validate 'urls_to_purge'
            if (isset($_POST['urls_to_purge']) && is_array($_POST['urls_to_purge'])) {
                $urls_to_purge = array_map('esc_url_raw', wp_unslash($_POST['urls_to_purge']));
            } else {
                isfwp_write_log('[DEBUG] Missing or invalid data: Expected array for urls_to_purge');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
                wp_die();
            }

            if (empty($urls_to_purge)) {
                isfwp_write_log('[DEBUG] Missing data: No URL to purge');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
                wp_die();
            }

            $data = ['url' => []];
            $urls = [];

            // Sanitize API KEY
            $api_key = sanitize_text_field($this->api_key);

            // Site base URL
            $base_url = get_site_url();

            foreach ($urls_to_purge as $url_to_purge) {
                // Get the current URL
                $url = strval($url_to_purge);

                // Remove the base URL from the current URL
                $url_part = str_replace($base_url, '', $url);

                // Add url to data to purge
                $urls[] = strval($url_part);
            }

            $data = ['url' => $urls];

            isfwp_write_log('[DEBUG] URLs to purge:');
            isfwp_write_log(wp_json_encode($data));

            // Prepare log data
            $operationType = 'URL';
            $currentUserId = get_current_user_id();
            $currentUserName = wp_get_current_user()->display_name;

            $apiUrl = esc_url_raw($this->api_url . "?api_key={$api_key}");

            $response = $this->make_curl_request($apiUrl, $data);

            if ($response['error']) {
                isfwp_write_log('[DEBUG] ERROR CURL RESPONSE');
                isfwp_write_log(['message' => $response['message']], $response['http_code'] ?? 500);

                // LOG RESULT
                $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName, true);

                wp_send_json_error(['message' => $response['message']], $response['http_code'] ?? 500);
            } else {
                isfwp_write_log('[DEBUG] SUCCESS CURL RESPONSE');
                isfwp_write_log($response['data']);

                // LOG RESULT
                $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName);

                wp_send_json_success($response['data']);
            }

            isfwp_write_log('[DEBUG] URL purged successfully');
        } catch (Exception $e) {
            isfwp_write_log('[ERROR] Exception: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'An error occurred while checking API key. Please try again later.',
            ], 500);
        }

        wp_die();
    }

    function isfwp_purge_by_url_edit() {
        isfwp_write_log('[DEBUG] isfwp_purge_by_url_edit');

        if (!check_ajax_referer('isfwp_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Nonce verification failed.'], 400);
            wp_die();
        }

        try {

            // Check if all data needed are present
            if (empty($this->api_key)) {
                isfwp_write_log('[DEBUG] Missing parameters');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
                wp_die();
            }

            // Sanitize and validate post ID
            $post_id = isset($_POST['post_id']) ? sanitize_text_field(wp_unslash($_POST['post_id'])) : '';
            if (empty($post_id) || !is_numeric($post_id)) {
                isfwp_write_log('[DEBUG] Missing or invalid post ID');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
                wp_die();
            }

            isfwp_write_log('POST: ' . $post_id);

            $permalink = get_permalink($post_id);

            if (!$permalink) {
                isfwp_write_log('[DEBUG] Missing page');
                wp_send_json_error(['message' => 'Invalid request.'], 400);
                wp_die();
            }

            $urls = [];
            $api_key = sanitize_text_field($this->api_key);
            $base_url = get_site_url();
            $url_to_purge = str_replace($base_url, '', $permalink);
            $urls[] = $url_to_purge;

            $data = ['url' => $urls];

            isfwp_write_log('[DEBUG] URLs to purge:');
            isfwp_write_log($data);

            // Prepare log data
            $operationType = 'URL';
            $currentUserId = get_current_user_id();
            $currentUserName = wp_get_current_user()->display_name;

            $apiUrl = esc_url_raw($this->api_url . "?api_key={$api_key}");

            $response = $this->make_curl_request($apiUrl, $data);

            if ($response['error']) {
                isfwp_write_log('[DEBUG] ERROR CURL RESPONSE');
                isfwp_write_log(['message' => $response['message']], $response['http_code'] ?? 500);

                // LOG RESULT
                $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName, true);

                wp_send_json_error(['message' => $response['message']], $response['http_code'] ?? 500);
            } else {
                isfwp_write_log('[DEBUG] SUCCESS CURL RESPONSE');
                isfwp_write_log($response['data']);

                // LOG RESULT
                $this->isf_log($operationType, $response, $data, $currentUserId, $currentUserName);

                wp_send_json_success($response['data']);
            }

            isfwp_write_log('[DEBUG] URL purged successfully');
        } catch (Exception $e) {
            isfwp_write_log('[ERROR] Exception: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'An error occurred while checking API key. Please try again later.',
            ], 500);
        }

        wp_die();
    }

    function purge_remaining_time() {
        if ($this->last_purge === null) {
            return false;
        }

        $cooldown_time = $this->purge_timeout * 60;
        $current_time = time();

        $remaining_time = $cooldown_time - ($current_time - $this->last_purge);

        return $remaining_time;
    }
}

// Register the uninstall hook
register_uninstall_hook(__FILE__, ['ISFWP_ISmartFrame', 'isfwp_plugin_uninstall']);

// Schedule the event if it hasn't been scheduled already
function isfwp_schedule_log_cleanup() {
    if (!wp_next_scheduled('isfwp_log_cleanup')) {
        wp_schedule_event(time(), 'hourly', 'isfwp_log_cleanup');
    }
}
add_action('wp', 'isfwp_schedule_log_cleanup');

// Clear the event on plugin deactivation
function isfwp_clear_scheduled_log_cleanup() {
    $timestamp = wp_next_scheduled('isfwp_log_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'isfwp_log_cleanup');
    }
}
register_deactivation_hook(__FILE__, 'isfwp_clear_scheduled_log_cleanup');

function isfwp_log_cleanup() {
    isfwp_clean_old_log_entries();
}

// Hook the cron job event to the cleanup function
add_action('isfwp_log_cleanup', 'isfwp_log_cleanup');

function isfwp_clean_old_log_entries() {
    isfwp_write_log('[DEBUG] CRON - Cleaning old log entries...');

    // Set the log file path
    $filename = plugin_dir_path(__DIR__) . 'ismartframe/ismartframe_activity.log';

    // Initialize the WP_Filesystem
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    if (WP_Filesystem()) {
        // Check if the file exists
        if (!$wp_filesystem->exists($filename)) {
            return;
        }

        // Get the contents of the log file
        $logContent = $wp_filesystem->get_contents($filename);
        if (!$logContent) {
            return;
        }

        $logEntries = explode("\n", $logContent);
        $validEntries = [];
        $now = new DateTime();
        $thresholdTime = $now->modify('-1 day');

        // Filter log entries by date
        foreach ($logEntries as $entry) {
            if (preg_match('/^\[(.*?)\]/', $entry, $matches)) {
                $entryTime = DateTime::createFromFormat('Y-m-d H:i:s', $matches[1]);

                if ($entryTime && $entryTime > $thresholdTime) {
                    $validEntries[] = $entry;
                }
            }
        }

        // Write back the valid log entries
        if (!empty($validEntries)) {
            $wp_filesystem->put_contents($filename, implode("\n", $validEntries) . "\n", FS_CHMOD_FILE);
        } else {
            $wp_filesystem->put_contents($filename, '', FS_CHMOD_FILE);
        }

        isfwp_write_log('[DEBUG] CRON - Cleaning log completed!');
    } else {
        isfwp_write_log('[ERROR] CRON - WP_Filesystem initialization failed.');
    }
}


function isfwp_write_log($data) {
    if (ISFWP_PLUGIN_LOGGING_ENABLED && WP_DEBUG) {
        if (is_array($data) || is_object($data)) {
            error_log(print_r($data, true));
        } else {
            error_log($data);
        }
    }
}

require dirname(__DIR__) . '/ismartframe/options-menu.php';
require dirname(__DIR__) . '/ismartframe/cache-header-functions.php';

//Run Class
new ISFWP_ISmartFrame();
