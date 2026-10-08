<?php
// Reading list: name => RSS or Atom feed URL. Fetched with ?rss=<name>
$RSS_URLS = array(
	"Slashdot" => "http://rss.slashdot.org/Slashdot/slashdot",
	"Elastic" => "https://www.elastic.co/blog/feed",
	"Linux Today" => "https://www.linuxtoday.com/feed/",
	"Linux.com" => "https://www.linux.com/feed/"
);
// YouTube list: name => channel ID (the UC... ID, not the @handle). Fetched with ?youtube=<name>
$YOUTUBE_CHANNELS = array(
	"Jeff Geerling" => "UCR-DXc1voovS8nhAvccRZhg",
	"The Linux Experiment" => "UC5UAwBUum7CPN5buc-_N1Fw"
);
// Defaults
$LIMIT = 15;
$TRUNCATE = 0;
$URL = null;
$YOUTUBE = false;

// Get request variables
if(isset($_GET['rss']) && isset($RSS_URLS[$_GET['rss']])) $URL = $RSS_URLS[$_GET['rss']];
if(isset($_GET['youtube']) && isset($YOUTUBE_CHANNELS[$_GET['youtube']])) {
	$URL = "https://www.youtube.com/feeds/videos.xml?channel_id=" . rawurlencode($YOUTUBE_CHANNELS[$_GET['youtube']]);
	$YOUTUBE = true;
}
if(isset($_GET['limit'])) $LIMIT=$_GET["limit"]; // How many rss items to get
if(isset($_GET['truncate'])) $TRUNCATE=$_GET["truncate"]; // Maximum words in description before cut...

function truncate($str, $width) {
	if (strlen($str) > $width) return strtok(wordwrap($str, $width, "...\n"), "\n");
	else return $str; 
}

// Atom gives the page URL in <link rel="alternate"> (or a <link> without rel); <id> is only an identifier
function atom_link($node) {
	foreach ($node->link as $link) {
		$rel = (string) $link['rel'];
		if ($rel === '' || $rel === 'alternate') return (string) $link['href'];
	}
	return (string) $node->id;
}

// Media RSS (used by YouTube): <media:group> holds the description and thumbnail of an Atom entry
function media_group($node) {
	return $node->children('http://search.yahoo.com/mrss/')->group;
}

// Reply with an error and stop. The HTTP status stays 200; clients check for "error"
function fail($message, $extra = array()) {
	header('Content-Type: application/json');
	echo(json_encode(array_merge(array("error" => $message), $extra), JSON_PARTIAL_OUTPUT_ON_ERROR));
	exit;
}
// Do we have an url ?
if($URL) {

	/*
		Never trust that UserAgent header
		Spoofing UserAgent since some "security" services block plain curl calls... 
	*/
	$userAgent = 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.2 (KHTML, like Gecko) Chrome/22.0.1216.0 Safari/537.2';
 
	// Get that xml fle
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$URL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_USERAGENT, $userAgent );
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout in seconds
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	$body = curl_exec($ch);
	if ($body === false) fail("Fetch failed: " . curl_error($ch));
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($status >= 400) fail("Feed returned HTTP " . $status);
	// Parse xml; libxml warnings would otherwise end up in the JSON response
	libxml_use_internal_errors(true);
	$xml = simplexml_load_string($body);
	if ($xml === false) fail("Cannot parse xml", array("xml" => strip_tags(substr($body, 0, 200)) . "..."));
	// An HTML error page can parse as XML; only accept RSS 2.0 (rss), RSS 1.0 (rdf:RDF) and Atom (feed)
	if (!in_array($xml->getName(), array("rss", "RDF", "feed"))) fail("Not an RSS or Atom feed");
	// Build your reply from xml data
	if (isset($xml->title)) {
                $channel = array(
                        "channel" => (string) $xml->title,
                        "link" => atom_link($xml),
                        "description" => (string) $xml->subtitle,
                        "lastBuildDate" => (string) $xml->updated
                );
        } else {
                $channel = array(
                        "channel" => (string) $xml->channel->title,
                        "link" => (string) $xml->channel->link,
                        "description" => (string) strip_tags($xml->channel->description),
                        "lastBuildDate" => (string) $xml->channel->lastBuildDate
                );
        }

	$data = array();

	if(isset($xml->channel->item)) { // rss version 2.0
		foreach ($xml->channel->item as $items) {
			if ($items->pubDate) $pubDate = $items->pubDate;
			else $pubDate = $items->children('http://purl.org/dc/elements/1.1/')->date; // RSS 1.0 uses dc:date
			array_push($data,array(
				"title" => (string) $items->title,
				"pubDate" => (string) $pubDate,
				"link" => (string) $items->link,
				"description" => (string) strip_tags($items->description))
			);
		}
	} else if(isset($xml->item)){ // rss version 1.0
		foreach ($xml->item as $items) {
			if ($items->pubDate) $pubDate = $items->pubDate;
			else $pubDate = $items->children('http://purl.org/dc/elements/1.1/')->date; // RSS 1.0 uses dc:date
			array_push($data,array(
				"title" => (string) $items->title,
				"pubDate" => (string) $pubDate,
				"link" => (string) $items->link,
				"description" => (string) strip_tags($items->description))
			);
		}
	} else if(isset($xml->entry)){ //Atom
		foreach ($xml->entry as $items) {
			$media = media_group($items);
			// <updated> changes when an entry is edited; <published> is the release date
			$pubDate = $items->published ? $items->published : $items->updated;
			$summary = $items->summary ? $items->summary : $media->description;
			$item = array(
				"title" => (string) $items->title,
				"pubDate" => (string) $pubDate,
				"link" => atom_link($items),
				"description" => (string) strip_tags($summary)
			);
			if ($YOUTUBE && $media->thumbnail) $item["thumbnail"] = (string) $media->thumbnail->attributes()->url;
			array_push($data,$item);
		}
	} //else if { ... } Note to self: other rss formats ? ... probably ...

	// Skip YouTube Shorts; channel feeds list them alongside regular videos
	if ($YOUTUBE) $data = array_values(array_filter($data, function($item) { return strpos($item["link"], "/shorts/") === false; }));

	// Feeds are not always in date order: sort newest first (undated last), then apply limit and truncate
	usort($data, function($a, $b) { return (int) strtotime($b["pubDate"]) <=> (int) strtotime($a["pubDate"]); });
	$data = array_slice($data, 0, (int) $LIMIT);
	if ((int) $TRUNCATE > 0) {
		foreach ($data as &$item) $item["description"] = truncate($item["description"], $TRUNCATE);
		unset($item);
	}

	// merge arrays before printing result
	$channel = array_merge($channel,array("item" => $data));

	//print result as json data
	header("HTTP/1.1 200 OK");
	header('Content-Type: application/json');
	echo(json_encode($channel));

} else {
	// No known feed requested: reply with both lists and the defaults
	header("HTTP/1.1 200 OK");
	header('Content-Type: application/json');

	$info = array(
		"about" => "https://github.com/j0nix/j0nixRSS",
		"truncate" => $TRUNCATE,
		"limit" => $LIMIT,
		"rss" => $RSS_URLS,
		"youtube" => $YOUTUBE_CHANNELS
	);

	echo(json_encode($info,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
?> 
