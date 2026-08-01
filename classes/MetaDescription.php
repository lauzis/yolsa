<?php

namespace SeoAudit;

/**
 * Generates SEO meta descriptions.
 *
 * Replaces ChatBot and ChatGptApi, which were two implementations of this same
 * job — one plain chat completions over raw cURL, one built on OpenAI's
 * Assistants API. The provider call now comes from lauzis/wp-plugin-packages;
 * what remains here is the part that is actually YoLSA's: the prompt.
 */
class MetaDescription
{
    /** Meta descriptions are only useful if they fit in a search result. */
    public const MAX_LENGTH = 200;

    /**
     * Asks the model for a meta description.
     *
     * @param string   $content       Article text, already stripped of markup.
     * @param string[] $keywords      Keywords to work in.
     * @param bool     $forceKeywords Require at least one keyword to appear.
     * @param string   $language      Optional language hint.
     * @return string|\WP_Error The description, or an error to surface.
     */
    public static function generate(
        string $content,
        array $keywords = [],
        bool $forceKeywords = false,
        string $language = ''
    ) {
        $content = trim($content);

        if ('' === $content) {
            return new \WP_Error('yolsa_no_content', __('There is no content to summarise.', 'yolsa'));
        }

        if (!class_exists('WpPackages_Registry')) {
            return new \WP_Error('yolsa_no_llm', __('The shared LLM component is unavailable.', 'yolsa'));
        }

        $text = \WpPackages_Registry::llm('yolsa')->complete(
            self::prompt($keywords, $forceKeywords, $language),
            $content
        );

        if (is_wp_error($text)) {
            return $text;
        }

        return self::tidy($text);
    }

    /**
     * Builds the system prompt from the configured instructions.
     *
     * @param string[] $keywords
     */
    private static function prompt(array $keywords, bool $forceKeywords, string $language): string
    {
        $settings = \WpPackages_Registry::settings('yolsa');

        $parts = [(string) $settings->get('assistant_instructions', '')];

        if ($keywords) {
            $key = $forceKeywords ? 'assistant_keyword_instructions_force' : 'assistant_keyword_instructions';
            $parts[] = trim((string) $settings->get($key, '')) . ' ' . implode(', ', $keywords) . '.';
        }

        $parts[] = (string) $settings->get('assistant_run_instructions', '');

        if ('' !== $language) {
            /* translators: %s: language name or code */
            $parts[] = sprintf(__('Write it in %s.', 'yolsa'), $language);
        }

        $parts[] = sprintf(
            /* translators: %d: maximum number of characters */
            __('Reply with the description only — no quotes, no preamble, no more than %d characters.', 'yolsa'),
            self::MAX_LENGTH
        );

        return implode(' ', array_filter(array_map('trim', $parts)));
    }

    /**
     * Cleans up what models habitually add: surrounding quotes, a "Meta
     * description:" preamble, and stray whitespace.
     */
    private static function tidy(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^(meta\s+description|description|summary)\s*:\s*/i', '', $text) ?? $text;
        $text = trim($text);

        // Strip a matched pair of surrounding quotes, not a lone apostrophe.
        if (preg_match('/^([\'"“”])(.*)\1$/su', $text, $m) || preg_match('/^“(.*)”$/su', $text, $m)) {
            $text = trim($m[count($m) - 1]);
        }

        return $text;
    }
}
