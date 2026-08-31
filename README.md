# YoLSA - Your Local SEO Auditor
  - Pages and posts are validated against SEO good practices
    - Page title
    - Meta description
    - H1
    - Alt tags
    - Other
  - Checks if there is not duplicate page titles or and meta descriptions
  - Possible to generate meta description via ChatGPT
- Keyword audit
  - Checks for keywords and if they are used exact match or phrase match
  - SEO audit shows if keywords are used in important parts of the site
  - Lists keywords and shows how often particular keyword is used and in which pages
- Self-test page to verify core audit logic is working correctly

# What it does
- Scans content pages (pages/posts) and validates against SEO best practices
- Also checks if the content has keywords in important sections of the content
- Possible to update meta description / generate it through ChatGPT, based on content of particular page
- Gives "penalty" score for each content item

# Prerequisites
- Carbon Fields (installed via Composer)
- Yoast SEO plugin (optional — if active, Yoast meta fields are used automatically; otherwise the plugin stores meta in its own `_yolsa_*` fields)

# Todos and ides
- Get data from search tool, what are keywords that gets visits
- Crawl versioning / compare
- Add possibility to list what post types should be analysed
- Add possibility to add some pages to ignore list
- Move the default chat gpt context out to the settings page
- The saving and getting of assistant should be improved, there is something wrong, like we are gettting and 
setting the assistant by the instructions, but only kind of.

# Change log

## Version 1.14.0

- **Terms get their own SEO box.** One decision per category or tag: whether that archive belongs in search results. No title or description — a term archive already has the term's own description, and a second one to maintain is a second one to forget.
- **Hiding a kind of thing is now a default, not a verdict.** Hide every tag archive on the Indexing page, then put back the handful worth having, one at a time. The exception reaches the sitemap too: a hidden taxonomy still publishes the terms that were put back, and a hidden post type still publishes the posts that were.
- **The boxes read the way the decision actually runs.** Where everything of a kind is hidden, the choice offered is *"Put this one in search results anyway"* rather than *"Hide this page"* — asking somebody to hide what is already hidden reads as though nothing they do there matters. Same three stored values either way.
- Fixed a way a setting could quietly lose a value: these lists of post types and taxonomies are built when the fields are declared, on `init` at priority zero, while plugins register their own types at priority ten. A choice whose type had not registered yet was a choice the field did not recognise, and a multi-value field drops what it does not recognise when it saves. Anything already stored now stays on the list.

## Version 1.13.0

- **Password-protected posts are never indexed and never listed.** A search engine reaching one sees the password form, not the article, so indexing it means ranking an empty page. This sits ahead of every other rule — "Always allow this page" cannot overrule it, because there is nothing behind it to allow. WordPress' own sitemap does not exclude them either; this one does.
- The sitemap's post query now runs core's own arguments, `wp_sitemaps_posts_query_args` filter included. Assembling the list per language meant building the query here, and a query standing in for core's does not get to quietly drop what core promised — including anything else hooked onto that filter.

## Version 1.12.0

- **The sitemap covers every language.** WordPress builds one sitemap in one language and registers no language-prefixed route — `/en/wp-sitemap.xml` is a 404 — so on a multilingual site three languages out of four would have had no sitemap at all. The lists are now assembled by walking each active language and paginated afterwards.
- Each address is taken while its own language is active. Asked in the wrong one, WordPress answers without the language prefix, which is a URL belonging to a different post.
- Deduplicated. An untranslated post answers with the default language's address in every language; on the site this was written for that is how the previous plugin's sitemap came to list **420 entries for 161 distinct URLs**, the same page up to four times. This one lists 420 URLs, none of them twice.
- The assembled lists join the existing cache: rebuilt when a post is saved or deleted, when the settings are saved, and once a day.

## Version 1.11.0

- **The sitemap writes to the log**, like the audit already does: one line when the list of hidden posts is built (post type, how many, how long), one when the daily rebuild finishes (totals, duration, and whether the schedule or a person triggered it), and one when a settings save clears the cache, naming what is now hidden.
- A failed query is logged as an error rather than passing quietly. An empty list looks exactly like "nothing is hidden", which would put every hidden page back into the sitemap — the one outcome worth shouting about, so it also refuses to cache that result.
- `save_post` deliberately does **not** log. It fires on every autosave, and a log line per keystroke is a log nobody reads.

## Version 1.10.0

- **An XML sitemap**, built on WordPress' own provider rather than a second implementation of one. Core has had paginated sitemaps since 5.5; what it lacks is any idea which pages this site wants hidden, and that is the part worth writing. While another SEO plugin is active YoLSA leaves core's sitemap switched off exactly as it found it — two sitemaps disagreeing about a site is worse than either.
- The sitemap obeys the Indexing page and the per-post setting: a hidden post type or taxonomy is left out, and so is any post that asked to be hidden — including one still inheriting Yoast's `noindex`. A page telling search engines to stay away while the sitemap invites them in is the site contradicting itself.
- Attachments are left out. Media pages carry no content of their own and are the classic way to fill a sitemap with nothing.
- The list of hidden posts is **one cached query, rebuilt when a post is saved or deleted, when the settings are saved, and once a day** by `yolsa_sitemap_rebuild`. Expressing the same rule as a `meta_query` meant several LEFT JOINs against a large postmeta table and did not finish inside two minutes; the query behind the cache takes 0.04s. The daily pass is the backstop for what changes without anybody saving anything — a scheduled publication going live, a bulk delete, an import.
- **The keyword list is its own page** under the YoLSA menu, and **Settings is now last** in the submenu. The keyword list is the working material of this plugin, edited far more often than the configuration; it should not sit behind a tab below it.

## Version 1.9.0

- **Indexing is its own page** under the YoLSA menu, not a tab inside Settings. Which pages are missing from Google is not a preference — it is a decision about what the site shows the world, and it should be findable without remembering which tab it was on.
- Nothing moved but the screen: the same two settings, stored under the same names, so a site that had already ticked something keeps it.

## Version 1.8.0

- **An Indexing tab in the settings**, so whole kinds of page can be kept out of search results without hiding them one at a time: tick the post types to hide, and the taxonomies whose archives to hide. Both lists are built from whatever this site actually has, not from a fixed list.
- Precedence, most specific first: a post's own "Search engines" setting, then a `noindex` inherited from Yoast, then the blanket rule for its type. So "Always allow this page" overrules a hidden post type, and a hidden type does not quietly re-hide a page somebody deliberately allowed.
- Taxonomy archives are hidden through the same `wp_robots` filter as posts, so a category listing declares `noindex, follow` — the listing stays out of the index while the posts it links to keep their own settings.

## Version 1.7.0

- **`og:type` is now a dropdown** in the SEO box — Article, Website, Profile or Video — defaulting to Article. The list is short on purpose: Open Graph defines many more, but the interesting ones only mean anything alongside properties this plugin does not collect, and declaring `product` without a price says less than saying nothing.
- Left alone it behaves as before: a post is an `article`, a page is a `website`. So nothing written before this field existed changes what it declares, and an unrecognised stored value falls back to that same rule rather than being emitted.

## Version 1.6.0

- **`og:image` from the featured image.** No second picture field: the featured image is the one an editor has already chosen and can see, and a second one to maintain is a second one to forget. `og:image:width`, `og:image:height` and `og:image:alt` go out with it, so a network can lay the card out before the picture has loaded.
- A post **without** a featured image gets no `og:image` rather than a stand-in — a wrong picture travels further than a missing one. The SEO box says so instead: "No featured image. Set one and it becomes the picture Facebook, Draugiem and the rest show." When there is one, it names the file and its size, and warns when it is under 1200px wide and will be shown as a small thumbnail rather than a large card.
- **`og:url`, `og:type` and `og:locale`** are derived too — the permalink, `article` for posts and `website` for pages, and the locale the request is being served in, which under WPML is the language of the translation being read.

## Version 1.5.0

- **Keep a page out of search results.** A "Search engines" setting in the SEO box with three options: default, hide (`noindex`), or always allow. Applied through the `wp_robots` filter, so WordPress assembles one robots tag rather than two contradictory ones.
- Falls back to Yoast's `_yoast_wpseo_meta-robots-noindex`, so pages already hidden there stay hidden the moment Yoast is switched off — on this site that is 16 pages, including the download page and the e-mail template page.
- Three options rather than a checkbox on purpose: unticking a box is indistinguishable from never having touched it, and the inherited Yoast value would then win forever. "Always allow" is how you overrule an old Yoast noindex.

## Version 1.4.0

- Added a **SEO box on the post editor**: search engine title and description, social title and description. Four fields, no score and no traffic light — the things a person editing an article actually decides.
- The fields write straight to the keys YoLSA already reads (`_yolsa_meta_title` and friends), so a value typed here is the value the head tag renders, with no mapping in between.
- Empty stays meaningful: a blank field falls back to whatever Yoast stored for that post, and to WordPress' own title after that. Nothing has to be filled in for the switch away from another SEO plugin to be safe.
- While another SEO plugin owns the page head, the box says so — the values are still saved, and take effect as soon as that plugin is switched off.

## Version 1.3.2

- Fixed: the block editor could not save any post while the plugin was active. The four SEO fields are registered for REST and every key starts with an underscore, which makes them protected meta — and WordPress defaults protected meta to an auth callback that refuses everybody. The editor posts every registered field back on save, core cannot skip one that has no stored row yet, so a post without SEO text was refused with "Sorry, you are not allowed to edit the _yolsa_meta_description custom field", administrators included. Whoever may edit the post may now edit its SEO text.

## Version 1.3.1

- Added a **Send a test message** button beside the Slack webhook field. It posts to whatever is in the field, saved or not, waits for Slack's answer and reports it — log traffic is fire-and-forget, so a webhook Slack rejects otherwise fails silently.

## Version 1.3.0

- Log entries can be sent to **Slack**. Two fields on the Logging settings: an incoming webhook URL, and whether Slack gets errors only (the default) or every entry. Errors are posted even with file logging switched off — an audit runs unattended, so a failure that only reaches a log file is a failure nobody reads until the next time somebody opens the Logs page.
- Sending is fire-and-forget, so an audit never waits on Slack; the trade-off is that a webhook Slack rejects fails quietly. Only `https://` URLs are used, since the webhook URL is itself a credential.
- "Every log entry" means one request per entry against a webhook Slack rate-limits to about a message a second, so it suits a specific investigation rather than everyday use.
- Bundled shared library updated to wp-plugin-packages 1.15.0.

## Version 1.0.16 (2026-04-25)

### Highlights
- ✨ **Complete Rebranding**: Now "YoLSA - Your Local SEO Auditor"
- 🔄 **Migrated to Carbon Fields**: No longer requires ACF PRO license
- 📦 **Composer Integration**: Modern dependency management
- 🎨 **Consistent Naming**: All files, constants, and references updated to "yolsa"

### ⚠️ Important Breaking Changes
- Settings must be re-entered (field names changed)
- Plugin file renamed - WordPress will deactivate/reactivate plugin
- Requires `composer install` for deployment
- Upload directory path changed from `/seo-audit/` to `/yolsa/`

See the **Change log** section above for detailed information about all versions.

---

--- initial MVP---

> This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).

## Meta output

YoLSA writes the description, Open Graph tags and document title into single
posts and pages — but only when nothing else is doing it.

It stays out of the head entirely while Yoast, Rank Math, SEOPress, All in One
SEO, The SEO Framework or Slim SEO is active, because two plugins each emitting
a meta description is worse than neither. The list is a guess and can only ever
be one, so `yolsa_seo_handled_elsewhere` lets a site correct it — for a theme
that handles SEO itself, say. The **Output meta tags** setting switches the
whole thing off.

It also says nothing at all unless a value has been stored for that post. An
og:title derived from the post title would duplicate what most themes already
provide, on every page, whether or not YoLSA has ever touched it.

### Which value is used

Reads prefer YoLSA's own key and fall back to Yoast's:

```
description = _yolsa_meta_description || _yoast_wpseo_metadesc
```

Both keys are in play because which one gets *written* depends on whether Yoast
was active at the time. Anything generated while Yoast was installed sits under
its key, and would otherwise disappear the moment Yoast was switched off. The
same fallback applies to the title and both Open Graph values.

A stored title replaces the document title through `pre_get_document_title`
rather than being joined to the site name — an SEO title is a complete title,
not a fragment.
