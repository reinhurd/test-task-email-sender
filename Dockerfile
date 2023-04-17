FROM php:8.1-apache

RUN apt-get update && apt-get install -y cron netcat

RUN docker-php-ext-install pdo_mysql

COPY ./html /var/www/html
COPY ./cron/crontab /etc/cron.d/crontab

RUN chmod 0644 /etc/cron.d/crontab && crontab /etc/cron.d/crontab

CMD bash -c "while ! nc -z db 3306; do sleep 1; done; service cron start && apache2-foreground"
