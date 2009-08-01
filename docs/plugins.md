# Plugins

Gitblog have support for plugins written in PHP. These plugins are simple PHP files which will be loaded and then a specially named function will be calles. You can learn more by reading the section "Loading and activation" further down this document.

There are a few different types of *execution contexts* in which a plugin can be loaded.

## Execution contexts

### rebuild

Plugins registered in the *rebuild* context are loaded when a rebuild is taking place.

Examples:

- Adding support for a new input format (i.e. markdown or similar)

- Building custom indices (i.e. popular comments)


### request

Plugins registered in this context are loaded for every public request to the blog interface. Please keep in mind the performance penelty introduced by PHP `require` which is used under the hood.

Examples:

- Modifying behaviour based on who is visiting the site (i.e. alternate interface for mobile users)


### admin

Plugins registered in this context will be loaded when an administrative task is taking place. For instance when a comment is added or removed.

Examples:

- Denying a comment based on some set of parameters.


## Loading and activation

Plugins are simple PHP files (or possibly other executable files, depending on what kind of plugin it is) which are installed by putting the file into one of the *search paths*. Plugins are enabled by editing the "plugins" section of [site.json](docs/site.json.md).

A special function is called after the plugin has been loaded. This function should setup the plugin and respond with a `true` value if it did initialize or a `false` value if it did not. Returning a `false` value might cause the plugin init function to be called again later when something in gitblog might have become available. Where these different load points are located, is currently undocumented here but is described in-line in the code (most notably in the file [lib/GBRebuilder.php](lib/GBRebuilder.php)).

Currently *rebuild* plugins will be offered initialization once the rebuild task starts, then again when all rebuilder classes have instantiated to allow for modifying these instances or previously loaded rebuilders.


### Initialization function

	function nameofplugin_init($context) {
		# initialize plugin
		return true;
	}

Where `nameofplugin` is the name of the plugin file, without filename extension. Any "." or "-" are converted to "\_" characters (i.e. "my-plug.in.php" -> "my\_plug\_in").

