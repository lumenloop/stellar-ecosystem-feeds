<?php
class FeedFinderBridge extends FeedExpander {

    const MAINTAINER = 'Raph';
    const NAME = 'Feed Finder Bridge';
    const URI = 'https://github.com/lumenloop/stellar-ecosystem-feeds/rss-bridges/';
    const DESCRIPTION = 'Finds the first RSS feed link on a given page and parses its feed. Optionally strips styling and HTML markup.';
    const PARAMETERS = [
        [
            'url' => [
                'name' => 'Website URL',
                'required' => true,
                'exampleValue' => 'https://medium.com/@cables-finance',
                'title' => 'The URL of the site to scrape for an RSS feed link'
            ],
            'strip' => [
                'name' => 'Strip styling/HTML?',
                'type' => 'checkbox',
                'title' => 'Check this box to remove styling and HTML markup from the output'
            ]
        ]
    ];
    const CACHE_TIMEOUT = 3600; // 1 hour

    public function collectData() {
        // Retrieve user inputs
        $targetUrl = $this->getInput('url');
        $strip     = $this->getInput('strip');

        // Fetch the HTML content from the provided URL
        $html = getSimpleHTMLDOM($targetUrl);
        if (!$html) {
            returnServerError('Could not request website: ' . $targetUrl);
        }

        // Find the first RSS feed link element (e.g. <link type="application/rss+xml" ...>)
        $rssLink = $html->find('link[type=application/rss+xml]', 0);
        if (!$rssLink) {
            returnServerError('No RSS feed link found in the provided URL.');
        }
        $feedUri = $rssLink->href;

        // Set the bridge URI for reference (assign directly to the property)
        $this->uri = $feedUri;

        // Use FeedExpander's method to collect and parse the feed
        $this->collectExpandableDatas($feedUri);

        // Optionally strip HTML markup from the content if requested by the user
        if ($strip) {
            foreach ($this->items as &$item) {
                if (isset($item['content'])) {
                    $item['content'] = strip_tags($item['content']);
                }
            }
        }
    }
}
