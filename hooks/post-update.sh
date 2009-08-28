#!/bin/sh
SECRET=
if [ -f ../secret ]; then
	SECRET=$(cat ../secret)
else
	SECRET=$(grep 'gb::$secret' ../gb-config.php | cut -d' ' -f 3 | sed "s/[;']//g")
fi
curl \
	-H 'X-gb-shared-secret: '$SECRET \
	--connect-timeout 5 \
	--max-time 30 \
	--silent --show-error \
	-k \
	$(cat info/gitblog-site-url|cut -d' ' -f1)'gitblog/hooks/post-update.php'
