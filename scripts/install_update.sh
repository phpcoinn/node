#!/bin/bash

# curl -s https://raw.githubusercontent.com/phpcoinn/node/main/scripts/install_update.sh | bash

CRON_EXISTS=$(crontab -l | grep '/var/www/phpcoin/scripts/update.sh' | wc -l)

if [ $CRON_EXISTS -eq 0 ]
then
	chmod +x /var/www/phpcoin/scripts/update.sh
	crontab -l | { cat; echo "*/5 * * * * /var/www/phpcoin/scripts/update.sh"; } | crontab -
	echo "Added new cron line"
else
	echo "Cron entry exists"
fi
