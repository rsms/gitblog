# site.json

This file contains the current state of the site and may be rewritten at any time by gitblog (in contrary to `gb-config.php` which gitblog will only read). This file contains the following information:

- `url`: Absolute site URL -- This value is used to determine if the site URL has 
	changed, in relation to `.git/info/gitblog/site-url`, which is used by git hook trigger.

- `version`: Gitblog version used -- Used to determine if an upgrade action need to be taken when comparing this string to `gb::$version`.

- `posts_pagesize`: Page size for posts -- If this differs from `gb::$posts_pagesize` a rebuild need to be issued, causing the new pagesize to be used live.

- `plugins`: Active plugins -- Dictionary of *context => list-of-plugins* which are loaded, depending on which *context* is executed.


## Example

	{
		"url": "https://apple.spotify.net/tmp/mygitblog/",
		"version": "0.1.0",
		"posts_pagesize": 10,
		"plugins": {
			"online": ["{site-plugins}/google-translate.php"],
			"rebuild": [
				"code-sections.php",
				"/Users/rasmus/markdown.php"
			]
		}
	}
