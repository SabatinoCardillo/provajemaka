# Usa PHP 8.2 con Apache
FROM php:8.2-apache

# Copia tutti i file del tuo progetto dentro la cartella web di Apache
COPY . /var/www/html/

# Espone la porta 80 (Render la userà per le richieste HTTP)
EXPOSE 80