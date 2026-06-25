<?php

namespace SeoAudit;

const KEYWORD_FOUND_PHRASE = 2;
const KEYWORD_FOUND_EXACT_MATCH = 1;

class Helpers
{
    static array $keywords = [];
    static bool $initialized = false;
    static bool $local_keywords = false;

    static public function get_keywords_arr($id = 'option'): array
    {
        $full_list_of_keywords = [];
        $keyword_list = [];
        
        if ($id === 'option') {
            $keyword_list = carbon_get_theme_option('keyword_list');
        } else {
            $keyword_list = carbon_get_post_meta($id, 'keyword_list');
        }
        
        if (!empty($keyword_list)) {
            foreach ($keyword_list as $item) {
                $keyword = $item['keyword'] ?? '';
                $keyword_variations_acf = $item['kyeword_variations'] ?? [];
                $keyword_variations = [];
                if ($keyword_variations_acf) {
                    foreach ($keyword_variations_acf as $keyword_variation) {
                        $keyword_variations[] = [
                            'keyword' => $keyword_variation['keyword_variation'] ?? '',
                            'count' => 0,
                            'count_exact' => 0,
                            'count_phrase' => 0,
                            'place_where_found' => []
                        ];
                    }
                }
                $full_list_of_keywords[] = [
                    'keyword' => $keyword,
                    'variations' => $keyword_variations,
                    'count' => 0,
                    'count_exact' => 0,
                    'count_phrase' => 0,
                    'place_where_found' => []
                ];
            }
        }
        return $full_list_of_keywords;
    }

    static public function get_keywords($id = 'option'): array
    {
        $full_list_of_keywords = [];
        if ($id !== 'option') {
            $full_list_of_keywords = self::get_keywords_arr($id);
            if (!empty($full_list_of_keywords)) {
                self::$local_keywords = true;
            } else {
                self::$local_keywords = false;
            }
        }
        if (empty($full_list_of_keywords)) {
            $full_list_of_keywords = !empty(self::$keywords) ? self::$keywords : self::get_keywords_arr();
        }
        self::$keywords = $full_list_of_keywords;
        return $full_list_of_keywords;
    }

    static public function get_audit_status($status, bool $reverseColors = false): void
    {
        if (is_bool($status)) {
            if (!$reverseColors) {
                ?>
                <span class="cgs-status kw-found-<?= $status ? 'yes' : 'no'; ?>">
                    <?= $status ? 'yes' : 'no'; ?>
                </span>
                <?php
            } else {
                ?>
                <span class="cgs-status kw-found-<?= !$status ? 'yes' : 'no'; ?>">
                    <?= $status ? 'yes' : 'no'; ?>
                </span>
                <?php
            }

        } else {
            $state = "missing";
            if ($status >= 0 && $status < 1) {
                $state = "ok";
            }

            if ($status > 1 && $status < 50) {
                $state = "some-are-missing";
            }
            if ($status >= 50 && $status < 75) {
                $state = "missing";
            }

            if ($status >= 75 && $status < 99) {
                $state = "mostly-missing";
            }

            if ($status >= 99 && $status <= 100) {
                $state = "missing";
            }
            ?>
            <span class="cgs-status kw-found-<?= $state ?>">
                <?= $state; ?>
            </span>
            <?php
        }


    }

    static public function audit_text($text)
    {

        $textMissing = false;
        $textHasNpKeywords = false;
        $penalty = 0;
        if (strlen($text) == 0) {
            $penalty = 2;
            $textMissing = true;
        } else {

            $result = \SeoAudit\Helpers::has_keywords($text, 'in_content');
            if ($result) {
                return $result;
            } else {
                $textHasNpKeywords = true;;
                $penalty = 1;
            }

        }

        if ($textMissing || $textHasNpKeywords) {
            return $penalty;
        }
    }

    static public function has_keywords($text, $placeWhereFound): bool|array
    {

        if (empty($text) && strlen(trim($text)) === 0) {
            return false;
        }
        $keywordList = self::$keywords;
        $keywordFound = false;
        foreach ($keywordList as $k_key => $keyword) {
            $kwFound = self::find_keyword($text, $keyword['keyword']);
            if ($kwFound) {
                $keywordList[$k_key]['count']++;
                $keywordFound = true;

                if ($kwFound === KEYWORD_FOUND_EXACT_MATCH) {
                    $keywordList[$k_key]['count_exact']++;
                }

                if ($kwFound === KEYWORD_FOUND_PHRASE) {
                    $keywordList[$k_key]['count_phrase']++;
                }

                if (!in_array($placeWhereFound, $keywordList[$k_key]['place_where_found'])) {
                    $keywordList[$k_key]['place_where_found'][] = $placeWhereFound;
                }
            }

            if (!is_array($keyword['variations']) && count($keyword['variations'])) {
                foreach ($keyword['variations'] as $kv_key => $variation) {
                    $variation_keyword = $variation['keyword'];
                    $kwFound = self::find_keyword($text, $variation_keyword);
                    if ($kwFound) {
                        $keywordFound = true;
                        $keywordList[$k_key]['variations'][$kv_key]['count']++;

                        if ($kwFound === KEYWORD_FOUND_EXACT_MATCH) {
                            $keywordList[$k_key]['variations'][$kv_key]['count_exact']++;
                        }

                        if ($kwFound === KEYWORD_FOUND_PHRASE) {
                            $keywordList[$k_key]['variations'][$kv_key]['count_phrase']++;
                        }

                        if (!in_array($placeWhereFound, $keywordList[$k_key]['variations'][$kv_key]['place_where_found'])) {
                            $keywordList[$k_key]['variations'][$kv_key]['place_where_found'][] = $placeWhereFound;
                        }
                    }
                }
            }
        }

        if ($keywordFound) {
            self::$keywords = $keywordList;
            return true;
        } else {
            return false;
        }

    }

    static public function find_keyword($text, $keyword): bool|int
    {
        $text_to_lower = mb_strtolower($text);
        $slit_words = explode(" ", $keyword);
        if (mb_substr_count($text_to_lower, $keyword) > 0) {
            return KEYWORD_FOUND_EXACT_MATCH;
        }

        $found_all = true;
        if (count($slit_words) > 0) {

            foreach ($slit_words as $kw) {
                if (!empty($kw) && mb_substr_count($text_to_lower, $kw) === 0) {
                    $found_all = false;
                }
            }
        }


        return $found_all ? KEYWORD_FOUND_PHRASE : false;
    }


    static public function get_HTML($url)
    {
        $user_agent = 'Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';

        $options = array(

            CURLOPT_CUSTOMREQUEST => "GET",        //set request type post or get
            CURLOPT_POST => false,        //set to GET
            CURLOPT_USERAGENT => $user_agent, //set user agent
            CURLOPT_COOKIEFILE => "cookie.txt", //set cookie file
            CURLOPT_COOKIEJAR => "cookie.txt", //set cookie jar
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING => "",       // handle all encodings
            CURLOPT_AUTOREFERER => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT => 120,      // timeout on response
            CURLOPT_MAXREDIRS => 10,       // stop after 10 redirects
        );

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);

        $status = $header['http_code'];
        return ['content' => $content, 'status' => $status];
    }

    static public function generate_file_name($name): string
    {
        return md5(NONCE_SALT . $name);
    }


    static public function save_json($name, $data)
    {
        file_put_contents(YOLSA_UPLOAD_DIR . "/" . self::generate_file_name($name) . ".json", json_encode($data, JSON_PRETTY_PRINT));
    }

    static public function get_json($name): array|bool
    {
        $file = YOLSA_UPLOAD_DIR . "/" . self::generate_file_name($name) . ".json";
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), TRUE);
        }
        return false;
    }

    static public function save_html_to_file($url, $html): void
    {
        file_put_contents(YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".html", $html);
    }

    static public function get_html_from_file($url)
    {
        $file = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".html";
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return false;
    }

    static public function get_raport_item_output_html($id, $formFile)
    {
        $ajax = true;
        ob_start();
        include(YOLSA_PLUGIN_DIR . "/templates/seo/table-content-item-first-row.php");
        $first_row_html = ob_get_contents();
        ob_end_clean();


        ob_start();
        include(YOLSA_PLUGIN_DIR . "/templates/seo/table-content-item-first-row.php");
        $second_row_html = ob_get_contents();
        ob_end_clean();

        return [
            'first_row_html' => $first_row_html,
            'second_row_html' => $second_row_html,
        ];
    }

    static public function save_report($url, $report)
    {
        file_put_contents(YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".json", json_encode($report, JSON_PRETTY_PRINT));
    }

    static public function get_report($url)
    {
        $file = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".json";
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        return false;
    }

    static public function remove_report($url)
    {
        $file_html = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".html";
        $file_json = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".json";
        $file_html_prev = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".prev.html";
        $file_json_prev = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".prev.json";
        file_put_contents($file_html_prev, file_get_contents($file_html));
        file_put_contents($file_json_prev, file_get_contents($file_json));

        if (file_exists($file_html)) {
            unlink($file_html);
        }
        if (file_exists($file_json)) {
            unlink($file_json);
        }
    }

    static public function get_report_url($url): bool|string
    {
        $file_url = YOLSA_REPORT_URL . "/" . self::generate_file_name($url) . ".json";
        $file = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".json";
        if (file_exists($file)) {
            return $file_url;
        }
        return false;
    }

    static public function get_report_html_url($url): bool|string
    {
        $file_url = YOLSA_REPORT_URL . "/" . self::generate_file_name($url) . ".html";
        $file = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".html";
        if (file_exists($file)) {
            return $file_url;
        }
        return false;
    }

    static public function get_report_json($url)
    {
        $file = YOLSA_REPORT_DIR . "/" . self::generate_file_name($url) . ".json";
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return false;
    }

    static public function get_domains(): array
    {
        return [$_SERVER['HTTP_HOST']];
    }

    static public function is_outgoing_url($url): bool
    {

        if (empty($url)) {
            return false;
        }
        $domains = self::get_domains();

        $parsed_url = parse_url($url);

        if (!isset($parsed_url['host'])) {
            return false;
        }

        $host = $parsed_url['host'];

        if (in_array($host, $domains)) {
            return false;
        }

        if (str_starts_with($url, "/") || str_starts_with($url, "#") || str_starts_with($url, "?")) {
            return false;
        }


//        if (substr($url,0,4)==="http"){
//            return true;
//        }
//
//        if (substr($url,0,2)==="//"){
//            return true;
//        }

        return true;
    }

    static public function audit_tags($tag, $doc, $report): array
    {

        $items = $doc->getElementsByTagName($tag);
        if (!$items) {
            return $report;
        }
        $report[$tag . '_count'] = count($items);
        $report[$tag . '_found_keyword'] = 0;

        foreach ($items as $item) {
            switch ($tag) {
                case 'a':
                    $url = $item->getAttribute('href');
                    if (!empty($url)) {
                        if (!isset($report[$tag . '_hrefs'])) {
                            $report[$tag . '_hrefs'] = [];
                        }
                        $report[$tag . '_hrefs'][] = $url;

                        if (!isset($report[$tag . '_hrefs_outgoing'])) {
                            $report[$tag . '_hrefs_outgoing'] = [];
                        }

                        if (self::is_outgoing_url($url)) {
                            $report[$tag . '_hrefs_outgoing'][] = $url;
                        }

                        $a_content = $item->textContent;

                        if (self::has_keywords($a_content, 'in_link')) {
                            $report[$tag . '_found_keyword']++;
                        }
                    }
                    break;

                case 'img':
                    if (isset($report[$tag . '_srcs'])) {
                        $report[$tag . '_srcs'] = [];
                    }

                    if (isset($report[$tag . '_alt_missing'])) {
                        $report[$tag . '_alt_missing'] = [];
                    }
                    $src = $item->getAttribute('src');
                    $report[$tag . '_srcs'][] = $src;
                    $alt = $item->getAttribute('alt');

                    if (empty($alt)) {
                        $report[$tag . '_alt_missing'][] = $src;
                    }
                    if (self::has_keywords($alt, 'in_image_alt_attribute')) {
                        $report[$tag . '_found_keyword']++;
                    }
                    break;

                default:
                    $tag_textual_content = $item->textContent;
                    if (self::has_keywords($tag_textual_content, "in_" . $tag . "_tag")) {
                        $report[$tag . '_found_keyword']++;
                    }
                    break;
            }
        }

        return $report;
    }

    static public function audit_html($html, $report): array
    {

        if (empty($html)) {
            $report['html_empty'] = true;
            return $report;
        }

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        $errors = libxml_get_errors();
        $tags = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'title', 'img'];


        foreach ($tags as $tag) {
            $report = self::audit_tags($tag, $doc, $report);
        }


        $h1 = $doc->getElementsByTagName('h1');
        $h1Text = "";
        if (count($h1) > 0) {
            $h1Text = $h1[0]->textContent;
        }
        $report['h1_text'] = $h1Text;

        $report['html_errors'] = $errors;

        $report = self::get_meta($doc, $report);


        $report = self::get_language($doc, $report);


        //remove header
        $headers = $doc->getElementsByTagName('header');
        if ($headers && count($headers) > 0) {
            $header = $headers[0];
            $parent = $header->parentNode;
            $parent->removeChild($header);
            //$doc->removeChild($header);
        }

        //remove footer
        $footers = $doc->getElementsByTagName('footer');
        if ($footers && count($footers) > 0) {
            $footer = $footers[count($footers) - 1];
            $parent = $footer->parentNode;
            $parent->removeChild($footer);
        }

        //remove asides
        $asides = $doc->getElementsByTagName('aside');
        if ($asides && count($asides) > 0) {
            foreach ($asides as $aside) {
                $parent = $aside->parentNode;
                $parent->removeChild($aside);
            }
        }

        $paragraph = $doc->getElementsByTagName('p');
        $firstParagraphText = "";
        if (count($paragraph) > 0) {
            $firstParagraphText = $paragraph[0]->textContent;
        }

        $report['first_paragraph'] = $firstParagraphText;
        $report['first_paragraph_found_keywords'] = self::has_keywords($firstParagraphText, 'in_first_paragraph');


        $striped_html = self::clean_html($html);
        $report['content_has_keywords'] = self::has_keywords($striped_html, 'in_content');

        return $report;
    }


    static public function get_meta($dom, $report)
    {

        $id = (int)$report['id'];
        $meta_fields = $dom->getElementsByTagName("meta");

        $report['meta_description'] = false;
        $report['meta_description_text'] = '';
        $report['meta_image'] = false;
        $report['meta_description_keyword_found'] = false;
        $report['meta_description_duplicate'] = false;
        $report['meta_title_duplicate'] = false;
        $report['textualContent'] = "";


        foreach ($meta_fields as $meta_field) {

            $meta_field_name = $meta_field->getAttribute("name");
            if ($meta_field_name === "description") {
                $meta_field_content = $meta_field->getAttribute("content");
                if (!empty($meta_field_content)) {
                    $report['meta_description'] = true;
                    $report['meta_description_keyword_found'] = self::has_keywords($meta_field_content, 'in_meta_description');
                    $report['textualContent'] .= " " . $meta_field_content;
                    $report['meta_description_text'] = $meta_field_content;
                    $report['meta_description_duplicate'] = self::meta_description_is_unique($id, $meta_field_content);
                    $report['meta_descriptions'] = self::get_all_fields('meta_description_text');
                }
            }

            $meta_field_property = $meta_field->getAttribute('property');
            if ($meta_field_property === "og:image") {
                $meta_field_content = $meta_field->getAttribute("content");
                if (!empty($meta_field_content)) {
                    $report['meta_image'] = true;
                }
            }
        }

        $report['meta_title_text'] = '';
        $report['meta_title_keyword_found'] = false;
        $report['meta_title_duplicate'] = false;
        $titles = $dom->getElementsByTagName("title");
        if (count($titles)) {
            $report['meta_title_text'] = $titles[0]->textContent;
            $report['meta_title_keyword_found'] = self::has_keywords($report['meta_title_text'], 'in_title_tag');
            $report['meta_title_duplicate'] = self::meta_title_is_unique($id, $titles[0]->textContent);
            $report['meta_titles'] = self::get_all_fields('meta_title_text');
        }
        return $report;
    }


    public static function clean_html($html)
    {
        $text = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);
        return strip_tags($text);
    }


    public static function meta_description_is_unique(int $id, string $meta_field_content): bool
    {
        $description = \SeoAudit\Helpers::string_to_id($meta_field_content);

        $all_descriptions = self::get_all_fields('meta_description_text');

        $current_description = $all_descriptions[$description] ?? [];

        if (count($current_description) > 1 || (count($current_description) == 1 && !in_array($id, $current_description))) {
            return true;
        }
        return false;
    }


    private static function get_all_fields(string $field): array
    {
        $data = [];
        $files = scandir(YOLSA_REPORT_DIR);

        foreach ($files as $file) {
            $full_path = YOLSA_REPORT_DIR . "/" . $file;
            $ext = self::getFileExtension($full_path);
            if ($file !== ".." && $file !== "." && $ext === 'json' && file_exists($full_path)) {

                $report = json_decode(file_get_contents($full_path), TRUE);
                if (isset($report[$field])) {
                    $field_value = \SeoAudit\Helpers::string_to_id($report[$field]);
                    $id = (int)$report['id'];
                    if (!isset($data[$field_value])) {
                        $data[$field_value] = [];
                    }
                    $data[$field_value][] = $id;
                }
            }
        }
        return $data;
    }

    private static function meta_title_is_unique(int $id, string $meta_title): bool
    {
        $title = \SeoAudit\Helpers::string_to_id($meta_title);
        $all_meta_titles = self::get_all_fields('meta_title_text');
        $current_meta_title = $all_meta_titles[$title] ?? [];
        if (count($current_meta_title) > 1 || count($current_meta_title) == 1 && !in_array($id, $current_meta_title)) {
            return true;
        }
        return false;
    }

    public static function getFileExtension($file)
    {
        $ext = explode(".", $file);
        $ext = end($ext);
        return $ext;
    }


    public static function string_to_id($text)
    {
        $return_text = strip_tags($text);
        $return_text = preg_replace('/\s+/', ' ', $return_text);
        $return_text = sanitize_title($text);
        return trim($return_text);
    }

    public static function get_language(\DOMDocument $doc, array $report): array
    {
        $lang = false;
        $html = $doc->getElementsByTagName('html');
        if ($html && isset($html[0])) {
            $lang = $html[0]->getAttribute('lang');
        }
        $report['lang'] = $lang;
        return $report;
    }


}
