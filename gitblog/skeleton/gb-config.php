<?
$gb_config = array(
	# Site title
	'title' => 'My gitblog',
	
	# Shared secret used for hook triggering.
	# You should change this to a string at least 64 bytes long.
	#
	# This utility can be used to generate suitable random strings:
	# http://api.wordpress.org/secret-key/1.1/
	'secret' => '',
	
	# URL to the base of the site.
	#
	# If your blog is hosted on it's own domain, for example 
	# http://my.blog.com/, the value of this parameter could be either "/" or the
	# complete url "http://my.blog.com/".
	#
	# If your blog is hosted in a subdirectory, for example
	# http://somesite.com/blogs/user/ the value of this parameter could be either
	# "/blogs/user/" or the complete url "http://somesite.com/blogs/user/".
	#
	# Must end with a slash ("/").
	'base-url' => '/gitblog/',
	
	# URL prefix for tags
	'tags-prefix' => 'tags/',
	
	# URL prefix for categories
	'categories-prefix' => 'categories/',
	
	# Related to posts
	'posts' => array(
		# URL prefix (strftime format and matching pcre pattern)
		'url-prefix' => '%Y/%m/%d/',
		'url-prefix-re' => '/^\d{4}\/\d{2}\/\d{2}\//',
		# Number of posts per page.
		# Changing this requires a rebuild before actually activated.
		'pagesize' => 10
	),
	
	# Absolute path to git repository.
	# 
	# Normally the default value is good, but in the case "site/" creates URL
	# clashes for you, you might want to change this.
	#
	# This should be the path to a non-bare repository, i.e. a directory in which
	# a working tree will be checked out and contain a regular .git-directory.
	#
	# The path must be writable by the web server and the contents will have 
	# umask 0220 (i.e. user and group writable) thus you can, after letting 
	# gitblog create the repo for you, chgrp or chown to allow for remote pushing
	# by other user(s) than the web server user.
	'repo' => dirname(realpath(__FILE__)).'/site',
);

# URL to gitblog index (request handler).
# Must end with a slash ("/").
$gb_config['index-url'] = $gb_config['base-url'].'index.php/';
# if you have server rewrite rules active, this is probably what you want:
#$gb_config['index-url'] =& $gb_config['base-url'];

?>