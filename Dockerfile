FROM php:8.2-apache

# pdo_mysql is the only extension the app needs. The original used the mysqli
# procedural API throughout; this rewrite uses PDO with prepared statements.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers

# Serve src/public only. The original exposed the whole project directory, so
# inc/connect.php — which held the database credentials — was fetchable over
# HTTP. config/ and admin/ now sit outside the document root and are reached
# through the front controller instead.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Keep PHP notices visible in the container log but never in the response body:
# the original printed mysqli_error() straight to the page, which leaks query
# structure to anyone who can trigger an error.
RUN { \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'error_log=/dev/stderr'; \
      echo 'error_reporting=E_ALL'; \
      echo 'expose_php=Off'; \
      echo 'session.cookie_httponly=On'; \
      echo 'session.cookie_samesite=Lax'; \
      echo 'session.use_strict_mode=On'; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
