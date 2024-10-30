<?php

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 *
 * Add iSF settings page to WordPress admin menu
 *
 */
function isfwp_add_isf_settings_in_admin_menu() {
    global $wp_filesystem;

    // Load the Filesystem API
    if (! function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    // Initialize the Filesystem API
    WP_Filesystem();

    // Get the SVG file path
    $svg_file_path = plugin_dir_path(__FILE__) . 'assets/isf-logo-svg.svg';

    // Initialize $svg_icon_base64
    $svg_icon_base64 = '';

    // Check if the file exists and read it using WP_Filesystem
    if ($wp_filesystem->exists($svg_file_path)) {
        // Read the SVG file
        $svg_icon = $wp_filesystem->get_contents($svg_file_path);
        $svg_icon_base64 = 'data:image/svg+xml;base64,' . base64_encode($svg_icon);
    } else {
        // Handle error or fallback to a Dashicon
        $svg_icon_base64 = 'dashicons-performance';
    }

    // Add main menu page
    add_menu_page(
        __('iSmartFrame WordPress Plugin', 'ismartframe'),  // Menu page title
        __('iSmartFrame', 'ismartframe'),                   // Menu item label
        'manage_options',                                   // Capability
        'ismartframe',                                      // Menu slug
        'isfwp_render_isf_settings_page',                   // Callback function
        $svg_icon_base64                                    // Icon
    );

    // Add the 'Settings' submenu item
    add_submenu_page(
        'ismartframe',
        __('iSmartFrame Settings', 'ismartframe'),          // Page title
        __('Settings', 'ismartframe'),                     // Submenu item label
        'manage_options',                                  // Capability
        'ismartframe',                                     // Menu slug
        'isfwp_render_isf_settings_page'                   // Callback function
    );

    // Add the 'Logs' submenu item
    add_submenu_page(
        'ismartframe',
        __('iSmartFrame Activity Logs', 'ismartframe'),     // Page title
        __('Logs', 'ismartframe'),                         // Submenu item label
        'manage_options',                                  // Capability
        'ismartframe-activity-logs',                       // Menu slug
        'isfwp_render_isf_logs_page'                       // Callback function
    );
}


add_action('admin_menu', 'isfwp_add_isf_settings_in_admin_menu');

/**
 *
 * Settings page structure
 *
 */
function isfwp_render_isf_settings_page() {
    ?>
    <div class="banner notice notice-warning is-dismissible isf-notice">
        <img src="<?php echo esc_url(plugins_url('assets/isf-logo.png', __FILE__)); ?>" alt="<?php esc_attr_e('First-time iSmartFrame configuration', 'ismartframe'); ?>">
        <div class="banner-text">
            <h3><?php esc_html_e('First time on iSmartFrame?', 'ismartframe'); ?></h3>
            <p>
                <a href="<?php echo esc_url('https://ismartframe.com/'); ?>"><?php esc_html_e('Get started now', 'ismartframe'); ?></a>,
                <?php esc_html_e('install the app and accelerate your website speed!', 'ismartframe'); ?>
            </p>
        </div>
    </div>

    <div class="isf-settings-container">
        <div class="isf-left-column">
            <form action="options.php" method="post" id="isfPluginOptionsForm">
                <?php
                settings_fields('isfwp_ismartframe_settings');
                do_settings_sections('isfWordpressOptions');
                ?>
                <button id="checkApiKeyButton" class="button button-primary">
                    <?php esc_html_e('Check API key and Save', 'ismartframe'); ?>
                </button>
            </form>
        </div>

        <div class="isf-right-column">
            <h3><?php esc_html_e('How to generate your API Key', 'ismartframe'); ?></h3>
            <p><?php esc_html_e('To generate your API key, follow these steps:', 'ismartframe'); ?></p>
            <ol>
                <li><?php esc_html_e('Log in to your account on', 'ismartframe'); ?>
                    <a href="<?php echo esc_url('https://app.ismartframe.com/'); ?>">app.ismartframe.com</a>
                </li>
                <li><?php esc_html_e('Navigate to your domain section in your dashboard.', 'ismartframe'); ?></li>
                <li><?php esc_html_e('Locate the "API Key" section, click "Generate" and copy it.', 'ismartframe'); ?></li>
                <li><?php esc_html_e('Paste it into the field in the plugin settings.', 'ismartframe'); ?></li>
            </ol>
        </div>
    </div>

    <?php
    $options = get_option('isfwp_ismartframe_settings');
    if (isset($options['api_key']) && $options['api_key'] !== '') {
    ?>
        <div class="banner notice notice-warning isf-notice" style="margin-top: 2rem;">
            <div class="banner-text">
                <h3><?php esc_html_e('Do you need a full cache reset?', 'ismartframe'); ?></h3>
                <p style="margin-bottom: .5rem;">
                    <b><?php esc_html_e('Warning!', 'ismartframe'); ?></b>
                    <?php esc_html_e('This operation is not immediate and will slow down the entire site and decrease performance until the cache is correctly repopulated.', 'ismartframe'); ?>
                </p>
                <button id="purgeByPatternButton" class="button button-secondary">
                    <?php esc_html_e('Clear cache', 'ismartframe'); ?>
                </button>
                <div id="confirmationSection" style="display: none; margin-top: 1.3rem;">
                    <p style="margin-bottom: .5rem;">
                        <?php esc_html_e('Are you sure you want to clear the cache?', 'ismartframe'); ?>
                    </p>
                    <button id="confirmClearCache" class="button button-primary">
                        <?php esc_html_e('Yes', 'ismartframe'); ?>
                    </button>
                    <button id="cancelClearCache" class="button">
                        <?php esc_html_e('No', 'ismartframe'); ?>
                    </button>
                </div>
            </div>
        </div>
    <?php
    }
    ?>
    <div class="banner notice notice-warning isf-notice" style="margin-top: 2rem;">
        <div class="banner-text">
            <h3><?php esc_html_e('How iSmartFrame plugin works', 'ismartframe'); ?></h3>
            <ul class="isf-ul">
                <li><strong><?php esc_html_e('Automatic Cache Purge:', 'ismartframe'); ?></strong>
                    <?php esc_html_e('Instantly clears cache when new content is published, or updates are made, ensuring only the updated page is affected.', 'ismartframe'); ?>
                </li>
                <li><strong><?php esc_html_e('Targeted Cache Management:', 'ismartframe'); ?></strong>
                    <?php esc_html_e('Purges only the specific page cache or tag without clearing the entire website cache, keeping the rest of your site fast.', 'ismartframe'); ?>
                </li>
                <li><strong><?php esc_html_e('Customizable Cache Control:', 'ismartframe'); ?></strong>
                    <?php esc_html_e('Allows for targeted cache purging by tags or custom URLs, giving you flexibility in managing what’s cached.', 'ismartframe'); ?>
                </li>
                <li><strong><?php esc_html_e('Complete Website Cache Clearing:', 'ismartframe'); ?></strong>
                    <?php esc_html_e('Allows a global cleanup of the site. iSmartFrame retrieves the information directly from the origin server.', 'ismartframe'); ?>
                </li>
            </ul>
            <p><?php esc_html_e('Learn more on how', 'ismartframe'); ?>
                <a href="<?php echo esc_url('https://www.ismartframe.com/en/features/'); ?>">iSmartFrame</a>
                <?php esc_html_e('works.', 'ismartframe'); ?>
            </p>
        </div>
    </div>
    <?php
}


/**
 * Render the logs page
 */
function isfwp_render_isf_logs_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'ismartframe'));
    }

    // Initialize WP_Filesystem
    global $wp_filesystem;
    if (!function_exists('WP_Filesystem')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    WP_Filesystem();

    // Define log file path
    $filename = plugin_dir_path(__DIR__) . 'ismartframe/ismartframe_activity.log';

    // Read log file content
    $logContents = '';
    if ($wp_filesystem->exists($filename)) {
        $logContents = $wp_filesystem->get_contents($filename);
    }

?>
    <div class="wrap">
        <h1><?php esc_html_e('iSmartFrame activity logs', 'ismartframe'); ?></h1>
        <p><?php esc_html_e('Here you can view the last 24 hours logs from the iSmartFrame plugin.', 'ismartframe'); ?></p>
        <textarea readonly rows="20" wrap="off" style="width: 100%; font-family: monospace; padding: 1rem;">
<?php
    echo esc_textarea($logContents);
?></textarea>
    </div>
<?php
}


/**
 *
 * Create and add settings field
 *
 */
function isfwp_register_isf_settings() {
    register_setting('isfwp_ismartframe_settings', 'isfwp_ismartframe_settings');

    // Add settings section
    add_settings_section('api_settings', '', 'isfwp_header_section_setting', 'isfWordpressOptions');

    // Add settings fields with translated labels
    add_settings_field(
        'isfwp_render_isf_api_key_setting',
        esc_html__('Your API key', 'ismartframe'),
        'isfwp_render_isf_api_key_setting',
        'isfWordpressOptions',
        'api_settings',
        ['class' => 'w-100']
    );

    add_settings_field(
        'isfwp_render_isf_domain_setting',
        esc_html__('Domain', 'ismartframe'),
        'isfwp_render_isf_domain_setting',
        'isfWordpressOptions',
        'api_settings',
        ['class' => 'w-100']
    );

    // Uncomment and translate if needed in the future
    // add_settings_field(
    //     'isfwp_render_isf_debug_setting',
    //     esc_html__('Debug', 'ismartframe'),
    //     'isfwp_render_isf_debug_setting',
    //     'isfWordpressOptions',
    //     'api_settings',
    //     ['class' => 'w-120']
    // );
}


add_action('admin_init', 'isfwp_register_isf_settings');

function isfwp_header_section_setting() {
    ?>
        <div class="plugin-options-title">
            <h2><?php esc_html_e('iSmartFrame plugin settings', 'ismartframe'); ?></h2>
            <div class="spinner"></div>
        </div>
        <div class="message"></div>

    <?php
        $options = get_option('isfwp_ismartframe_settings');
        if (!isset($options['api_key']) || $options['api_key'] == '') {
            echo '<div class="notice notice-warning isf-notice settings-error is-dismissible ml-0 mr-0"><p>' . esc_html__('Fill in all the fields to start using the plugin.', 'ismartframe') . '</p></div>';
        }
    }


function isfwp_render_isf_api_key_setting() {
    $options = get_option('isfwp_ismartframe_settings');
    if (isset($options['api_key']) && $options['api_key'] != '') {
        echo "<input id='isfwp_ismartframe_api_key' required name='isfwp_ismartframe_settings[api_key]' class='input-width' type='text' value='" . esc_attr($options['api_key']) . "' />";
    } else {
        echo "<input id='isfwp_ismartframe_api_key' required name='isfwp_ismartframe_settings[api_key]' class='input-width' type='text' value='' />";
    }
}

function isfwp_render_isf_domain_setting() {
    echo "<input id='isfwp_ismartframe_domain' required readonly name='isfwp_ismartframe_settings[domain]' class='input-width' type='text' value='" . esc_attr(wp_parse_url(get_site_url(), PHP_URL_HOST)) . "' />";
}


function isfwp_render_isf_debug_setting() {
    // $options = get_option( 'isfwp_ismartframe_settings' );
    // if(is_array($options )) {
    //     if(isset($options['debug']) && esc_attr( $options['debug'] ) === "on"){
    //         echo "<input id='isfwp_ismartframe_debug' name='isfwp_ismartframe_settings[debug]' size='80' type='checkbox' checked />";
    //     } else {
    //         echo "<input id='isfwp_ismartframe_debug' name='isfwp_ismartframe_settings[debug]' size='80' type='checkbox' />";
    //     }
    // } else {
    //     echo "<input type='checkbox' id='isfwp_ismartframe_debug'  name='isfwp_ismartframe_settings[debug]' size='80' checked  />";
    // }
}
