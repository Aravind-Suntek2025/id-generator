# NGINX + PHP-FPM base image
FROM richarvey/nginx-php-fpm:latest

# Web root (this image serves from /var/www/html)
ENV WEBROOT=/var/www/html

# Copy your app into the image
COPY . /var/www/html

# Use our Nginx site config (override the default in the image)
COPY conf/nginx/default.conf /etc/nginx/sites-enabled/default.conf

# Remove the default phpinfo index.php that the base image ships with
RUN rm -f /var/www/html/index.php

# Ensure /output is writable for generated PNGs
RUN mkdir -p /var/www/html/output \
    && chmod -R 777 /var/www/html/output