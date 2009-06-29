# gitblog

A git-based blog/cms platform for PHP, meant as a replacement for Wordpress.

## Installing & Getting started

Clone a copy of gitblog:

	$ cd my/website/document/root
	$ git clone git://github.com/rsms/gitblog.git my-blog
	$ cd my-blog

If your web server is not running as yourself, your group or root, you need to change owner. In this example `www-data` is the web server user. You will still be able to edit the blog.

	$ chmod -R g+w .
	$ sudo chown -R www-data .

Open a web browser and point it to your `/my-blog`. Enter email and your real name -- these will be used for commit messages. Also choose a good pass phrase wich in combination with your email will grand you administration privileges in the web administration interface.

When you're done you should see a single "Hello world" post. Okay, all good.

Let's try editing the hello world post:

	$ cd site
	$ $EDITOR content/posts/*/*-*-hello-world.html

Make some changes, be creative!

To demonstrate that the "working tree" is indeed a working area and not the live stage, reload your web brwoser and see that the "Hello world" post is still not modified.

Now, let's commit the changes, pusing them live:

	$ git commit -a -m 'Updated my awesome hello-world post'

Reload your web browser and... voila!


## Further play

The gb-config.php file (present in your site root) contains site-specific configuration. A default gb-config.php file, as it looks just after a blog has been setup, contains only the minimum set of paramters. There are a bunch of other paramters which might do something you whish.

Have a look in the file `gitblog/gitblog.php` -- scroll down a few lines and you'll find a class called `gb` which houses documentation and a list of all available configuration parameters, as well as their default values.


## Authors

- Rasmus Andersson &lt;rasmus notion se&gt;


## History

A strangely cold morning in june 2009 Mattias Arrelid pressed the "Yeah, upgrade Wordpress". What happened seconds later still brings me down sometimes... *Every file* on our server--removable by the web server--was deleted in an instant. Many years worth of photos, audio recordings and not to mention the 30+ web sites which disappeared into the void of an unrecoverable ext3 file system.

We swore to never again use Wordpress and to do backups.

As we all like Git--this pretty little creation of the open source community--the blog tool of our future was of course based on Git. But after giving a few days of research we had not found any tool that suited our taste. (The closest match was [Jekyll](http://github.com/mojombo/jekyll/), however we wanted something more flexible, like Word...euhm). So what the heck, after all we are software engineers so why not write something ourselves?

Gitblog was born.
