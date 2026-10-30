# MetMemo web app (Laravel + Inertia/Vue), served by FrankenPHP: PHP and Caddy in one process.
# Caddy gets the HTTPS certificate for SERVER_NAME by itself, and requests run in parallel
# (unlike `php artisan serve`, see failure F6), so production needs no second internal server.

# 1. PHP dependencies (no dev packages).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# 2. Front-end build. app.js imports Ziggy from vendor/, so it comes from stage 1.
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor/tightenco ./vendor/tightenco
ENV VITE_APP_NAME=MetMemo
RUN npm run build

# 3. Runtime.
FROM dunglas/frankenphp:1-php8.3
RUN install-php-extensions pdo_pgsql intl zip opcache pcntl
COPY deploy/php.ini $PHP_INI_DIR/conf.d/metmemo.ini
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
