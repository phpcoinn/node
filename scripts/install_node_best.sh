#!/bin/bash
# setup node on ubuntu server 21.04, 20.04, 18.04
# one liner: curl -s https://raw.githubusercontent.com/phpcoinn/node/test/scripts/install_node.sh | bash
servers=("phpcoin.net" "cn.phpcoin.net")
declare -A git_urls
declare -A server_urls

git_urls["phpcoin.net"]="https://github.com/phpcoinn/node"
git_urls["cn.phpcoin.net"]="https://cn.phpcoin.net/git/phpcoin-node.git"

server_urls["phpcoin.net"]="https://phpcoin.net"
server_urls["cn.phpcoin.net"]="https://cn.phpcoin.net"

find_best_server() {
    get_ping_time() {
        local server=$1
        local ping_result=$(ping -c 1 "$server" | tail -1| awk '{print $4}' | cut -d '/' -f 2)

        if [ -z "$ping_result" ]; then
            echo "1000"
        else
            echo "$ping_result"
        fi
    }
    local best_server=""
    local min_ping_time=1000
    for server in "${servers[@]}"; do
        ping_time=$(get_ping_time "$server")
        echo "Ping time for $server: $ping_time ms"
        if [ "$(echo "$ping_time < $min_ping_time" | bc)" -eq 1 ]; then
            min_ping_time=$ping_time
            best_server=$server
        fi
    done
    best_server_result=$best_server
}

echo "PHPCoin Testnet node Installation"
echo "==================================================================================================="
echo "PHPCoin: define db user and pass"
echo "==================================================================================================="
export DEBIAN_FRONTEND=noninteractive
export DB_NAME=phpcointest
export DB_USER=phpcoin
export DB_PASS=phpcoin
export NODE_DIR=/var/www/phpcoin-testnet

echo "PHPCoin: update system"
echo "==================================================================================================="
apt update
apt install curl wget git -y
echo "install php with apache server"
apt install apache2 php libapache2-mod-php php-mysql php-gmp php-bcmath php-curl unzip -y
apt install mariadb-server -y
service mysql start

echo "PHPCoin: create database and set user"
echo "==================================================================================================="
mysql -e "create database $DB_NAME;"
mysql -e "create user '$DB_USER'@'localhost' identified by '$DB_PASS';"
mysql -e "grant all privileges on $DB_NAME.* to '$DB_USER'@'localhost';"

echo "PHPCoin: find best network server"
echo "==================================================================================================="
find_best_server
echo "Best server: $best_server_result"

echo "PHPCoin: download node"
echo "==================================================================================================="
mkdir $NODE_DIR
cd $NODE_DIR
git config --global --add safe.directory $NODE_DIR
git clone ${git_urls[$best_server_result]} --branch test .
git config core.fileMode false

echo "PHPCoin: Configure apache"
echo "==================================================================================================="
cat << EOF > /etc/apache2/sites-available/phpcoin-testnet.conf
<VirtualHost *:81>
        ServerAdmin webmaster@localhost
        DocumentRoot $NODE_DIR/web
        ErrorLog ${APACHE_LOG_DIR}/phpcoin-testnet.error.log
        RewriteEngine on
        RewriteRule ^/dapps/(.*)$ /dapps.php?url=$1
</VirtualHost>
EOF
echo "Listen 81" >> /etc/apache2/ports.conf

a2dissite 000-default
a2ensite phpcoin-testnet
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
curl "http://$IP:81" > /dev/null 2>&1

sleep 5
mysql $DB_NAME -e "update config set val='http://$IP:81' where cfg='hostname';"

echo "PHPCoin: import blockchain"
echo "==================================================================================================="
cd $NODE_DIR
wget ${server_urls[$best_server_result]}/download/blockchain-testnet.sql.zip -O blockchain-testnet.sql.zip
unzip -o blockchain-testnet.sql.zip
cd $NODE_DIR
time php cli/util.php importdb blockchain-testnet.sql
rm blockchain-testnet.sql
rm blockchain-testnet.sql.zip
rm -rf $NODE_DIR/tmp/sync-lock

echo "==================================================================================================="
echo "PHPCoin: Install finished"
echo "PHPCoin: Open your node at http://$IP:81"
