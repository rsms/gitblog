<?
class gb_upgrade {
	static function _000101($from, $to) {
		# ignore site.json
		if (substr(file_get_contents(gb::$site_dir.'/.gitignore'), '/site.json') === false)
			file_put_contents(gb::$site_dir.'/.gitignore', "\n/site.json\n", FILE_APPEND);
	}
	
	static function _000103($from, $to) {
		gb_maint::add_gitblog_submodule();
	}
	
	static function build_stages($from, $to) {
		$stages = array();
		for ($v=$from; $v<=$to; $v++) {
			$stagefunc = array('gb_upgrade', sprintf('_%06x', $v));
			if (function_exists($stagefunc))
				$stages[$v] = $stagefunc;
		}
		return $stages;
	}

	static function perform($from, $to) {
		# don't break on client abort
		ignore_user_abort(true);
		
		$from = gb::version_parse($from);
		$to = gb::version_parse($to);
		$froms = gb::version_format($from);
		$tos = gb::version_format($to);
		$is_upgrade = $from < $to;
		$stages = self::build_stages($from, $to);
		
		gb::log('%s gitblog %s -> %s in %d stages',
			($is_upgrade ? 'upgrading' : 'downgrading'), $froms, $tos, count($stages));
		
		foreach ($stages as $v => $stagefunc) {
			gb::log('%s -> %s (%s)',
				gb::version_format($v ? $v-1 : $v), gb::version_format($v), $stagefunc[1]);
			$stages[$v] = call_user_func($stagefunc, $from, $to);
		}
		
		return $stages;
	}
}
?>