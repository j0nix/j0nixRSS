<?php

// Arrat with availible rss sitet...
$RSS_URLS = array(
	"slashdot.org" => "http://rss.slashdot.org/Slashdot/slashdot",
	"opensource.com" => "https://opensource.com/feed",
	"elastic.co" => "https://www.elastic.co/blog/feed",
	"nixcraft" => "http://feeds.cyberciti.biz/Nixcraft-LinuxFreebsdSolarisTipsTricks",
	"linuxtoday" => "http://feeds.feedburner.com/linuxtoday/linux"
);

// Defaults
$LIMIT = 10;
$URL=null;

//we return JSON
header('Content-Type: application/json');

// Get request variables
if(isset($_GET['rss'])) $URL = $RSS_URLS[$_GET["rss"]]; // get url where name equals get variable q
if(isset($_GET['limit'])) $LIMIT=$_GET["limit"]; // How many rss items to get

// Do we have an url ?
if($URL) {

	// Get that xml fle
	$ch = curl_init();
	curl_setopt($ch,CURLOPT_URL,$URL);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 5);
	$xml = curl_exec($ch);

	// Parse xml
	$xml=simplexml_load_string($xml) or die('{"error": "Cannot parse xml","xml": "'.strip_tags(substr($xml,0,200).'..."}'));

	// Build your reply from xml data
	$channel = array(
		"channel" => (string) $xml->channel->title,
		"link" => (string) $xml->channel->link,
		"description" => (string) strip_tags($xml->channel->description),
		"lastBuildDate" => (string) $xml->channel->lastBuildDate
	);

	$data = array();
	$c = 1;
	if(isset($xml->channel->item)) { // rss version 2.0
		foreach ($xml->channel->item as $items) {
			$pubDate = null; // since standard defines title,link & description as required we make sure that we have something set for pubDate if it's not inmcluded ... 
			if($c <= $LIMIT) {
				if ($items->pubDate) $pubDate = $items->pubDate;	
				array_push($data,array(
					"title" => (string) $items->title,
					"pubDate" => (string) $items->pubDate,
					"link" => (string) $items->link, 
					"description" => (string) strip_tags($items->description))
				);
			} else break;
			$c++;
		}
	} else if(isset($xml->item)){ // rss version 1.0
		foreach ($xml->item as $items) {
			$pubDate = null; // since standard defines title,link & description as required we make sure that we have something set for pubDate if it's not inmcluded ... 
			if($c <= $LIMIT) {
				if ($items->pubDate) $pubDate = $items->pubDate;	
				array_push($data,array(
					"title" => (string) $items->title,
					"pubDate" => (string) $pubDate,
					"link" => (string) $items->link, 
					"description" => (string) strip_tags($items->description))
				);
			} else break;
			$c++;
		}
	}

	// merge arrays before printing result
	$channel = array_merge($channel,array("item" => $data));
	//print result as json data
	echo(json_encode($channel));

} else {
	//If we didn't match request variable rss with something in array RSS_URLS we reply an error message + values defined for RSS_URLS and LIMIT
	echo (json_encode(array(
		"error" => "not a valid request. Ex. this.php?rss=<rss>&limit=<limit>",
		"rss" => $RSS_URLS, 
		"limit" => $LIMIT),JSON_PRETTY_PRINT)
	);
}
?> 
