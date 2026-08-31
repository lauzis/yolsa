<?php

namespace SeoAudit;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * The per-term SEO box.
 *
 * One decision: whether this archive belongs in search results. There is no
 * title or description here — a term archive's description is the term's own,
 * and a second one to maintain would be a second one to forget.
 *
 * The field reads differently depending on the taxonomy it is on. On a taxonomy
 * hidden wholesale, the useful action is putting one term back, so the options
 * say that; on a visible one, the useful action is hiding a term. Same three
 * stored values either way.
 */
class TermSeoBox
{
    /** Declares the box on every public taxonomy. Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        foreach (array_keys(SeoMeta::taxonomyOptions()) as $taxonomy) {
            $hidden = SeoMeta::isNoIndexTaxonomy($taxonomy);

            Container::make('term_meta', __('Search engines', 'yolsa'))
                ->where('term_taxonomy', '=', $taxonomy)
                ->add_fields([
                    Field::make('select', 'yolsa_robots_index', __('This archive in search results', 'yolsa'))
                        ->set_options(self::options($hidden))
                        ->set_help_text(self::help($hidden)),
                ]);
        }
    }

    /**
     * @param bool $hidden Whether the whole taxonomy is hidden.
     * @return array<string, string>
     */
    private static function options(bool $hidden): array
    {
        if ($hidden) {
            return [
                '' => __('Hidden — like every archive of this kind', 'yolsa'),
                'index' => __('Put this one in search results anyway', 'yolsa'),
                'noindex' => __('Hidden', 'yolsa'),
            ];
        }

        return [
            '' => __('Shown — like every archive of this kind', 'yolsa'),
            'noindex' => __('Hide this one from search results', 'yolsa'),
            'index' => __('Shown', 'yolsa'),
        ];
    }

    /**
     * @param bool $hidden
     * @return string
     */
    private static function help(bool $hidden): string
    {
        if ($hidden) {
            return __('Every archive of this kind is hidden on the Indexing page. Putting one back here also adds it to the sitemap.', 'yolsa');
        }

        return __('Hiding an archive here also removes it from the sitemap. The posts it lists keep their own settings.', 'yolsa');
    }
}
