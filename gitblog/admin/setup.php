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
		if (!GBUserAccount::create(trim($_POST['email']), $_POST['passphrase'], 
			trim($_POST['name']), true))
		{
			$errors[] = 'Failed to create administrator user account';
		}
	}
	
	# -------------------------------------------------------------------------
	# commit changes (done by $gitblog->init())
	if (!$errors) {
		try {
			if (!$gitblog->commit('gitblog created', GBUserAccount::getAdmin()))
				$errors[] = 'failed to commit creation';
		}
		catch (Exception $e) {
			$errors[] = 'failed to commit creation: '.nl2br(h(strval($e)));
		}
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

include '_header.php';
?>
			<h2>Setup your gitblog</h2>
			<p>
				It's time to setup your new gitblog.
			</p>
			<form action="setup.php" method="post">
				
				<div class="inputgroup">
					<h4>Create an administrator account</h4>
					<p>Email:</p>
					<input type="text" name="email" value="<?= h(@$_POST['email']) ?>" />
					<p>Real name:</p>
					<input type="text" name="name" value="<?= h(@$_POST['name']) ?>" />
					<p class="note">
						This will be used for commit messages, along with email.
						Commit history can not be changed afterwards, so please provide your real name here.
					</p>
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
<? include '_footer.php'; ?>