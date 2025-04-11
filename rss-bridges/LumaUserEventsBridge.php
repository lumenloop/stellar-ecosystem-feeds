<?php
class LumaUserEventsBridge extends BridgeAbstract {
    const NAME        = 'Luma User Events Bridge';
    const URI = 'https://github.com/lumenloop/stellar-ecosystem-feeds/rss-bridges/';
    const DESCRIPTION = 'Returns events from a specified Luma username.';
    const MAINTAINER  = 'Raph';

    // Input parameters for the bridge
    const PARAMETERS = [
        [
            'username' => [
                'name' => 'Username',
                'type' => 'text',
                'required' => true,
                'title' => 'The Luma user name, e.g. usr-u0BsDhGKInedrcA'
            ]
        ]
    ];

    public function getName(){
        if(!empty($this->getInput('username'))) {
            return self::NAME . ' for ' . $this->getInput('username');
        }
        return parent::getName();
    }

    public function getURI(){
        // For reference, might link to user’s Luma page if needed
        return 'https://lu.ma/' . $this->getInput('username');
    }

    public function collectData(){
        $username = $this->getInput('username');

        // 1) Fetch the JSON data from Luma’s API
        $apiUrl = 'https://api.lu.ma/user/profile/events?username=' . urlencode($username);
        $jsonString = getContents($apiUrl);
        $data = json_decode($jsonString, true);

        // We look for events in these arrays:
        $eventsHosting  = $data['events_hosting']  ?? [];
        $eventsPast     = $data['events_past']     ?? [];
        $eventsTogether = $data['events_together'] ?? [];

        // Merge all (or pick whichever you need)
        $allEvents = array_merge($eventsHosting, $eventsPast, $eventsTogether);

        // 2) Build feed items
        foreach ($allEvents as $evt) {
            $event = $evt['event'] ?? null;
            if (!$event) {
                continue;
            }

            // The short slug for the event is in $event['url'], e.g. "4zintwlo"
            $slug  = $event['url'] ?? null;
            if(!$slug) {
                continue;
            }

            // Build the full page URL
            $itemLink = 'https://lu.ma/' . $slug;

            // Prepare an RSS item
            $item = [];

            // Title
            $item['title'] = $event['name'] ?? '(No Title)';

            // Link
            $item['uri'] = $itemLink;

            // Timestamp: pick whichever date you prefer
            if (!empty($event['start_at'])) {
                $item['timestamp'] = strtotime($event['start_at']);
            }

            // 3) Load the actual page and scrape .spark-content
            //    For performance, you may want to cache it with getSimpleHTMLDOMCached()
            $html = getSimpleHTMLDOM($itemLink);
            if ($html) {
                $sparkContent = $html->find('.spark-content', 0);
                if ($sparkContent) {
                    // Use the entire HTML content of the .spark-content block
                    $description = $sparkContent->innertext;
                } else {
                    $description = 'No `.spark-content` found.';
                }
            } else {
                $description = 'Could not load event page.';
            }

            // 4) Optionally, you can add other details
            //    like the time or location from the JSON
            $location = $event['geo_address_info']['full_address'] ?? '';
            if (!empty($location)) {
                $description .= '<p><strong>Location:</strong> ' . $location . '</p>';
            }

            $item['content'] = $description;

            // Finally, add to the feed
            $this->items[] = $item;
        }
    }
}
