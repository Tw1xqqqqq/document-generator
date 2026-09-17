#!/bin/sh
set -e

cd /var/www/html

# 1. Зависимости PHP (папка vendor не хранится в git)
if [ ! -d vendor ]; then
    echo "==> composer install"
    composer install --no-interaction --prefer-dist
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

# 4. Миграции и демонстрационные данные
echo "==> миграции"
php artisan migrate --force --seed

# 5. Публичная ссылка на хранилище файлов
php artisan storage:link 2>/dev/null || true

chown -R www-data:www-data storage bootstrap/cache database

exec "$@"
