FROM php:8.2-apache

# 安裝 pdo_mysql 與 zip 等必要模組
RUN docker-php-ext-install pdo pdo_mysql

# 啟用 Apache mod_rewrite（如有需要）
RUN a2enmod rewrite

# 複製專案檔案到容器
COPY . /var/www/html/

# 設定工作目錄
WORKDIR /var/www/html

