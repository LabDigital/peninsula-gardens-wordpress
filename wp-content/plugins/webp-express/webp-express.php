<?php
/**
 * Plugin Name: WebP Express
 * Plugin URI: https://github.com/rosell-dk/webp-express
 * Description: Serve autogenerated WebP images instead of jpeg/png to browsers that supports WebP. Works on anything (media library images, galleries, theme images etc).
 * Version: 0.25.9
 * Author: Bjørn Rosell
 * Author URI: https://www.bitwise-it.dk
 * License: GPL2
 * Network: true
 */

/*
Note: Perhaps create a plugin page on my website?, ie https://www.bitwise-it.dk/software/wordpress/webp-express
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (defined('WEBPEXPRESS_PLUGIN')) exit;  // Prevent problems if plugin is included twice (#472)

define('WEBPEXPRESS_PLUGIN', __FILE__);
define('WEBPEXPRESS_PLUGIN_DIR', __DIR__);

// Autoload WebPExpress classes
spl_autoload_register('webpexpress_autoload');
function webpexpress_autoload($class) {
    if (strpos($class, 'WebPExpress\\') === 0) {
        require_once WEBPEXPRESS_PLUGIN_DIR . '/lib/classes/' . substr($class, 12) . '.php';
    }
}

if (is_admin()) {
    \WebPExpress\AdminInit::init();
}

if ( class_exists( 'WP_CLI' ) ) {
    \WP_CLI::add_command('webp-express', '\WebPExpress\CLI');
}

function webp_express_process_post() {
    // strip query string
    $requestUriNoQS = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    //echo '<pre>' . print_r($_SERVER, true) . '</pre>'; die();

    if (preg_match('/webp-express-web-service$/', $requestUriNoQS)) {
        include __DIR__ . '/web-service/wpc.php';
        die();
    }
}
add_action( 'init', 'webp_express_process_post' );
//add_action( 'parse_request', 'webp_express_process_post' );

if (\WebPExpress\Option::getOption('webp-express-alter-html', false)) {
    require_once __DIR__ . '/lib/classes/AlterHtmlInit.php';
    \WebPExpress\AlterHtmlInit::setHooks();
}

// When images are uploaded with Gutenberg, is_admin() returns false, so, hook needs to be added here
add_filter('wp_handle_upload', array('\WebPExpress\HandleUploadHooks', 'handleUpload'), 10, 2);
add_filter('image_make_intermediate_size', array('\WebPExpress\HandleUploadHooks', 'handleMakeIntermediateSize'), 10, 1);
add_filter('wp_delete_file', array('\WebPExpress\HandleDeleteFileHook', 'deleteAssociatedWebP'), 10, 2);

//add_action( 'template_redirect', 'webp_express_template_redirect' );

// Add hooks for tasks that might be scheduled for wp_cron
add_action('webp_express_task_bulk_update_dummy_files', array('\WebPExpress\BiggerThanSourceDummyFilesBulk', 'updateStatus'), 10, 0);
add_action('webp_express_task_regenerate_config', array('\WebPExpress\Config', 'regenerateConfig'), 10, 0);
add_action('webp_express_task_regenerate_config_and_htaccess', array('\WebPExpress\Config', 'regenerateConfigAndHtaccessFiles'), 10, 0);
