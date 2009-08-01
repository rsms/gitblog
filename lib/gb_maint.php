<?
/** Gitblog maintenance */
class gb_maint {
	static public $branch = 'stable';
	
	static function add_gitblog_submodule() {
		# Add gitblog submodule
		$roundtrip_temp = false;
		$dotgitmodules = gb::$dir . '/.gitmodules';
		$did_have_dotgitmodules = is_file($dotgitmodules);
		$added = array();
		$origin_url = 'git://github.com/rsms/gitblog.git';
		
		# first, find the origin url if any
		$broken_gitblog_repo = false;
		if (is_dir(gb::$dir.'/.git')) {
			try {
				gb::log('deducing remote.origin.url for existing gitblog');
				$s = trim(gb::exec('config remote.origin.url', null, gb::$dir.'/.git', gb::$dir));
				if ($s)
					$origin_url = $s;
			}
			catch (GitError $e) {
				gb::log(LOG_WARNING, 'failed to read config remote.origin.url: %s', $e->getMessage());
				$broken_gitblog_repo = true;
			}
		}
		
		# if gitblog is not a repo or broken, rename existing and clone a fresh copy from upstream
		if ($broken_gitblog_repo) {
			$stash_dir = gb::$dir.'.old';
			$i = 1;
			while (file_exists($stash_dir))
				$stash_dir = gb::$dir.'.old'.($i++);
			
			gb::log('moving broken gitblog %s to %s', gb::$dir, $stash_dir);
			if (!rename(gb::$dir, $stash_dir)) {
				# Note: This is tricky. If we get here, it probably means we are unable to
				# write in dirname(gb::$dir) and gb::$site_dir which we will try to do
				# further down, where we clone a new copy, which will most likely fail
				# because we can not create a new directory called "gitblog" due to lack of
				# priveleges.
				#
				# Now, one solution would be to:
				#
				#   git clone origin /tmp/xy
				#   mkdir ./gitblog/old
				#   mv ./gitblog/(?!old)+ ./gitblog/old/
				#   mv /tmp/xy/* ./gitblog/
				#   rm -rf ./gitblog/old
				#
				# But as this is a very thin use case, almost vanishingly small since
				# the gitblog itself can not function w/o write rights, we die hard:
				gb::log(LOG_CRIT,
					'unable to replace gitblog with gitblog submodule (mv %s %s) -- directory not writable? Aborting.',
					gb::$dir, $stash_dir);
				exit;
			}
		}
		
		try {
			# remove "/gitblog" ignore from .gitignore
			$gitignore_path = gb::$site_dir.'/.gitignore';
			$gitignore = file_get_contents($gitignore_path);
			$gitignore2 = preg_replace('/(?:\r?\n)\/gitblog([\t\s \r\n]+|^)/m', '$1', $gitignore);
			if ($gitignore2 !== $gitignore) {
				gb::log('removing "/gitblog" from %s', $gitignore_path);
				file_put_contents($gitignore_path, $gitignore2, LOCK_EX);
				$added[] = gb::add('.gitignore');
			}
			
			# register (and clone if needed) the gitblog submodule. This might take some time.
			try {
				gb::exec('submodule --quiet add -b '
					. escapeshellarg(self::$branch).' -- '
					. escapeshellarg($origin_url) . ' gitblog');
				# add gitblog
				$added[] = gb::add('gitblog');
			}
			catch (GitError $e) {
				if (strpos($e->getMessage(), 'already exists in the index') === false)
					throw $e;
			}
			
			# move back old shallow gitblog dir
			if ($roundtrip_temp) {
				$old_dst = gb::$dir . '/gitblog.old';
				gb::log('moving %s to %s', $roundtrip_temp, $old_dst);
				if (!@rename($roundtrip_temp, $old_dst))
					gb::log(LOG_ERR, 'failed to move back %s to %s', $roundtrip_temp, $old_dst);
				
				# we want to explicitly checkout the branch we requested
				gb::exec('checkout '.escapeshellarg(self::$branch), null, gb::$dir.'/.git', gb::$dir);
			}
		}
		catch (Exception $e) {
			# move back gitblog dir
			if ($roundtrip_temp) {
				gb::log('moving %s to %s', $roundtrip_temp, gb::$dir);
				if (!@rename($roundtrip_temp, gb::$dir))
					gb::log(LOG_ERR, 'failed to move back %s to %s', $roundtrip_temp, gb::$dir);
			}
			
			# forward exception
			throw $e;
		}
		
		# if .submodules did not exist when we started, track it
		if (!$did_have_dotgitmodules && is_file(gb::$site_dir.'/.gitmodules'))
			$added[] = gb::add('.gitmodules');
		
		# commit any modifications
		if ($added)
			gb::commit('added '.implode(', ',$added), GBUserAccount::getAdmin()->gitAuthor(), $added);
	}
	
	
	static function repair_repo_setup() {
		gb::exec('config receive.denyCurrentBranch ignore');
		gb::exec('config core.sharedRepository 1');
		foreach (array('post-commit', 'post-update') as $name) {
			$dst = gb::$site_dir.'/.git/hooks/'.$name;
			if (!file_exists($dst)) {
				copy(gb::$dir.'/skeleton/hooks/'.$name, $dst);
				@chmod($dst, 0774);
			}
		}
	}
	
	
	static function sync_site_state() {
		ignore_user_abort(true);
		
		# verify repo setup, which also makes sure the repo setup (hooks, config,
		# etc) is up to date:
		self::repair_repo_setup();
		
		# assure gitblog submodule is set up
		$dotgitmodules = gb::$dir . '/.gitmodules';
		if (!is_file($dotgitmodules) || 
		    !preg_match('/\[submodule[\s\t ]+"gitblog"\]/', file_get_contents($dotgitmodules)))
		{
			self::add_gitblog_submodule();
		}
		
		# read id of HEAD and current branch
		$gb_branch = 'master';
		$gb_head = '0000000000000000000000000000000000000000';
		try {
			$branches = trim(gb::exec('branch --no-abbrev --verbose --no-color', null, gb::$dir.'/.git', gb::$dir));
			foreach (explode("\n", $branches) as $line) {
				if (!$line)
					continue;
				if ($line{0} === '*') {
					$line = explode(' ', $line, 4);
					$gb_branch = $line[1];
					$gb_head_id = $line[2];
					break;
				}
			}
		}
		catch (GitError $e) {
			gb::log(LOG_WARNING, 'failed to read branch info for gitblog -- git: %s',
				$e->getMessage());
		}
		
		# no previous state?
		if (!gb::$site_state)
			gb::$site_state = json_decode(file_get_contents(gb::$dir.'/skeleton/site.json'), true);
		
		# Set current values
		gb::$site_state['url'] = gb::$site_url;
		gb::$site_state['version'] = gb::$version;
		gb::$site_state['posts_pagesize'] = gb::$posts_pagesize;
		# appeard in 0.1.3:
		gb::$site_state['gitblog'] = array(
			'branch' => $gb_branch,
			'head' => $gb_head_id
		);
		
		# Write site url for hooks
		$bytes_written = file_put_contents(gb::$site_dir.'/.git/info/gitblog-site-url',
			gb::$site_url, LOCK_EX);
		
		# Encode site.json
		$json = json::pretty(gb::$site_state)."\n";
		$path = gb::$site_dir.'/site.json';
		
		# Write site.json
		$bytes_written += file_put_contents($path, $json, LOCK_EX);
		chmod($path, 0664);
		gb::log(LOG_NOTICE, 'wrote site state to %s (%d bytes)', $path, $bytes_written);
		
		return $bytes_written;
	}
	
	
	static function upgrade($fromVersion) {
		self::sync_site_state();
		
		# this can potentially take a very long time
		set_time_limit(120);
		
		$stages = gb_upgrade::perform($fromVersion, gb::$version);
		
		if ($stages) {
			$failures = GBRebuilder::rebuild(true);
			gb::log('gitblog is now version %s', gb::$version);
			if ($failures) {
				gb::log(LOG_WARNING, 'rebuilding failed with %d failures', count($failures));
				return false;
			}
		}
		else {
			gb::log('upgrade not needed');
		}
		
		return true;
	}
}
?>