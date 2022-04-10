#!/bin/bash

export DB_NAME=phpcoin

usermod -d /var/lib/mysql/ mysql
service mysql start
service apache2 start

cd /var/www/phpcoin
git pull origin main

chown -R www-data:www-data tmp
chown -R www-data:www-data web/apps

FILE=first-run
if test -f "$FILE"; then
    echo "First run node"
    export IP=$(curl -s http://whatismyip.akamai.com/)
    curl "http://$IP" > /dev/null 2>&1
    sleep 5

    echo "PHPCoin: import blockchain"
    echo "==================================================================================================="
    cd /var/www/phpcoin/tmp
    wget -q https://phpcoin.net/download/blockchain.sql.zip
    unzip blockchain.sql.zip
    cd /var/www/phpcoin
    php cli/util.php importdb tmp/blockchain.sql
    rm first-run
fi

php cli/util.php download-apps

rm -rf /var/www/phpcoin/tmp/sync-lock

tail -f tmp/phpcoin.log
