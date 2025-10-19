FROM php:8.3-apache

# Install composer
COPY --from=composer:2.8.2 /usr/bin/composer /usr/bin/composer

# Install dependencies
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
  git \
  unzip \
  curl \
  && rm -rf /var/lib/apt/lists/*

# Apache document root to /public and enable rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
  && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/../!g' /etc/apache2/apache2.conf \
  && a2enmod rewrite

# Use custom apache logging config
COPY ./my_apache2.conf /etc/apache2/conf-enabled/my_apache2.conf

# PHP extensions
RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

# Create writable directories
RUN mkdir -p \
  /var/www/html/logs \
  /var/www/html/cache/firebase/api_token \
  /var/www/html/cache/firebase/auth_pubkey \
  && chown -R www-data:www-data /var/www/html \
  && chmod -R 775 /var/www/html/logs /var/www/html/cache

# Copy composer files and install deps
COPY ./composer.json /var/www/html/composer.json
# Copy lock file only if present to leverage caching; ignore error if missing
# hadolint ignore=DL3059
RUN if [ -f /var/www/html/composer.lock ]; then true; fi
RUN composer install --no-interaction --prefer-dist --no-dev

# Copy application source
COPY ./config /var/www/html/config
COPY ./src /var/www/html/src
COPY ./public /var/www/html/public
COPY ./bin /var/www/html/bin

# Set environment
ENV APP_ENV=prod

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s CMD curl -fsS http://localhost/ || exit 1
