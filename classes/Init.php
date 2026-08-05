<?php

namespace SeoAudit;

class Init
{
    public function init(): void
    {
        if (is_admin()) {
            // Priority 9: the parent menu must exist before Carbon Fields attaches
            // the settings subpage on `admin_menu` at the default priority 10.
            add_action('admin_menu', [$this, 'add_menu_links'], 9);
        }
        \SeoAudit\SeoMeta::registerFallbackMeta();
        \SeoAudit\SeoMeta::init();
        $this->setup_hooks();
        $this->setup_api_routes();
    }

    public static function add_settings_link_to_plugin_list($links)
    {
        $links[] = '<a href="' . self::get_settings_page_url() . '">Settings</a>';
        return $links;
    }

    public static function get_settings_page_url()
    {
        return esc_url(get_admin_url(null, 'admin.php?page=' . self::get_settings_page_relative_path()));
    }

    public static function get_settings_page_relative_path()
    {
        return 'yolsa-settings';
    }


    public function setup_api_routes(): void
    {

        add_action('rest_api_init', function () {
            register_rest_route('seo-audit/v1', '/audit-item/(?P<id>\d+)', array(
                'methods' => 'GET',
                'callback' => 'SeoAudit\RestRoutes::audit_item_request',
                'args' => array(
                    'id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

            register_rest_route('seo-audit/v1', '/force-audit-item/(?P<id>\d+)', array(
                'methods' => 'GET',
                'callback' => 'SeoAudit\RestRoutes::force_audit_item_request',
                'args' => array(
                    'id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

            register_rest_route('seo-audit/v1', '/clear-audit-data', array(
                'methods' => 'GET',
                'callback' => 'SeoAudit\RestRoutes::clear_audit_data',
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

            register_rest_route('seo-audit/v1', '/update-meta-description/(?P<id>\d+)', array(
                'methods' => 'POST',
                'callback' => 'SeoAudit\RestRoutes::update_meta_description',
                'args' => array(
                    'id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));


            register_rest_route('seo-audit/v1', '/generate-meta-description/(?P<id>\d+)', array(
                'methods' => 'POST',
                'callback' => 'SeoAudit\RestRoutes::generate_meta_description',
                'args' => array(
                    'id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                ),
                'permission_callback' => function () {
                    return current_user_can('edit_others_posts');
                }
            ));

            register_rest_route('seo-audit/v1', '/self-test', array(
                'methods' => 'GET',
                'callback' => 'SeoAudit\RestRoutes::run_self_tests',
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ));
        });
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=yolsa-settings">' . __('Settings', 'yolsa') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    private function setup_hooks(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (in_array($page, ['yolsa-audit', 'yolsa-keyword-audit'], true)) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_styles']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_scripts']);
            add_action('admin_enqueue_scripts', [$this, 'my_custom_rest_api_nonce']);
        }
        if ($page === 'yolsa-self-test') {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_styles']);
        }
    }

    public function my_custom_rest_api_nonce() {
        $nonce = wp_create_nonce('wp_rest');
        wp_localize_script('yolsa-js', 'yolsaNonce', ['yolsaNonce' => $nonce ]);
    }

    public function enqueue_plugin_styles(): void
    {
        wp_enqueue_style('yolsa-css', YOLSA_PLUGIN_URL . '/assets/css/main.css', [], YOLSA_VERSION);
        wp_enqueue_style('yolsa-lib-table-styles', YOLSA_PLUGIN_URL . '/assets/lib/jquery.dataTables.css', [], YOLSA_VERSION);
    }

    public function enqueue_plugin_scripts(): void
    {
        wp_enqueue_script('yolsa-lib-table-js', YOLSA_PLUGIN_URL . '/assets/lib/jquery.dataTables.js.js', array('jquery'), YOLSA_VERSION, true);
        wp_enqueue_script('yolsa-js', YOLSA_PLUGIN_URL . '/assets/js/main.js', array('jquery', 'yolsa-lib-table-js'), YOLSA_VERSION, true);
    }

    public function add_menu_links(): void
    {
        add_menu_page(
            'YoLSA',
            'YoLSA',
            'manage_options',
            'yolsa-audit',
            [$this, 'seo_audit'],//'Init\Init::seo_audit',
            'dashicons-chart-bar', // You can change the icon
            85 // Adjust the position as needed
        );

        add_submenu_page(
            'yolsa-audit',
            'Keyword Audit',
            'Keyword Audit',
            'manage_options',
            'yolsa-keyword-audit',
            [$this, 'keyword_audit']
        );

        add_submenu_page(
            'yolsa-audit',
            'Self test',
            'Self test',
            'manage_options',
            'yolsa-self-test',
            [$this, 'self_test']
        );

        // Hidden while logging is switched off — there is nothing to show, and
        // the same is true of splecheh's Logs page. The page callback keeps its
        // own capability check, so this is presentation only.
        if (\SeoAudit\Logs::enabled()) {
            add_submenu_page(
                'yolsa-audit',
                'Logs',
                'Logs',
                'manage_options',
                'yolsa-logs',
                [$this, 'logs']
            );
        }

    }

    public function keyword_audit(): void
    {
        ?>
        <div class="wrap">
            <?php include(YOLSA_PLUGIN_DIR . "/templates/keywords.php"); ?>
        </div>
        <?php
    }

    public function seo_audit(): void
    {

        ?>
        <div class="wrap">
            <?php include(YOLSA_PLUGIN_DIR . "/templates/seo.php"); ?>
        </div>
        <?php
    }

    /** Admin page callback: renders the log viewer. */
    public function logs(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            isset($_POST['action'], $_POST['yolsa_clear_logs_nonce'])
            && 'clear_logs' === $_POST['action']
            && wp_verify_nonce(sanitize_key(wp_unslash($_POST['yolsa_clear_logs_nonce'])), 'yolsa_clear_logs')
        ) {
            \SeoAudit\Logs::clear();
        }

        $files    = \SeoAudit\Logs::files();
        $dates    = array_column($files, 'date');
        $requested = isset($_GET['log_date']) ? sanitize_key(wp_unslash($_GET['log_date'])) : '';
        $selected = in_array($requested, $dates, true) ? $requested : ($dates[0] ?? '');
        $lines    = '' === $selected ? [] : \SeoAudit\Logs::read($selected);
        ?>
        <div class="wrap">
            <?php include(YOLSA_PLUGIN_DIR . "/templates/logs.php"); ?>
        </div>
        <?php
    }

    public function self_test(): void
    {
        ?>
        <div class="wrap">
            <?php include(YOLSA_PLUGIN_DIR . "/templates/self-test.php"); ?>
        </div>
        <?php
    }


}
