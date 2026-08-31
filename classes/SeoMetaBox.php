<?php

namespace SeoAudit;

use Carbon_Fields\Container;
use Carbon_Fields\Field;

/**
 * The per-post SEO box.
 *
 * Four fields, which is what a person editing an article actually needs: the
 * title and description a search engine shows, and the pair a social network
 * shows. No score, no traffic light, no readability essay.
 *
 * The field names are deliberate. Carbon Fields stores post meta under
 * `_<name>`, so `yolsa_meta_title` lands on `_yolsa_meta_title` — the key
 * `SeoMeta` already reads and the REST API already registers. Nothing had to be
 * mapped, and a value typed here is the value the head tag renders.
 *
 * Empty is a real answer: `SeoMeta::read()` falls back to the Yoast key for
 * anything written while Yoast was the active SEO plugin, and to WordPress'
 * own title after that. So an empty box means "whatever was there before",
 * not "blank".
 */
class SeoMetaBox
{
    /**
     * Post types the box appears on.
     *
     * The same set `SeoMeta::registerFallbackMeta()` registers for REST, minus
     * attachments — an image has no meta description worth writing.
     *
     * @return array<int, string>
     */
    public static function postTypes(): array
    {
        $types = get_post_types(['public' => true]);
        unset($types['attachment']);

        return array_values($types);
    }

    /**
     * Tells the editor which picture will be shared, or that none will.
     *
     * @return string
     */
    public static function ogImageNotice(): string
    {
        $postId = (int) get_the_ID();

        if (!$postId) {
            return '';
        }

        $image = SeoMeta::getOgImage($postId);

        if (!$image) {
            return '<p><strong>' . esc_html__('No featured image.', 'yolsa') . '</strong> '
                . esc_html__('Set one and it becomes the picture Facebook, Draugiem and the rest show when this page is shared. Without it they pick something from the page, or show nothing.', 'yolsa')
                . '</p>';
        }

        $size = ($image['width'] && $image['height'])
            ? sprintf(' (%d×%d)', $image['width'], $image['height'])
            : '';

        // 1200×630 is the size the large card is cut to; below it the networks
        // fall back to a small square thumbnail beside the text.
        $small = ($image['width'] && $image['width'] < 1200)
            ? ' ' . esc_html__('It is under 1200px wide, so it may be shown as a small thumbnail rather than a large card.', 'yolsa')
            : '';

        return '<p>' . esc_html__('Shared as:', 'yolsa') . ' <code>' . esc_html(basename($image['url'])) . '</code>'
            . esc_html($size) . ' — ' . esc_html__('the featured image.', 'yolsa') . $small . '</p>';
    }

    /** Declares the box. Hooked on carbon_fields_register_fields. */
    public static function register(): void
    {
        $container = Container::make('post_meta', __('SEO', 'yolsa'))
            ->set_context('normal')
            ->set_priority('default');

        $types = self::postTypes();

        if (!$types) {
            return;
        }

        $container->where('post_type', 'IN', $types);

        $fields = [];

        // Said once, at the top, rather than repeated under every field: while
        // another SEO plugin owns the head, what is typed here is stored but
        // not rendered, and an editor deserves to know that before typing.
        if (SeoMeta::isHandledElsewhere()) {
            $fields[] = Field::make('html', 'yolsa_seo_notice')
                ->set_html(
                    '<p><em>' . esc_html__(
                        'Another SEO plugin is writing the meta tags on this site, so YoLSA is staying out of the page head. These values are saved, and are used as soon as that plugin is switched off.',
                        'yolsa'
                    ) . '</em></p>'
                );
        }

        // The social picture is the featured image, so the only thing to say
        // here is whether there is one — said next to the fields somebody is
        // already editing, rather than in an audit they have to go and read.
        $fields[] = Field::make('html', 'yolsa_og_image_notice')
            ->set_html([self::class, 'ogImageNotice']);

        $fields[] = Field::make('text', 'yolsa_meta_title', __('Search engine title', 'yolsa'))
            ->set_help_text(__('Shown as the clickable heading in search results. Around 60 characters before Google truncates it. Empty uses the post title.', 'yolsa'));

        $fields[] = Field::make('textarea', 'yolsa_meta_description', __('Search engine description', 'yolsa'))
            ->set_rows(3)
            ->set_help_text(__('The sentence under the heading in search results. Around 155 characters. Empty means the search engine picks a passage from the article itself.', 'yolsa'));

        $fields[] = Field::make('text', 'yolsa_og_title', __('Social title', 'yolsa'))
            ->set_help_text(__('Used when the article is shared on Facebook, Draugiem and the rest. Empty uses the search engine title above.', 'yolsa'));

        $fields[] = Field::make('textarea', 'yolsa_og_description', __('Social description', 'yolsa'))
            ->set_rows(3)
            ->set_help_text(__('Empty uses the search engine description above.', 'yolsa'));

        $fields[] = Field::make('select', 'yolsa_og_type', __('Content type', 'yolsa'))
            ->set_options(SeoMeta::ogTypes())
            ->set_default_value('article')
            ->set_help_text(__('What a social network is told this page is, which decides how the share card is drawn. Left alone, a post is an article and a page is a website.', 'yolsa'));

        // Three options rather than a checkbox: unticking a box would look
        // exactly like never having touched it, and the Yoast value this falls
        // back to would then win forever.
        $fields[] = Field::make('select', 'yolsa_robots_index', __('Search engines', 'yolsa'))
            ->set_options([
                '' => __('Default — whatever the site allows', 'yolsa'),
                'noindex' => __('Hide this page from search results (noindex)', 'yolsa'),
                'index' => __('Always allow this page in search results', 'yolsa'),
            ])
            ->set_help_text(__('Default keeps any setting made in another SEO plugin for this post. Hidden pages stay reachable by anyone with the link — this only asks search engines to leave them out.', 'yolsa'));

        $container->add_fields($fields);
    }
}
