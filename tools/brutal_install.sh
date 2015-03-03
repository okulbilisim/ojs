#!/bin/sh
git pull origin master && composer update -vvv && bower update --allow-root && php app/console assets:install web --symlink && php app/console assetic:dump && mysql -u root -p -e "DROP DATABASE IF EXISTS ojs;create database ojs;" && php app/console ojs:install:travis && php app/console ojs:install:sampledata && app/console fos:elastica:populate && php app/console doctrine:cache:clear-m --env=prod && app/console cache:clear --env prod && chmod -R 777 app/cache/