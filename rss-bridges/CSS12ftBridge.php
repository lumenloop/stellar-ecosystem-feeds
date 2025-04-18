<?php
class Css12ftBridge extends BridgeAbstract
{
    const MAINTAINER = 'Raph';
    const NAME = 'CSS 12ft Bridge';
    const URI = 'https://github.com/lumenloop/stellar-ecosystem-feeds/rss-bridges/';
    const DESCRIPTION = 'Convert any site to an RSS feed using CSS selectors with an option to disable scripts via 12ft.io (Advanced Users)';
    const PARAMETERS = [
        [
            'home_page' => [
                'name' => 'Site URL: Home page with latest articles',
                'exampleValue' => 'https://example.com/blog/',
                'required' => true
            ],
            'use_12ft_prefix' => [
                'name' => '[Optional] Use 12ft option',
                'title' => 'When enabled, article pages are fetched via the 12ft proxy (https://12ft.io/api/proxy?q=...) while the feed shows the original URL.',
                'type' => 'checkbox'
            ],
            'url_selector' => [
                'name' => 'Selector for article links or their parent elements',
                'title' => 'For example, "a.article" will match all <a class="article" href="...">TITLE</a> elements.',
                'exampleValue' => 'a.article',
                'required' => true
            ],
            'url_pattern' => [
                'name' => '[Optional] Pattern for site URLs to keep in feed',
                'title' => 'Regular expression to filter the URLs.',
                'exampleValue' => '/blog/article/.*'
            ],
            'content_selector' => [
                'name' => '[Optional] Selector to expand each article content',
                'title' => 'When specified, the bridge will fetch each article from its URL and extract content using the provided selector (slower!).',
                'exampleValue' => 'article.content'
            ],
            'content_cleanup' => [
                'name' => '[Optional] Content cleanup: List of items to remove',
                'title' => 'CSS selectors to remove unnecessary parts, e.g. "div.ads, div.comments".',
                'exampleValue' => 'div.ads, div.comments'
            ],
            'title_cleanup' => [
                'name' => '[Optional] Text to remove from expanded article title',
                'title' => 'Specify text to remove from page titles (for example, " | BlogName").',
                'exampleValue' => ' | BlogName'
            ],
            'discard_thumbnail' => [
                'name' => '[Optional] Discard thumbnail set by site author',
                'title' => 'Discard thumbnails that might not be relevant.',
                'type' => 'checkbox'
            ],
            'thumbnail_as_header' => [
                'name' => '[Optional] Insert thumbnail as article header',
                'title' => 'Place the main image at the top of the article content.',
                'type' => 'checkbox'
            ],
            'date_selector' => [
                'name' => '[Optional] Selector for date element',
                'title' => 'CSS selector to locate the date element (for example, "time.published").',
                'exampleValue' => 'time.published'
            ],
            'date_format' => [
                'name' => '[Optional] Date format',
                'title' => 'The date format to parse the date string, e.g. "Y-m-d H:i:s".',
                'exampleValue' => 'Y-m-d'
            ],
            'author_selector' => [
                'name' => '[Optional] Selector for author element',
                'title' => 'CSS selector to locate the author element (for example, "span.author").',
                'exampleValue' => 'span.author'
            ],
            'remove_styling' => [
                'name' => '[Optional] Remove styling',
                'title' => 'If enabled, inline style attributes will be removed from the article content.',
                'type' => 'checkbox'
            ],
            'remove_markup' => [
                'name' => '[Optional] Remove markup',
                'title' => 'If enabled, all HTML tags will be removed from the article content, leaving only plain text.',
                'type' => 'checkbox'
            ],
            'limit' => self::LIMIT
        ]
    ];

    protected $feedName = '';
    protected $homepageUrl = '';

    public function getURI()
    {
        $url = $this->homepageUrl;
        if (empty($url)) {
            $url = parent::getURI();
        }
        return $url;
    }

    public function getName()
    {
        if (!empty($this->feedName)) {
            return $this->feedName;
        }
        return parent::getName();
    }

    /**
     * Returns the 12ft proxy URL for fetching content if the option is enabled.
     * Otherwise, returns the original URL.
     */
    protected function getFetchUrl($url)
    {
        if ($this->getInput('use_12ft_prefix')) {
            return 'https://12ft.io/api/proxy?q=' . urlencode($url);
        }
        return $url;
    }

    public function collectData()
    {
        // Use the provided homepage URL as is to build the article list.
        $this->homepageUrl = $this->getInput('home_page');
        $url_selector         = $this->getInput('url_selector');
        $url_pattern          = $this->getInput('url_pattern');
        $content_selector     = $this->getInput('content_selector');
        $content_cleanup      = $this->getInput('content_cleanup');
        $title_cleanup        = $this->getInput('title_cleanup');
        $discard_thumbnail    = $this->getInput('discard_thumbnail');
        $thumbnail_as_header  = $this->getInput('thumbnail_as_header');
        $date_selector        = $this->getInput('date_selector');
        $date_format          = $this->getInput('date_format');
        $author_selector      = $this->getInput('author_selector');
        $remove_styling       = $this->getInput('remove_styling');
        $remove_markup        = $this->getInput('remove_markup');
        $limit                = $this->getInput('limit') ?? 10;

        $html = defaultLinkTo(getSimpleHTMLDOM($this->homepageUrl), $this->homepageUrl);
        $this->feedName = $this->titleCleanup($this->getPageTitle($html), $title_cleanup);
        $items = $this->htmlFindEntries(
            $html,
            $url_selector,
            $url_pattern,
            $limit,
            $content_cleanup,
            $date_selector,
            $date_format,
            $author_selector
        );

        if (empty($content_selector)) {
            // Apply remove styling/markup options to content from the home page entries
            if (($remove_styling || $remove_markup) && is_array($items)) {
                foreach ($items as &$item) {
                    if (isset($item['content'])) {
                        if ($remove_styling) {
                            // Remove inline style attributes
                            $item['content'] = preg_replace('/\s*style=("|\').*?("|\')/i', '', $item['content']);
                        }
                        if ($remove_markup) {
                            // Strip all HTML tags
                            $item['content'] = strip_tags($item['content']);
                        }
                    }
                }
            }
            $this->items = $items;
        } else {
            foreach ($items as $item) {
                // Pass the original URL to expandEntryWithSelector so that it is used for the feed.
                // The fetching, however, is done using the 12ft proxy if enabled.
                $expandedItem = $this->expandEntryWithSelector(
                    $item['uri'],
                    $content_selector,
                    $content_cleanup,
                    $title_cleanup,
                    $item['title'],
                    $author_selector
                );
                if ($discard_thumbnail && isset($expandedItem['enclosures'])) {
                    unset($expandedItem['enclosures']);
                }
                if ($thumbnail_as_header && isset($expandedItem['enclosures'][0])) {
                    $expandedItem['content'] = '<p><img src="' . $expandedItem['enclosures'][0] . '" /></p>' . $expandedItem['content'];
                }
                // Apply remove styling/markup options to the expanded article content
                if (isset($expandedItem['content'])) {
                    if ($remove_styling) {
                        $expandedItem['content'] = preg_replace('/\s*style=("|\').*?("|\')/i', '', $expandedItem['content']);
                    }
                    if ($remove_markup) {
                        $expandedItem['content'] = strip_tags($expandedItem['content']);
                    }
                }
                $this->items[] = $expandedItem;
            }
        }
    }

    /**
     * Filter a list of URLs using a pattern and limit.
     */
    protected function filterUrlList($links, $url_pattern, $limit = 0)
    {
        if (!empty($url_pattern)) {
            $url_pattern = '/' . str_replace('/', '\/', $url_pattern) . '/';
            $links = array_filter($links, function ($url) use ($url_pattern) {
                return preg_match($url_pattern, $url) === 1;
            });
        }

        if ($limit > 0 && count($links) > $limit) {
            $links = array_slice($links, 0, $limit);
        }

        return $links;
    }

    /**
     * Retrieve title from webpage URL or DOM.
     */
    protected function getPageTitle($page)
    {
        if (is_string($page)) {
            $page = getSimpleHTMLDOMCached($page);
        }
        $title = html_entity_decode($page->find('title', 0)->plaintext);
        return $title;
    }

    /**
     * Clean article title.
     */
    protected function titleCleanup($title, $title_cleanup)
    {
        if (!empty($title) && !empty($title_cleanup)) {
            return trim(str_replace($title_cleanup, '', $title));
        }
        return $title;
    }

    /**
     * Remove all elements from HTML content matching cleanup selector.
     */
    protected function cleanArticleContent($content, $cleanup_selector)
    {
        $string_convert = false;
        if (is_string($content)) {
            $string_convert = true;
            $content = str_get_html($content);
        }

        if (!empty($cleanup_selector)) {
            foreach ($content->find($cleanup_selector) as $item_to_clean) {
                $item_to_clean->outertext = '';
            }
        }

        if ($string_convert) {
            $content = $content->outertext;
        }
        return $content;
    }

    /**
     * Retrieve first N link+title+truncated-content from webpage URL or DOM.
     */
    protected function htmlFindEntries(
        $page,
        $url_selector,
        $url_pattern = '',
        $limit = 0,
        $content_cleanup = null,
        $date_selector = null,
        $date_format = null,
        $author_selector = null
    ) {
        if (is_string($page)) {
            $page = getSimpleHTMLDOM($page);
        }

        $elements = $page->find($url_selector);

        if (empty($elements)) {
            returnClientError('No results for URL selector');
        }

        $link_to_item = [];
        foreach ($elements as $element) {
            $container = $element; // reference to the original element
            $item = [];
            if ($container->innertext != $container->plaintext) {
                $item['content'] = $container->innertext;
            }
            if ($container->tag != 'a') {
                $anchor = $container->find('a', 0);
                if (is_null($anchor)) {
                    continue;
                }
            } else {
                $anchor = $container;
            }
            // Keep the original URL for the RSS feed.
            $item['uri'] = $anchor->href;
            $item['title'] = $anchor->plaintext;

            if (isset($item['content'])) {
                $item['content'] = convertLazyLoading($item['content']);
                $item['content'] = defaultLinkTo($item['content'], $item['uri']);
                $item['content'] = $this->cleanArticleContent($item['content'], $content_cleanup);
            }

            // Extract date if available
            if (!empty($date_selector)) {
                $dateElement = $container->find($date_selector, 0);
                if (!$dateElement && $anchor !== $container) {
                    $dateElement = $anchor->find($date_selector, 0);
                }
                if ($dateElement) {
                    $dateString = trim($dateElement->plaintext);
                    if (!empty($date_format)) {
                        $dt = DateTime::createFromFormat($date_format, $dateString);
                        if ($dt !== false) {
                            $item['timestamp'] = $dt->getTimestamp();
                        } else {
                            $item['timestamp'] = strtotime($dateString);
                        }
                    } else {
                        $item['timestamp'] = strtotime($dateString);
                    }
                }
            }

            // Extract author if available
            if (!empty($author_selector)) {
                $authorElement = $container->find($author_selector, 0);
                if (!$authorElement && $anchor !== $container) {
                    $authorElement = $anchor->find($author_selector, 0);
                }
                if ($authorElement) {
                    $item['author'] = trim($authorElement->plaintext);
                }
            }

            $link_to_item[$anchor->href] = $item;
        }

        if (empty($link_to_item)) {
            returnClientError('The provided URL selector matches some elements, but they do not contain links.');
        }

        $filteredLinks = $this->filterUrlList(array_keys($link_to_item), $url_pattern, $limit);

        if (empty($filteredLinks)) {
            returnClientError('No results for URL pattern');
        }

        $items = [];
        foreach ($filteredLinks as $link) {
            $items[] = $link_to_item[$link];
        }

        return $items;
    }

    /**
     * Retrieve article content from its URL using content selector and return a feed item.
     * If the 12ft option is enabled, the content is fetched via the 12ft proxy while keeping the original URL.
     */
    protected function expandEntryWithSelector(
        $entry_url,
        $content_selector,
        $content_cleanup = null,
        $title_cleanup = null,
        $title_default = null,
        $author_selector = null
    ) {
        if (empty($content_selector)) {
            returnClientError('Please specify a content selector');
        }

        // Use the proxy URL for fetching if the 12ft option is enabled.
        $fetchUrl = $this->getFetchUrl($entry_url);
        $entry_html = getSimpleHTMLDOMCached($fetchUrl);
        $item = html_find_seo_metadata($entry_html);

        // Use the original URL for the RSS feed.
        if (empty($item['uri'])) {
            $item['uri'] = $entry_url;
        }

        if (empty($item['title'])) {
            $article_title = $this->getPageTitle($entry_html);
            if (!empty($title_default) && (empty($article_title) || $article_title === $this->feedName)) {
                $article_title = $title_default;
            }
            $item['title'] = $article_title;
        }

        $item['title'] = $this->titleCleanup($item['title'], $title_cleanup);

        $article_content = $entry_html->find($content_selector);
        if (!empty($article_content)) {
            $article_content = $article_content[0];
            $article_content = convertLazyLoading($article_content);
            // Use the original entry URL for resolving relative links.
            $article_content = defaultLinkTo($article_content, $entry_url);
            $article_content = $this->cleanArticleContent($article_content, $content_cleanup);
            $item['content'] = $article_content;
        } else if (!empty($item['content'])) {
            $item['content'] .= '<br /><p><em>Could not extract full content, selector may need to be updated.</em></p>';
        }

        // Extract date from the full article page if available
        $date_selector = $this->getInput('date_selector');
        $date_format   = $this->getInput('date_format');
        if (!empty($date_selector)) {
            $dateElement = $entry_html->find($date_selector, 0);
            if ($dateElement) {
                $dateString = trim($dateElement->plaintext);
                if (!empty($date_format)) {
                    $dt = DateTime::createFromFormat($date_format, $dateString);
                    if ($dt !== false) {
                        $item['timestamp'] = $dt->getTimestamp();
                    } else {
                        $item['timestamp'] = strtotime($dateString);
                    }
                } else {
                    $item['timestamp'] = strtotime($dateString);
                }
            }
        }

        // Extract author from the full article page if available
        if (!empty($author_selector)) {
            $authorElement = $entry_html->find($author_selector, 0);
            if ($authorElement) {
                $item['author'] = trim($authorElement->plaintext);
            }
        }

        return $item;
    }
}
?>
