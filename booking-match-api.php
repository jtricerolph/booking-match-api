<?php
/**
 * Plugin Name: Booking Match API
 * Plugin URI: https://yourwebsite.com
 * Description: Core booking matching engine and REST API for hotel/restaurant reservation matching. Provides matching logic and API endpoints for client applications.
 * Version: 1.5.0
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
define('BMA_VERSION', '1.5.0');
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

        // Add CORS support for Chrome extension
        add_action('rest_api_init', array($this, 'add_cors_support'));
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-rest-controller.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-newbook-search.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-matcher.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-comparison.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-booking-actions.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-response-formatter.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-authenticator.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-template-helper.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-booking-source.php';
        require_once BMA_PLUGIN_DIR . 'includes/class-bma-issue-checker.php';

        // Load admin class if in admin area
        if (is_admin()) {
            require_once BMA_PLUGIN_DIR . 'includes/class-bma-admin.php';
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize admin if in admin area
        if (is_admin()) {
            new BMA_Admin();
        }

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

    /**
     * Add CORS headers to support Chrome extension requests
     */
    public function add_cors_support() {
        // Add CORS headers to response
        add_filter('rest_post_dispatch', function($response, $server, $request) {
            // Only for BMA endpoints
            if (strpos($request->get_route(), '/bma/') !== false) {
                $response->header('Access-Control-Allow-Origin', '*');
                $response->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                $response->header('Access-Control-Allow-Credentials', 'true');
            }
            return $response;
        }, 15, 3);
    }
}

/**
 * Initialize the plugin
 */
function bma_init() {
    return Booking_Match_API::get_instance();
}

/**
 * Debug logging utility - respects bma_enable_debug_logging setting
 *
 * @param string $message Log message
 * @param string $level Log level (debug, info, warning, error)
 */
function bma_log($message, $level = 'debug') {
    // Always log errors, only log debug/info/warning if setting is enabled
    $debug_enabled = get_option('bma_enable_debug_logging', false);

    if ($level === 'error' || $debug_enabled) {
        $prefix = strtoupper($level);
        $formatted_message = sprintf('[BMA] [%s] %s', $prefix, $message);
        error_log($formatted_message);
    }
}

/**
 * Extract comprehensive request context for logging
 *
 * @param WP_REST_Request $request The REST request object
 * @return array Context data for logging
 */
function bma_get_request_context($request) {
    $current_user = wp_get_current_user();

    // Get IP address (anonymized if setting enabled)
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    if (class_exists('NewBook_API_Cache')) {
        $anonymize = get_option('newbook_cache_anonymize_ips', true);
        if ($anonymize) {
            $ip_address = bma_anonymize_ip($ip_address);
        }
    }

    $context = array(
        // WHO made the request
        'user_id' => $current_user->ID ?: 0,
        'username' => $current_user->user_login ?: 'guest',
        'ip_address' => $ip_address,
        'user_agent' => $request->get_header('user_agent') ?: 'unknown',

        // WHAT was requested
        'route' => $request->get_route(),
        'method' => $request->get_method(),

        // WHERE did they come from
        'referrer' => $request->get_header('referer') ?: 'none',
        'origin' => $request->get_header('origin') ?: 'none',

        // WHEN
        'timestamp' => current_time('mysql'),

        // Client identification (to distinguish Chrome extension vs webapp)
        'client_type' => bma_identify_client_type($request),

        // Context param from request (chrome-extension, chrome-sidepanel, webapp, json)
        'response_format' => $request->get_param('context') ?: 'json',
    );

    return $context;
}

/**
 * Identify client type from user agent and request parameters
 *
 * @param WP_REST_Request $request
 * @return string Client identifier
 */
function bma_identify_client_type($request) {
    $user_agent = $request->get_header('user_agent') ?: '';
    $context_param = $request->get_param('context') ?: '';

    // Check context parameter first (most reliable)
    if (in_array($context_param, array('chrome-extension', 'chrome-sidepanel'))) {
        return 'chrome-extension';
    }
    if (in_array($context_param, array('webapp', 'web-app'))) {
        return 'webapp';
    }

    // Parse user agent for known patterns
    if (stripos($user_agent, 'NewBookAssistant') !== false) {
        return 'chrome-extension';
    }
    if (stripos($user_agent, 'chrome') !== false && stripos($user_agent, 'extension') !== false) {
        return 'chrome-extension';
    }
    if (stripos($user_agent, 'curl') !== false) {
        return 'curl';
    }
    if (stripos($user_agent, 'postman') !== false) {
        return 'postman';
    }
    if (stripos($user_agent, 'insomnia') !== false) {
        return 'insomnia';
    }
    if (stripos($user_agent, 'mozilla') !== false || stripos($user_agent, 'chrome') !== false) {
        return 'browser';
    }

    return 'unknown';
}

/**
 * Anonymize IP address for privacy compliance (GDPR)
 *
 * @param string $ip IP address to anonymize
 * @return string Anonymized IP address
 */
function bma_anonymize_ip($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4: Remove last octet (192.168.1.123 → 192.168.1.0)
        return preg_replace('/\.\d+$/', '.0', $ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6: Remove last 80 bits
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, -5)) . '::';
    }
    return $ip;
}

// Start the plugin
bma_init();
