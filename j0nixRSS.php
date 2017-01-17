<?php

// Array with availible rss feeds...
// The idea here is that this array could be populated from whatever... for now updated manually
$RSS_URLS = array(
	"slashdot" => "http://rss.slashdot.org/Slashdot/slashdot",
	"opensource" => "https://opensource.com/feed",
	"elastic" => "https://www.elastic.co/blog/feed",
	"nixcraft" => "https://www.cyberciti.biz/feed/",
	"linuxtoday" => "http://feeds.feedburner.com/linuxtoday/linux"
);

// Defaults
$LIMIT = 15;
$URL=null;

// Get request variables
if(isset($_GET['rss'])) $URL = $RSS_URLS[$_GET["rss"]]; // get url where name equals get variable q
if(isset($_GET['limit'])) $LIMIT=$_GET["limit"]; // How many rss items to get

// Do we have an url ?
if($URL) {

	// Get that xml fle
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$URL);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
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
        //else if { ... } Note to self: other rss formats ? ... probably ...

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

	echo (json_encode(array(
		"rss" => $RSS_URLS, 
		"limit" => $LIMIT))
	);
}
?> 
