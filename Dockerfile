FROM php:7.4-apache

# 安裝 mysqli 模組
RUN docker-php-ext-install mysqli

# 啟用 mod_rewrite（未來路由會用到）
RUN a2enmod rewrite

# 複製專案檔案到 container
COPY . /var/www/html/

# 設定正確權限
RUN chown -R www-data:www-data /var/www/html/
