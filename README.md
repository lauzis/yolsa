# YoLSA - Your Local SEO Auditor
  - Pages and posts are validated against SEO good practices
    - Page title
    - Meta description
    - H1
    - Alt tags
    - Other
  - Checks if there is not duplicate page titles or and meta descriptions
  - Possible to generate meta description via (chatGPT) (assuming site uses Yoast Seo Plugin)
- Keyword audit
  - Checks for keywords and if they are used exact match or phrase match
  - SEO audit shows if keywords are used in important parts of the site
  - Lists keywords and shows how often particular keyword is used and in witch sites
  

# What it does
- Scans content pages (pages/posts) and validates against soe best practices
- Also checks if the content has keywords in important sections of the content
- Possible to update meta description / generate it trough chatGpt, based on content of particular page
- Gives "penalty" score for each content item

# Prerequisites
- Carbon Fields (installed via Composer)
- Yoast SEO plugin

# Todos and ides
- Get data from search tool, what are keywords that gets visits
- Crawl versioning / compare
- Add possibility to list what post types should be analysed
- Add possibility to add some pages to ignore list
- Move the default chat gpt context out to the settings page
- The saving and getting of assistant should be improved, there is something wrong, like we are gettting and 
setting the assistant by the instructions, but only kind of.

# Change log

## Latest Release: Version 1.0.16 (2026-04-25)

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

**[View Full Changelog](CHANGELOG.md)** for detailed information about all versions.

---

--- initial MVP---

> This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).
