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

echo "Starting Apache"
exec apache2-foreground


