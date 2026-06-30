<?php

namespace SeoAudit;

class Keywords
{

    private function get_api_settings():array
    {
        $test_mode = carbon_get_theme_option('yolsa_test_mode');
        $token = carbon_get_theme_option('chat_gpt_seo_test_token');
        $url = carbon_get_theme_option('yolsa_test_url');
        if (!$test_mode) {
            $token = carbon_get_theme_option('chat_gpt_seo_live_token');
            $url = carbon_get_theme_option('yolsa_live_url');
        }
        return ['token' => $token, 'url' => $url];
    }


    public static function force_audit_item(\WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $url = get_the_permalink($id);
        //WP Fastest Cache clean before reaudit
        if(function_exists('wpfc_clear_post_cache_by_id')){
            wpfc_clear_post_cache_by_id($id);
        }

        \SeoAudit\Helpers::remove_report($url);
        return self::audit_item($request);
    }

    public static function audit_item(\WP_REST_Request $request)
    {

        $id = $request->get_param('id');
        $url = get_the_permalink($id);
        $keywords = \SeoAudit\Helpers::get_keywords($id);

        $report = [
            'status' => 0,
            'url' => $url,
            'id' => $id,
            'timestamp' => time(),
            'keywords' => $keywords
        ];


        $fromFile = false;
        $html_from_file = \SeoAudit\Helpers::get_html_from_file($url);
        $report_from_file = \SeoAudit\Helpers::get_report($url);

        if (!empty($html_from_file) && !empty($report_from_file)) {
            $fromFile = true;
            $html = $html_from_file;
            $report = $report_from_file;
        } else {
            $sleepTimer = carbon_get_theme_option('delay_between_crawl_request') ?? 1;
            if ($sleepTimer>-1){
                sleep($sleepTimer);
            }

            $result = \SeoAudit\Helpers::get_HTML($url);
            $html = $result['content'];
            $report['status']= $result['status'];

            //todo sleep get from settings
            $fromFile = false;
            $html = $result['content'];
            $status = $result['status'];

            $reports['status'] = $status;

            if (!empty($html)) {
                \SeoAudit\Helpers::save_html_to_file($url, $html);
            }

            $report = \SeoAudit\Helpers::audit_html($html, $report);
            $report['keywords'] = \SeoAudit\Helpers::$keywords;




            \SeoAudit\Helpers::save_report($url, $report);
        }

        $html = \SeoAudit\Helpers::get_raport_item_output_html($id, $fromFile);
        return [
            'html' => $html,
            'report' => $report,
            'id' => $id
        ];
    }


    public static function update_meta_description(\WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $data = $request->get_json_params();
        $meta_description = $data['meta_description'];

        \SeoAudit\SeoMeta::setMetaDescription($id, $meta_description);


        return [
            'id' => $id,
            'meta_description' => $meta_description
        ];
    }

    public static function generate_meta_description(\WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $force_keyword = (bool) $request->get_param('force-keyword');
        $content = apply_filters('the_content', get_the_content(null, false, $id));
        $data = $request->get_json_params();



        $keywords = [];
        foreach($data['keywords[]'] as $keyword){
            $keywords[] = $keyword;
        }



        $content = \SeoAudit\Helpers::clean_html($content);



        $ChatBot = new ChatBot();
        // Send the message to our AI.
        $resMessage = $ChatBot->sendMessage($content, $keywords,  $force_keyword);
        if ($resMessage){
            return [
                'id' => $id,
                'content'=>$content,
                'data'=>$data,
                'keywords' => $keywords,
                'response'=>$resMessage,
                'status'=>'ok'
            ];
        }
        return [
            'id' => $id,
            'content'=>$content,
            'data'=>$data,
            'keywords' => $keywords,
            'response'=>"Pleace check if you have valid ChatGpt token",
            'status'=>'failed'
        ];


    }
}
