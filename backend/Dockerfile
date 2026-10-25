FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip supervisor libicu-dev libzip-dev \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/* \
    && echo "date.timezone=UTC" > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/postpickr.conf

WORKDIR /var/www/html
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction
COPY . .
ENV APP_ENV=prod
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var && chown -R www-data:www-data var

# Un solo container: Apache (api) + worker + worker-llm sotto supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/postpickr.conf"]
