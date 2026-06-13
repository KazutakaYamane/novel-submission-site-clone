
# syntax=docker/dockerfile:1.7
# ------------------------------------------------------------
# Laravel 13 (PHP 8.4) アプリ用 multi-stage Dockerfile
# - base: PHP ランタイム + 拡張(アプリ非依存)
# - dev:  Xdebug 追加のみ。app/ には依存しない(bind mount 前提)
# - prod: 依存解決済み、コードを焼き込み(CI 内でのみビルド)
# ------------------------------------------------------------
 
# ============== base ==============
FROM php:8.4-fpm-alpine AS base
 
RUN set -eux; \
    apk add --no-cache \
        mariadb-client icu-libs libzip oniguruma \
        tzdata bash curl git; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev linux-headers; \
    docker-php-ext-install -j$(nproc) \
        pdo_mysql intl zip opcache pcntl bcmath sockets; \
    apk del .build-deps; \
    cp /usr/share/zoneinfo/Asia/Tokyo /etc/localtime; \
    echo "Asia/Tokyo" > /etc/timezone
 
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/app/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/app/www.conf /usr/local/etc/php-fpm.d/www.conf
 
WORKDIR /var/www/html
 
# opcache + JIT
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.jit_buffer_size=64M"; \
        echo "opcache.jit=tracing"; \
    } > /usr/local/etc/php/conf.d/zz-opcache.ini
 
ARG UID=82
ARG GID=82
RUN if [ "$UID" != "82" ] || [ "$GID" != "82" ]; then \
        deluser www-data; \
        addgroup -g "$GID" www-data; \
        adduser -D -u "$UID" -G www-data -s /bin/sh www-data; \
    fi
 
# ============== dev ==============
# 重要: dev 段は app/ には一切依存しない
# - アプリ本体は compose.yaml の bind mount で実行時に注入される
# - composer install / create-project はコンテナ起動後に実行する
# - これにより、ホストに PHP/Composer を入れず、空の app/ から始められる
FROM base AS dev
 
RUN set -eux; \
    apk add --no-cache --virtual .xdebug-build-deps $PHPIZE_DEPS linux-headers; \
    pecl install xdebug; \
    docker-php-ext-enable xdebug; \
    apk del .xdebug-build-deps
 
COPY docker/app/php.dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

# 名前付きボリュームの初期化時にwww-data所有で作成されるよう事前にディレクトリを用意する
RUN mkdir -p \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
        /var/www/html/vendor \
    && chown -R www-data:www-data /var/www/html

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]
 
# ============== prod ==============
# 重要: prod 段は app/ をビルドコンテキストから取り込む
# ただし、これがビルドされるのは CI(GitHub Actions)の中だけ
# ローカルマシンには影響しない(ローカルは常に target: dev)
FROM base AS prod
 
COPY --chown=www-data:www-data app/composer.json app/composer.lock ./
RUN composer install \
        --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --optimize-autoloader
 
COPY --chown=www-data:www-data app/ ./

RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs

# 注意: config:cache 等はここ(ビルド時)では実行しない。ビルド時の空 env が
# 焼き込まれ、実行時に Task Definition が注入する DB_HOST 等が無視されるため。
# キャッシュ生成は entrypoint.prod.sh(起動時)が行う。
RUN composer dump-autoload --optimize --classmap-authoritative

COPY docker/app/php.prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY --chmod=755 docker/app/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data
EXPOSE 9000

HEALTHCHECK --interval=10s --timeout=3s --retries=3 \
    CMD php-fpm-healthcheck || exit 1

ENTRYPOINT ["entrypoint.prod.sh"]
CMD ["php-fpm", "-F"]
