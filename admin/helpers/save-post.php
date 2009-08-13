<?
ini_set('html_errors', '0');
require '../_base.php';
gb::authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
	gb_admin::error_rsp('405 Method Not Allowed', '405 Method Not Allowed');

try {
	# parse input
	static $spec_fields = array(
		'name' => '',
		'version' => '(work)'
	);
	static $state_fields = array(
		'title' => '',
		'body' => '',
		'tags' => '[:strtoupper]',
		'categories' => '[]',
		'published' => '@GBDateTime',
		'slug' => '',
		'author' => '@GBAuthor',
		'commentsOpen' => 'bool',
		'pingbackOpen' => 'bool',
		'draft' => 'bool'
	);
	$input = gb_input::process(array_merge($spec_fields, $state_fields));
	
	# find post
	if ($input['name'] !== null) {
		if (!($post = GBPost::findByName($input['name'], $input['version'])))
			gb_admin::error_rsp('Post '.r($input['name']).' not found');
	}
	else {
		$post = new GBPost();
	}
	
	# set post state
	$modified_state = array();
	foreach ($state_fields as $k => $discard) {
		$v = $input[$k];
		if ($v !== null && $post->$k !== $v) {
			$post->$k = $v;
			$modified_state[$k] = $post->$k;
		}
	}
	
	$rsp = array(
		'name' => $post->name,
		'version' => $post->id,
		'state' => $modified_state
	);
	
	gb_admin::json_rsp($rsp);
}
catch (UnexpectedValueException $e) {
	gb_admin::error_rsp($e->getMessage());
}

?>