FROM php:8.2-apache

# Abilita mod_rewrite se serve
RUN a2enmod rewrite

# Copia i file del progetto
COPY . /var/www/html/

# Usa la porta dinamica di Render
ARG PORT
ENV PORT=${PORT}
RUN sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf \
 && sed -i "s/:80/:${PORT}/g" /etc/apache2/sites-enabled/000-default.conf

EXPOSE ${PORT}

CMD ["apache2-foreground"]
