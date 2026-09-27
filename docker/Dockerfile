# Optional container image. The primary deployment target is the existing
# LAMP host; this file exists so the portal can also run isolated.
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && a2enmod rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html

RUN mkdir -p logs storage/uploads \
    && chown -R www-data:www-data logs storage

ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
