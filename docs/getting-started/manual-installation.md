[PHPCoin Docs](../) > [Getting Started](./) > Manual Installation

---

# Manual Installation Guide

This guide provides step-by-step instructions for manually installing a PHPCoin node on a Debian-based system (e.g., Ubuntu). This process is intended for advanced users who require more control over the installation.

## Prerequisites

*   A server running a Debian-based Linux distribution (e.g., Ubuntu 20.04, 22.04).
*   Root or `sudo` privileges.
*   Familiarity with the Linux command line.

---

## Step 1: Update System and Install Dependencies

First, update your system's package list and install the required software packages, including Nginx, PHP, and MariaDB.

```bash
# Update package list
sudo apt update

# Install dependencies
sudo apt install -y curl wget git sed net-tools unzip bc nginx php-fpm php-mysql php-gmp php-bcmath php-curl php-mbstring mariadb-server
```

---

## Step 2: Set Up the Database

Next, create a new MariaDB database and user for the PHPCoin node.

```bash
# Start the MariaDB service
sudo service mariadb start

# Choose a network (mainnet or testnet)
export NETWORK="mainnet" # Or "testnet"

# Set database credentials
export DB_NAME="phpcoin${NETWORK}"
export DB_USER="phpcoin"
export DB_PASS="phpcoin" # It is recommended to use a more secure password

# Create the database and user
sudo mysql -e "CREATE DATABASE ${DB_NAME};"
sudo mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
```

---

## Step 3: Download the PHPCoin Node

Clone the PHPCoin source code from the official Git repository into the `/var/www` directory.

```bash
# Set the installation directory
export NODE_DIR="/var/www/phpcoin-${NETWORK}"

# Create the directory and clone the source code
sudo mkdir -p ${NODE_DIR}
cd ${NODE_DIR}

if [ "${NETWORK}" = "mainnet" ]; then
    sudo git clone https://git.phpcoin.net/node .
else
    sudo git clone https://git.phpcoin.net/node --branch test .
fi
```

---

## Step 4: Configure the Web Server (Nginx)

Configure Nginx to serve the PHPCoin web interface.

```bash
# Get your server's public IP address
export IP=$(curl -s http://whatismyip.akamai.com/)

# Set the port and hostname
if [ "${NETWORK}" = "mainnet" ]; then
    export PORT="80"
    export HOSTNAME="http://${IP}"
else
    export PORT="81"
    export HOSTNAME="http://${IP}:${PORT}"
fi

# Create the Nginx configuration file
sudo tee /etc/nginx/sites-available/phpcoin-${NETWORK} > /dev/null <<EOF
server {
    listen ${PORT};
    server_name _;
    root ${NODE_DIR}/web;
    index index.html index.htm index.php;
    rewrite ^/dapps/(.*)$ /dapps.php?url=\$1 break;
    access_log off;
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

# Enable the new site and restart services
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -s /etc/nginx/sites-available/phpcoin-${NETWORK} /etc/nginx/sites-enabled/
sudo service nginx restart
sudo service php8.1-fpm start
```

---

## Step 5: Configure the PHPCoin Node

Create the node's configuration file and set the correct permissions.

```bash
# Navigate to the node directory
cd ${NODE_DIR}

# Create the configuration file from the sample
sudo cp config/config-sample.inc.php config/config.inc.php

# Set the database credentials in the config file
sudo sed -i "s/ENTER-DB-NAME/${DB_NAME}/g" config/config.inc.php
sudo sed -i "s/ENTER-DB-USER/${DB_USER}/g" config/config.inc.php
sudo sed -i "s/ENTER-DB-PASS/${DB_PASS}/g" config/config.inc.php

# Create necessary directories and set permissions
sudo mkdir tmp dapps
sudo chown -R www-data:www-data .
```

---

## Step 6: Import the Blockchain Snapshot

To speed up the initial synchronization, download and import a recent snapshot of the blockchain.

```bash
# Navigate to the node directory
cd ${NODE_DIR}

# Set the snapshot filename
if [ "${NETWORK}" = "mainnet" ]; then
    export BLOCKCHAIN_SNAPSHOT="blockchain"
else
    export BLOCKCHAIN_SNAPSHOT="blockchain-${NETWORK}"
fi

# Download and extract the snapshot
sudo wget https://phpcoin.net/download/${BLOCKCHAIN_SNAPSHOT}.sql.zip -O ${BLOCKCHAIN_SNAPSHOT}.sql.zip
sudo unzip -o ${BLOCKCHAIN_SNAPSHOT}.sql.zip

# Import the snapshot into the database
sudo php cli/util.php importdb ${BLOCKCHAIN_SNAPSHOT}.sql

# Clean up the snapshot files
sudo rm ${BLOCKCHAIN_SNAPSHOT}.sql ${BLOCKCHAIN_SNAPSHOT}.sql.zip
```

---

## Step 7: Finalize the Installation

Complete the installation by initializing the node and setting the hostname in the database.

```bash
# Initialize the node by making a web request
curl ${HOSTNAME} > /dev/null 2>&1
sleep 5

# Set the hostname in the database
sudo mysql ${DB_NAME} -e "UPDATE config SET val='${HOSTNAME}' WHERE cfg='hostname';"

# Clear the temporary directory
sudo rm -rf ${NODE_DIR}/tmp/*

# Restart Nginx
sudo service nginx start

echo "Installation complete!"
echo "You can now access your node at: ${HOSTNAME}"

<!-- Screenshot placeholder: A screenshot of the web interface after a successful installation -->

---

## Troubleshooting

If you encounter any issues during the installation, here are some common problems and their solutions:

*   **Port Conflict:** If another service is using port 80 (for mainnet) or 81 (for testnet), the Nginx service may fail to start. You can check for port conflicts using the `sudo netstat -tulpn | grep LISTEN` command.
*   **Database Connection Issues:** Verify that the MariaDB service is running and that the database credentials in `config/config.inc.php` are correct.
*   **Firewall Issues:** If you are unable to access the web interface, ensure that your server's firewall is configured to allow traffic on the appropriate port (80 or 81).
*   **PHP-FPM Not Found:** The installation script assumes PHP 8.1. If you are using a different version, you will need to adjust the `fastcgi_pass` directive in the Nginx configuration file.
