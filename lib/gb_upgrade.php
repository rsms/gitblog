<?php
class gb_upgrade {
	static function _000101($from, $to) {
		# ignore site.json
		if ($to <= 0x000104 && substr(file_get_contents(gb::$site_dir.'/.gitignore'), '/site.json') === false)
			file_put_contents(gb::$site_dir.'/.gitignore', "\n/site.json\n", FILE_APPEND);
	}
	
	static function _000103($from, $to) {
		gb_maint::add_gitblog_submodule();
	}
	
	static function _000104($from, $to) {
		$datadir = gb::$site_dir.'/data/';
		$added = array();
		
		# create data/
		if (!file_exists($datadir)) {
			gb::log('creating directory %s', $datadir);
			mkdir($datadir, 0775);
			chmod($datadir, 0775);
		}
		
		# load old site.json
		gb::log('loading %s', gb::$site_dir.'/site.json');
		gb::$site_state = is_readable(gb::$site_dir.'/site.json') ? 
			json_decode(file_get_contents(gb::$site_dir.'/site.json'), true) : array();
		
		# move site.json:plugins to data/plugins.json
		$plugins = isset(gb::$site_state['plugins']) ? gb::$site_state['plugins'] : array();
		gb::log('creating data:plugins');
		gb::data('plugins')->storage()->set($plugins);
		unset(gb::$site_state['plugins']);
		
		# write data/site.json
		gb::log('moving %s -> data:site', gb::$site_dir.'/site.json');
		# gb_maint::sync_site_state() will be called after this method returns
		@unlink(gb::$site_dir.'/site.json');
		
		# remove /site.json from .gitignore
		if (gb_maint::gitignore_sub('/(?:\r?\n)\/site\.json([\t\s \r\n]+|^)/m', '$1')) {
			gb::log('removed "/site.json" from .gitignore');
			$added[] = git::add('.gitignore');
		}
		
		# load settings.json
		gb::log('loading %s', gb::$site_dir.'/settings.json');
		$settings = is_readable(gb::$site_dir.'/settings.json') ?
			json_decode(file_get_contents(gb::$site_dir.'/settings.json'), true) : array();
		
		# move settings.json:* to data/plugins/*
		foreach ($settings as $pluginn => $d) {
			if (!is_array($d))
				$d = $d !== null ? array($d) : array();
			if ($d) {
				gb::log('copying %s:%s -> data:plugins/%s', gb::$site_dir.'/settings.json', $pluginn, $pluginn);
				gb::data('plugins/'.$pluginn)->storage()->set($d);
			}
		}
		gb::log('removing old %s', gb::$site_dir.'/settings.json');
		@unlink(gb::$site_dir.'/settings.json');
		
		# load gb-users.php
		$users = array();
		if (is_readable(gb::$site_dir.'/gb-users.php')) {
			gb::log('loading %s', gb::$site_dir.'/gb-users.php');
			eval('class GBUserAccount {
				static function __set_state($state) {
					return GBUser::__set_state($state);
				}
			}');
			require gb::$site_dir.'/gb-users.php';
			if (isset($db)) {
				$admin = isset($db['_admin']) ? $db['_admin'] : '';
				foreach ($db as $email => $user) {
					if (is_object($user)) {
						$user->admin = ($email === $admin);
						$users[$email] = $user;
						gb::log('transponded user %s', $email);
					}
				}
			}
		}
		
		# move gb-users.php to data/users.json
		gb::log('moving %s -> data:users', gb::$site_dir.'/gb-users.php');
		GBUser::storage()->set($users);
		@unlink(gb::$site_dir.'/gb-users.php');
		
		# commit any modifications
		if ($added) {
			try {
				git::commit('upgrade 0.1.4 modified '
					. implode(', ',$added), GBUser::findAdmin()->gitAuthor(), $added);
			}
			catch (GitError $e) {
				if (strpos($e->getMessage(), 'no changes added to commit') === false)
					throw $e;
			}
		}
	}
	
	static function _000105($from, $to) {
		# remove old hooks, allowing new symlinked ones to appear (which will
		# happen after the upgrade is complete, by effect of calling
		# gb_main::sync_site_state()).
		foreach (array('post-commit', 'post-update') as $name) {
			$path = gb::$site_dir.'/.git/hooks/'.$name;
			if (is_file($path)) {
				gb::log('removing old hook %s', $path);
				unlink($path);
			}
		}
	}
	
	static function build_stages($from, $to) {
		$stages = array();
		for ($v=$from+1; $v<=$to; $v++) {
			$f = array(__CLASS__, sprintf('_%06x', $v));
			if (method_exists($f[0], $f[1]))
				$stages[$v] = $f;
		}
		return $stages;
	}

	static function perform($from, $to) {
		$from = gb::version_parse($from);
		$to = gb::version_parse($to);
		$froms = gb::version_format($from);
		$tos = gb::version_format($to);
		if ($from === $to)
			return null;
		$is_upgrade = $from < $to;
		$stages = self::build_stages($from, $to);
		
		gb::log('%s gitblog %s -> %s in %d stages',
			($is_upgrade ? 'upgrading' : 'downgrading'), $froms, $tos, count($stages));
		
		# don't break on client abort
		ignore_user_abort(true);
		
		foreach ($stages as $v => $stagefunc) {
			$prevvs = gb::version_format($v > 0 ? $v-1 : $v);
			gb::log('%s -> %s (%s)', $prevvs, gb::version_format($v), $stagefunc[1], $v);
			
			# write prev version to site.json so next run will take off where we crashed, if we crash.
			$orig_v = gb::$version;
			gb::$version = $prevvs;
			gb_maint::sync_site_state();
			gb::$version = $orig_v;
			
			$stages[$v] = call_user_func($stagefunc, $from, $to);
		}
		
		gb_maint::sync_site_state();
		
		return $stages;
	}
}
?>