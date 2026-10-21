FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && mkdir -p /var/run/apache2 /var/lock/apache2 \
    && chown www-data:www-data /var/run/apache2 /var/lock/apache2

WORKDIR /var/www/html/recipe

USER www-data

EXPOSE 8080
