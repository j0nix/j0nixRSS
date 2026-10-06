<?php
// The idea here is that this array could be populated from whatever... for now updated manually
$RSS_URLS = array(
	"Slashdot" => "http://rss.slashdot.org/Slashdot/slashdot",
	"Elastic" => "https://www.elastic.co/blog/feed",
	"Linux Today" => "https://www.linuxtoday.com/feed/",
	"Linux.com" => "https://www.linux.com/feed/"
);
// Defaults
$LIMIT = 15;
$TRUNCATE = 0;
$URL = null;

// Get request variables
if(isset($_GET['rss'])) $URL = $RSS_URLS[$_GET["rss"]]; // get url where name equals get variable q
if(isset($_GET['limit'])) $LIMIT=$_GET["limit"]; // How many rss items to get
if(isset($_GET['truncate'])) $TRUNCATE=$_GET["truncate"]; // Maximum words in description before cut...

function truncate($str, $width) {
	if (strlen($str) > $width) return strtok(wordwrap($str, $width, "...\n"), "\n");
	else return $str; 
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
	$xml = curl_exec($ch);
	// Parse xml
	$xml=simplexml_load_string($xml) or die('{"error": "Cannot parse xml","xml": "'.strip_tags(substr($xml,0,200).'..."}'));
	// Build your reply from xml data
	if (isset($xml->title)) {
                $channel = array(
                        "channel" => (string) $xml->title,
                        "link" => (string) $xml->id,
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
			array_push($data,array(
				"title" => (string) $items->title,
				"pubDate" => (string) $items->updated,
				"link" => (string) $items->id,
				"description" => (string) strip_tags($items->summary))
			);
		}
	} //else if { ... } Note to self: other rss formats ? ... probably ...

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
	//If we didn't match request variable rss with something in array RSS_URLS we reply with values defined for RSS_URLS and LIMIT
	header("HTTP/1.1 200 OK");
	header('Content-Type: application/json');

	$info = array(
		"about" => "https://github.com/j0nix/j0nixRSS",
		"truncate" => $TRUNCATE,
		"limit" => $LIMIT,
		"rss" => $RSS_URLS
	);

	echo(json_encode($info,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
?> 
