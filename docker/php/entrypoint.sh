#!/bin/sh
set -e

cd /var/www/html

APP_ENV="${APP_ENV:-local}"
SEED_DEMO_DATA="${SEED_DEMO_DATA:-true}"

# 1. Зависимости PHP (папка vendor не хранится в git).
#    На сервере ставим без dev-пакетов и с оптимизированным автозагрузчиком.
if [ ! -d vendor ]; then
    echo "==> composer install"
    if [ "$APP_ENV" = "production" ]; then
        composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
    else
        composer install --no-interaction --prefer-dist
    fi
fi

# 2. Файл настроек и ключ приложения
if [ ! -f .env ]; then
    echo "==> создаю .env"
    cp .env.example .env
    php artisan key:generate --force
fi

# 3. База данных SQLite: просто пустой файл
if [ ! -f database/database.sqlite ]; then
    echo "==> создаю базу SQLite"
    touch database/database.sqlite
fi

# 4. Миграции. Демонстрационные данные заливаются только при
#    SEED_DEMO_DATA=true, иначе на сервере они затирали бы правки
#    в карточках организаций при каждом перезапуске.
echo "==> миграции"
if [ "$SEED_DEMO_DATA" = "true" ]; then
    php artisan migrate --force --seed
else
    php artisan migrate --force
fi

# 5. Публичная ссылка на хранилище файлов (логотипы организаций)
if [ ! -e public/storage ]; then
    php artisan storage:link
fi

# 6. Кэши. В боевом режиме их собираем (быстрее отклик),
#    в разработке сбрасываем, чтобы правки применялись сразу.
if [ "$APP_ENV" = "production" ]; then
    echo "==> кэширую конфигурацию и маршруты"
    php artisan config:cache
    php artisan route:cache
else
    php artisan config:clear
    php artisan route:clear
fi

chown -R www-data:www-data storage bootstrap/cache database

exec "$@"
