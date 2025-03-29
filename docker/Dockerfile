FROM php:8.2-apache

# Configurer le serveur Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && a2enmod rewrite 

# Mise à jour et installation des dépendances
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
    locales apt-utils git libicu-dev g++ libpng-dev libxml2-dev libzip-dev libonig-dev libxslt-dev unzip curl \
    \
    # Configurer les locales
    && echo "en_US.UTF-8 UTF-8" > /etc/locale.gen \
    && echo "fr_FR.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen \
    \
    # Installer Composer
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    \
    # Installer Node.js et npm
    && curl -sL https://deb.nodesource.com/setup_18.x | bash \
    && apt-get install -y nodejs \
    \
    # Installer Symfony CLI
    && curl -sS https://get.symfony.com/cli/installer | bash \
    && mv /root/.symfony5/bin/symfony /usr/local/bin/symfony \
    \
    # Configurer et installer les extensions PHP
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
    pdo pdo_mysql opcache intl zip calendar dom mbstring gd xsl \
    \
    # Installer APCu
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    \
    # Nettoyer les fichiers temporaires
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Définir le répertoire de travail
WORKDIR /var/www/

# Ajouter ces lignes après la définition de WORKDIR /var/www/
RUN sed -i 's|/var/www/html|/var/www/public|g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Exposer le port Apache
EXPOSE 80