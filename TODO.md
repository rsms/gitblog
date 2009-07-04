# ToDo

- Comments
	
		{site_dir}/comments/{name}.json
		or
		{site_dir}/content/{name}.comments
		or
		{site_dir}/comments/{name}/{comment}+

- Package structure
	
		mkdir myblog && cd myblog
		git clone gitblog
		open localhost/myblog/gitblog
			git init --shared
			ln -s index.php gitblog/index.php
			mkdir -p content/pages content/posts
			cp -Rp gitblog/themes/default theme
			git add index.php theme
			git commit -m 'initial creation'
		open localhost/myblog

- Admin interface

- Tags and Categories index

- Wordpress importer
	
	`deps`: Comments
