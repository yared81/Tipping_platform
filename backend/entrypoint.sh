#!/usr/bin/env bash
set -euo pipefail

# Default to 8080 if PORT is not set (Render sets PORT)
PORT_NUMBER="${PORT:-8080}"

echo "Configuring Apache to listen on port ${PORT_NUMBER}"

# Update Apache ports.conf
if [ -f /etc/apache2/ports.conf ]; then
  sed -ri "s/^Listen\s+[0-9]+/Listen ${PORT_NUMBER}/" /etc/apache2/ports.conf || true
  if ! grep -q "Listen ${PORT_NUMBER}" /etc/apache2/ports.conf; then
    echo "Listen ${PORT_NUMBER}" >> /etc/apache2/ports.conf
  fi
fi

# Update the default vhost to match the PORT
if [ -f /etc/apache2/sites-available/000-default.conf ]; then
  sed -ri "s#^<VirtualHost \*:[0-9]+>#<VirtualHost *:${PORT_NUMBER}>#" /etc/apache2/sites-available/000-default.conf || true
fi

echo "Preparing Laravel application"
cd /var/www/html

# Ensure .env exists
if [ ! -f .env ]; then
  cp .env.example .env || true
fi

# Update APP_URL if provided
if [ -n "${APP_URL:-}" ]; then
  sed -ri "s#^APP_URL=.*#APP_URL=${APP_URL}#" .env || true
fi

# Generate APP_KEY if missing
if ! grep -qE '^APP_KEY=base64:' .env || [ -z "$(php -r 'echo getenv("APP_KEY");')" ]; then
  php artisan key:generate --force || true
fi

# Optimize caches and storage link
php artisan storage:link || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run migrations (non-interactive)
php artisan migrate --force || true

echo "Starting Apache"
exec apache2-foreground


