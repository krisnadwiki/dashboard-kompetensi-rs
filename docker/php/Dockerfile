FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    bash \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    freetype-dev \
    libjpeg-turbo-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install pdo pdo_mysql mbstring zip gd intl

# PHP config
RUN echo "upload_max_filesize = 20M" > /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 20M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.cookie_samesite = Lax" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "clear_env = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www/html

RUN chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
