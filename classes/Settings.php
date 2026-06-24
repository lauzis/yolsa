<?php

namespace SeoAudit;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Settings
{
    public static function register(): void
    {
        Container::make( 'theme_options', __( 'YoLSA Settings' ) )
            ->set_page_parent( 'chat-gpt-seo-audit' )
            ->set_page_slug( 'yolsa-settings' )
            ->add_fields( [
                Field::make( 'text', 'yolsa_api_key', __( 'ChatGPT API Key' ) ),
                Field::make( 'text', 'yolsa_api_url', __( 'ChatGPT API URL' ) ),
                Field::make( 'checkbox', 'yolsa_test_mode', __( 'Test Mode' ) ),
                Field::make( 'text', 'yolsa_test_api_key', __( 'Test API Key' ) ),
                Field::make( 'text', 'yolsa_test_api_url', __( 'Test API URL' ) ),
                Field::make( 'text', 'yolsa_delay', __( 'Delay Between Crawl Requests (seconds)' ) )
                    ->set_default_value( '1' ),
            ] );
    }
}
