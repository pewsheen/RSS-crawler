<?php

echo "Initiating...\n";

/* Connention test, should remove after publish */
$test = new RSS_Crawler("http://chinese.engadget.com/rss.xml");
/* RSS FEED INFORMATION */
echo "-- RSS information --\n";
echo "Title: ".$test->getHeader()->title."\n";
echo "Link: ".$test->getHeader()->link."\n";
echo "Description: ".$test->getHeader()->description."\n";
// Optional
if ($test->getHeader()->ttl != NULL)
	echo "TTL: ".$test->getHeader()->ttl." min\n";
if ($test->getHeader()->pubDate != NULL)
	echo "Last updated: ".$test->getHeader()->pubDate."\n";
/* RSS CONTENT INFORMATION */
echo "-- RSS content --\n";
echo "Amount: ".$test->getContentCount()."\n";
echo "New Articles:\n";
if ($test->getUncachedContent() == NULL) {
	echo "Nothing new.\n";
} else {
	print_r($test->getUncachedContent());
}

class RSS_Crawler {
	/* cURL option */
	private $feedURL = "";
	private $userAgent = "RSS crawler";

	/* content */
	private $header;
	private $content;
	private $count;
	private $uncachedContent;

	public function __construct ($url) {
		global $count, $header, $content;
		$feedURL = $url;
		if ($feedURL == NULL) {
			echo ">> Need an argument.\n Example: \$test = new RSS_Crawler(\"http://chinese.engadget.com/rss.xml\");";
		} else {
			$count = 0;
			$header = new stdClass();
			$content[] = new stdClass();
			$uncachedContent[] = new stdClass();
			$this->capture($feedURL);
		}
	}

	/**
	*	Capture RSS feed content.
	*
	*	@param String URL of RSS feed
	*
	*	Postcondition: check local cache is newest?
	*/
	private function capture($URL) {
		/* initialate */
		global $count, $header, $content;
		$ch = curl_init();

		$options = array(
			CURLOPT_URL=>$URL,
			CURLOPT_HEADER=>0,
			CURLOPT_VERBOSE=>0,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_USERAGENT=>$userAgent,
			);
		curl_setopt_array($ch, $options);

		echo ">> Captureing...\n"."target: ".$URL."\n";

		/* Capture content */
		$result = curl_exec($ch);

		/* Close connection */ 
		curl_close($ch);
		echo ">> Captured!\n";

		/* Convert XML to object */
		$rss = simplexml_load_string($result);

		$count = count($rss->channel->item);

		/* Save Source information */
		$header->title = $rss->channel->title;
		$header->link = $rss->channel->link;
		$header->description = $rss->channel->description;
		// Optional
		$header->pubDate = $rss->channel->pubDate;
		$header->ttl = $rss->channel->ttl;

		/* Save Content */
		for($i = 0; $i < $count; $i++) {
			$content[$i] = clone $rss->channel->item[$i];
			//echo($item[$i]->guid);
		}

		/* Check local cache exist? */
		$this->checkExist($rss);
	}

	/**
	*	check the file of RSS feed exist?
	*
	*	@param object transform from RSS XML
	*
	*	Postcondition:
	*		Not exist: Store current RSS content.
	*		Exist: Compare local file and RSS content if pubDate are same?
	*/
	private function checkExist($rss) {
		global $uncachedContent;

		echo "\n>> Compare to exist file\n";
		/* check site cache exist? */
		$filename = md5($rss->channel->link).".json";
		echo "Filename: ".$filename."\n";
		if (file_exists($filename) == false) {
			echo "File not exist, saving cache.\n";
			$fp = fopen($filename, "w");
			fwrite($fp, json_encode($rss->channel));
			fclose($fp);
			echo "Saved!\n";
			$uncachedContent = $this->uncachedContent(NULL, $rss);
		} else {
			$json = json_decode(file_get_contents($filename), false);
			if ($this->isUpdated($json, $rss) == false) {
				$fp = fopen($filename, "w");
				fwrite($fp, json_encode($rss->channel));
				fclose($fp);
				echo "Local file has been updated!\n";

				// Get uncached content.
				$uncachedContent = $this->uncachedContent($json, $rss);
			}
		}
	}

	/**
	*	Compare local and RSS feed newest pubDate
	*
	*	@param ObjectArray local cache file after json decode.
	*	@param ObjectArray newest RSS feed content.
	*
	*	Postcondition: 
	*		Same: return true. local and RSS feed are consistent.
	*		Different: return false. RSS feed has new content.
	*/
	private function isUpdated($local, $rss) {
		echo "Local: ".$local->item[0]->pubDate." // Newest: ".$rss->channel->item[0]->pubDate."\n";
		if ($local->item[0]->pubDate != $rss->channel->item[0]->pubDate) {
			echo "Local file is old\n";
			return false;
		} else {
			echo "Local file is already updated!\n";
			return true;
		}
	}

	/**
	*	Get amount of RSS content
	*
	*	Post:	Return an interger of amount of RSS content
	*/
	public function getContentCount() {
		global $count;
		return $count;
	}

	/**
	*	Get RSS feed information
	*
	*	Post:	Return an object array of RSS feed information
	*/
	public function getHeader() {
		global $header;
		return $header;
	}

	/**
	*	Get RSS content
	*
	*	Post:	Return an object array of RSS content
	*/
	public function getContent() {
		global $content;
		return $content;
	}

	/**
	*	Get uncached RSS content
	*
	*	Post:	Return an object array of RSS content which hasn't been cached. return NULL if nothing new.
	*/
	private function uncachedContent($local, $rss) {
		global $count;
		$uncachedContent[] = new stdClass();

		if($this->isUpdated($local, $rss) == true) {
			echo "Nothing new.\n";
			return NULL;
		} else {
			for ($i = 0; $i < $count; $i++) {
				if ($local->item[$i]->pubDate != $rss->channel->item[$i]->pubDate) {
					$uncachedContent[$i] = clone $rss->channel->item[$i];
				} else {
					break;
				}
			}
			return $uncachedContent;
		}
	}

	public function getUncachedContent() {
		global $uncachedContent;
		return $uncachedContent;
	}
}
?>