FROM php:8.2-apache

# Enable Apache modules required for PWA
RUN a2enmod rewrite headers

# Install PHP extensions + Composer + PHPMailer
RUN apt-get update && apt-get install -y unzip curl git --no-install-recommends && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_mysql
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy all project files (including hidden files like .htaccess)
COPY . /var/www/html/

# Install PHPMailer via Composer
WORKDIR /var/www/html
RUN composer require phpmailer/phpmailer --no-interaction --quiet

# Copy Apache config
COPY apache.conf /etc/apache2/sites-enabled/000-default.conf

# Create js/ subfolder and move JS files into it
# (pages reference js/api.js and js/pwa.js)
RUN mkdir -p /var/www/html/js \
    && mv /var/www/html/api.js /var/www/html/js/api.js \
    && mv /var/www/html/pwa.js /var/www/html/js/pwa.js

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod +x /var/www/html/start.sh

EXPOSE 8080

CMD ["/var/www/html/start.sh"]
