#!/bin/sh
SECRET=
if [ -f ../secret ]; then
	SECRET=$(cat ../secret)
else
	SECRET=$(perl -lne 's/^(gb::\$secret[\t ]*=[\t ]*'"'"'([^'"'"']+)'"'"'[\t ]*;[\t ]*$|.*$)/$2/g;if($_){print $_;}' ../gb-config.php)
fi
curl \
	-H 'X-gb-shared-secret: '$SECRET \
	--connect-timeout 5 \
	--max-time 30 \
	--silent --show-error \
	-k \
	$(cat info/gitblog-site-url|cut -d' ' -f1)'gitblog/hooks/post-update.php'
