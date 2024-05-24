#!/bin/bash
# setup node on ubuntu server 21.04, 20.04, 18.04
# one liner: curl -s https://phpcoin.net/scripts/install_node.sh | bash -s -- --network testnet

NETWORK="mainnet" # Default network
DOCKER=false

function parse_arguments() {
    while (( "$#" )); do
        case "$1" in
            --network)
                if [ -n "$2" ] && [ ${2:0:1} != "-" ]; then
                    NETWORK="$2"
                    shift 2
                else
                    echo "Error: Argument for --network is missing" >&2
                    exit 1
                fi
                ;;
            --help)
                echo "Usage: ./install_node.sh [--network <network_name>]"
                echo "network_name: testnet or mainnet. If not provided, mainnet is used by default."
                exit 0
                ;;
	    --docker)
		echo "Runnung in docker"
                DOCKER=true
                shift 1
                ;;
            *)
                echo "Error: Unsupported option $1" >&2
                exit 1
                ;;
        esac
    done
    # Check if network is valid (either mainnet or testnet)
    if [[ "$NETWORK" != "mainnet" && "$NETWORK" != "testnet" ]]; then
        echo "Error: Invalid network specified. Network should be either 'mainnet' or 'testnet'." >&2
        exit 1
    fi
}

parse_arguments "$@" # Call the function and pass all arguments

# Rest of your script here

servers=("phpcoin.net" "cn.phpcoin.net")
declare -A git_urls
declare -A server_urls

git_urls["phpcoin.net"]="https://git.phpcoin.net/node"
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

echo "PHPCoin $NETWORK node Installation"
echo "==================================================================================================="
echo "PHPCoin: define db user and pass"
echo "==================================================================================================="
export DEBIAN_FRONTEND=noninteractive
export DB_NAME=phpcoin$NETWORK
export DB_USER=phpcoin
export DB_PASS=phpcoin
export NODE_DIR=/var/www/phpcoin-$NETWORK

if [ "$DOCKER" = true ]; then
  NODE_DIR=/var/www/phpcoin
  DB_NAME=phpcoin
fi

echo "PHPCoin: update system"
echo "==================================================================================================="
apt update
apt install curl wget git sed net-tools unzip -y
echo "install php with nginx server"
apt install nginx php-fpm php-mysql php-gmp php-bcmath php-curl php-mbstring -y
apt install mariadb-server -y
service mariadb start

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


if [ "$DOCKER" = true ]; then
  if [ -d "$NODE_DIR/config" ]; then
    cd $NODE_DIR
    git init
    git remote add origin ${git_urls[$best_server_result]}
    git fetch origin
    git add .
    if [ "$NETWORK" = "mainnet" ]; then
      git pull origin main
    else
      git pull origin test
    fi
    git restore --staged .
  fi
fi

if [ ! -d "$NODE_DIR" ]; then
  mkdir $NODE_DIR
  cd $NODE_DIR
  if [ "$NETWORK" = "mainnet" ]
  then
    git clone ${git_urls[$best_server_result]} .
  elif [ "$NETWORK" = "testnet" ]
  then
    git clone ${git_urls[$best_server_result]} --branch test .
  fi
fi


git config --global --add safe.directory $NODE_DIR


export IP=$(curl -s http://whatismyip.akamai.com/)

PORT=""
HOSTNAME=""
BLOCKCHAIN_SNAPSHOT=""
if [ "$NETWORK" = "mainnet" ]
then
  PORT="80"
  HOSTNAME="http://$IP"
  if [ "$DOCKER" = true ]; then
    HOSTNAME="http://$IP:$EXT_PORT"
  fi
  BLOCKCHAIN_SNAPSHOT="blockchain"
elif [ "$NETWORK" = "testnet" ]
then
  PORT="81"
  HOSTNAME="http://$IP:$PORT"
  if [ "$DOCKER" = true ]; then
    PORT="80"
    HOSTNAME="http://$IP:$EXT_PORT"
  fi
  BLOCKCHAIN_SNAPSHOT="blockchain-$NETWORK"
fi

git config core.fileMode false

echo "PHPCoin: Configure nginx"
echo "==================================================================================================="
cat << EOF > /etc/nginx/sites-available/phpcoin-$NETWORK
server {
    listen $PORT;
    server_name _;
    root $NODE_DIR/web;
    index index.html index.htm index.php;
    rewrite ^/dapps/(.*)$ /dapps.php?url=\$1 break;
    access_log  off;
    absolute_redirect off;
    location / {
        try_files \$uri \$uri/ =404;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }
    location ~ /\.ht {
        deny all;
    }
}
EOF
rm /etc/nginx/sites-enabled/default
ln -sr /etc/nginx/sites-available/phpcoin-$NETWORK /etc/nginx/sites-enabled/phpcoin-$NETWORK
service nginx restart
service php8.1-fpm start

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

echo "PHPCoin: open start page"
echo "==================================================================================================="
curl $HOSTNAME > /dev/null 2>&1
cd $NODE_DIR
php cli/util.php version > /dev/null
sleep 5
mysql $DB_NAME -e "update config set val='$HOSTNAME' where cfg='hostname';"

echo "PHPCoin: import blockchain"
echo "==================================================================================================="
cd $NODE_DIR
wget ${server_urls[$best_server_result]}/download/$BLOCKCHAIN_SNAPSHOT.sql.zip -O $BLOCKCHAIN_SNAPSHOT.sql.zip
unzip -o $BLOCKCHAIN_SNAPSHOT.sql.zip
cd $NODE_DIR

memory_info=$(free -m | grep Mem)
total_memory=$(echo $memory_info | awk '{print $2}')
echo "PHPCoin: Total memory is ${total_memory}M"
if [ $total_memory -lt 16000 ]
then

  echo "PHPCoin: tweaking db import config ..."
  nearest_power_of_2() {
    input=$1;
    power=$(echo "l($input)/l(2)" | bc -l);
    rounded_power=$(printf "%.0f" $power);
    echo "2^($rounded_power)" | bc -l;
  }

  innodb_buffer_pool_size=$(nearest_power_of_2 $(echo "$total_memory / 2" | bc))
  innodb_log_file_size=$(nearest_power_of_2 $(echo "$innodb_buffer_pool_size / 4" | bc))
  innodb_log_buffer_size=$(nearest_power_of_2 $(echo "$innodb_log_file_size / 2" | bc))

  cat << EOF > /etc/mysql/mariadb.conf.d/import.cnf
[mysqld]
innodb_buffer_pool_size=${innodb_buffer_pool_size}M
innodb_log_buffer_size=${innodb_log_buffer_size}M
innodb_log_file_size=${innodb_log_file_size}M
innodb_write_io_threads=16
innodb_flush_log_at_trx_commit=0
max_allowed_packet=256M
innodb-doublewrite=0
skip_log_bin
innodb_io_capacity=700
innodb_io_capacity_max=1500
net_write_timeout=300
interactive_timeout=300
EOF
  service nginx stop
  service mariadb restart
  sleep 5
fi

echo "PHPCoin: Starting import ..."
time php cli/util.php importdb $BLOCKCHAIN_SNAPSHOT.sql

echo "PHPCoin: reverting db config ..."
rm /etc/mysql/mariadb.conf.d/import.cnf
service mariadb restart

rm $BLOCKCHAIN_SNAPSHOT.sql
rm $BLOCKCHAIN_SNAPSHOT.sql.zip
rm -rf $NODE_DIR/tmp/*

service nginx start

echo "==================================================================================================="
echo "PHPCoin: Install finished"
echo "PHPCoin: Open your node at $HOSTNAME"
