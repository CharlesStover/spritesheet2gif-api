FROM php:7.2-apache
LABEL Author "Charles Stover <docker@charlesstover.com>"
COPY src/ /
RUN chmod 644 /var/www/html/.htaccess
RUN cp /etc/mime.types /etc/apache2/mime.types
RUN rm -rf /etc/apache2/conf-available
RUN rm -rf /etc/apache2/conf-enabled
RUN rm -rf /etc/apache2/ports.conf
RUN rm -rf /etc/apache2/sites-available
RUN rm -rf /etc/apache2/sites-enabled
RUN a2enmod rewrite
RUN service apache2 restart
EXPOSE 80
