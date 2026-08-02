<?php

namespace SeoAudit;

class Audit
{
    public static function audit_item(string  $id):array
    {
        $url = get_the_permalink($id);
        $keywords = \SeoAudit\Helpers::get_keywords($id);
        $started = microtime(true);

        Logs::add('audit', 'Auditing a page.', [
            'id'       => $id,
            'url'      => $url,
            'keywords' => count($keywords),
        ]);

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

            Logs::add('audit', 'Served from the stored report; no crawl needed.', [
                'id'  => $id,
                'url' => $url,
            ]);
        } else {
            $sleepTimer = carbon_get_theme_option('delay_between_crawl_request') ?? 1;
            if ($sleepTimer>-1){
                sleep($sleepTimer);
            }

            $result = \SeoAudit\Helpers::get_HTML($url);
            $html = $result['content'];
            $report['status']= $result['status'];

            if (200 !== (int) $result['status'] || '' === trim((string) $html)) {
                // Worth an unconditional record: an audit built on a failed
                // crawl reports missing tags that may be perfectly present.
                Logs::error('audit', 'The crawl did not return a usable page.', [
                    'id'     => $id,
                    'url'    => $url,
                    'status' => $result['status'],
                    'bytes'  => strlen((string) $html),
                ]);
            }

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

        Logs::add('audit', 'Audit complete.', [
            'id'          => $id,
            'url'         => $url,
            'status'      => $report['status'] ?? null,
            'from_cache'  => $fromFile,
            'h1_count'    => $report['h1_count'] ?? null,
            'has_meta'    => !empty($report['meta_description']),
            'images_no_alt' => is_array($report['img_alt_missing'] ?? null) ? count($report['img_alt_missing']) : 0,
            'ms'          => (int) round((microtime(true) - $started) * 1000),
        ]);

        $html = \SeoAudit\Helpers::get_raport_item_output_html($id, $fromFile);
        return [
            'html' => $html,
            'report' => $report,
            'id' => $id
        ];
    }
}
