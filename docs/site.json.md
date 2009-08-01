# site.json

The `site.json` file contains the current state of your gitblog and may be updated at any time by gitblog (in contrary to `gb-config.php` which gitblog will only read).

For instance, `site.json` contains the following information:

- `url`: Absolute site URL -- This value is used to determine if the site URL has 
	changed, in relation to `.git/info/gitblog/site-url`, which is used by git hook trigger.

- `version`: Gitblog version used -- Used to determine if an upgrade action need to be taken (when comparing this string to `gb::$version`).

- `posts_pagesize`: Page size for posts -- If this differs from `gb::$posts_pagesize` a rebuild need to be issued, causing the new pagesize to be used live.

- `plugins`: Active plugins -- Dictionary of *context => list-of-plugins* which are loaded, depending on which *context* is executed. Read more in [docs/plugins.md](docs/plugins.md).

This file will be automatically created by gitblog when needed.

## Example

	{
		"url": "http://blog.hunch.se/",
		"version": "0.1.3",
		"posts_pagesize": 10,
		"plugins": {
			"request": ["google-translate.php"],
			"rebuild": [
				"code-blocks.php",
				"/Users/rasmus/markdown.php"
			]
		}
	}
