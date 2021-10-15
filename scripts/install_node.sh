#!/bin/bash
# setup node on ubuntu server
# one liner: curl -s https://raw.githubusercontent.com/phpcoinn/node/main/scripts/install_node.sh | bash

echo "define db user and pass"
export DB_NAME=phpcoin
export DB_USER=phpcoin
export DB_PASS=phpcoin

echo "update system"
apt update
echo "install php with apache server"
apt install apache2 php libapache2-mod-php php-mysql php-gmp php-bcmath php-curl -y
apt install mysql-server -y

echo "create database and set use"
mysql -e "create database $DB_NAME;"
mysql -e "create user '$DB_USER'@'localhost' identified by '$DB_PASS';"
mysql -e "grant all privileges on $DB_NAME.* to '$DB_USER'@'localhost';"

echo "download node"
mkdir /var/www/phpcoin
cd /var/www/phpcoin
git clone https://github.com/phpcoinn/node .

echo "Configure apache"
cat << EOF > /etc/apache2/sites-available/phpcoin.conf
<VirtualHost *:80>
        ServerAdmin webmaster@localhost
        DocumentRoot /var/www/phpcoin/web
        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
a2dissite 000-default
a2ensite phpcoin
service apache2 restart

echo "setup config file"
cp config/config-sample.inc.php config/config.inc.php
sed -i "s/ENTER-DB-NAME/$DB_NAME/g" config/config.inc.php
sed -i "s/ENTER-DB-USER/$DB_USER/g" config/config.inc.php
sed -i "s/ENTER-DB-PASS/$DB_PASS/g" config/config.inc.php

echo "configure node"
mkdir tmp
chown -R www-data:www-data tmp
chown -R www-data:www-data web/apps

echo "open start page"
export IP=$(curl -s http://whatismyip.akamai.com/)
curl "http://$IP" > /dev/null 2>&1

sleep 5

echo "synchronize apps"
php cli/util.php download-apps
