FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# The base image ships AllowOverride None, so www/.htaccess is ignored and the
# rules that deny direct access to _*.php, includes/ and lib/ would silently do
# nothing here — the opposite of cPanel, where they do apply. Turn it on (and
# mod_rewrite with it) so dev behaves like production.
RUN a2enmod rewrite \
 && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' \
      > /etc/apache2/conf-available/lapikad-htaccess.conf \
 && a2enconf lapikad-htaccess

# Default PHP limits (2M per file / 8M per request) are too small for phone photos.
RUN { \
      echo "upload_max_filesize = 10M"; \
      echo "post_max_size = 12M"; \
      echo "memory_limit = 256M"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# The base image leaves display_errors on, so any uncaught error prints the
# server path and a stack trace into the page. Send them to the log instead
# (docker compose logs web) — same as a sane production php.ini.
RUN { \
      echo "display_errors = Off"; \
      echo "display_startup_errors = Off"; \
      echo "log_errors = On"; \
    } > /usr/local/etc/php/conf.d/errors.ini

COPY ./www /var/www/html

EXPOSE 80
