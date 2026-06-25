<?php

namespace SeoAudit;

class Audit
{
    public static function audit_item(string  $id):array
    {
        $url = get_the_permalink($id);
        $keywords = \SeoAudit\Helpers::get_keywords($id);

        $report = [
            'status' => 0,
            'url' => $url,
            'id' => $id,
            'timestamp' => time(),
            'keywords' => $keywords,
            'local_keywords' => \SeoAudit\Helpers::$local_keywords
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
}
