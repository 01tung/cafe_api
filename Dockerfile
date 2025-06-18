FROM php:8.1-apache

# 啟用 mod_rewrite
RUN a2enmod rewrite

# 安裝 mysqli 和 pdo_mysql 擴充
RUN docker-php-ext-install mysqli pdo pdo_mysql

# 將檔案複製到網站根目錄
COPY . /var/www/html/

# 設定工作目錄
WORKDIR /var/www/html
