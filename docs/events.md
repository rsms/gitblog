# Events

*This document is early work in progress*

Gitblog post *events* when things are about to happen or did happen. These events can be observed and you (themes, plugins, etc) can run custom code when an observed event is posted.

Example of theme usage:

	<?
	function disable_keepalive() {
		header('Connection: close');
	}
	gb::observe('will-handle-request', 'disable_keepalive');
	?>

Example of plugin usage:

	<?
	function forward_comment_to_irc($comment) {
		if ($comment->approved)
			SomeIRCLibrary::send($comment->name.' said: '.$comment->body);
	}
	gb::observe('did-add-comment', 'forward_comment_to_irc');
	?>
