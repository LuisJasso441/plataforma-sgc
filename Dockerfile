FROM php:8.3-apache

# --- Paquetes del sistema (Composer/Git necesitan git + unzip) ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

# --- Composer (copiado desde la imagen oficial) ---
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# --- Node.js 22 LTS + npm/npx (Opción A: Node dentro de la imagen) ---
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# --- Extensiones PHP ---
# install-php-extensions resuelve dependencias del sistema, es idempotente
# (no falla si mbstring/xml ya vienen compiladas) y limpia lo que sobra.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_mysql \
        mysqli \
        mbstring \
        xml \
        bcmath \
        zip \
        intl \
        gd

# --- PHP: límites de subida (reportes/evidencias de NC) y memoria para PhpSpreadsheet ---
RUN { \
        echo 'upload_max_filesize = 20M'; \
        echo 'post_max_size = 25M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_file_uploads = 20'; \
        echo 'date.timezone = America/Mexico_City'; \
    } > /usr/local/etc/php/conf.d/zz-sga.ini

# --- Apache: mod_rewrite + DocumentRoot a /public ---
RUN a2enmod rewrite
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# --- Composer: sin límite de tiempo (mount lento en Windows) ---
ENV COMPOSER_PROCESS_TIMEOUT=0

# --- Alinear www-data con el UID/GID del usuario de WSL (1000) ---
RUN groupmod -g 1000 www-data \
    && usermod -u 1000 -g 1000 www-data

# --- Zona horaria ---
ENV TZ=America/Mexico_City
