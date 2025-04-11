<?php
class PureJsonBridge extends BridgeAbstract {
    const NAME = 'Pure JSON Bridge';
    const URI = 'https://github.com/lumenloop/stellar-ecosystem-feeds/rss-bridges/';
    const DESCRIPTION = 'Generates an RSS feed entirely from a JSON API without page scraping.';
    const MAINTAINER = 'Raph';

    const PARAMETERS = array(
        'Main' => array(
            'url' => array(
                'name' => 'API URL',
                'required' => true,
                'exampleValue' => 'https://example.com/api',
            ),
            'items_path' => array(
                'name' => 'JSON path to items array (dot notation, optional)',
                'required' => false,
                'exampleValue' => 'data.articles',
            ),
            'article_field' => array(
                'name' => 'JSON field for article URL',
                'required' => true,
                'exampleValue' => 'link',
            ),
            'title_field' => array(
                'name' => 'JSON field for article title',
                'required' => true,
                'exampleValue' => 'title',
            ),
            'prefix' => array(
                'name' => 'Prefix for article URL (if needed)',
                'required' => false,
                'exampleValue' => 'https://example.com',
            ),
            'author_field' => array(
                'name' => 'JSON field for author',
                'required' => false,
                'exampleValue' => 'author.name',
            ),
            'pubdate_field' => array(
                'name' => 'JSON field for publication date',
                'required' => false,
                'exampleValue' => 'published_at',
            ),
            'date_format' => array(
                'name' => 'Date format (optional)',
                'required' => false,
                'exampleValue' => 'Y-m-d H:i:s'
            ),
            'content_field' => array(
                'name' => 'JSON field for content (output as is, even if array)',
                'required' => false,
                'exampleValue' => 'content'
            )
        )
    );

    /**
     * Helper function to extract nested fields using dot notation.
     * For example, if the field is "author.name", it extracts $data['author']['name'].
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

    public function collectData(){
        $apiUrl       = $this->getInput('url');
        $itemsPath    = $this->getInput('items_path');
        $articleField = $this->getInput('article_field');
        $titleField   = $this->getInput('title_field');
        $prefix       = $this->getInput('prefix');
        $authorField  = $this->getInput('author_field');
        $pubdateField = $this->getInput('pubdate_field');
        $dateFormat   = $this->getInput('date_format');
        $contentField = $this->getInput('content_field');

        // Fetch the JSON content from the specified URL
        $jsonContent = getContents($apiUrl);
        if ($jsonContent === false) {
            returnServerError('Could not fetch content from: ' . $apiUrl);
        }

        $data = json_decode($jsonContent, true);
        if ($data === null) {
            returnServerError('Invalid JSON data.');
        }

        // Get the items array based on items_path, defaulting to the top level if not specified
        $items = $data;
        if (!empty($itemsPath)) {
            $items = $this->extractField($data, $itemsPath);
            if ($items === null || !is_array($items)) {
                returnServerError('Invalid or non-array items path: ' . $itemsPath);
            }
        }

        // Verify that items is an array
        if (!is_array($items)) {
            returnServerError('JSON data must be an array or contain an array at the specified items path.');
        }

        // Loop over each item in the items array
        foreach ($items as $item) {
            // Skip if the item does not have the specified article URL field
            $articleUrlValue = $this->extractField($item, $articleField);
            if ($articleUrlValue === null) {
                continue;
            }

            $articleUrl = $articleUrlValue;
            if (!empty($prefix)) {
                $articleUrl = $prefix . $articleUrl;
            }

            // Extract the title using the specified title field
            $title = $this->extractField($item, $titleField);
            if ($title === null) {
                $title = $articleUrl; // Fallback to URL if title is not found
            }

            // Extract the author if provided
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
            if (!empty($pubdateField)) {
                $pubDate = $this->extractField($item, $pubdateField);
                if (!empty($pubDate)) {
                    if (!empty($dateFormat)) {
                        $dateTime = DateTime::createFromFormat($dateFormat, $pubDate);
                        if ($dateTime !== false) {
                            $timestamp = $dateTime->getTimestamp();
                        } else {
                            $timestamp = strtotime($pubDate);
                        }
                    } else {
                        $timestamp = strtotime($pubDate);
                    }
                }
            }

            // Extract the content using the JSON field for content
            $content = '';
            if (!empty($contentField)) {
                $content = $this->extractField($item, $contentField);
                // Output as is even if it's an array
                if (is_array($content)) {
                    $content = json_encode($content);
                }
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
