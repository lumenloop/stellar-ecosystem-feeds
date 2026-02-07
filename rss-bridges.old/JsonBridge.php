<?php
class JsonBridge extends BridgeAbstract {
    const NAME = 'JSON Bridge';
    const URI = 'https://github.com/lumenloop/stellar-ecosystem-feeds/rss-bridges/';
    const DESCRIPTION = 'Generates an RSS feed from a JSON API with customizable field mapping';
    const MAINTAINER = 'Raph';

    const PARAMETERS = array(
        'Main' => array(
            'url' => array(
                'name' => 'API URL',
                'required' => true,
                'exampleValue' => 'https://example.com/api',
            ),
            'items_path' => array(
                'name' => 'JSON path to items array (optional, use dot notation)',
                'required' => false,
                'exampleValue' => 'data.articles',
            ),
            'article_field' => array(
                'name' => 'JSON field for article URL',
                'required' => true,
                'exampleValue' => 'link',
            ),
            'prefix' => array(
                'name' => 'Prefix for article URL (if needed)',
                'required' => false,
                'exampleValue' => 'https://example.com',
            ),
            'author_field' => array(
                'name' => 'JSON field for author (supports dot notation, e.g. user.userName)',
                'required' => false,
                'exampleValue' => 'user.userName',
            ),
            'pubdate_field' => array(
                'name' => 'JSON field for publication date',
                'required' => false,
                'exampleValue' => 'published_at',
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
                'exampleValue' => 'Y-m-d H:i:s'
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
            )
        )
    );

    /**
     * Helper function to extract nested fields using dot notation.
     * For example, if the field is "user.userName", then it extracts $data['user']['userName'].
     */
    private function extractField($data, $fieldPath) {
        $fields = explode('.', $fieldPath);
        foreach($fields as $field) {
            if(is_array($data) && isset($data[$field])) {
                $data = $data[$field];
            } else {
                return null;
            }
        }
        return $data;
    }

    public function collectData(){
        $apiUrl                = $this->getInput('url');
        $itemsPath             = $this->getInput('items_path');
        $articleField          = $this->getInput('article_field');
        $prefix                = $this->getInput('prefix');
        $authorField           = $this->getInput('author_field');
        $pubdateField          = $this->getInput('pubdate_field');
        $pubdateSource         = $this->getInput('pubdate_source');
        $dateFormat            = $this->getInput('date_format');
        $contentField          = $this->getInput('content_field');
        $scrapeContentSelector = $this->getInput('scrape_content_selector');
        $contentCleanupRegex   = $this->getInput('content_cleanup_regex');
        $removeStyling         = $this->getInput('remove_styling');

        // Fetch the JSON content from the specified URL
        $jsonContent = getContents($apiUrl);
        if($jsonContent === false) {
            returnServerError('Could not fetch content from: ' . $apiUrl);
        }

        $data = json_decode($jsonContent, true);
        if($data === null) {
            returnServerError('Invalid JSON data.');
        }

        // Get the items array based on items_path, default to top level if not specified
        $items = $data;
        if(!empty($itemsPath)) {
            $items = $this->extractField($data, $itemsPath);
            if($items === null || !is_array($items)) {
                returnServerError('Invalid or non-array items path: ' . $itemsPath);
            }
        }

        // Verify items is an array
        if(!is_array($items)) {
            returnServerError('JSON data must be an array or contain an array at the specified items path');
        }

        // Loop over each item in the items array
        foreach($items as $item) {
            // Skip if the item does not have the specified article URL field
            $articleUrlValue = $this->extractField($item, $articleField);
            if($articleUrlValue === null) {
                continue;
            }
            
            $articleUrl = $articleUrlValue;
            if(!empty($prefix)) {
                $articleUrl = $prefix . $articleUrl;
            }
            
            // Set the title (using 'title' if available, otherwise fallback to the article URL)
            $title = isset($item['title']) ? $item['title'] : $articleUrl;
            
            // Set the author using nested extraction if needed
            $author = '';
            if(!empty($authorField)) {
                $authorValue = $this->extractField($item, $authorField);
                if($authorValue !== null) {
                    $author = $authorValue;
                }
            }
            
            // Determine the publication date
            $pubDate = '';
            $timestamp = null;
            if($pubdateSource === 'json') {
                $pubDate = ($pubdateField) ? $this->extractField($item, $pubdateField) : '';
                if (!empty($pubDate)) {
                    if (!empty($dateFormat)) {
                        // Try to parse using the specified date format
                        $dateTime = DateTime::createFromFormat($dateFormat, $pubDate);
                        if($dateTime !== false) {
                            $timestamp = $dateTime->getTimestamp();
                        } else {
                            // Fallback to strtotime if the format fails
                            $timestamp = strtotime($pubDate);
                        }
                    } else {
                        $timestamp = strtotime($pubDate);
                    }
                }
            }
            elseif($pubdateSource === 'scrape') {
                // Attempt to scrape the article page for a publication date
                $articleContent = getContents($articleUrl);
                if($articleContent !== false) {
                    // Example: look for a meta tag like <meta property="article:published_time" content="...">
                    if(preg_match('/<meta\s+property=["\']article:published_time["\']\s+content=["\']([^"\']+)["\']/i', $articleContent, $matches)) {
                        $pubDate = $matches[1];
                        $timestamp = strtotime($pubDate);
                    }
                }
            }
            
            // Determine the content:
            $content = '';
            if(!empty($contentField)) {
                // Use the JSON field specified by the user (supports dot notation)
                $content = $this->extractField($item, $contentField);
            }
            // Fallback: use 'content' field from JSON if available
            elseif(isset($item['content']) && !empty($item['content'])) {
                $content = $item['content'];
            }
            // Final fallback: scrape the article page using a CSS selector
            elseif(!empty($scrapeContentSelector)) {
                $html = getSimpleHTMLDOM($articleUrl);
                if($html) {
                    $element = $html->find($scrapeContentSelector, 0);
                    if($element) {
                        $content = $element->innertext;
                        // Clean up content if a cleanup regex is provided
                        if(!empty($contentCleanupRegex)) {
                            $content = preg_replace($contentCleanupRegex, '', $content);
                        }
                    }
                    $html->clear();
                    unset($html);
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