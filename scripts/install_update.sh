#!/bin/bash

# curl -s https://raw.githubusercontent.com/phpcoinn/node/main/scripts/install_update.sh | bash

CRON_LINE="cd /var/www/phpcoin && php cli/util.php update"
CRON_EXISTS=$(crontab -l | grep "$CRON_LINE" | wc -l)

if [ $CRON_EXISTS -eq 0 ]
then
	crontab -l | { cat; echo "*/5 * * * * $CRON_LINE"; } | crontab -
	echo "Added new cron line"
else
	echo "Cron entry exists"
fi
