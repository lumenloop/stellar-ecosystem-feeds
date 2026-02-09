# Stellar Ecosystem Feeds

Per-project RSS feeds for the Stellar blockchain ecosystem, auto-generated from [Lumen Loop](https://lumenloop.com)'s article database.

## Structure

```
feeds/
  abroad.xml
  allbridge.xml
  aquarius.xml
  ...
```

Each XML file is a valid RSS 2.0 feed with:
- Full article content in `<content:encoded>`
- Publication dates, authors, and images
- Self-referencing `<atom:link>` for feed readers

## Stats

- **79** project feeds
- **343** articles matched
- **3** articles per feed (most recent)

## Sources

Articles are collected from:
- **Project websites** — blog posts crawled from project domains
- **Medium / Substack** — matched via project blog links
- **Twitter/X articles** — matched via project Twitter accounts

All articles must mention Stellar ecosystem keywords (stellar, xlm, soroban) and have been processed through our content pipeline.

## Usage

Subscribe to any feed using the raw GitHub URL:

```
https://raw.githubusercontent.com/lumenloop/stellar-ecosystem-feeds/main/feeds/{project-slug}.xml
```

Example for Abroad:
```
https://raw.githubusercontent.com/lumenloop/stellar-ecosystem-feeds/main/feeds/abroad.xml
```

## Updates

Feeds are regenerated via the [directory-sync](https://github.com/lumenloop/stellar-ecosystem-db) pipeline. Each run diffs against the existing feeds and only commits changes.

## Legacy System

This repo previously used [RSS-Bridge](https://github.com/RSS-Bridge/rss-bridge) with custom CSS bridges to generate feeds. Each bridge was configured with CSS selectors to scrape article content from project websites — for example using `CSSLostDateBridge` with `url_selector`, `content_selector`, and `date_selector` parameters. The old bridges can be found in the `rss-bridges/` folder for reference. The current system replaces this with automated article matching from our centralized content pipeline.

## Related

- [stellar-ecosystem-db](https://github.com/lumenloop/stellar-ecosystem-db) — YAML project database + ecosystem report
