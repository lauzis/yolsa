<?php

namespace SeoAudit;

class Init
{
    public function init(): void
    {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_menu_links']);
        }
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
        return esc_url(get_admin_url(null, 'options-general.php?page=' . self::get_settings_page_relative_path()));
    }

    public static function get_settings_page_relative_path()
    {
        return 'chat-gpt-seo-settings';
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
        });
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=chat-gpt-seo-logs">' . __('Settings', 'chat-gpt-seo') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }


    private function setup_hooks(): void
    {
        $page = sanitize_text_field( $_GET['page'] ?? '' );
        if ( $page === 'chat-gpt-seo-audit' || $page === 'chat-gpt-keyword-audit' ) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_styles']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_plugin_scripts']);
            add_action('admin_enqueue_scripts', [$this, 'my_custom_rest_api_nonce']);
        }
    }

    public function my_custom_rest_api_nonce() {
        $nonce = wp_create_nonce('wp_rest');
        wp_localize_script('chat-gpt-seo-js', 'chatGptSeoNonce', ['chatGptSeoNonce' => $nonce ]);
    }

    public function enqueue_plugin_styles(): void
    {
        wp_enqueue_style('chat-gpt-seo-css', CHAT_GPT_SEO_PLUGIN_URL . '/assets/css/main.css', [], YOLSA_VERSION);
        wp_enqueue_style('chat-gpt-seo-lib-table-styles', CHAT_GPT_SEO_PLUGIN_URL . '/assets/lib/jquery.dataTables.css', [], YOLSA_VERSION);
    }

    public function enqueue_plugin_scripts(): void
    {
        wp_enqueue_script('chat-gpt-seo-lib-table-js', CHAT_GPT_SEO_PLUGIN_URL . '/assets/lib/jquery.dataTables.js.js', array('jquery'), CHAT_GPT_SEO_VERSION, true);
        wp_enqueue_script('chat-gpt-seo-js', CHAT_GPT_SEO_PLUGIN_URL . '/assets/js/main.js', array('jquery', 'chat-gpt-seo-lib-table-js'), CHAT_GPT_SEO_VERSION, true);
    }

    public function add_menu_links(): void
    {
        add_menu_page(
            'SEO Audit',
            'SEO Audit',
            'manage_options',
            'chat-gpt-seo-audit',
            [$this, 'seo_audit'],//'Init\Init::seo_audit',
            'dashicons-chart-bar', // You can change the icon
            85 // Adjust the position as needed
        );

        add_submenu_page(
            'chat-gpt-seo-audit',
            'Keyword Audit',
            'Keyword Audit',
            'manage_options',
            'chat-gpt-keyword-audit',
            [$this, 'keyword_audit']
        );

        add_submenu_page(
            'chat-gpt-seo-audit',
            'Self test',
            'Self test',
            'manage_options',
            'chat-gpt-seo-self-test',
            [$this, 'self_test']
        );

    }

    public function keyword_audit(): void
    {
        ?>
        <div class="wrap">
            <?php include(CHAT_GPT_SEO_PLUGIN_DIR . "/templates/keywords.php"); ?>
        </div>
        <?php
    }

    public function seo_audit(): void
    {

        ?>
        <div class="wrap">
            <?php include(CHAT_GPT_SEO_PLUGIN_DIR . "/templates/seo.php"); ?>
        </div>
        <?php
    }

    public function self_test(): void
    {
        ?>
        <div class="wrap">
            <?php include(CHAT_GPT_SEO_PLUGIN_DIR . "/templates/self-test.php"); ?>
        </div>
        <?php
    }


}
