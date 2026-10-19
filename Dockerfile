FROM php:8.4-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl imagick gd zip opcache \
 && apt-get update \
 && apt-get install -y --no-install-recommends git unzip mariadb-client \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers expires
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/apache-neos.conf /etc/apache2/sites-available/000-default.conf
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-neos.ini
COPY docker/entrypoint.sh /usr/local/bin/neos-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/neos-entrypoint && chmod +x /usr/local/bin/neos-entrypoint

WORKDIR /app
COPY . /app
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
 && mkdir -p Data/Persistent Data/Logs \
 && chown -R www-data:www-data /app

EXPOSE 80
ENTRYPOINT ["neos-entrypoint"]
CMD ["apache2-foreground"]
