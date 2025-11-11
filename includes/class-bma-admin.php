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
        // Register settings
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

        // Add settings section
        add_settings_section(
            'bma_general_section',
            __('General Settings', 'booking-match-api'),
            array($this, 'render_section_description'),
            'booking-match-api'
        );

        // Add booking page URL field
        add_settings_field(
            'bma_booking_page_url',
            __('Booking Management Page URL', 'booking-match-api'),
            array($this, 'render_booking_url_field'),
            'booking-match-api',
            'bma_general_section'
        );

        // Add package inventory name field
        add_settings_field(
            'bma_package_inventory_name',
            __('Package Inventory Item Name', 'booking-match-api'),
            array($this, 'render_package_inventory_field'),
            'booking-match-api',
            'bma_general_section'
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
     * Render section description
     */
    public function render_section_description() {
        echo '<p>' . __('Configure settings for the Booking Match API plugin.', 'booking-match-api') . '</p>';
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
                <h3 style="margin-top: 0;">Configuration Dependencies</h3>
                <p>This plugin relies on settings from the <strong>Hotel Admin</strong> plugin for API credentials:</p>
                <ul>
                    <li>NewBook API credentials (username, password, API key, region)</li>
                    <li>Resos API key</li>
                    <li>Hotel ID</li>
                </ul>
                <p>Make sure those settings are configured at <a href="<?php echo admin_url('options-general.php?page=hotel-booking-table-settings'); ?>">Settings → Hotel Booking Table</a>.</p>
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
