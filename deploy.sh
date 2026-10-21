#!/bin/bash
# WLS Portal Deploy: git pull → build → caches → migrate → fpm restart
set -e
umask 022
cd /var/www/wls-portal

echo "→ Git Pull..."
git pull origin main

echo "→ Composer..."
composer install --no-dev --optimize-autoloader

echo "→ NPM..."
npm ci
npm run build

echo "→ Permissions..."
chown -R www-data:www-data storage bootstrap/cache
chown root:www-data .env && chmod 640 .env

echo "→ Laravel Cache (als www-data)..."
sudo -u www-data php8.4 artisan config:clear
sudo -u www-data php8.4 artisan config:cache
sudo -u www-data php8.4 artisan route:clear
sudo -u www-data php8.4 artisan route:cache
sudo -u www-data php8.4 artisan view:clear
sudo -u www-data php8.4 artisan cache:clear

echo "→ Migrations (als www-data)..."
sudo -u www-data php8.4 artisan migrate --force

echo "→ Horizon Restart..."
systemctl restart wls-portal-horizon.service

echo "→ PHP-FPM Restart..."
systemctl restart php8.4-fpm

echo "→ Verify..."
find storage bootstrap/cache -user root | head -5
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://100.64.0.3/ -H "Host: portal.weblab-studio.de"

echo "✅ Deploy complete"
