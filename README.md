# stellar-ecosystem-feeds

A snapshot collection of RSS feeds from across the Stellar ecosystem. This repo is **offered as-is** and will not be updated. New feed items will be offered through the Lumen Loop platform.

## rss-bridges

Included in the `rss-bridges/` folder are custom RSS bridges used to generate this feed list. These were created to handle cases where:

- Sites offered truncated RSS feeds
- Sites had no RSS support
- Where content wasn't news but benefitted from rss for triggers or time organized content (json, luma bridge)


## Ways content is collected
At the top of each feed you will information about the custom rss-bridge used and the scrape rules set for each site.

For example a custom CSS Bridge:

```action=display&amp;bridge=CSSLostDateBridge&amp;home_page=https%3A%2F%2Fvibrantapp.com%2Fblog%2F&amp;url_selector=.w-dyn-item+a&amp;url_pattern=%2Fblog%2F.*&amp;content_selector=.w-richtext&amp;content_cleanup=&amp;title_cleanup=&amp;date_selector=&amp;date_format=&amp;date_selector_index=&amp;author_selector=&amp;remove_styling=on&amp;remove_markup=on&amp;limit=3&amp;_cache_timeout=3600&amp;format=Atom```

- bridge= Bridge name - some have been shared under `/rss-bridges/`

- url_selector: CSS selector to find article links on the page.

- url_pattern: regex to match valid article URLs.

- content_selector: CSS selector to extract the article body.

- content_cleanup, title_cleanup: (optional) regex to clean up the content or title.

- date_selector, date_format, date_selector_index: define how and where to extract the article date.

- author_selector: CSS selector for the author (if present).

- remove_styling, remove_markup: strip inline styles and extra HTML for cleaner content. This content will be checked for keywords in the Lumen Loop platform.



Some blog feeds are missing due to recent site changes that require modifications to extract full content (a very small number).

Thanks to these tools, Lumen Loop was able to provide full-content feeds for a broader range of Stellar-related content.

---

Feel free to fork or reuse!
