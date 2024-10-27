#!/bin/bash

FILE=/first-run
if test -f "$FILE"; then
    echo "First run node"
    wget https://phpcoin.net/scripts/install_node.sh -O /install_node.sh
    chmod +x install_node.sh
    if [ "$NETWORK" = "mainnet" ]
    then
      /install_node.sh --docker
    else
      /install_node.sh --docker --network testnet
    fi
    rm $FILE
    rm /install_node.sh
else
    rm -rf /var/www/phpcoin/tmp/*
    service mariadb start
    service nginx start
    service php8.1-fpm start
fi
php /var/www/phpcoin/cli/util.php clear-peers
bash
