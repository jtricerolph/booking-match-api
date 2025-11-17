<?php
/**
 * Admin Settings Class
 *
 * Handles admin settings page for Booking Match API
 *
 * @package BookingMatchAPI
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class BMA_Admin {

    /**
     * Initialize admin functionality
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_booking-match-api/booking-match-api.php', array($this, 'add_settings_link'));
    }

    /**
     * Add settings link to plugin actions
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=booking-match-api') . '">' . __('Settings', 'booking-match-api') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Add settings page to WordPress admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Booking Match API Settings', 'booking-match-api'),
            __('Booking Match API', 'booking-match-api'),
            'manage_options',
            'booking-match-api',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register general settings
        register_setting(
            'bma_settings_group',
            'bma_booking_page_url',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_url'),
                'default' => ''
            )
        );

        register_setting(
            'bma_settings_group',
            'bma_package_inventory_name',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'bma_settings_group',
            'bma_hotel_id',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '1'
            )
        );

        // Register Resos API settings
        register_setting(
            'bma_settings_group',
            'bma_resos_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        // Register NewBook API settings
        register_setting(
            'bma_settings_group',
            'bma_newbook_username',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'bma_settings_group',
            'bma_newbook_password',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'bma_settings_group',
            'bma_newbook_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        register_setting(
            'bma_settings_group',
            'bma_newbook_region',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'au'
            )
        );

        register_setting(
            'bma_settings_group',
            'bma_use_newbook_cache',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            )
        );

        // Register excluded email domains setting
        register_setting(
            'bma_settings_group',
            'bma_excluded_email_domains',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_excluded_domains'),
                'default' => ''
            )
        );

        // Register debug logging setting
        register_setting(
            'bma_settings_group',
            'bma_enable_debug_logging',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );

        // Add General Settings section
        add_settings_section(
            'bma_general_section',
            __('General Settings', 'booking-match-api'),
            array($this, 'render_general_section_description'),
            'booking-match-api'
        );

        // Add Resos API section
        add_settings_section(
            'bma_resos_section',
            __('Resos API Settings', 'booking-match-api'),
            array($this, 'render_resos_section_description'),
            'booking-match-api'
        );

        // Add NewBook API section
        add_settings_section(
            'bma_newbook_section',
            __('NewBook API Settings', 'booking-match-api'),
            array($this, 'render_newbook_section_description'),
            'booking-match-api'
        );

        // Add Debug Settings section
        add_settings_section(
            'bma_debug_section',
            __('Debug Settings', 'booking-match-api'),
            array($this, 'render_debug_section_description'),
            'booking-match-api'
        );

        // Add general settings fields
        add_settings_field(
            'bma_booking_page_url',
            __('Booking Management Page URL', 'booking-match-api'),
            array($this, 'render_booking_url_field'),
            'booking-match-api',
            'bma_general_section'
        );

        add_settings_field(
            'bma_package_inventory_name',
            __('Package Inventory Item Name', 'booking-match-api'),
            array($this, 'render_package_inventory_field'),
            'booking-match-api',
            'bma_general_section'
        );

        add_settings_field(
            'bma_hotel_id',
            __('Hotel ID', 'booking-match-api'),
            array($this, 'render_hotel_id_field'),
            'booking-match-api',
            'bma_general_section'
        );

        add_settings_field(
            'bma_excluded_email_domains',
            __('Excluded Email Domains', 'booking-match-api'),
            array($this, 'render_excluded_email_domains_field'),
            'booking-match-api',
            'bma_general_section'
        );

        // Add Resos API fields
        add_settings_field(
            'bma_resos_api_key',
            __('Resos API Key', 'booking-match-api'),
            array($this, 'render_resos_api_key_field'),
            'booking-match-api',
            'bma_resos_section'
        );

        // Add NewBook API fields
        add_settings_field(
            'bma_newbook_username',
            __('Username', 'booking-match-api'),
            array($this, 'render_newbook_username_field'),
            'booking-match-api',
            'bma_newbook_section'
        );

        add_settings_field(
            'bma_newbook_password',
            __('Password', 'booking-match-api'),
            array($this, 'render_newbook_password_field'),
            'booking-match-api',
            'bma_newbook_section'
        );

        add_settings_field(
            'bma_newbook_api_key',
            __('API Key', 'booking-match-api'),
            array($this, 'render_newbook_api_key_field'),
            'booking-match-api',
            'bma_newbook_section'
        );

        add_settings_field(
            'bma_newbook_region',
            __('Region', 'booking-match-api'),
            array($this, 'render_newbook_region_field'),
            'booking-match-api',
            'bma_newbook_section'
        );

        add_settings_field(
            'bma_use_newbook_cache',
            __('API Caching', 'booking-match-api'),
            array($this, 'render_use_newbook_cache_field'),
            'booking-match-api',
            'bma_newbook_section'
        );

        // Add debug settings fields
        add_settings_field(
            'bma_enable_debug_logging',
            __('Enable Debug Logging', 'booking-match-api'),
            array($this, 'render_debug_logging_field'),
            'booking-match-api',
            'bma_debug_section'
        );
    }

    /**
     * Sanitize URL input
     */
    public function sanitize_url($url) {
        if (empty($url)) {
            return '';
        }

        // Add trailing slash if missing
        $url = trailingslashit(esc_url_raw($url));

        return $url;
    }

    /**
     * Sanitize excluded email domains input
     */
    public function sanitize_excluded_domains($input) {
        if (empty($input)) {
            return '';
        }

        // Split by comma or newline, trim, lowercase, remove @ prefix
        $domains = preg_split('/[\n,]+/', $input);
        $cleaned = array();

        foreach ($domains as $domain) {
            $domain = trim(strtolower($domain));
            $domain = ltrim($domain, '@'); // Remove leading @ if present

            if (!empty($domain)) {
                $cleaned[] = $domain;
            }
        }

        // Return as comma-separated string for storage
        return implode(',', $cleaned);
    }

    /**
     * Render general section description
     */
    public function render_general_section_description() {
        echo '<p>' . __('Configure general settings for the Booking Match API plugin.', 'booking-match-api') . '</p>';
    }

    /**
     * Render Resos section description
     */
    public function render_resos_section_description() {
        echo '<p>' . __('Configure your Resos API credentials for restaurant reservation management.', 'booking-match-api') . '</p>';
    }

    /**
     * Render NewBook section description
     */
    public function render_newbook_section_description() {
        echo '<p>' . __('Configure your NewBook API credentials for hotel booking management.', 'booking-match-api') . '</p>';
    }

    /**
     * Render Debug section description
     */
    public function render_debug_section_description() {
        echo '<p>' . __('Configure debug settings for the plugin.', 'booking-match-api') . '</p>';
    }

    /**
     * Render booking page URL field
     */
    public function render_booking_url_field() {
        $value = get_option('bma_booking_page_url', '');
        $placeholder = home_url('/bookings/');

        echo '<input type="url" name="bma_booking_page_url" id="bma_booking_page_url" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '" />';
        echo '<p class="description">';
        echo __('Enter the full URL of the page that displays your hotel bookings (with the <code>[hotel-table-bookings-by-date]</code> shortcode).', 'booking-match-api');
        echo '<br />';
        echo sprintf(__('Example: <code>%s</code>', 'booking-match-api'), 'https://admin.hotelnumberfour.com/bookings/');
        echo '<br />';
        echo sprintf(__('If left empty, will default to: <code>%s</code>', 'booking-match-api'), $placeholder);
        echo '</p>';
    }

    /**
     * Render package inventory name field
     */
    public function render_package_inventory_field() {
        $value = get_option('bma_package_inventory_name', '');
        echo '<input type="text" name="bma_package_inventory_name" id="bma_package_inventory_name" value="' . esc_attr($value) . '" class="regular-text" placeholder="Dinner" />';
        echo '<p class="description">';
        echo __('Enter the text to look for in NewBook inventory item descriptions to identify package bookings.', 'booking-match-api');
        echo '<br />';
        echo __('Examples: "Dinner", "DBB", "Half Board", "Package"', 'booking-match-api');
        echo '<br />';
        echo __('This is used to determine if a booking should have the DBB/Package field set to "Yes" in Resos.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render hotel ID field
     */
    public function render_hotel_id_field() {
        $value = get_option('bma_hotel_id', '1');
        echo '<input type="text" name="bma_hotel_id" id="bma_hotel_id" value="' . esc_attr($value) . '" class="regular-text" placeholder="1" />';
        echo '<p class="description">';
        echo __('Enter your NewBook hotel ID (usually "1").', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render excluded email domains field
     */
    public function render_excluded_email_domains_field() {
        $value = get_option('bma_excluded_email_domains', '');
        // Convert comma-separated to newline-separated for display
        $display_value = str_replace(',', "\n", $value);

        echo '<textarea name="bma_excluded_email_domains" id="bma_excluded_email_domains" rows="4" class="large-text code">' . esc_textarea($display_value) . '</textarea>';
        echo '<p class="description">';
        echo __('Enter email domains to exclude from email update suggestions (one per line or comma-separated).', 'booking-match-api');
        echo '<br />';
        echo __('Example: <code>booking.com</code>, <code>expedia.com</code>, <code>hotels.com</code>', 'booking-match-api');
        echo '<br />';
        echo '<strong>' . __('Use case:', 'booking-match-api') . '</strong> ' . __('Agent bookings often use forwarding emails (@booking.com, @expedia.com). If the guest entered their personal email in Resos, you don\'t want to overwrite it with the forwarding address.', 'booking-match-api');
        echo '<br />';
        echo '<strong>' . __('Logic:', 'booking-match-api') . '</strong>';
        echo '<ul style="margin: 5px 0 0 20px;">';
        echo '<li>' . __('If Resos has <strong>no email</strong> → Still suggest excluded domain email (better than nothing)', 'booking-match-api') . '</li>';
        echo '<li>' . __('If Resos has email + NewBook has excluded domain → Don\'t suggest overwriting', 'booking-match-api') . '</li>';
        echo '<li>' . __('If Resos has email + NewBook has non-excluded domain → Normal suggestion logic', 'booking-match-api') . '</li>';
        echo '</ul>';
        echo '</p>';
    }

    /**
     * Render Resos API key field
     */
    public function render_resos_api_key_field() {
        $value = get_option('bma_resos_api_key', '');
        echo '<input type="password" name="bma_resos_api_key" id="bma_resos_api_key" value="' . esc_attr($value) . '" class="large-text" />';
        echo '<p class="description">';
        echo __('Enter your Resos API key for accessing restaurant reservation data.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render NewBook username field
     */
    public function render_newbook_username_field() {
        $value = get_option('bma_newbook_username', '');
        echo '<input type="text" name="bma_newbook_username" id="bma_newbook_username" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">';
        echo __('Enter your NewBook API username.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render NewBook password field
     */
    public function render_newbook_password_field() {
        $value = get_option('bma_newbook_password', '');
        echo '<input type="password" name="bma_newbook_password" id="bma_newbook_password" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">';
        echo __('Enter your NewBook API password.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render NewBook API key field
     */
    public function render_newbook_api_key_field() {
        $value = get_option('bma_newbook_api_key', '');
        echo '<input type="password" name="bma_newbook_api_key" id="bma_newbook_api_key" value="' . esc_attr($value) . '" class="large-text" />';
        echo '<p class="description">';
        echo __('Enter your NewBook API key.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render NewBook region field
     */
    public function render_newbook_region_field() {
        $value = get_option('bma_newbook_region', 'au');
        echo '<select name="bma_newbook_region" id="bma_newbook_region">';
        echo '<option value="au"' . selected($value, 'au', false) . '>Australia (au)</option>';
        echo '<option value="nz"' . selected($value, 'nz', false) . '>New Zealand (nz)</option>';
        echo '<option value="eu"' . selected($value, 'eu', false) . '>Europe (eu)</option>';
        echo '<option value="us"' . selected($value, 'us', false) . '>United States (us)</option>';
        echo '</select>';
        echo '<p class="description">';
        echo __('Select your NewBook API region.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render NewBook cache plugin toggle field
     */
    public function render_use_newbook_cache_field() {
        $enabled = get_option('bma_use_newbook_cache', true);
        $cache_plugin_active = class_exists('NewBook_API_Cache');

        echo '<label for="bma_use_newbook_cache">';
        echo '<input type="checkbox" name="bma_use_newbook_cache" id="bma_use_newbook_cache" value="1" ' . checked($enabled, true, false);

        if (!$cache_plugin_active) {
            echo ' disabled';
        }

        echo ' />';
        echo ' ' . __('Use NewBook API Cache plugin for faster performance', 'booking-match-api');
        echo '</label>';

        if ($cache_plugin_active) {
            echo '<p class="description" style="color: #46b450;">';
            echo '✓ ' . __('NewBook API Cache plugin detected and active', 'booking-match-api');
            echo '<br />';
            echo '<a href="' . admin_url('options-general.php?page=newbook-cache-settings') . '">';
            echo __('Configure cache settings', 'booking-match-api') . ' →';
            echo '</a>';
            echo '</p>';
        } else {
            echo '<p class="description" style="color: #f0b849;">';
            echo '⚠ ' . __('NewBook API Cache plugin not installed', 'booking-match-api');
            echo '<br />';
            echo __('Install the newbook-api-cache plugin to enable caching and reduce API calls by ~95%.', 'booking-match-api');
            echo '</p>';
        }
    }

    /**
     * Render debug logging field
     */
    public function render_debug_logging_field() {
        $value = get_option('bma_enable_debug_logging', false);
        echo '<label for="bma_enable_debug_logging">';
        echo '<input type="checkbox" name="bma_enable_debug_logging" id="bma_enable_debug_logging" value="1" ' . checked($value, true, false) . ' />';
        echo ' ' . __('Enable debug logging to WordPress debug.log', 'booking-match-api');
        echo '</label>';
        echo '<p class="description">';
        echo __('When enabled, the plugin will write detailed debug information to the WordPress debug.log file. Disable this in production to improve performance and reduce log file size.', 'booking-match-api');
        echo '</p>';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Show success message if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'bma_messages',
                'bma_message',
                __('Settings saved successfully.', 'booking-match-api'),
                'success'
            );
        }

        // Show error messages
        settings_errors('bma_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="bma-admin-header" style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0;">About Booking Match API</h2>
                <p>This plugin provides a REST API endpoint that searches for hotel bookings and matches them with restaurant reservations.</p>

                <div style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 12px; margin: 15px 0;">
                    <strong>API Endpoint:</strong> <code>POST /wp-json/bma/v1/bookings/match</code>
                </div>

                <details style="margin: 15px 0;">
                    <summary style="cursor: pointer; font-weight: 600;">API Documentation</summary>
                    <div style="margin-top: 10px; padding-left: 15px;">
                        <h4>Request Parameters</h4>
                        <ul>
                            <li><code>booking_id</code> - NewBook booking ID</li>
                            <li><code>email_address</code> - Guest email</li>
                            <li><code>phone_number</code> - Guest phone</li>
                            <li><code>guest_name</code> - Guest full name (requires additional field)</li>
                            <li><code>travelagent_reference</code> - Travel agent reference</li>
                            <li><code>context</code> - Response format: <code>json</code> or <code>chrome-extension</code></li>
                        </ul>

                        <h4>Response Formats</h4>
                        <ul>
                            <li><strong>JSON</strong>: Structured data with booking and match information</li>
                            <li><strong>Chrome Extension</strong>: Formatted HTML for display in browser extensions</li>
                        </ul>

                        <h4>Deep Links</h4>
                        <p>The API generates deep links to open specific bookings with auto-actions:</p>
                        <ul>
                            <li><code>?booking_id=123&date=2025-01-30&auto-action=create</code> - Open create booking form</li>
                            <li><code>?booking_id=123&date=2025-01-30&resos_id=xyz&auto-action=match</code> - Open match comparison</li>
                        </ul>
                    </div>
                </details>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('bma_settings_group');
                do_settings_sections('booking-match-api');
                submit_button(__('Save Settings', 'booking-match-api'));
                ?>
            </form>

            <div class="bma-admin-footer" style="background: #f9f9f9; border: 1px solid #ccd0d4; padding: 15px; margin-top: 20px; border-radius: 4px;">
                <h3 style="margin-top: 0;">Important Notes</h3>
                <ul>
                    <li>All API credentials are stored securely in your WordPress database</li>
                    <li>Make sure to configure all API settings above before using the plugin</li>
                    <li>If you previously used the Hotel Booking Table plugin, you may need to re-enter your credentials here</li>
                    <li>The plugin will fall back to Hotel Booking Table settings if not configured here (for backward compatibility)</li>
                </ul>
            </div>

            <div class="bma-admin-testing" style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-top: 20px; border-radius: 4px;">
                <h3 style="margin-top: 0;">🧪 Test the API</h3>
                <p>Use this example to test your API endpoint:</p>
                <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto; border-radius: 4px;">curl -X POST <?php echo rest_url('bma/v1/bookings/match'); ?> \
  -H "Content-Type: application/json" \
  -d '{
    "email_address": "guest@example.com",
    "context": "json"
  }'</pre>
                <p><em>Note: Authentication may be required depending on your configuration.</em></p>
            </div>
        </div>
        <?php
    }
}
