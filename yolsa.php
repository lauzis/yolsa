<?php
/**
 * Plugin Name: YoLSA - Your Local SEO Auditor
 * Plugin URI:
 * Description: Local SEO auditing tool with keyword tracking and AI-generated meta descriptions.
 * Version: 1.3.1
 * Author:
 * Text Domain: yolsa
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('YOLSA_VERSION', '1.3.1');
define('YOLSA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YOLSA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YOLSA_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/yolsa');
define('YOLSA_REPORT_DIR', WP_CONTENT_DIR . '/uploads/yolsa');
define('YOLSA_REPORT_URL', WP_CONTENT_URL . '/uploads/yolsa');

if (!defined('YOLSA_LOG_PATH')) {
    // Under uploads/, never inside the plugin directory: WordPress deletes and
    // re-extracts that folder on every update, which would take the logs with it.
    $yolsa_uploads = wp_upload_dir();
    define('YOLSA_LOG_PATH', str_replace('\\', '/', $yolsa_uploads['basedir']) . '/yolsa-logs/');
    unset($yolsa_uploads);
}

$autoload = YOLSA_PLUGIN_DIR . 'vendor/autoload.php';
if (!file_exists($autoload)) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>YoLSA:</strong> Run <code>composer install</code> in the plugin directory before activating.</p></div>';
    });
    return;
}

require_once $autoload;
// Required explicitly: Composer's files autoload runs only one copy of this
// package per request, so the version gate would never see the others.
require_once YOLSA_PLUGIN_DIR . 'vendor/lauzis/wp-plugin-packages/bootstrap.php';

add_action('after_setup_theme', function () {
    \Carbon_Fields\Carbon_Fields::boot();
});

// Carbon Fields fires this on `init` at priority 0, so the callback has to be
// attached before `init` runs — not from inside an `init` callback.
add_action('carbon_fields_register_fields', ['\SeoAudit\Settings', 'register']);

// The Slack test button answers over admin-ajax, which never renders the
// settings page, so its endpoint is registered on every admin request.
if (is_admin()) {
    add_action('admin_init', static function (): void {
        $tester = \SeoAudit\Logs::slackTester();

        if ($tester) {
            $tester->boot();
        }
    });
}

add_action('plugins_loaded', function () {
    $init = new \SeoAudit\Init();
    add_action('init', [$init, 'init']);
    add_filter(
        'plugin_action_links_' . plugin_basename(__FILE__),
        ['\SeoAudit\Init', 'add_settings_link_to_plugin_list']
    );
});

register_activation_hook(__FILE__, function () {
    wp_mkdir_p(YOLSA_UPLOAD_DIR);
});

// The plugin's version in the admin footer, beside WordPress's own — the first
// thing worth knowing about a page misbehaving is which version drew it.
add_action( 'admin_init', static function () {
    if ( ! class_exists( '\\Lauzis\\WpPackages\\Admin\\Footer' ) ) {
        return;
    }

    \Lauzis\WpPackages\Admin\Footer::show(
        'yolsa',
        array(
            'name'    => 'YoLSA',
            'version' => defined( 'YOLSA_VERSION' ) ? YOLSA_VERSION : '',
        )
    );
} );
