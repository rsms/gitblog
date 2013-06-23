<?php
/** Gitblog admin */
class gb_admin {
	static public $url;
	
	/**
	 * The menu.
	 * 
	 * Item prototype:
	 *   [string accesskey =>] array( string title [, string uri [, array( item .. )]] )
	 */
	static public $menu = array(
		'h' => array('Dashboard',''),
		array('New',null,array(
			'n' => array('Post', 'edit/post.php'),
			'p' => array('Page', '#todo:edit/page.php')
		)),
		array('Manage',null, array(
			array('Posts','manage/posts.php'),
			array('Pages','#todo:manage/pages.php'),
			array('Comments','manage/comments.php'),
			array('Attachments','#todo:manage/attachments.php')
		)),
		array('Settings',null, array(
			array('Basics','settings/basics.php'),
			array('Theme','#todo:settings/theme.php'),
			array('Plugins','#todo:settings/plugins.php', array(
				# This is a good place for plugins to add custom menu items. Example:
				# array('Some plugin', '../plugins/some-plugin/ui.php')
			))
		)),
		array('Maintenance',null,array(
			'r' => array('Rebuild', 'maintenance/rebuild.php'),
			array('Import Wordpress site', 'maintenance/import-wordpress.php'),
			array('Status', 'maintenance/git-status.php')
		))
	);
	
	# resolved by render_menu
	public static $current_domid = '';
	
	function render_menu($menu_disabled=false, $items=null, $baseurl=null, $currurlpath=null, 
	                     $liststart='<ul>', $listend='</ul>')
	{
		if ($items === null)
			$items = self::$menu;
		if ($baseurl === null)
			$baseurl = gb_admin::$url;
		if ($currurlpath === null)
			$currurlpath = gb::url()->path;
		$accesskey_prefix = '';
		$is_osx = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X') !== false;
		if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false) {
			if ($is_osx)
				$accesskey_prefix = '&#x2303;&#x2325;';
		}
		$s = $liststart;
		foreach ($items as $k => $item) {
			$uri = $url = '';
			$is_curr = $is_todo = false;
			$accesskey = is_string($k) ? strtoupper($k) : '';
			if (isset($item[1]) && is_string($item[1])) {
				$uri = $item[1];
				$url = ($uri && ($uri{0} === '/' || strpos($uri, '://') !== false)) ? $uri : $baseurl . $uri;
				$url_st = GBURL::parse($url);
				$actual_currpath = $url_st->path;
				$is_todo = strpos($url_st->fragment, 'todo:') === 0;
				$is_curr = ( !$is_todo ) && ( $actual_currpath === substr($currurlpath, 0, strlen($actual_currpath)) );
				if ($uri === '')
					$is_curr = gb::url()->path === GBURL::parse(gb_admin::$url)->path;
			}
			$dom_id = $uri ? gb_strtodomid(gb_filenoext($item[1])) : $k;
			$s .= '<li id="menu-item-'.$dom_id.'"';
			$css_class = '';
			if ($is_curr) {
				$css_class .= 'selected';
				self::$current_domid = $dom_id;
			}
			if ($is_todo)
				$css_class .= ' todo';
			if ($css_class)
				$s .= ' class="'.$css_class.'"';
			$s .= '><a';
			if ($url && !$is_todo && !$menu_disabled)
				$s .= ' href="'.h($url).'"';
			if ($accesskey && !$menu_disabled)
				$s .= ' accesskey="'.$accesskey.'"';
			$s .= '><span class="title">'.h($item[0]).'</span>';
			if ($accesskey && !$menu_disabled)
				$s .= '<span class="accesskey-hint">'.$accesskey_prefix.$accesskey.'</span>';
			$s .= '</a>';
			if (isset($item[2]) && $item[2])
				$s .= self::render_menu($menu_disabled, $item[2], $baseurl, $currurlpath, $liststart, $listend);
			$s .= '</li>';
		}
		$s .= $listend;
		return $s;
	}
	
	static function error_rsp($msg, $status='400 Bad Request', $content_type='text/plain; charset=utf-8', $exit=true) {
		self::abrupt_rsp($status."\n".$msg."\n", $status, $content_type, $exit);
	}
	
	static function json_rsp($data=null, $status='200 OK', $exit=true, $pretty=true) {
		if ($data !== null)
			$data = $pretty ? json::pretty($data)."\n" : json_encode($data);
		else
			$data = '';
		self::abrupt_rsp($data, $status, 'application/json; charset=utf-8', $exit);
	}
	
	static function abrupt_rsp($body='', $status='200 OK', $content_type='text/plain; charset=utf-8', $exit=true) {
		if (!$body)
			$body = '';
		if (!headers_sent()) {
			if ($status)
				header('HTTP/1.1 '.$status);
			if ($body) {
				if ($content_type)
					header('Content-Type: '.$content_type);
				header('Content-Length: '.strlen($body));
			}
			header('Cache-Control: no-cache');
		}
		if ($exit)
			exit($body);
		echo $body;
	}
	
	static function mkdirs($path, $maxdepth=999, $mode=0775) {
		if ($maxdepth <= 0)
			return;
		$parent = dirname($path);
		if (!is_dir($parent))
			self::mkdirs($parent, $maxdepth-1, $mode);
		mkdir($path, $mode);
		@chmod($path, $mode);
	}
	
	/** Write blob version of $obj. Returns path written to or false if write was not needed. */
	static function write_content(GBContent $obj) {
		# build blob
		$blob = $obj->toBlob();
		
		# build destination path
		$dstpath = gb::$site_dir.'/'.$obj->name;
		
		# assure destination dir is prepared
		$dstpathdir = dirname($dstpath);
		if (!is_dir($dstpathdir))
			self::mkdirs($dstpathdir);
		
		# write
		file_put_contents($dstpath, $blob, LOCK_EX);
		@chmod($dstpath, 0664);
		
		return $dstpath;
	}
}

gb_admin::$url = gb::$site_url.'gitblog/admin/';

?>