FROM php:8.3-apache
RUN apt-get update && apt-get install -y git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev libonig-dev libcurl4-openssl-dev libpq-dev && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install mysqli intl zip gd soap opcache exif mbstring && rm -rf /var/lib/apt/lists/*
RUN git clone --depth 1 --branch v4.5.15 https://github.com/moodle/moodle.git /var/www/html && rm -rf /var/www/html/.git
COPY docker/config.php /var/www/html/config.php
RUN mkdir -p /var/www/moodledata && chown -R www-data:www-data /var/www/moodledata
RUN echo 'max_input_vars=5000' > /usr/local/etc/php/conf.d/moodle.ini
