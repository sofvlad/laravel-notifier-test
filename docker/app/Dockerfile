FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    supervisor \
    curl \
    libzip-dev \
    unzip \
    oniguruma-dev \
    nodejs \
    npm \
    mysql-client

RUN docker-php-ext-install zip mbstring pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

CMD ["/usr/local/bin/entrypoint.sh"]
