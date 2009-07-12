<?
require '../gitblog.php';
ini_set('html_errors', '0');
header('Content-Type: text/plain; charset=utf-8');

GitBlog::verifyConfig();

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
	exit('missing parameter(s): '.implode(', ', $fields_missing));

# remove any ".." and trim "/" from $input['reply-post']
$input['reply-post'] = trim(str_replace('..', '', $input['reply-post']), '/');

# verify nonce
if ($input['gb-nonce'] && gb_nonce_verify($input['gb-nonce'], 'post-comment-'.$input['reply-post']) === false)
	exit('nonce verification failure');

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

# create comment object
$comment = new GBComment(array(
	'date'      => $date->__toString(),
	'ipAddress' => $_SERVER['REMOTE_ADDR'],
	'email'     => $input['author-email'],
	'uri'       => $input['author-uri'],
	'name'      => $input['author-name'],
	'body'      => $input['reply-message'],
	'approved'  => false,
));	

# apply filters
$comment = GBFilter::apply('pre-comment', $comment);

# append to comment db
if ($comment) {
	$pathspec = gb_filenoext($input['reply-post']).'.comments';
	$stagename = GB_SITE_DIR.'/'.$pathspec;
	$cdb = new GBCommentDB($stagename);
	$index = $cdb->append($comment, $input['reply-to'] ? $input['reply-to'] : null);
	
	# duplicate?
	if ($index === false) {
		# done
		if (isset($input['gb-referrer']))
			header('Location: '.$input['gb-referrer'].'#reply');
		else
			echo "skipped duplicate comment\n";
		exit(0);
	}
	
	# add & commit
	GitBlog::add($pathspec);
	try {
		$ciauthor = ($input['author-name'] ? $input['author-name'].' ' : '') . '<'.$input['author-email'].'>';
		$cimsg = 'new comment';
		if ($input['reply-to'])
			$cimsg .= ' in reply to comment #'.$input['reply-to'].')';
		GitBlog::commit($cimsg, $ciauthor);
		
		# done
		if (isset($input['gb-referrer']))
			header('Location: '.$input['gb-referrer'].'#comment-'.$index);
		else
			echo "new comment index: $index\n";
	}
	catch (GitError $e) {
		GitBlog::reset($pathspec);
		throw $e;
	}
}

?>