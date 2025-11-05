<?php
/**
 * Plugin Name: Booking Match API
 * Plugin URI: https://yourwebsite.com
 * Description: REST API endpoint for searching and matching hotel bookings with restaurant reservations. Provides versatile response formats for different client types (Chrome extension, mobile apps, etc.)
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: booking-match-api
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BMA_VERSION', '1.0.0');
define('BMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BMA_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Booking_Match_API {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Initialize
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-rest-controller.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-newbook-search.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-matcher.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-response-formatter.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-authenticator.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-template-helper.php';
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Plugin initialized
        do_action('bma_init');
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $controller = new BMA_REST_Controller();
        $controller->register_routes();
    }
}

/**
 * Initialize the plugin
 */
function bma_init() {
    return Booking_Match_API::get_instance();
}

// Start the plugin
bma_init();
