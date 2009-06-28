<?
require_once '_base.php';

if ($integrity === 2) {
	header("Location: ".GITBLOG_SITE_URL."gitblog/admin/setup.php");
	exit(0);
}

gb::$title[] = 'Admin';
#require_once '_header.php';

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?= gb_title() ?></title>
  </head>
  <body>
    
  </body>
</html>