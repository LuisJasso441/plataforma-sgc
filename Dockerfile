FROM php:8.3-apache

# Extensiones típicas para MariaDB/MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Habilitar mod_rewrite (útil para rutas amigables)
RUN a2enmod rewrite

# (Opcional) zona horaria
ENV TZ=America/Mexico_City