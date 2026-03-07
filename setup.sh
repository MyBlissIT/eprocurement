#!/bin/bash
# eProcurement Plugin — Docker Setup Script
# Usage: bash setup.sh

set -e

echo "Starting eProcurement development environment..."
docker compose up -d

echo "Waiting for WordPress to be ready..."
sleep 15

echo "Installing WordPress..."
docker compose run --rm cli core install \
  --url="http://localhost:8190" \
  --title="eProcurement Dev" \
  --admin_user=admin \
  --admin_password=admin123 \
  --admin_email=admin@example.com \
  --skip-email

echo "Activating eProcurement plugin..."
docker compose run --rm cli plugin activate eprocurement

echo "Flushing rewrite rules..."
docker compose run --rm cli rewrite flush

echo ""
echo "=== Setup Complete ==="
echo "WordPress: http://localhost:8190"
echo "Admin:     http://localhost:8190/wp-admin"
echo "Login:     admin / admin123"
echo ""
echo "eProcurement menu should now appear in the WP Admin sidebar."
