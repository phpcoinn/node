FROM ubuntu:20.04

ENV DEBIAN_FRONTEND=noninteractive
ARG DB_NAME=phpcoin
ARG DB_USER=phpcoin
ARG DB_PASS=phpcoin
ARG NODE_DIR=/var/www/phpcoin
ARG NETWORK=main
ENV NETWORK=$NETWORK

EXPOSE 80

RUN export DEBIAN_FRONTEND=noninteractive && \
apt update && \
apt install nano curl wget git -y && \
apt install apache2 php libapache2-mod-php php-mysql php-gmp php-bcmath php-curl unzip -y && \
apt install mariadb-server -y && \
service mysql start && \
mysql -e "create database $DB_NAME;" && \
mysql -e "create user '$DB_USER'@'localhost' identified by '$DB_PASS';" && \
mysql -e "grant all privileges on $DB_NAME.* to '$DB_USER'@'localhost';"

RUN mkdir $NODE_DIR && \
cd $NODE_DIR && \
git config --global --add safe.directory $NODE_DIR && \
git clone https://github.com/phpcoinn/node . && \
git config core.fileMode false && \
if [ "$NETWORK" = "test" ] ; then cd $NODE_DIR && git switch test; fi

RUN echo "<VirtualHost *:80>\n\
       ServerAdmin webmaster@localhost\n\
       DocumentRoot $NODE_DIR/web\n\
       RewriteRule ^/dapps/(.*)$ /dapps.php?url=$1\n\
</VirtualHost>" > /etc/apache2/sites-available/phpcoin.conf && \
a2dissite 000-default && \
a2ensite phpcoin && \
a2enmod rewrite && \
rm /etc/apache2/conf-enabled/other-vhosts-access-log.conf && \
service apache2 restart

RUN cd $NODE_DIR && \
cp config/config-sample.inc.php config/config.inc.php && \
sed -i "s/ENTER-DB-NAME/$DB_NAME/g" config/config.inc.php && \
sed -i "s/ENTER-DB-USER/$DB_USER/g" config/config.inc.php && \
sed -i "s/ENTER-DB-PASS/$DB_PASS/g" config/config.inc.php && \
mkdir tmp && \
mkdir dapps && \
chown -R www-data:www-data . && \
touch first-run

COPY docker_start.sh docker_start.sh
RUN chmod +x docker_start.sh
CMD /docker_start.sh

