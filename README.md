# gitblog

A git-based blog/cms platform for PHP, meant as a replacement for Wordpress.

Post-action hooks in git are used to manage an intermediate cache which consist only of structured data (no formatting), allowing dynamic presentation. This is one of the biggest differences tech-wise in comparison to Jekyll and similar tools.

Licensed under MIT means free to use for everyone. See LICENSE for more information.

## Features

- Fully git-based -- no mysql or similar involved
- Everything is versioned
- Themes
- No custom file formats for content (only JSON and HTML)
- High performance
- Hierarchical comments
- Remote editing (git push/pull)
- Wordpress import

### Work in progress

- Plugins

### Planned

- Pingback
- Web administration
- Search


## Installing & Getting started

Clone a copy of gitblog:

	$ cd /path/to/my-blog
	$ git clone git://github.com/rsms/gitblog.git

If your web server is not running as yourself, your group, or the root user, you need to change owner. In this example `www-data` is the web server user. (You will still be able to edit the blog.)

	$ chmod -R g+w .
	$ sudo chown -R www-data .

Open a web browser and point it to your `/my-blog/gitblog`. Enter email and your real name -- these will be used for commit messages. Also choose a good pass phrase which in combination with your email will grant you administration privileges in the web administration interface.

When you're done you should see a single "Hello world" post. Okay, all good.

> **What did just happen?** Gitblog initialized a git repository in `/path/to/my-blog` and added a few standard files and directories. If you ever would like to start over, just delete everything except the gitblog directory and visit `/my-blog/gitblog` in a browser again.

Let's try editing the hello world post:

	$ $EDITOR content/posts/*/*-*-hello-world.html

Make some changes, be creative!

To demonstrate that the "working tree" is indeed a working area and not the live stage, reload your web browser and see that the "Hello world" post is still not modified.

Now, let's commit the changes, pusing them live:

	$ git commit -a -m 'Updated my awesome hello-world post'

Reload your web browser and... voila!

> **Warnings when committing?** If you see `error: Could not access 'HEAD@{1}'` on stderr when committing, do not worry. This is an issue that currently do not affect gitblog, but we're looking into what causes it.

### Importing a Wordpress blog

If you have a Wordpress blog you would like to import, there is a built-in tool which does it for you! Just visit `/my-blog/gitblog/admin/import-wordpress.php` and follow the simple instructions.


## Requirements

- PHP 5.2 or newer (only standard modules are needed though)
- Git 1.6 or newer
- POSIX system


## Further play

The gb-config.php file (present in your site root) contains site-specific configuration. A default gb-config.php file, as it looks just after a blog has been setup, contains only the minimum set of paramters. There are a bunch of other paramters which might do something you whish.

Have a look in the file `gitblog/gitblog.php` -- scroll down a few lines and you'll find a class called `gb` which houses documentation and a list of all available configuration parameters, as well as their default values.


## Known bugs and issues

- Post-hook system is a bit shaky because of the nature of itself. Running scripts directly instead of POSTing to a URL would be better but many systems does not have CLI PHP or have another version than the web PHP.


## Authors

- Rasmus Andersson &lt;rasmus notion se&gt;


## History

A strangely cold morning in june 2009 Mattias Arrelid pressed the "Yeah, upgrade Wordpress". What happened seconds later still brings me down sometimes... *Every file* on our server--removable by the web server--was deleted in an instant. Many years worth of photos, audio recordings and not to mention the 30+ web sites which disappeared into the void of an unrecoverable ext3 file system.

We swore to never again use Wordpress and to do backups.

As we all like Git--this pretty little creation of the open source community--the blog tool of our future was of course based on Git. But after giving a few days of research we had not found any tool that suited our taste. (The closest match was [Jekyll](http://github.com/mojombo/jekyll/), however we wanted something more flexible, like Word...euhm). So what the heck, after all we are software engineers so why not write something ourselves?

Gitblog was born.
