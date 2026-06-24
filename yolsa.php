<?php
/**
 * Plugin Name: YoLSA
 * Plugin URI: https://www.awave.com/
 * Description: SEO analysis for pages and posts. Generate meta description using chat-gpt.
 * Version: 1.0.16
 * Author: Awave AB
 * Author URI: https://www.awave.com/
 * License: (c) 2020 Awave AB - All right reserved.
 */

if ( ! defined( 'YOLSA_VERSION' ) ) {
    define( 'YOLSA_VERSION', '1.0.16' );
}

if ( ! defined( 'YOLSA_PLUGIN_DIR' ) ) {
    define( 'YOLSA_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
}

if ( ! defined( 'YOLSA_PLUGIN_URL' ) ) {
    define( 'YOLSA_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
}

if ( ! defined( 'YOLSA_PLUGIN_FILE' ) ) {
    define( 'YOLSA_PLUGIN_FILE', plugin_basename( __FILE__ ) );
}

if ( file_exists( YOLSA_PLUGIN_DIR . '/vendor/autoload.php' ) ) {
    require_once YOLSA_PLUGIN_DIR . '/vendor/autoload.php';
}

use Carbon_Fields\Carbon_Fields;

add_action( 'after_setup_theme', function () {
    Carbon_Fields::boot();
} );

add_action( 'carbon_fields_register_fields', [ 'SeoAudit\Settings', 'register' ] );

require_once YOLSA_PLUGIN_DIR . '/classes/Helpers.php';
require_once YOLSA_PLUGIN_DIR . '/classes/Audit.php';
require_once YOLSA_PLUGIN_DIR . '/classes/Settings.php';
require_once YOLSA_PLUGIN_DIR . '/classes/Init.php';
require_once YOLSA_PLUGIN_DIR . '/classes/RestRoutes.php';
require_once YOLSA_PLUGIN_DIR . '/classes/ChatGptApi.php';
require_once YOLSA_PLUGIN_DIR . '/classes/ChatBot.php';
require_once YOLSA_PLUGIN_DIR . '/classes/Tests.php';

function yolsa_init(): void {
    $init = new \SeoAudit\Init();
    $init->init();
}

add_action( 'init', 'yolsa_init' );
