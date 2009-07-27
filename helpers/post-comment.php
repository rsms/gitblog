<?
/**
 * Takes care of posting a comment.
 * 
 * Events:
 *   
 * - "was-duplicate-comment", $comment
 *   Posted after a comment was detected to be a duplicate and will not be
 *   added $exposedcontentobj. Posted just before the response is sent.
 * 
 * - "did-add-comment", $comment
 *   Posted after a comment was successfully added to $exposedcontentobj, but
 *   before the response is sent.
 * 
 */
require '../gitblog.php';
ini_set('html_errors', '0');
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');

gb::catch_errors();
gb::verify();
gb::authenticate(false);
gb::load_plugins('admin');

/**
 * Acceptable fields.
 * array( string name => filterspec , .. )
 */
$fields = array(
	# -------------------------------------------------------------------
	# required fields
	
	# Stage name of the object on which to add the comment.
	'reply-post' => FILTER_REQUIRE_SCALAR,
	
	# The actual comment
	'reply-message' => FILTER_REQUIRE_SCALAR,
	
	# Authors email address
	'author-email' => FILTER_VALIDATE_EMAIL,
	
	# Authors name
	'author-name' => FILTER_REQUIRE_SCALAR,
	
	# -------------------------------------------------------------------
	# optional fields
	
	# In reply to a supercomment with comment id <value>
	'reply-to' => FILTER_REQUIRE_SCALAR,
	
	# Authors URL
	'author-url' => FILTER_SANITIZE_URL,
	
	# Authors URI (shadowed by "author-url" unless author-url === false)
	'author-uri' => FILTER_REQUIRE_SCALAR,
	
	# client timezone offset in seconds (east of UTC is positive, west of UTC is
	# negative). Could be derived from javascript Date object like this:
	#   -((new Date()).getTimezoneOffset()*60);
	'client-timezone-offset' => array(
		'filter'  => FILTER_VALIDATE_INT,
		'options' => array('min_range' => -43200, 'max_range' => 43200)
	),
	
	# -------------------------------------------------------------------
	# implicit fields
	
	# Nonce
	'gb-nonce' => array(
		'filter' => FILTER_SANITIZE_STRING,
		'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
	),
	
	# Referrer
	'gb-referrer' => FILTER_SANITIZE_URL
);

function exit2($msg, $status='400 Bad Request') {
	header('Status: '.$status);
	exit($status."\n".$msg."\n");
}

# only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
	exit2('Only POST is allowed', '405 Method Not Allowed');

# sanitize and validate input
static $required_fields = array('reply-post', 'reply-message', 'author-email', 'author-name');
$input = filter_input_array(INPUT_POST, $fields);

# assure required fields are OK
$fields_missing = array();
foreach ($required_fields as $field) {
	if (!$input[$field])
		$fields_missing[] = $field;
}
if ($fields_missing)
	exit2('missing parameter(s): '.implode(', ', $fields_missing));
elseif (strlen($input['reply-message']) < 2)
	exit2('you have to say more than a single character my friend.');

# sanitize $input['reply-post']
$input['reply-post'] = trim(str_replace('..', '', $input['reply-post']), '/');
if (strpos($input['reply-post'], 'content/') !== 0)
	exit2('malformed parameter "reply-post"');

# look up post/page
$post = GBExposedContent::findByCacheName($input['reply-post']);

# verify existing content and that comments are enabled
if (!$post) exit2('no such reply-post '.$input['reply-post']);
if (!$post->commentsOpen) exit2('commenting not allowed', '403 Forbidden');

# verify nonce
if ($input['gb-nonce'] && gb_nonce_verify($input['gb-nonce'], 'post-comment-'.$input['reply-post']) === false)
	exit2('nonce verification failure');

# adjust date with clients local timezone
$date = new GBDateTime(null, 0);
if ( $input['client-timezone-offset'] !== false 
	&& (($tzoffset = intval($input['client-timezone-offset'])) !== false)
	&& ($tzoffset < 43200 || $tzoffset > -43200) )
{
	$date->offset = $tzoffset;
}

# author-url -> author-uri if set
if ($input['author-url'] !== false)
	$input['author-uri'] = GBFilter::apply('sanitize-url', $input['author-url']);

# if we are logged in, use the canonical email
if (gb::$authorized)
	$input['author-email'] = gb::$authorized->email;

# set author cookie
gb_author_cookie::set($input['author-email'], $input['author-name'], $input['author-uri']);

# create comment object
$comment = new GBComment(array(
	'date'      => $date->__toString(),
	'ipAddress' => preg_replace('/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR']),
	'email'     => $input['author-email'],
	'uri'       => $input['author-uri'],
	'name'      => $input['author-name'],
	'body'      => $input['reply-message'],
	'approved'  => false,
	
	# not stored, but used until request has finished
	'post'      => $post
));

# always approve admin comments
if (gb::$authorized)
	$comment->approved = true;

# apply filters
$comment = GBFilter::apply('pre-comment', $comment);

# append to comment db
if ($comment) {
	try {
		$cdb = $post->getCommentsDB();
		$added = $cdb->append($comment, $input['reply-to'] ? $input['reply-to'] : null);
		
		# duplicate?
		if ($added === false) {
			gb::log(LOG_NOTICE, 'skipped duplicate comment from '.var_export($comment->email,1));
			gb::event('was-duplicate-comment', $comment);
			if (isset($input['gb-referrer'])) {
				$dest = new GBURL($input['gb-referrer']);
				$dest->fragment = 'comments';
				$dest['skipped-duplicate-comment'] = $comment->id;
				header('Status: 304 Not Modified');
				header('Location: '.$dest);
				exit(0);
			}
			else {
				exit2("duplicate comment\n", '200 OK');
			}
		}
		
		gb::log(LOG_NOTICE, 'added comment from '.var_export($comment->email,1)
			.' to '.$post->cachename());
		
		gb::event('did-add-comment', $comment);
		
		# done
		if (isset($input['gb-referrer'])) {
			gb::log($input['gb-referrer']);
			$dest = new GBURL($input['gb-referrer']);
			$dest->fragment = 'comment-'.$comment->id;
			if (!$comment->approved) {
				$dest->fragment = 'comments';
				$dest['comment-pending-approval'] = $comment->id;
			}
			header('Status: 303 See Other');
			gb::log('Location: '.$dest);
			header('Location: '.$dest);
		}
		else {
			exit2("new comment: {$comment->id}\n", '200 OK');
		}
	}
	catch (Exception $e) {
		if ($e instanceof GitError && strpos($e->getMessage(), 'nothing to commit') !== false) {
			gb::log(LOG_NOTICE, 'skipped duplicate comment from '
				.var_export($comment->email,1).' (nothing to commit)');
			gb::event('was-duplicate-comment', $comment);
			header('Status: 304 Not Modified');
			header('Location: '.$input['gb-referrer'].'#skipped-duplicate-reply');
			exit(0);
		}
		
		gb::log(LOG_ERR, 'failed to add comment '.var_export($comment->body,1)
			.' from '.var_export($comment->name,1).' <'.var_export($comment->email,1).'>'
			.' to '.$post->cachename());
		
		header('Status: 500 Internal Server Error');
		echo '$input => ';var_export($input);echo "\n";
		flush();
		throw $e;
	}
}

?>