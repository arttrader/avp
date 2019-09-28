<?php
require_once 'videoDataClass.php';

if ($_POST) {
	$send = $_POST['send'];

} else if ($_GET) {
	$send = $_GET['send'];

} else die('no parameters');




// for URL quotation moved from CombData in k2
class GetURLInfo {
	public $url;
	public $title, $desc, $keywords, $image;
	private $dom, $xpath;

	public function __construct($url) {
		$this->url = $url;
		$this->dom = new DOMDocument();
		$html = getUrl($url);
		//日本語を数値文字参照に変換する(文字化け対策)
		$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'ASCII, JIS, UTF-8, EUC-JP, SJIS');
		$this->dom->loadHTML($html);
		$this->xpath = new DOMXPath($this->dom);
	}

	public function getTitle() {
		$nodes = $this->dom->getElementsByTagName('title');
		if (is_object($nodes->item(0)))
			$this->title = $nodes->item(0)->nodeValue;
		else
			$this->title = "";

		return $this->title;
	}
}



function getUrl($url) {
	global $debugmode;
	$REFERER = 'http://www.google.com/search';
	$HEADERS = array("User-Agent: Mozilla/5.0 (Macintosh; U; PPC Mac OS X; ja-jp) AppleWebKit/312.8 (KHTML, like Gecko) Safari/312.6");
	
	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_HTTPHEADER, $HEADERS);
	//curl_setopt($ch, CURLOPT_REFERER, $REFERER);
	curl_setopt($ch, CURLOPT_HEADER, false);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	$contents = curl_exec($ch);
	curl_close($ch);

	return $contents;	
}
