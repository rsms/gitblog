<?
require_once '_base.php';

# do not render this page unless there is no repo
if ($integrity !== 2) {
	header("Location: ".GITBLOG_SITE_URL."gitblog/admin/");
	exit(0);
}

# Got POST
if (isset($_POST['submit'])) {
	# -------------------------------------------------------------------------
	# check input	
	if (!trim($_POST['email']) or strpos($_POST['email'], '@') === false) {
		$errors[] = '<b>Missing email.</b>
			Please supply a valid email address to be used for the administrator account.';
	}
	if (!trim($_POST['passphrase'])) {
		$errors[] = '<b>Empty pass phrase.</b>
			The pass phrase is empty or contains only spaces.';
	}
	if ($_POST['passphrase'] !== $_POST['passphrase2']) {
		$errors[] = '<b>Pass phrases not matching.</b>
			You need to type in the same pass phrase in the two input fields below.';
	}
	
	# -------------------------------------------------------------------------
	# create gb-config.php
	if (!$errors) {
		$config_path = GITBLOG_SITE_DIR."/gb-config.php";
		$s = file_get_contents(GITBLOG_DIR.'/skeleton/gb-config.php');
		# title
		$s = preg_replace('/(gb::\$site_title[\t ]*=[\t ]*)\'[^\']*\';/', 
			'${1}'.var_export($_POST['title'],1).";", $s, 1);
		# secret
		$secret = '';
		while (strlen($secret) < 62) {
			mt_srand();
			$secret .= base_convert(mt_rand(), 10, 36);
		}
		$s = preg_replace('/(gb::\$secret[\t ]*=[\t ]*)\'\';/',
			'${1}'.var_export($secret,1).";", $s, 1);
		#header('content-type: text/plain; charset=utf-8');var_dump($s);exit(0);
		file_put_contents($config_path, $s);
		chmod($config_path, 0660);
		# reload config
		require $config_path;
	}
	
	# -------------------------------------------------------------------------
	# create repository	
	if (!$errors) {
		if (!$gitblog->init())
			$errors[] = 'Failed to create and initialize repository at '.var_export(gb::$repo,1);
	}
	
	# -------------------------------------------------------------------------
	# create admin account
	if (!$errors) {
		class GBUserAccount {
			static public $db = null;
			
			static function _reload() {
				if (file_exists(gb::$repo.'/.git/info/gitblog-users.php')) {
					include gb::$repo.'/.git/info/gitblog-users.php';
					self::$db = $db;
				}
				else {
					self::$db = array();
				}
			}
			
			static function _sync() {
				if (self::$db === null)
					return;
				file_put_contents(gb::$repo.'/.git/info/gitblog-users.php', 
					'<? $db = '.var_export(self::$db, 1).'; ?>', LOCK_EX);
				chmod(gb::$repo.'/.git/info/gitblog-users.php', 0660);
			}
			
			static function passhash($email, $passphrase) {
				return sha1($email . ' ' . $passphrase . ' ' . gb::$secret);
			}
			
			static function create($email, $passphrase, $name=null) {
				if (self::$db === null)
					self::_reload();
				self::$db = array(
					'email' => $email,
					'passhash' => sha1($email . ' ' . $passphrase . ' ' . gb::$secret),
					'name' => $name
				);
				self::_sync();
			}
		}
		
		GBUserAccount::create($_POST['email'], $_POST['passphrase']);
	}
	
	# -------------------------------------------------------------------------
	# send the client along
	if (!$errors) {
		header('Location: '.GITBLOG_SITE_URL);
		exit(0);
	}
}

# ------------------------------------------------------------------------------------------------
# prepare for rendering

gb::$title[] = 'Setup';
$is_writable_dir = dirname(gb::$repo);
$is_writable = is_writable(file_exists(gb::$repo) ? gb::$repo : $is_writable_dir);

if (!$is_writable) {
	$errors[] = "<b>Ooops.</b> The directory <code>".h($is_writable_dir)."</code> is not writable.
		Gitblog need to write some files and create a few directories in this directory.
		Please make this writable and reload this page.";
}

header('Content-Type: application/xhtml+xml; charset=utf-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8"/>
		<title><?= gb_title() ?></title>
		<style type="text/css">
			* { margin:0; padding:0; }
			body {
				font-family:'helvetica neue',helvetica,arial,sans-serif;
				background-color:#fff;
				color:#333;
			}
			body, p, li, td, div { font-size:13px; }
			p { margin:15px 0; }
			hr { border:none; height:1px; background-color:#ddd; }
			div.breaker { clear:both; }
			
			#head { background-color:#ff9; padding:10px 20px; }
			#head h1 { font-size:14px; color:#640; }
			
			#content { background-color:#fff; margin:0 20px; }
			#content h2 { margin:15px 0 10px 0; font-size:24px; }
			
			body > address {
				border-top:1px solid #ddd;
				color:#aaa;
				padding:10px 20px;
				margin-top:20px;
				font-size:11px;
			}
			
			
			div.inputgroup {
				display:block;
				margin-bottom:10px;
				float:left;
				margin-right:10px;
				padding-right:10px;
				width:300px;
				border-right:1px solid #ddd;
			}
			div.inputgroup > h4 { font-size:100%; margin-bottom:4px; }
			div.inputgroup > p { margin:6px 0 2px 0; }
			div.inputgroup > p.note { margin-top:2px; font-size:11px; color:#999; }
			div.inputgroup > input { margin:2px 0; }
			div.inputgroup > input[type=text], div.inputgroup > input[type=password] { width:290px; }
			
			#errormsg {
				background-color:#fa9;
				border-top:1px solid #c77;
			}
			#errormsg > p {
				padding:15px 20px;
				margin:0;
				color:#400;
				font-size:16px;
				border-bottom:1px solid #c77;
			}
			#errormsg code {
				background-color:#fdd;
			}
			
		</style>
	</head>
	<body>
		<div id="head">
			<h1><?= h(gb::$site_title) ?></h1>
		</div>
		<? if ($errors): ?>
			<div id="errormsg">
				<p>
					<?= implode('</p><p>', $errors) ?>
				</p>
			</div>
		<? endif; ?>		
		<div id="content">
			<h2>Setup your gitblog</h2>
			<p>
				It's time to setup your new gitblog.
			</p>
			<form action="setup.php" method="post">
				
				<div class="inputgroup">
					<h4>Create an administrator account</h4>
					<p>Email:</p>
					<input type="text" name="email" value="<?= h(@$_POST['email']) ?>" />
					<p>Pass phrase:</p>
					<input type="password" name="passphrase" />
					<input type="password" name="passphrase2" />
					<p class="note">
						Choose a pass phrase used to authenticate as administrator. Type it twice.
					</p>
				</div>
				
				<div class="inputgroup">
					<h4>Site settings</h4>
					<p>Title:</p>
					<input type="text" name="title" value="<?= h(gb::$site_title) ?>" />
					<p class="note">
						The title of your site can be changed later.
					</p>
				</div>
				
				<div class="breaker"></div>
				<p>
				<? if (!$is_writable): ?>
					<input type="button" value="Setup" disabled="true"/>
				<? else: ?>
					<input type="submit" name="submit" value="Setup"/>
				<? endif; ?>
				</p>
			</form>
			<div class="breaker"></div>
		</div>
		<address>
			Gitblog/<?= GITBLOG_VERSION ?> (processing time <? $s = (microtime(true)-$debug_time_started); printf('%.3f ms', 1000.0 * $s) ?>)
		</address>
	</body>
</html>