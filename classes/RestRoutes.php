<?php

namespace SeoAudit;

class RestRoutes
{
    public static function force_audit_item_request(\WP_REST_Request $request):array
    {
        $id = $request->get_param('id');
        $url = get_the_permalink($id);
        //WP Fastest Cache clean before reaudit
        if(function_exists('wpfc_clear_post_cache_by_id')){
            wpfc_clear_post_cache_by_id($id);
        }

        \SeoAudit\Helpers::remove_report($url);
        return self::audit_item_request($request);
    }

    public static function audit_item_request(\WP_REST_Request $request):array{
        $id = $request->get_param('id');
        return \SeoAudit\Audit::audit_item($id);
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
        $url = get_the_permalink($id);
        $force_keyword = (bool) $request->get_param('force-keyword');
        $content = apply_filters('the_content', get_the_content(null, false, $id));
        $report = \SeoAudit\Helpers::get_report($url);
        $wpml_lnag_info = apply_filters( 'wpml_post_language_details', NULL, $id );
        $lang = $report['lang'] ?? "";
        if ($wpml_lnag_info && $wpml_lnag_info['locale']){
            $lang = $wpml_lnag_info['locale'];
        }
        $data = $request->get_json_params();

        $keywords = [];
        if (is_array($data['keywords[]'])){
            foreach($data['keywords[]'] as $keyword){
                $keywords[] = $keyword;
            }
        } else {
            $keywords[] = $data['keywords[]'];
        }


        $content = \SeoAudit\Helpers::clean_html($content);

        $ChatBot = new \SeoAudit\ChatGptApi();
        // Send the message to our AI.
        $resMessage = $ChatBot->generate_meta_description($content, $keywords,  $force_keyword, $lang);
        if ($resMessage){
            return [
                'aaaaaa'=>'beeeeeeee',
                'id' => $id,
                'content'=>$content,
                'data'=>$data,
                'keywords' => $keywords,
                'response'=>$resMessage['message'],
                'status'=>'ok',
                'debug'=>$resMessage['debug'],
                'resMessage'=>$resMessage,
                'chatBot'=>json_encode($ChatBot)
            ];
        }
        //$jsonResponse = json_encode(array("responseMessage" => $resMessage));
        return [
            'ccccccccccccccc'=>'dddddddddddddddddddddddd',
            'id' => $id,
            'content'=>$content,
            'data'=>$data,
            'keywords' => $keywords,
            'response'=>"Pleace check if you have valid ChatGpt token",
            'status'=>'failed',
            'debug'=>$resMessage['debug'],
            'resMessage'=>$resMessage,
            'chatBot'=>json_encode($ChatBot)
        ];
    }

    public static function run_self_tests(\WP_REST_Request $request): array
    {
        $tests = $request->get_param('tests');
        if (!is_array($tests)) {
            $tests = !empty($tests) ? [$tests] : array_keys(SelfTest::get_all_tests());
        }

        $all_tests = SelfTest::get_all_tests();
        $results = [];
        foreach ($tests as $id) {
            $id = sanitize_key($id);
            if (isset($all_tests[$id])) {
                $results[$id] = [
                    'label' => $all_tests[$id]['label'],
                    'result' => SelfTest::run_test($id),
                ];
            }
        }
        return $results;
    }

    public static function clear_audit_data() {
        $files = scandir(YOLSA_REPORT_DIR);
        foreach($files as $file){
            if ($file!=='.' && $file!=='..'){
                unlink(YOLSA_REPORT_DIR."/".$file);
            }
        }
    }
}
