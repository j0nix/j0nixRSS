<?php
// The idea here is that this array could be populated from whatever... for now updated manually
$RSS_URLS = array(
	"Slashdot" => "http://rss.slashdot.org/Slashdot/slashdot",
	"OpenSource" => "https://opensource.com/feed",
	"Elastic - Blog" => "https://www.elastic.co/blog/feed",
	"Nixcraft" => "https://www.cyberciti.biz/feed/",
	"LinuxToday" => "http://feeds.feedburner.com/linuxtoday/linux",
	"Linux.com - Tutorials" => "https://www.linux.com/feeds/tutorials/rss"
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

				if ((int) $TRUNCATE > 0) $desc = truncate((string) strip_tags($items->description),$TRUNCATE); 
				else $desc = (string) strip_tags($items->description);
				array_push($data,array(
					"title" => (string) $items->title,
					"pubDate" => (string) $items->pubDate,
					"link" => (string) $items->link, 
					"description" => $desc )
				);
			} else break;
			$c++;
		}
	} else if(isset($xml->item)){ // rss version 1.0
		foreach ($xml->item as $items) {
			$pubDate = null; // since standard defines title,link & description as required we make sure that we have something set for pubDate if it's not inmcluded ... 
			if ((int) $TRUNCATE > 0) $desc = truncate((string) strip_tags($items->description),$TRUNCATE); 
			else $desc = (string) strip_tags($items->description);
			if($c <= $LIMIT) {
				if ($items->pubDate) $pubDate = $items->pubDate;	
				array_push($data,array(
					"title" => (string) $items->title,
					"pubDate" => (string) $pubDate,
					"link" => (string) $items->link, 
					"description" => $desc)
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
	header("HTTP/1.1 406 Not Acceptable");
	header('Content-Type: text/plain');

	echo "https://github.com/j0nix/j0nixRSS\n";
	echo "\n\n";
	echo "\t truncate:".$TRUNCATE;
	echo "\n";
	echo "\t limit:".$LIMIT;
	echo "\n";
	echo "\t rss:\n";
	foreach ($RSS_URLS as $X => $Y) {

		echo "\t  -".$X." => ".$Y."\n";

	
	}
	echo "\n\n\t ex) j0nixRSS.php?rss=nixcraft&limit=2&truncate=50\n";
	/*
	echo (print_r(array(
		"about" => "https://github.com/j0nix/j0nixRSS",
		"rss" => $RSS_URLS,
		"truncate" => $TRUNCATE, 
		"limit" => $LIMIT))
	);
	*/
}
?> 
