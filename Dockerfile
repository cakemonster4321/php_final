# 使用官方 PHP + Apache 基礎映像檔
FROM php:8.2-apache

# 安裝 PDO MySQL 擴充套件
RUN docker-php-ext-install pdo_mysql

# 將專案複製進容器的 Apache 網頁根目錄
COPY . /var/www/html/