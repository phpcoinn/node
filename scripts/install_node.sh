#!/bin/bash
# setup node on ubuntu server 21.04, 20.04, 18.04
# one liner: curl -s https://raw.githubusercontent.com/phpcoinn/node/main/scripts/install_node.sh | bash

echo "PHPCoin node Installation"
echo "==================================================================================================="
echo "PHPCoin: define db user and pass"
echo "==================================================================================================="
export DEBIAN_FRONTEND=noninteractive
export DB_NAME=phpcoin
export DB_USER=phpcoin
export DB_PASS=phpcoin
export NODE_DIR=/var/www/phpcoin

echo "PHPCoin: update system"
echo "==================================================================================================="
apt update
echo "install php with apache server"
apt install curl wget nano git -y
apt install apache2 php libapache2-mod-php php-mysql php-gmp php-bcmath php-curl unzip -y
apt install mariadb-server -y

echo "PHPCoin: create database and set user"
echo "==================================================================================================="
mysql -e "create database $DB_NAME;"
mysql -e "create user '$DB_USER'@'localhost' identified by '$DB_PASS';"
mysql -e "grant all privileges on $DB_NAME.* to '$DB_USER'@'localhost';"

echo "PHPCoin: download node"
echo "==================================================================================================="
mkdir $NODE_DIR
cd $NODE_DIR
git config --global --add safe.directory $NODE_DIR
git clone https://github.com/phpcoinn/node .
git config core.fileMode false

echo "PHPCoin: Configure apache"
echo "==================================================================================================="
cat << EOF > /etc/apache2/sites-available/phpcoin.conf
<VirtualHost *:80>
        ServerAdmin webmaster@localhost
        DocumentRoot $NODE_DIR/web
        RewriteEngine on
        RewriteRule ^/dapps/(.*)$ /dapps.php?url=$1
</VirtualHost>
EOF
a2dissite 000-default
a2ensite phpcoin
a2enmod rewrite
service apache2 restart

echo "PHPCoin: setup config file"
echo "==================================================================================================="
CONFIG_FILE=config/config.inc.php
if [ ! -f "$CONFIGFILE" ]; then
  cp config/config-sample.inc.php config/config.inc.php
  sed -i "s/ENTER-DB-NAME/$DB_NAME/g" config/config.inc.php
  sed -i "s/ENTER-DB-USER/$DB_USER/g" config/config.inc.php
  sed -i "s/ENTER-DB-PASS/$DB_PASS/g" config/config.inc.php
fi
echo "PHPCoin: configure node"
echo "==================================================================================================="
mkdir tmp
mkdir dapps
chown -R www-data:www-data .

export IP=$(curl -s http://whatismyip.akamai.com/)
echo "PHPCoin: open start page"
echo "==================================================================================================="
curl "http://$IP" > /dev/null 2>&1

sleep 5
mysql $DB_NAME -e "update config set val='http://$IP' where cfg='hostname';"

echo "PHPCoin: import blockchain"
echo "==================================================================================================="
cd $NODE_DIR/tmp
wget https://phpcoin.net/download/blockchain.sql.zip -O blockchain.sql.zip
unzip -o blockchain.sql.zip
cd $NODE_DIR
php cli/util.php importdb tmp/blockchain.sql

rm -rf $NODE_DIR/tmp/sync-lock

echo "==================================================================================================="
echo "PHPCoin: Install finished"
echo "PHPCoin: Open your node at http://$IP"
