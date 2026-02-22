#!/bin/bash
# Deployment script pro america.webresent.cz
# Použití: bash deploy.sh

set -e  # Exit on error

echo "🚀 America Pod Věží - Deployment"
echo "================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "index.php" ]; then
    echo -e "${RED}❌ Error: index.php not found. Run this from project root.${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Backing up local changes...${NC}"
git stash save "deployment-backup-$(date +%Y%m%d-%H%M%S)"

echo -e "${YELLOW}2. Fetching latest from GitHub...${NC}"
git fetch origin

echo -e "${YELLOW}3. Hard reset to origin/main...${NC}"
git reset --hard origin/main

echo -e "${YELLOW}4. Setting correct permissions...${NC}"
# Files: 644
find . -type f -exec chmod 644 {} \; 2>/dev/null || true

# Directories: 755
find . -type d -exec chmod 755 {} \; 2>/dev/null || true

# Ensure daily_menu.json is writable
if [ -f "daily_menu.json" ]; then
    chmod 666 daily_menu.json
    echo -e "${GREEN}✓ daily_menu.json: 666${NC}"
else
    touch daily_menu.json
    chmod 666 daily_menu.json
    echo -e "${GREEN}✓ daily_menu.json created: 666${NC}"
fi

# Make scripts executable
chmod 755 scrape_menu.php 2>/dev/null || true

echo -e "${YELLOW}5. Testing scraper...${NC}"
if php scrape_menu.php > /tmp/scrape_test.log 2>&1; then
    echo -e "${GREEN}✓ Scraper test passed${NC}"
    cat /tmp/scrape_test.log | head -n 5
else
    echo -e "${RED}⚠️  Scraper test failed (check manually)${NC}"
    cat /tmp/scrape_test.log
fi

echo -e "${YELLOW}6. Checking crontab...${NC}"
if crontab -l 2>/dev/null | grep -q "scrape_menu.php"; then
    echo -e "${GREEN}✓ Cron job exists${NC}"
    crontab -l | grep scrape_menu
else
    echo -e "${YELLOW}⚠️  No cron job found. Add manually:${NC}"
    echo "crontab -e"
    echo "0 6 * * * cd $(pwd) && /usr/bin/php scrape_menu.php >> scrape.log 2>&1"
fi

echo ""
echo -e "${GREEN}================================="
echo "✅ Deployment complete!${NC}"
echo "================================="
echo ""
echo "URLs:"
echo "  Main: https://america.webresent.cz"
echo "  Admin: https://admin.america.webresent.cz"
echo "  API: https://america.webresent.cz/get_today_menu.php"
echo ""
echo "Next steps:"
echo "  1. Test main site: curl https://america.webresent.cz"
echo "  2. Test API: curl https://america.webresent.cz/get_today_menu.php"
echo "  3. Check logs: tail -f scrape.log"
echo ""
