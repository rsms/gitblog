<?php
require_once '../_base.php';
function gb_log_html($priority, $msg) {
	echo '<p class="logmsg '.($priority === LOG_WARNING ? 'warning' : ($priority < LOG_WARNING ? 'error' : ''))
		.'">'.h($msg).'</p>';
	gb_flush();
}
gb::$log_cb = 'gb_log_html';
gb::authenticate();
gb::$title[] = 'Rebuild';
include '../_header.php';
?>
<style type="text/css">
	p.logmsg { margin:2px 0; font-size:9px; font-family:monospace; }
	p.logmsg.error, p.logmsg.warning { padding:1em; font-size:13px; }
	p.logmsg.error { background:#faa; white-space:pre; }
	p.logmsg.warning { background:#ff9; }
</style>
<div id="content" class="<?php echo gb_admin::$current_domid ?> margins">
	<h2>Rebuilding</h2>
	<?php GBRebuilder::rebuild(isset($_GET['force-full-rebuild'])); ?>
	<p>
		<input type="button" value="Force full rebuild" onclick="document.location.href='?force-full-rebuild'" />
	</p>
</div>
<?php include '../_footer.php' ?>
