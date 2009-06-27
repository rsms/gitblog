<?
class GBPost extends GBContent {
	function cachename() {
		return 'content/posts/'.date("Y/m/d/", $this->published).$this->slug;
	}
	
	static function getCached($cachebase, $published, $slug) {
		$path = $cachebase.'/content/posts/'.date("Y/m/d/", $published).$slug;
		return @unserialize(file_get_contents($path));
	}
}
?>