#!/bin/bash
usermod -d /var/lib/mysql/ mysql
service mysql start
service apache2 start
tail -f /var/www/phpcoin/tmp/phpcoin.log
