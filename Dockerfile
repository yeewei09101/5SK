FROM php:8.1-apache

# 安装并开启 pdo 和 pdo_pgsql 扩展（用于连接 Supabase / PostgreSQL）
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

COPY . /var/www/html/
EXPOSE 80