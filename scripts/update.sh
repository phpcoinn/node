#!/bin/bash

echo "Checking node source change"

cd /var/www/phpcoin
CHANGE=$([ `git log --pretty=%H ...refs/heads/main^` = `git ls-remote origin -h refs/heads/main |cut -f1` ] && echo "up to date" || echo "not up to date")

if  [ "$CHANGE" = "not up to date" ]
then
	echo "Updating source"
	git pull origin main
	php cli/util.php download-apps
	echo "Finished"
else
	echo "Nothing to update"
fi
