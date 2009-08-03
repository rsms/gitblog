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

Plugins registered in this context are loaded for every public request to the blog interface. Please keep in mind the performance penalty introduced by PHP `require` which is used under the hood.

Examples:

- Modifying behaviour based on who is visiting the site (i.e. alternate interface for mobile users)


### admin

Plugins registered in this context will be loaded when an administrative task is taking place. For instance when a comment is added or removed.

Examples:

- Denying a comment based on some set of parameters.


## Loading and activation

Plugins are simple PHP files (or possibly other executable files, depending on what kind of plugin it is) which are installed by putting the file into one of the *search paths*. Plugins are enabled by `data/plugins.json`.

A class of the name <name of plugin> "_plugin" is expected to exists after the plugin file has been loaded. Next, the static method `init` is called on that class with a single argument `string $context`. The `init` method should return `true` if the plugin did initialize, otherwise `false` or nothing should be returned. Returning a `false` value might cause the plugin init method to be called again when something in gitblog might have become available.
	
> Where these different load points are located, is currently undocumented but is described in-line in the code (most notably in the file [lib/GBRebuilder.php](../lib/GBRebuilder.php)).
> 
> Currently *rebuild* plugins will be offered initialization once the rebuild task starts, then again when all rebuilder classes have instantiated to allow for modifying these instances or previously loaded rebuilders.

## Configuration and settings

If a plugin need any sort of configuration and/or keep state it should use a `gb::data()` store, named like *"plugins/name-of-plugin"*.

### Example

`my-example.php`:

	<?
	/**
	 * @name    My example
	 * @version 1.0
	 * @author  Fred Boll
	 * @uri     http://fredboll.com/
	 * 
	 * This plugins doesn't really do anything but send a stupid log message.
	 */
	class my_example_plugin {
		static function init($context) {
			$conf = gb::data('plugins/my-example', array('key' => 'default value'));
			gb::log('Yay! I can haz loaded in the %s context', $context);
			gb::log('key => %s', $conf['key']);
			return true;
		}
	}

> **Note:** The name of the plugin is constructed as follows: `$name = str_replace(array('-', '.'), '_', substr($filename, 0, -4))` and the class name is constructed like this: `$class = $name . '_plugin'`.

Have a look at the [built-in plugins](../plugins) as they are pretty good for learn-by-example.
