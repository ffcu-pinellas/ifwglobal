FROM php:8.2-apache

# Install system packages & PHP extensions required by IFW Global
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    libonig-dev \
    ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mysqli gd zip mbstring opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers

# Configure Apache DocumentRoot, AllowOverride All, and security deny rules
RUN { \
    echo '<Directory /var/www/html>'; \
    echo '    Options -Indexes +FollowSymLinks'; \
    echo '    AllowOverride All'; \
    echo '    Require all granted'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/uploads>'; \
    echo '    php_flag engine off'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/public/uploads>'; \
    echo '    php_flag engine off'; \
    echo '</Directory>'; \
    echo '<Directory /var/www/html/admin/backups>'; \
    echo '    Require all denied'; \
    echo '</Directory>'; \
    echo 'RedirectMatch 403 (?i)^/\.git(?:/|$)'; \
    echo '<FilesMatch "(?i)^(\.(env|git|htaccess|dockerignore).*|composer\.(json|lock)|Dockerfile|docker-entrypoint\.sh|render\.yaml|RENDER_SETUP\.md)$|\.(sql|pem|ps1|py|log|bak|sh|ya?ml)$">'; \
    echo '    Require all denied'; \
    echo '</FilesMatch>'; \
} > /etc/apache2/conf-available/ifwglobal.conf \
    && a2enconf ifwglobal

WORKDIR /var/www/html

# Copy application files (includes committed vendor/ PHPMailer and isrgrootx1.pem)
COPY . .

# Setup entrypoint (Render dynamic $PORT + writable upload/backup directories)
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
