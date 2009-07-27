<?
/**
 * Takes care of removing comments.
 * 
 * Events:
 * 
 * - "did-remove-comment", $comment
 *   Posted after a comment was removed, but before the response is sent.
 * 
 */
require '../gitblog.php';
ini_set('html_errors', '0');
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');

gb::catch_errors();
gb::verify();
gb::authenticate();
gb::load_plugins('admin');

static $fields = array(
	'object' => FILTER_REQUIRE_SCALAR,
	'comment' => FILTER_REQUIRE_SCALAR,
	'referrer' => FILTER_SANITIZE_URL
);
static $required_fields = array('object','comment');

# sanitize and validate input
$input = filter_input_array($_SERVER['REQUEST_METHOD'] === 'POST' ? INPUT_POST : INPUT_GET, $fields);

function exit2($msg, $status='400 Bad Request') {
	header('Status: '.$status);
	exit($status."\n".$msg."\n");
}

# Optimally only allow the DELETE method, but as we live with HTML that's not
# gonna happen very soon unfortunately.

# assure required fields are OK
$fields_missing = array();
foreach ($required_fields as $field) {
	if (!$input[$field])
		$fields_missing[] = $field;
}
if ($fields_missing)
	exit2('missing parameter(s): '.implode(', ', $fields_missing));

# sanitize $input['object']
$input['object'] = trim(str_replace('..', '', $input['object']), '/');
if (strpos($input['object'], 'content/') !== 0)
	exit2('malformed parameter "object"');

# sanitize $input['comment']
$input['comment'] = preg_replace('/[^0-9\.]+/', '', $input['comment']);

# look up post/page
$post = GBExposedContent::findByCacheName($input['object']);

# verify existing content and that comments are enabled
if (!$post) exit2('no such object '.$input['object']);

# remove from comment db
try {
	$cdb = $post->getCommentsDB();
	$removed_comment = $cdb->remove($input['comment']);
	
	# comment not found
	if (!$removed_comment) {
		if (isset($input['referrer'])) {
			$dest = new GBURL($input['referrer']);
			$dest['gb-error'] = 'Comment '.$input['comment'].' not found';
			header('Status: 303 See Other');
			header('Location: '.$dest);
		}
		else {
			header('Status: 404 Not Found');
		}
		exit('no such comment '.$input['comment']);
	}
	
	gb::log(LOG_NOTICE, 'removed comment %s by %s from post %s',
		$input['comment'], $removed_comment->name, $post->cachename());
	gb::event('did-remove-comment', $removed_comment);
	
	# done OK
	if (isset($input['referrer'])) {
		header('Status: 303 See Other');
		header('Location: '.$input['referrer'].'#comments');
	}
	else {
		exit2("removed comment: {$removed_comment->id}\n", '200 OK');
	}
}
catch (Exception $e) {
	gb::log(LOG_ERR, 'failed to remove comment %s from %s', $input['comment'], $post->cachename());
	header('Status: 500 Internal Server Error');
	echo '$input => ';var_export($input);echo "\n";
	flush();
	throw $e;
}

?>