FROM php:8.3-apache

COPY --from=composer:2.8.2 /usr/bin/composer /usr/bin/composer
RUN apt update && apt install -y git unzip && apt clean && rm -rf /var/lib/apt/lists/*
COPY ./my_apache2.conf /etc/apache2/mods-enabled/my_apache2.conf

ENV APACHE_DOCUMENT_ROOT='/var/www/html/public'
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

RUN a2enmod rewrite

RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html
RUN mkdir access cache && chmod 777 access cache /tmp

COPY \
	./composer.json \
	./composer.lock \
	/var/www/html/

RUN composer install

COPY \
	./config \
	/var/www/html/config
RUN mv /var/www/html/config/prod/config.docker.inc.php /var/www/html/config/prod/config.inc.php && \
	chown www-data:www-data /var/www/html/config/prod/*
COPY \
	./lib \
	/var/www/html/lib
COPY \
	./public \
	/var/www/html/public
COPY \
	./src \
	/var/www/html/src

ENV APP_IS_DOCKER=true

EXPOSE 80
