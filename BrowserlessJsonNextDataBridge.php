<?php
class BrowserlessJsonNextDataBridge extends BridgeAbstract {
    const MAINTAINER = 'Raph';
    const NAME = 'Browserless Json Next Data Bridge';
    const URI = 'https://github.com/lumenloop/stellar-ecosystem-feeds/rss-bridges/';
    const DESCRIPTION = 'Generates an RSS feed from JSON embedded in HTML (inside a script tag) with customizable field mapping via Browserless';
    const MAINTAINER = 'Raph';

    const PARAMETERS = array(
        'Main' => array(
            'browserless_url' => array(
                'name' => 'Browserless Instance URL',
                'required' => true,
                'exampleValue' => 'http://10.0.0.124:3050/'
            ),
            'browserless_token' => array(
                'name' => 'Browserless Token',
                'required' => true,
                'exampleValue' => 'your-token-here'
            ),
            'url' => array(
                'name' => 'Page URL containing JSON',
                'required' => true,
                'exampleValue' => 'https://example.com/page',
            ),
            'items_field' => array(
                'name' => 'JSON field for items array (optional, use dot notation e.g. props.pageProps.articles)',
                'required' => false,
                'exampleValue' => 'props.pageProps.articles'
            ),
            'article_field' => array(
                'name' => 'JSON field for article URL (or slug)',
                'required' => true,
                'exampleValue' => 'publicId',
            ),
            'prefix' => array(
                'name' => 'Prefix for article URL (if needed)',
                'required' => false,
                'exampleValue' => 'https://example.com/articles/',
            ),
            'title_field' => array(
                'name' => 'JSON field for title (optional, supports dot notation)',
                'required' => false,
                'exampleValue' => 'title'
            ),
            'author_field' => array(
                'name' => 'JSON field for author (supports dot notation, e.g. author.name)',
                'required' => false,
                'exampleValue' => 'author.name',
            ),
            'pubdate_field' => array(
                'name' => 'JSON field for publication date',
                'required' => false,
                'exampleValue' => 'publishDate',
            ),
            'pubdate_source' => array(
                'name' => 'Publication date source',
                'type' => 'list',
                'values' => array(
                    'json' => 'json',
                    'scrape' => 'scrape'
                ),
                'defaultValue' => 'json',
                'required' => true,
            ),
            'date_format' => array(
                'name' => 'Date format (optional)',
                'required' => false,
                'exampleValue' => 'Y-m-d\TH:i:s.u\Z'
            ),
            'content_field' => array(
                'name' => 'JSON field for content (optional, replaces scraping)',
                'required' => false,
                'exampleValue' => 'content'
            ),
            'scrape_content_selector' => array(
                'name' => 'Scrape content CSS selector (optional)',
                'required' => false,
                'exampleValue' => '.article-content',
            ),
            'article_json_field' => array(
                'name' => 'JSON field for content in article page (scrape from JSON in a script tag, optional; supports dot notation)',
                'required' => false,
                'exampleValue' => 'props.pageProps.article.content'
            ),
            'content_cleanup_regex' => array(
                'name' => 'Content cleanup regex (optional)',
                'required' => false,
                'exampleValue' => '/<div class="ad">.*?<\/div>/s'
            ),
            'remove_styling' => array(
                'name' => 'Remove styling from content',
                'type' => 'checkbox',
                'required' => false,
                'exampleValue' => 'true'
            ),
            'json_tag' => array(
                'name' => 'Script tag id for JSON (optional)',
                'required' => false,
                'exampleValue' => '__NEXT_DATA__'
            ),
            'limit' => array(
                'name' => 'Limit number of items to process (optional)',
                'required' => false,
                'exampleValue' => '10'
            )
        )
    );

    /**
     * Helper function to extract nested fields using dot notation.
     * For example, if the field is "author.name", then it extracts $data['author']['name'].
     */
    private function extractField($data, $fieldPath) {
        $fields = explode('.', $fieldPath);
        foreach ($fields as $field) {
            if (is_array($data) && isset($data[$field])) {
                $data = $data[$field];
            } else {
                return null;
            }
        }
        return $data;
    }

    /**
     * Fetch raw HTML content via Browserless.
     * Sends a POST request to Browserless at /content?token=... with a JSON payload containing the URL.
     */
    private function fetchContentViaBrowserless($url) {
        $baseUrl = rtrim($this->getInput('browserless_url'), '/');
        $token = $this->getInput('browserless_token');
        $endpoint = $baseUrl . '/content?token=' . urlencode($token);
        $postData = json_encode(['url' => $url]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ]);
        $response = curl_exec($ch);
        if(curl_errno($ch)) {
            returnServerError('cURL error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $response;
    }

    public function collectData(){
        $pageUrl               = $this->getInput('url');
        $itemsField            = $this->getInput('items_field');
        $articleField          = $this->getInput('article_field');
        $prefix                = $this->getInput('prefix');
        $titleField            = $this->getInput('title_field');
        $authorField           = $this->getInput('author_field');
        $pubdateField          = $this->getInput('pubdate_field');
        $pubdateSource         = $this->getInput('pubdate_source');
        $dateFormat            = $this->getInput('date_format');
        $contentField          = $this->getInput('content_field');
        $scrapeContentSelector = $this->getInput('scrape_content_selector');
        $articleJsonField      = $this->getInput('article_json_field');
        $contentCleanupRegex   = $this->getInput('content_cleanup_regex');
        $removeStyling         = $this->getInput('remove_styling');
        $jsonTag               = $this->getInput('json_tag');
        $limitParam            = $this->getInput('limit');

        // Default to __NEXT_DATA__ if json_tag parameter is not set
        if(empty($jsonTag)) {
            $jsonTag = '__NEXT_DATA__';
        }

        // Fetch the HTML content via Browserless from the specified URL
        $htmlContent = $this->fetchContentViaBrowserless($pageUrl);
        if($htmlContent === false) {
            returnServerError('Could not fetch content from: ' . $pageUrl);
        }

        // Extract JSON from the inline script tag with the specified id and type
        $pattern = '/<script\s+id=["\']'.preg_quote($jsonTag, '/').'["\']\s+type=["\']application\/json["\']>(.*?)<\/script>/is';
        if (preg_match($pattern, $htmlContent, $matches)) {
            $jsonContent = $matches[1];
        } else {
            returnServerError('Could not find JSON data in the page.');
        }

        $data = json_decode($jsonContent, true);
        if($data === null) {
            returnServerError('Invalid JSON data.');
        }

        // Extract the array of articles based on the items_field parameter (if provided)
        if (!empty($itemsField)) {
            $itemsArray = $this->extractField($data, $itemsField);
            if (!is_array($itemsArray)) {
                $itemsArray = array();
            }
        } else {
            // If not provided, assume the entire JSON is the array of items.
            $itemsArray = is_array($data) ? $data : array();
        }

        // If a valid limit is specified, restrict the items array accordingly
        if (!empty($limitParam) && is_numeric($limitParam)) {
            $limit = intval($limitParam);
            if ($limit > 0) {
                $itemsArray = array_slice($itemsArray, 0, $limit);
            }
        }

        // Loop over each item in the JSON array.
        foreach ($itemsArray as $item) {
            // Skip if the item does not have the specified article URL (or slug) field
            $articleUrlValue = $this->extractField($item, $articleField);
            if ($articleUrlValue === null) {
                continue;
            }
            
            // Build the full article URL using the prefix (if provided)
            $articleUrl = $articleUrlValue;
            if (!empty($prefix)) {
                $articleUrl = rtrim($prefix, '/') . '/' . ltrim($articleUrlValue, '/');
            }
            
            // Set the title using the provided title_field if available; otherwise, fallback to the 'title' field or the article URL.
            $title = '';
            if (!empty($titleField)) {
                $titleValue = $this->extractField($item, $titleField);
                if ($titleValue !== null) {
                    $title = $titleValue;
                }
            }
            if (empty($title)) {
                $title = isset($item['title']) ? $item['title'] : $articleUrl;
            }
            
            // Set the author using nested extraction if needed
            $author = '';
            if (!empty($authorField)) {
                $authorValue = $this->extractField($item, $authorField);
                if ($authorValue !== null) {
                    $author = $authorValue;
                }
            }
            
            // Determine the publication date
            $pubDate = '';
            $timestamp = null;
            if ($pubdateSource === 'json') {
                $pubDate = ($pubdateField) ? $this->extractField($item, $pubdateField) : '';
                if (!empty($pubDate)) {
                    if (!empty($dateFormat)) {
                        // Try to parse using the specified date format
                        $dateTime = DateTime::createFromFormat($dateFormat, $pubDate);
                        if ($dateTime !== false) {
                            $timestamp = $dateTime->getTimestamp();
                        } else {
                            // Fallback to strtotime if the format fails
                            $timestamp = strtotime($pubDate);
                        }
                    } else {
                        $timestamp = strtotime($pubDate);
                    }
                }
            } elseif ($pubdateSource === 'scrape') {
                // Attempt to scrape the article page for a publication date via Browserless
                $articleContent = $this->fetchContentViaBrowserless($articleUrl);
                if ($articleContent !== false) {
                    // Example: look for a meta tag like <meta property="article:published_time" content="...">
                    if (preg_match('/<meta\s+property=["\']article:published_time["\']\s+content=["\']([^"\']+)["\']/i', $articleContent, $matches)) {
                        $pubDate = $matches[1];
                        $timestamp = strtotime($pubDate);
                    }
                }
            }
            
            // Determine the content:
            $content = '';
            if (!empty($contentField)) {
                // Use the JSON field specified by the user (supports dot notation)
                $content = $this->extractField($item, $contentField);
            }
            // Fallback: use 'content' field from JSON if available
            elseif (isset($item['content']) && !empty($item['content'])) {
                $content = $item['content'];
            }
            // Next fallback: scrape the article page using a CSS selector via Browserless
            elseif (!empty($scrapeContentSelector)) {
                $articleHtmlContent = $this->fetchContentViaBrowserless($articleUrl);
                if ($articleHtmlContent) {
                    $html = str_get_html($articleHtmlContent);
                    if ($html) {
                        $element = $html->find($scrapeContentSelector, 0);
                        if ($element) {
                            $content = $element->innertext;
                            // Clean up content if a cleanup regex is provided
                            if (!empty($contentCleanupRegex)) {
                                $content = preg_replace($contentCleanupRegex, '', $content);
                            }
                        }
                        $html->clear();
                        unset($html);
                    }
                }
            }
            // Additional fallback: scrape the article page's inline JSON for content if parameter is set via Browserless
            if (empty($content) && !empty($articleJsonField)) {
                $articleHtml = $this->fetchContentViaBrowserless($articleUrl);
                if ($articleHtml !== false) {
                    $patternArticle = '/<script\s+id=["\']'.preg_quote($jsonTag, '/').'["\']\s+type=["\']application\/json["\']>(.*?)<\/script>/is';
                    if (preg_match($patternArticle, $articleHtml, $matchesArticle)) {
                        $articleJsonContent = $matchesArticle[1];
                        $articleJsonData = json_decode($articleJsonContent, true);
                        if ($articleJsonData !== null) {
                            $content = $this->extractField($articleJsonData, $articleJsonField);
                        }
                    }
                }
            }
            
            // Optionally remove styling if the option is enabled
            if (!empty($content) && $removeStyling) {
                // Remove <style> tags
                $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
                // Remove inline style attributes from all tags
                $content = preg_replace('/(<[^>]+) style="[^"]*"/i', '$1', $content);
            }
            
            // Build the feed item
            $this->items[] = array(
                'uri'       => $articleUrl,
                'title'     => $title,
                'author'    => $author,
                'timestamp' => $timestamp,
                'content'   => $content,
            );
        }
    }
}
?>
