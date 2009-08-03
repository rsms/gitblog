<?
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
		gb::data('plugins')->set($plugins);
		unset(gb::$site_state['plugins']);
		
		# write data/site.json
		gb::log('copying %s -> data:site', gb::$site_dir.'/site.json');
		gb_maint::sync_site_state();
		gb::log('removing old %s', gb::$site_dir.'/site.json');
		@unlink(gb::$site_dir.'/site.json');
		
		# remove /site.json from .gitignore
		if (self::gitignore_sub('/(?:\r?\n)\/site\.json([\t\s \r\n]+|^)/m', '$1')) {
			gb::log('removed "/site.json" from .gitignore');
			$added[] = gb::add('.gitignore');
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
				gb::data('plugins/'.$pluginn)->set($d);
			}
		}
		gb::log('removing old %s', gb::$site_dir.'/settings.json');
		@unlink(gb::$site_dir.'/settings.json');
		
		# load gb-users.php
		$users = array();
		if (is_readable(gb::$site_dir.'/gb-users.php')) {
			gb::log('loading %s', gb::$site_dir.'/gb-users.php');
			class GBUserAccount {
				static function __set_state($state) {
					return GBUser::__set_state($state);
				}
			}
			require gb::$site_dir.'/gb-users.php';
			if (isset($db)) {
				$admin = isset($db['_admin']) ? $db['_admin'] : '';
				foreach ($db as $email => $user) {
					$user->admin = ($email === $admin);
					$users[$email] = $user;
					gb::log('transponded user %s', $email);
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
				gb::commit('upgrade 0.1.4 modified '
					. implode(', ',$added), GBUser::findAdmin()->gitAuthor(), $added);
			}
			catch (GitError $e) {
				if (strpos($e->getMessage('no changes added to commit')) === false)
					throw $e;
			}
		}
	}
	
	static function build_stages($from, $to) {
		$stages = array();
		for ($v=$from; $v<=$to; $v++) {
			$stagefunc = array(__CLASS__, sprintf('_%06x', $v));
			if (function_exists($stagefunc))
				$stages[$v] = $stagefunc;
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
			gb::log('%s -> %s (%s)',
				gb::version_format($v ? $v-1 : $v), gb::version_format($v), $stagefunc[1]);
			$stages[$v] = call_user_func($stagefunc, $from, $to);
		}
		
		return $stages;
	}
}
?>