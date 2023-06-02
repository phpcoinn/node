#!/bin/bash

export DB_NAME=phpcoin
export NODE_DIR=/var/www/phpcoin

service mysql start > /dev/null
service apache2 start > /dev/null 2>&1

cd /var/www/phpcoin
git pull origin main > /dev/null 2>&1

chown -R www-data:www-data .

FILE=first-run
if test -f "$FILE"; then
    echo "First run node"
    export IP=$(curl -s http://whatismyip.akamai.com/)
    echo "Node external IP=$IP"
    echo "Node external port=$EXT_PORT"
    curl "http://127.0.0.1" > /dev/null 2>&1
    sleep 5

    mysql $DB_NAME -e "update config set val='http://$IP:$EXT_PORT' where cfg='hostname'"

    echo "Import $NETWORK blockchain..."

    cd $NODE_DIR/tmp
    if [ "$NETWORK" = "test" ]
    then
      wget https://phpcoin.net/download/blockchain-testnet.sql.zip -O blockchain.sql.zip
    else
      wget https://phpcoin.net/download/blockchain.sql.zip -O blockchain.sql.zip
    fi

    unzip blockchain.sql.zip
    if [ "$NETWORK" = "test" ]
    then
      mv blockchain-testnet.sql blockchain.sql
    fi
    cd $NODE_DIR
    php cli/util.php importdb tmp/blockchain.sql
    rm first-run
    > tmp/phpcoin.log
fi

rm -rf /var/www/phpcoin/tmp/sync-lock
echo "Ready"
tail -f tmp/phpcoin.log
