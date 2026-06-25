<?php

namespace SeoAudit;

class SelfTest
{
    private static string $test_keyword = 'yolsa-selftest-xyz';

    private static function setup_keywords(): void
    {
        Helpers::$keywords = [[
            'keyword' => self::$test_keyword,
            'variations' => [],
            'count' => 0,
            'count_exact' => 0,
            'count_phrase' => 0,
            'place_where_found' => [],
        ]];
    }

    private static function base_report(): array
    {
        return [
            'status' => 200,
            'url' => 'https://self-test.internal/',
            'id' => 0,
            'timestamp' => time(),
            'keywords' => [],
            'local_keywords' => false,
        ];
    }

    private static function run_html(string $html): array
    {
        self::setup_keywords();
        return Helpers::audit_html($html, self::base_report());
    }

    public static function get_all_tests(): array
    {
        return [
            'meta_description_detected' => [
                'label' => 'Meta description detected when present',
                'group' => 'Meta Description',
            ],
            'meta_description_missing' => [
                'label' => 'Missing meta description is flagged',
                'group' => 'Meta Description',
            ],
            'h1_detected' => [
                'label' => 'H1 tag detected when present',
                'group' => 'H1 Tag',
            ],
            'h1_missing' => [
                'label' => 'Missing H1 is flagged',
                'group' => 'H1 Tag',
            ],
            'h1_keyword_found' => [
                'label' => 'Keyword found in H1',
                'group' => 'Keyword Detection',
            ],
            'h1_keyword_missing' => [
                'label' => 'Missing keyword in H1 is flagged',
                'group' => 'Keyword Detection',
            ],
            'meta_description_keyword_found' => [
                'label' => 'Keyword found in meta description',
                'group' => 'Keyword Detection',
            ],
            'meta_description_keyword_missing' => [
                'label' => 'Missing keyword in meta description is flagged',
                'group' => 'Keyword Detection',
            ],
            'first_paragraph_keyword_found' => [
                'label' => 'Keyword found in first paragraph',
                'group' => 'Keyword Detection',
            ],
            'first_paragraph_keyword_missing' => [
                'label' => 'Missing keyword in first paragraph is flagged',
                'group' => 'Keyword Detection',
            ],
            'meta_title_keyword_found' => [
                'label' => 'Keyword found in page title',
                'group' => 'Keyword Detection',
            ],
            'meta_title_keyword_missing' => [
                'label' => 'Missing keyword in page title is flagged',
                'group' => 'Keyword Detection',
            ],
            'img_alt_present' => [
                'label' => 'Image with alt text passes correctly',
                'group' => 'Image Alt Tags',
            ],
            'img_alt_missing_detected' => [
                'label' => 'Image missing alt text is flagged',
                'group' => 'Image Alt Tags',
            ],
            'url_analysis' => [
                'label' => 'URL analysis returns valid report for site homepage',
                'group' => 'URL Analysis',
            ],
        ];
    }

    public static function run_test(string $id): array
    {
        $method = 'test_' . $id;
        if (!method_exists(self::class, $method)) {
            return ['pass' => false, 'message' => 'Unknown test: ' . esc_html($id)];
        }
        try {
            return call_user_func([self::class, $method]);
        } catch (\Throwable $e) {
            return ['pass' => false, 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    private static function test_meta_description_detected(): array
    {
        $html = '<html><head><meta name="description" content="A description here"></head><body><h1>Title</h1></body></html>';
        $report = self::run_html($html);
        $pass = $report['meta_description'] === true;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'meta_description=true when <meta name="description"> is present'
                : 'Expected meta_description=true, got ' . var_export($report['meta_description'], true),
        ];
    }

    private static function test_meta_description_missing(): array
    {
        $html = '<html><head><title>Test Page</title></head><body><h1>Title</h1></body></html>';
        $report = self::run_html($html);
        $pass = $report['meta_description'] === false;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'meta_description=false when no <meta name="description"> is present'
                : 'Expected meta_description=false, got ' . var_export($report['meta_description'], true),
        ];
    }

    private static function test_h1_detected(): array
    {
        $html = '<html><head></head><body><h1>My Heading</h1><p>Content</p></body></html>';
        $report = self::run_html($html);
        $pass = isset($report['h1_count']) && $report['h1_count'] >= 1;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'h1_count=' . $report['h1_count'] . ' when H1 is present'
                : 'Expected h1_count>=1, got ' . ($report['h1_count'] ?? 'unset'),
        ];
    }

    private static function test_h1_missing(): array
    {
        $html = '<html><head></head><body><p>No heading here</p></body></html>';
        $report = self::run_html($html);
        $pass = isset($report['h1_count']) && $report['h1_count'] === 0;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'h1_count=0 when no H1 tag is present'
                : 'Expected h1_count=0, got ' . ($report['h1_count'] ?? 'unset'),
        ];
    }

    private static function test_h1_keyword_found(): array
    {
        $kw = self::$test_keyword;
        $html = '<html><head></head><body><h1>Page about ' . $kw . '</h1><p>Content</p></body></html>';
        $report = self::run_html($html);
        $pass = isset($report['h1_found_keyword']) && $report['h1_found_keyword'] >= 1;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'h1_found_keyword=' . $report['h1_found_keyword'] . ' when keyword is in H1'
                : 'Expected h1_found_keyword>=1, got ' . ($report['h1_found_keyword'] ?? 'unset'),
        ];
    }

    private static function test_h1_keyword_missing(): array
    {
        $html = '<html><head></head><body><h1>Page without the keyword</h1><p>Content</p></body></html>';
        $report = self::run_html($html);
        $pass = isset($report['h1_found_keyword']) && $report['h1_found_keyword'] === 0;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'h1_found_keyword=0 when keyword is absent from H1'
                : 'Expected h1_found_keyword=0, got ' . ($report['h1_found_keyword'] ?? 'unset'),
        ];
    }

    private static function test_meta_description_keyword_found(): array
    {
        $kw = self::$test_keyword;
        $html = '<html><head><meta name="description" content="' . $kw . ' explained here"></head><body></body></html>';
        $report = self::run_html($html);
        $pass = $report['meta_description_keyword_found'] === true;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'meta_description_keyword_found=true when keyword is in meta description'
                : 'Expected true, got ' . var_export($report['meta_description_keyword_found'], true),
        ];
    }

    private static function test_meta_description_keyword_missing(): array
    {
        $html = '<html><head><meta name="description" content="A description without the test keyword"></head><body></body></html>';
        $report = self::run_html($html);
        $pass = $report['meta_description_keyword_found'] === false;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'meta_description_keyword_found=false when keyword is absent from meta description'
                : 'Expected false, got ' . var_export($report['meta_description_keyword_found'], true),
        ];
    }

    private static function test_first_paragraph_keyword_found(): array
    {
        $kw = self::$test_keyword;
        $html = '<html><head></head><body><p>This paragraph mentions ' . $kw . ' explicitly.</p></body></html>';
        $report = self::run_html($html);
        $pass = $report['first_paragraph_found_keywords'] === true;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'first_paragraph_found_keywords=true when keyword is in first paragraph'
                : 'Expected true, got ' . var_export($report['first_paragraph_found_keywords'], true),
        ];
    }

    private static function test_first_paragraph_keyword_missing(): array
    {
        $html = '<html><head></head><body><p>This paragraph contains no keyword at all.</p></body></html>';
        $report = self::run_html($html);
        $pass = $report['first_paragraph_found_keywords'] === false;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'first_paragraph_found_keywords=false when keyword is absent from first paragraph'
                : 'Expected false, got ' . var_export($report['first_paragraph_found_keywords'], true),
        ];
    }

    private static function test_meta_title_keyword_found(): array
    {
        $kw = self::$test_keyword;
        $html = '<html><head><title>' . $kw . ' - My Site</title></head><body></body></html>';
        $report = self::run_html($html);
        $pass = $report['meta_title_keyword_found'] === true;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'meta_title_keyword_found=true when keyword is in page title'
                : 'Expected true, got ' . var_export($report['meta_title_keyword_found'], true),
        ];
    }

    private static function test_meta_title_keyword_missing(): array
    {
        $html = '<html><head><title>A Title Without The Test Keyword</title></head><body></body></html>';
        $report = self::run_html($html);
        $pass = $report['meta_title_keyword_found'] === false;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'meta_title_keyword_found=false when keyword is absent from page title'
                : 'Expected false, got ' . var_export($report['meta_title_keyword_found'], true),
        ];
    }

    private static function test_img_alt_present(): array
    {
        $html = '<html><head></head><body><img src="test.jpg" alt="A descriptive alt text"></body></html>';
        $report = self::run_html($html);
        $missing = $report['img_alt_missing'] ?? [];
        $pass = count($missing) === 0;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'img_alt_missing is empty when all images have alt text'
                : 'Expected 0 missing alt tags, got ' . count($missing),
        ];
    }

    private static function test_img_alt_missing_detected(): array
    {
        $html = '<html><head></head><body><img src="no-alt.jpg"></body></html>';
        $report = self::run_html($html);
        $missing = $report['img_alt_missing'] ?? [];
        $pass = count($missing) >= 1;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'img_alt_missing contains image src when alt attribute is absent'
                : 'Expected >=1 entry in img_alt_missing, got ' . count($missing),
        ];
    }

    private static function test_url_analysis(): array
    {
        $url = get_site_url();
        $result = Helpers::get_HTML($url);

        if (empty($result['content'])) {
            return [
                'pass' => false,
                'message' => 'Could not fetch HTML from ' . esc_url($url) . ' (HTTP ' . intval($result['status']) . ')',
            ];
        }

        self::setup_keywords();
        $report = Helpers::audit_html($result['content'], [
            'status' => $result['status'],
            'url' => $url,
            'id' => 0,
            'timestamp' => time(),
            'keywords' => [],
            'local_keywords' => false,
        ]);

        $required_keys = ['h1_count', 'p_count', 'a_count', 'img_count', 'meta_description', 'meta_title_text', 'first_paragraph'];
        $missing_keys = [];
        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $report)) {
                $missing_keys[] = $key;
            }
        }

        $pass = count($missing_keys) === 0;
        return [
            'pass' => $pass,
            'message' => $pass
                ? 'Valid report returned for ' . esc_url($url) . ' (HTTP ' . intval($result['status']) . ')'
                : 'Report missing keys: ' . implode(', ', $missing_keys),
        ];
    }
}
