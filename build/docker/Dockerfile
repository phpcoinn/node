# syntax=docker/dockerfile:1
FROM ubuntu:20.04
ARG DEBIAN_FRONTEND="noninteractive"
ARG DB_NAME=phpcoin
ARG DB_USER=phpcoin
ARG DB_PASS=phpcoin

EXPOSE 80

RUN export DB_NAME=phpcoin \
&& export DB_USER=phpcoin \
&& export DB_PASS=phpcoin \
&& apt update \
&& echo "install php with apache server" \
&& apt install apache2 php libapache2-mod-php php-mysql php-gmp php-bcmath php-curl unzip git wget curl -y \
&& apt install mysql-server -y \
&& usermod -d /var/lib/mysql/ mysql \
&& service mysql start \
&& mysql -e "create database $DB_NAME;" \
&& mysql -e "create user '$DB_USER'@'localhost' identified by '$DB_PASS';" \
&& mysql -e "grant all privileges on $DB_NAME.* to '$DB_USER'@'localhost';"

RUN mkdir /var/www/phpcoin \
&& cd /var/www/phpcoin \
&& git clone https://github.com/phpcoinn/node .

RUN echo '<VirtualHost *:80>\n\
       ServerAdmin webmaster@localhost\n\
       DocumentRoot /var/www/phpcoin/web\n\
       ErrorLog ${APACHE_LOG_DIR}/error.log\n\
       CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/phpcoin.conf \
&& a2dissite 000-default \
&& a2ensite phpcoin \
&& service apache2 restart \
&& cd /var/www/phpcoin \
&& cp config/config-sample.inc.php config/config.inc.php \
&& sed -i "s/ENTER-DB-NAME/$DB_NAME/g" config/config.inc.php \
&& sed -i "s/ENTER-DB-USER/$DB_USER/g" config/config.inc.php \
&& sed -i "s/ENTER-DB-PASS/$DB_PASS/g" config/config.inc.php \
&& mkdir tmp \
&& mkdir web/apps \
&& chown -R www-data:www-data tmp \
&& chown -R www-data:www-data web/apps \
&& touch first-run

COPY docker_start.sh docker_start.sh
RUN chmod +x docker_start.sh
CMD /docker_start.sh
