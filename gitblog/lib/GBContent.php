<?
class GBContent {
	public $name;
	public $id;
	public $slug;
	public $meta;
	public $title;
	public $body;
	public $tags = array();
	public $categories = array();
	public $author = null;
	public $published = false; # timestamp
	public $modified = false; # timestamp
	
	function __construct($name, $id, $slug, $meta=array(), $body=null) {
		$this->name = $name;
		$this->id = $id;
		$this->slug = $slug;
		$this->meta = $meta;
		$this->body = $body;
	}
	
	function reload(&$data, $commits) {
		$bodystart = strpos($data, "\n\n");
		
		if ($bodystart === false) {
			trigger_error("malformed content object '{$this->name}' missing header");
			return;
		}
		
		$this->body = null;
		$this->meta = array();
		
		gb_parse_content_obj_headers(substr($data, 0, $bodystart), $this->meta);
		
		# lift lists from meta to this
		static $special_lists = array('tag'=>'tags', 'categry'=>'categories');
		foreach ($special_lists as $singular => $plural) {
			if (isset($this->meta[$plural])) {
				$this->$plural = preg_split('/[, ]+/', $this->meta[$plural]);
				unset($this->meta[$plural]);
			}
			elseif (isset($this->meta[$singular])) {
				$this->$plural = array($this->meta[$singular]);
				unset($this->meta[$singular]);
			}
		}
		
		# lift specials, like title, from meta to this
		static $special_singles = array('title');
		foreach ($special_singles as $singular) {
			if (isset($this->meta[$singular])) {
				$this->$singular = $this->meta[$singular];
				unset($this->meta[$singular]);
			}
		}
		
		# freeze meta
		$this->meta = (object)$this->meta;
		
		# set body
		$this->body = substr($data, $bodystart+2);
		#if ($this->body)
		#	$this->applyFilters('body', $body);
		
		# translate info from commits
		if ($commits) {
			# latest one is last modified
			$this->modified = $commits[0]->authorDate;
			
			# first one is when the content was created
			$initial = $commits[count($commits)-1];
			if ($this->published === false)
				$this->published = $initial->authorDate;
			if (!$this->author) {
				$this->author = (object)array(
					'name' => $initial->authorName,
					'email' => $initial->authorEmail
				);
			}
		}
	}
	
	function cachename() {
		return gb_filenoext($this->name);
	}
	
	static function getCached($cachebase, $name) {
		$path = $cachebase.'/'.gb_filenoext($name);
		return @unserialize(file_get_contents($path));
	}
	
	function writeCache($cachebase) {
		$path = $cachebase.'/'.$this->cachename();
		$dirname = dirname($path);
		if (!is_dir($dirname))
			mkdir($dirname, 0775, true);
		$data = serialize($this);
		return gb_atomic_write($path, $data);
	}
}
?>