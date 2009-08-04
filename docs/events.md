# Events

*This document is early work in progress*

Gitblog post *events* when things are about to happen or did happen. These events can be observed and you (themes, plugins, etc) can run custom code when an observed event is posted.

## Standard events

Currently the easist way to learn about standard (built-in) events is to grep for `gb::event(` in the gitblog directory:

	$ grep -r 'gb::event(' gitblog/

## Example of use

In a theme:

	function disable_keepalive() {
		header('Connection: close');
	}
	gb::observe('will-handle-request', 'disable_keepalive');

A simple plugin:

	class example_plugin {
		static function init($context) {
			gb::observe('did-reload-object', __CLASS__.'::did_reload_object');
		}
		
		static function did_reload_object(GBContent $post) {
			# do something with or because of $post
		}
	}

