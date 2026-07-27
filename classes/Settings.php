<?php

namespace SeoAudit;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Settings
{
    public static function register(): void
    {
        Container::make('theme_options', __('YoLSA Settings', 'yolsa'))
            ->set_page_parent('yolsa-audit')
            ->set_page_file('yolsa-settings')
            // Tab order follows the order of these calls — keywords first.
            ->add_tab(__('Keywords', 'yolsa'), [
                Field::make('complex', 'keyword_list', __('Keyword List', 'yolsa'))
                    ->set_help_text(__('Keywords audited across the site. Each keyword can have variations that count towards the same keyword.', 'yolsa'))
                    ->add_fields([
                        Field::make('text', 'keyword', __('Keyword', 'yolsa')),
                        Field::make('complex', 'kyeword_variations', __('Variations', 'yolsa'))
                            ->add_fields([
                                Field::make('text', 'keyword_variation', __('Variation', 'yolsa')),
                            ]),
                    ]),
            ])
            ->add_tab(__('API', 'yolsa'), [
                Field::make('checkbox', 'yolsa_test_mode', __('Test Mode', 'yolsa')),
                Field::make('text', 'yolsa_test_token', __('Test API Token', 'yolsa')),
                Field::make('text', 'yolsa_live_token', __('Live API Token', 'yolsa')),
                Field::make('text', 'chat_gpt_seo_test_token', __('ChatGPT Test Token', 'yolsa')),
                Field::make('text', 'chat_gpt_seo_live_token', __('ChatGPT Live Token', 'yolsa')),
                Field::make('text', 'chat_gpt_seo_api_version', __('ChatGPT API Version', 'yolsa'))
                    ->set_attribute('placeholder', 'gpt-3.5-turbo'),
                Field::make('text', 'yolsa_api_version', __('Assistant API Version', 'yolsa'))
                    ->set_attribute('placeholder', 'gpt-4'),
            ])
            ->add_tab(__('AI Instructions', 'yolsa'), [
                Field::make('textarea', 'assistant_instructions', __('System Instructions', 'yolsa')),
                Field::make('text', 'assistant_keyword_instructions', __('Keyword Instructions', 'yolsa')),
                Field::make('text', 'assistant_keyword_instructions_force', __('Forced Keyword Instructions', 'yolsa')),
                Field::make('text', 'assistant_run_instructions', __('Run Instructions', 'yolsa')),
            ])
            ->add_tab(__('Crawl', 'yolsa'), [
                Field::make('text', 'delay_between_crawl_request', __('Delay Between Crawl Requests (seconds)', 'yolsa'))
                    ->set_attribute('type', 'number')
                    ->set_attribute('placeholder', '1'),
            ]);
    }
}
