# Seo Audit
- Seo Audit 
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
- ACF PRO plugin
- Yoast Seo plugin

# Todos and ides
- Get data from search tool, what are keywords that gets visits
- Crawl versioning / compare
- Add possibility to list what post types should be analysed
- Add possibility to add some pages to ignore list
- Move the default chat gpt context out to the settings page
- The saving and getting of assistant should be improved, there is something wrong, like we are gettting and 
setting the assistant by the instructions, but only kind of.

# Change log
-- version 1.0.15 ---
- adding options for acf, to edit instructions for chat GPT
- adding close button/link added to the popup
- passing locale to chatgpt

-- version 1.0.14 ---
- adding options for acf, to edit instructions for chat GPT
- adding close button/link added to the popup
- passing locale to chatgpt


--- version 1.0.13 ---
- refactoring classes
- moving from conversational api to assistant api
- some code cleanup

--- version 1.0.12 ---
- some refactoring
- added buttons for clearing all the cashed audit data
- instead of auto starting audit added button for starting running the audit
- added keyword audit page / keyword use stats page
- added authentication to the api requests
- local keyword flag for in the table

--- version 1.0.10 ---
- bugfixes and code cleanup
- added table filtering / search
- added fixed administration menu
- keywords coming with a fallback if there is not defined for page then use default keywords

--- initial MVP---

> This project is maintained with the assistance of [Claude Code](https://claude.ai/code) and [CodeRabbit](https://coderabbit.ai).
