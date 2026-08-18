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
