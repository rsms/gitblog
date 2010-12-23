<?php
ini_set('html_errors', '0');
require '../_base.php';
gb::authenticate();

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
	gb_admin::error_rsp('405 Method Not Allowed', '405 Method Not Allowed');

try {
	# parse input
	static $spec_fields = array(
		'name' => '',
		'version' => '(work)',
		'commit' => 'bool(false)'
	);
	static $state_fields = array(
		'mimeType' => ':trim',
		'title' => ':trim',
		'slug' => ':trim',
		'body' => '',
		'tags' => '[]',
		'categories' => '[]',
		'published' => '@GBDateTime',
		'author' => '@GBAuthor',
		'commentsOpen' => 'bool',
		'pingbackOpen' => 'bool',
		'draft' => 'bool'
	);
	$input = gb_input::process(array_merge($spec_fields, $state_fields));
	
	# find post
	$created = false;
	if ($input['name'] !== null) {
		if (!($post = GBPost::findByName($input['name'], $input['version'])))
			gb_admin::error_rsp('Post '.r($input['name']).' not found');
	}
	else {
		$post = new GBPost();
		$created = true;
	}
	
	# set post state
	$modified_state = array();
	foreach ($state_fields as $k => $discard) {
		$v = $input[$k];
		if ($v !== null && $post->$k !== $v) {
			if ($k === 'body') {
				$post->setRawBody($v);
				$modified_state[$k] = $post->rawBody();
			}
			else {
				$post->$k = $v;
				$v = $post->$k;
				if ($v instanceof GBDateTime || $v instanceof GBAuthor)
					$v = strval($v);
				$modified_state[$k] = $v;
			}
		}
	}
	
	# post-process checks before saving
	if ($modified_state) {
		$post->modified = new GBDateTime();
		if (!$post->title && !$post->slug) {
			throw new UnexpectedValueException(
				'Both title and slug can not both be empty. Please choose a title for this post.');
		}
		if (!$post->slug)
			$post->slug = gb_cfilter::apply('sanitize-title', $post->title);
		elseif ($created && !$post->title)
			$post->title = ucfirst($post->slug);
	}
	
	# set newborn properties
	if ($created) {
		if (!$post->mimeType) {
			$post->mimeType = 'text/html';
			gb::log('did force html');
		}
		else {
			gb::log('mime type is %s', $post->mimeType);
		}
		if (!$post->published)
			$post->published = $post->modified;
		$post->name = $post->recommendedName();
	}
	else {
		gb::log('already exists (OK)');	
	}
	
	# was the state actually modified?
	if ($modified_state) {
		gb::log('write %s', r($modified_state));
		# write to work area
		gb_admin::write_content($post);
	}
	
	# if the post was created, reload it to find appropriate values
	if ($created) {
		$post = GBPost::findByName($post->name, 'work');
		$modified_state = array();
		foreach ($state_fields as $k => $discard) {
			if ($k === 'body') {
				$modified_state[$k] = $post->rawBody();
			}
			else {
				$v = $post->$k;
				if ($v instanceof GBDateTime)
					$v = strval($v);
				$modified_state[$k] = $v;
			}
		}
	}
	
	# commit?
	if ($input['commit']) {
		git::add($post->name);
		git::commit(($created ? 'Created' : 'Updated').' post '.r($post->title), 
			gb::$authorized, $post->name);
	}
	
	# build response entity
	$rsp = array(
		'name' => $post->name,
		'version' => $post->id,
		'exists' => $post->exists(),
		'isTracked' => $post->isTracked(),
		'isDirty' => $post->isDirty(),
		'state' => $modified_state
	);
	
	# status
	$status = '200 OK';
	if ($created) {
		$status = '201 Created';
	}
	
	# send JSON response
	gb_admin::json_rsp($rsp, $status);
	gb::log('saved post %s', $post->name);
}
catch (Exception $e) {
	gb::log('failed to save post: %s', GBException::format($e, true, false, null, 0));
	gb_admin::json_rsp($e->getMessage(), '400 Bad Request');
}

?>