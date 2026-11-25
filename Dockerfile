FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Default PHP limits (2M per file / 8M per request) are too small for phone photos.
RUN { \
      echo "upload_max_filesize = 10M"; \
      echo "post_max_size = 12M"; \
      echo "memory_limit = 256M"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY ./www /var/www/html

EXPOSE 80
