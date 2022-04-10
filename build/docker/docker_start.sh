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
    php cli/util.php
    export IP=$(curl -s http://whatismyip.akamai.com/)
    mysql -e "update config set val='http://$IP' where cfg ='hostname'" $DB_NAME

    echo "PHPCoin: import blockchain"
    echo "==================================================================================================="
    cd /var/www/phpcoin/tmp
    wget -q https://phpcoin.net/download/blockchain.sql.zip
    unzip blockchain.sql.zip
    cd /var/www/phpcoin
    php cli/util.php importdb tmp/blockchain.sql
    rm first-run
    > tmp/phpcoin.log
fi

php cli/util.php download-apps

bash
