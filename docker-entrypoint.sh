#!/bin/bash
set -e

PORT="${PORT:-10000}"

# Bind Apache to Render dynamic PORT
sed -i "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
sed -i "s!DocumentRoot /var/www/html/public!DocumentRoot /var/www/html!g" /etc/apache2/sites-available/000-default.conf

# Ensure upload and backup directories exist and are writable by www-data
mkdir -p /var/www/html/uploads/vault \
         /var/www/html/uploads/kyc \
         /var/www/html/uploads/payment_proofs \
         /var/www/html/uploads/chat \
         /var/www/html/uploads/documents \
         /var/www/html/uploads/avatars \
         /var/www/html/public/uploads/avatars \
         /var/www/html/admin/backups

chown -R www-data:www-data /var/www/html/uploads /var/www/html/public/uploads /var/www/html/admin/backups
chmod -R 775 /var/www/html/uploads /var/www/html/public/uploads /var/www/html/admin/backups

exec "$@"
