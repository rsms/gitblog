<?
/** Gitblog admin */
class gb_admin {
	static public $url;
	
	static public $errors = array();
	
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
			'p' => array('Page', 'edit/page.php')
		)),
		array('Manage',null, array(
			array('Posts','manage/posts.php'),
			array('Pages','manage/pages.php'),
			array('Attachments','manage/attachments.php')
		)),
		array('Settings',null, array(
			array('Basics','settings/basics.php'),
			array('Theme','settings/theme.php'),
			array('Plugins','settings/plugins.php', array(
				# This is a good place for plugins to add custom menu items. Example:
				# array('Some plugin', '../plugins/some-plugin/ui.php')
			))
		)),
		array('Maintenance',null,array(
			'r' => array('Rebuild', 'maintenance/rebuild.php'),
			array('Import Wordpress site', 'maintenance/import-wordpress.php')
		))
	);
	
	# resolved by render_menu
	public static $current_domid = '';
	
	function render_menu($items=null, $baseurl=null, $currurlpath=null, $liststart='<ul>', $listend='</ul>') {
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
			$is_curr = false;
			$accesskey = is_string($k) ? strtoupper($k) : '';
			if (isset($item[1]) && is_string($item[1])) {
				$uri = $item[1];
				$url = ($uri && $uri{0} === '/' || strpos($uri, '://') !== false) ? $uri : $baseurl . $uri;
				$actual_currpath = GBURL::parse($url)->path;
				$is_curr = $actual_currpath === substr($currurlpath, 0, strlen($actual_currpath));
				if ($uri === '')
					$is_curr = gb::url()->path === GBURL::parse(gb_admin::$url)->path;
			}
			$dom_id = $uri ? gb_strtodomid(gb_filenoext($item[1])) : $k;
			$s .= '<li id="menu-item-'.$dom_id.'"';
			if ($is_curr) {
				$s .= ' class="selected"';
				self::$current_domid = $dom_id;
			}
			$s .= '><a';
			if ($url)
				$s .= ' href="'.h($url).'"';
			if ($accesskey)
				$s .= ' accesskey="'.$accesskey.'"';
			$s .= '><span class="title">'.h($item[0]).'</span>';
			if ($accesskey)
				$s .= '<span class="accesskey-hint">'.$accesskey_prefix.$accesskey.'</span>';
			$s .= '</a>';
			if (isset($item[2]) && $item[2])
				$s .= self::render_menu($item[2], $baseurl, $currurlpath, $liststart, $listend);
			$s .= '</li>';
		}
		$s .= $listend;
		return $s;
	}
}

gb_admin::$url = gb::$site_url.'gitblog/admin/';

?>