#!/bin/bash
# ═══════════════════════════════════════════════════════════
# Hexa Billing System — Deploy / Update Script
# ═══════════════════════════════════════════════════════════
# Usage: update-hws-billing (bash alias) or ./deploy.sh
#
# What it does:
#   1. Pulls latest code from origin/main (force reset)
#   2. Runs any new database migrations
#   3. Clears all Laravel caches (config, route, view, app)
#
# Bash alias setup (add to ~/.bashrc):
#   alias update-hws-billing='/home/hexawebsystems/public_html/billing.hexawebsystems.com/deploy.sh'
# ═══════════════════════════════════════════════════════════

set -e  # Exit on any error

# Project root
DIR="/home/hexawebsystems/public_html/billing.hexawebsystems.com"

echo "═══════════════════════════════════════"
echo "  Hexa Billing System — Deploying..."
echo "═══════════════════════════════════════"

# Step 1: Pull latest code
echo ""
echo "→ Fetching latest from origin..."
cd "$DIR"
git fetch origin

echo "→ Resetting to origin/main..."
git reset --hard origin/main

# Step 2: Run migrations
echo ""
echo "→ Running database migrations..."
php artisan migrate --force

# Step 3: Clear all caches
echo ""
echo "→ Clearing caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Done
echo ""
echo "═══════════════════════════════════════"
echo "  ✅ Deploy complete!"
echo "  Version: $(grep "'version'" config/hws.php | grep -oP "'[^']*'" | tail -1 | tr -d "'")"
echo "  Commit:  $(git log -1 --pretty='%h — %s')"
echo "═══════════════════════════════════════"
