FROM php:8.4-fpm-alpine

# Системные пакеты и расширения PHP:
#   intl      - локали и форматирование (нужно Laravel и PhpWord)
#   zip       - docx это zip-архив, без него PhpWord не откроет шаблон
#   gd        - вставка картинок (логотип организации) в документ
#   pdo_sqlite - наша база данных
RUN apk add --no-cache \
        git unzip libzip-dev icu-dev oniguruma-dev sqlite-dev \
        libpng-dev libjpeg-turbo-dev freetype-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" intl zip gd pdo_sqlite opcache \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Скрипт запуска: ставит зависимости, готовит базу и стартует php-fpm
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
