<?
# This script is called by the post-commit hook with the commit patch on stdin.
require 'git.php';

# debug
if (isset($_ENV['TM_SELECTED_FILE'])) {
	$_SERVER['HTTP_X_GB_COMMIT'] = '1245592666 26a243919a31880d663fa04144bae26c92962064';
	$_SERVER['HTTP_X_GB_SHARED_SECRET'] = 'xyz';
}

# parse commit object and timestamp
$commit = explode(' ', $_SERVER['HTTP_X_GB_COMMIT']);
$commit_timestamp = intval($commit[0]);
$commit_id = $commit[1];

# authorized?
if ($_SERVER['HTTP_X_GB_SHARED_SECRET'] != 'xyz') {
	header('Status: 401 Unauthorized');
	exit('401 Unauthorized');
}

if (isset($_ENV['TM_SELECTED_FILE']))
	$patches = GitPatch::parse(file_get_contents("diffsample2.diff"));
else
	$patches = GitPatch::parse(file_get_contents("php://input"));
#var_export($patches);

# todo: optimize so rebuild can take an argument of modified objects, parsed from $patches,
# in order to only run writeContentObjectToStage for what's needed.
GitObjectIndex::rebuild($repo);

?>