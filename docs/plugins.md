# Plugins

## Execution contexts

- rebuild

- request


### Rebuild

Plugins registered in the *rebuild* context are loaded when a rebuild is taking place.

Examples:

- Adding support for a new input format (i.e. markdown or similar)

- Building custom indices (i.e. popular comments)


### Request

Plugins registered in this context are loaded for every public request to the blog interface. Please keep in mind the performance penelty introduced by PHP `require` which is used under the hood.

Examples:

- Modifying behaviour based on who is visiting the site (i.e. alternate interface for mobile users)

> **Not yet implemented.** This execution context has not yet been fully implemented but will be available in a future version.


### Hook

Plugins registered in this context can be triggered from external URLs, for instance by a scheduling service like cron or a push-notification like a new photo uploaded on Flickr.

These plugins are very flexible. When a hook is triggered, Gitblog first authenticates the request, then the plugin entry function is called in which the plugin do the rest of the job.

Examples:

- Importing data from other sites (i.e. Twitter status or Flickr photos) on demand

- Triggering a rebuild based on custom logic

Gitblog comes with one built-in hook: The rebuild hook, causing partial or complete rebuild of the live site. This hook is automatically called by git when the repository is altered.

> **Not yet implemented.** This execution context has not yet been fully implemented but will be available in a future version.

## Loading and activation

Plugins are simple PHP files (or possibly other executable files, depending on what kind of plugin it is) which are installed by putting the file into one of the *search paths*. Plugins are enabled by editing the "plugins" section of `site.json`.

A special function is called after the plugin has been loaded. This function should setup the plugin and respond with a true value if it did initialize or a false value if it did not. Returning a false value might cause the plugin init function to be called again, later when something in gitblog might have become available. When these different load points are, is currently undefined.

Currently *rebuild* plugins will be offered initialization once the rebuild task starts, then again when all rebuilder classes have instantiated to allow for modifying these instances or previously loaded rebuilders.

### Initialization function

	function nameofplugin_init($context) {
		# initialize plugin
		return true;
	}

Where `nameofplugin` is the name of the plugin file, without filename extension. Any "." or "-" are converted to "\_" characters (i.e. "my-plug.in.php" -> "my\_plug\_in").

