FROM php:8.1-cli

# 安装 PostgreSQL 扩展所需的开发包
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# 将当前目录代码复制到容器中
WORKDIR /var/www/html
COPY . /var/www/html

# 使用 PHP 内置服务器监听 Render 的动态端口
CMD php -S 0.0.0.0:${PORT:-10000} index.php