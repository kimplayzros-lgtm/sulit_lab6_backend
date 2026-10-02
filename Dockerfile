FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf

COPY . /var/www/html

RUN mkdir -p /var/www/html/runtime/cache /var/www/html/runtime/logs \
    && chown -R www-data:www-data /var/www/html/runtime

WORKDIR /var/www/html

CMD ["bash", "-lc", "chown -R www-data:www-data /var/www/html/runtime && apache2-foreground"]