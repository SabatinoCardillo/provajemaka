FROM php:8.2-apache

# Imposta la working dir
WORKDIR /var/www/html

# Copia i file del progetto
COPY . /var/www/html/

# Installa estensioni PHP necessarie
RUN docker-php-ext-install mysqli pdo pdo_mysql \
    && docker-php-ext-enable mysqli pdo_mysql

# Abilita mod_rewrite (utile per molti CMS/Framework)
RUN a2enmod rewrite

# Espone la porta 80
EXPOSE 80

# Avvia Apache
CMD ["apache2-foreground"]
