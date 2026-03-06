# Simple NGINX + PHP-FPM image
FROM richarvey/nginx-php-fpm:latest

# Configure the web root to our repo root (contains index.html & generate.php)
ENV WEBROOT=/var/www/html

# Copy the whole repo into the image
COPY . /var/www/html

# Make sure the output folder exists and is writable by PHP
RUN mkdir -p /var/www/html/output \
    && chmod -R 777 /var/www/html/output

# (Optional) If you have a custom nginx.conf, copy it like:
# COPY conf/nginx/default.conf /etc/nginx/sites-enabled/default.conf
# and ensure it listens on the $PORT env if you prefer. Otherwise we'll set Render's service port to 80.