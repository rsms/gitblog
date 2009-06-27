<?
class GBPost extends GBContent {
	function urlpath() {
		static $dateprefixpat = '%Y/%m/%d/'; # xxx todo move to config
		return gmstrftime($dateprefixpat, $this->published).str_replace('%2F', '/', urlencode($this->slug));
	}
	
	function cachename() {
		return 'content/posts/'.gmdate("Y/m/d/", $this->published).$this->slug;
	}
	
	static function getCached($cachebase, $published, $slug) {
		$path = $cachebase.'/content/posts/'.gmdate("Y/m/d/", $published).$slug;
		return @unserialize(file_get_contents($path));
	}
}
?>