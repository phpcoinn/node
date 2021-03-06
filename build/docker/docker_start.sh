#!/bin/bash

export DB_NAME=phpcoin

service mysql start > /dev/null
service apache2 start > /dev/null 2>&1

cd /var/www/phpcoin
git pull origin main > /dev/null 2>&1

chown -R www-data:www-data tmp
chown -R www-data:www-data web/apps

FILE=first-run
if test -f "$FILE"; then
    echo "First run node"
    export IP=$(curl -s http://whatismyip.akamai.com/)
    echo "Node external IP=$IP"
    curl "http://127.0.0.1" > /dev/null 2>&1
    sleep 5

    mysql $DB_NAME -e "update config set val='http://$IP' where cfg='hostname'"

    echo "Import blockchain... "
    cd /var/www/phpcoin/tmp
    wget -q https://phpcoin.net/download/blockchain.sql.zip
    unzip blockchain.sql.zip > /dev/null 2>&1
    cd /var/www/phpcoin
    php cli/util.php importdb tmp/blockchain.sql > /dev/null
    rm first-run
    > tmp/phpcoin.log
fi

php cli/util.php download-apps

rm -rf /var/www/phpcoin/tmp/sync-lock

echo "Ready"
tail -f tmp/phpcoin.log
