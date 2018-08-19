FROM php:7.2-apache
LABEL Author "Charles Stover <docker@charlesstover.com>"
ENV ACCESS_CONTROL_ALLOW_ORIGIN https://charlesstover.com

# Dependencies
RUN apt-get update
RUN apt-get install -y libpng-dev

# Remove Unused Files
RUN rm -rf /etc/apache2/conf-available
RUN rm -rf /etc/apache2/conf-enabled
RUN rm -rf /etc/apache2/ports.conf
RUN rm -rf /etc/apache2/sites-available
RUN rm -rf /etc/apache2/sites-enabled

# Configure PHP
RUN docker-php-ext-install gd

# Configure Apache
RUN cp /etc/mime.types /etc/apache2/mime.types
COPY src/ /
RUN chmod 644 /var/www/html/.htaccess
RUN a2enmod rewrite
RUN service apache2 restart

# Start
EXPOSE 80
