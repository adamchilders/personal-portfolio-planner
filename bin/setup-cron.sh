#!/bin/bash

# Portfolio Tracker - Background Data Fetching Cron Setup
# This script sets up automated stock data fetching

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get the absolute path to the project directory
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FETCH_SCRIPT="${PROJECT_DIR}/bin/fetch-stock-data.php"

echo -e "${BLUE}Portfolio Tracker - Cron Setup${NC}"
echo "================================"
echo ""

# Check if the fetch script exists
if [ ! -f "$FETCH_SCRIPT" ]; then
    echo -e "${RED}❌ Error: fetch-stock-data.php not found at $FETCH_SCRIPT${NC}"
    exit 1
fi

# Make sure the script is executable
chmod +x "$FETCH_SCRIPT"

echo -e "${GREEN}📁 Project directory: $PROJECT_DIR${NC}"
echo -e "${GREEN}📄 Fetch script: $FETCH_SCRIPT${NC}"
echo ""

# Function to add cron job
add_cron_job() {
    local schedule="$1"
    local command="$2"
    local description="$3"
    
    # Check if cron job already exists
    if crontab -l 2>/dev/null | grep -q "$command"; then
        echo -e "${YELLOW}⚠️ Cron job already exists: $description${NC}"
        return 0
    fi
    
    # Add the cron job
    (crontab -l 2>/dev/null; echo "$schedule $command # $description") | crontab -
    echo -e "${GREEN}✅ Added cron job: $description${NC}"
    echo -e "   Schedule: $schedule"
    echo -e "   Command: $command"
    echo ""
}

# Check if user wants to proceed
echo "This script will set up automated stock data fetching with the following schedule:"
echo ""
echo -e "${BLUE}Real-time Quotes:${NC}"
echo "  • Every 15 minutes during market hours (Mon-Fri 9:30 AM - 4:00 PM ET)"
echo "  • Every 30 minutes outside market hours"
echo "  • Every 30 minutes on weekends"
echo ""
echo -e "${BLUE}Historical Data:${NC}"
echo "  • Daily at 4:05 PM ET (5 minutes after market close)"
echo "  • Fetches 1 year of historical OHLCV data"
echo "  • Only updates missing data (smart incremental updates)"
echo ""

read -p "Do you want to proceed? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}Setup cancelled.${NC}"
    exit 0
fi

echo ""
echo -e "${BLUE}Setting up cron jobs...${NC}"
echo ""

# Market hours: Every 15 minutes from 9:30 AM to 4:00 PM, Monday to Friday
add_cron_job "*/15 9-16 * * 1-5" \
    "/usr/bin/php $FETCH_SCRIPT >/dev/null 2>&1" \
    "Portfolio Tracker - Market Hours Quote Fetch"

# After hours weekdays: Every 30 minutes from 12:00 AM to 9:29 AM and 4:01 PM to 11:59 PM
add_cron_job "*/30 0-9,16-23 * * 1-5" \
    "/usr/bin/php $FETCH_SCRIPT >/dev/null 2>&1" \
    "Portfolio Tracker - After Hours Quote Fetch (Weekdays)"

# Weekends: Every 30 minutes
add_cron_job "*/30 * * * 0,6" \
    "/usr/bin/php $FETCH_SCRIPT >/dev/null 2>&1" \
    "Portfolio Tracker - Weekend Quote Fetch"

# Historical data: Daily at 4:05 PM ET (5 minutes after market close)
add_cron_job "5 16 * * 1-5" \
    "/usr/bin/php $FETCH_SCRIPT --historical >/dev/null 2>&1" \
    "Portfolio Tracker - Daily Historical Data Fetch"

echo -e "${GREEN}✅ Cron setup completed!${NC}"
echo ""

# Show current cron jobs
echo -e "${BLUE}Current cron jobs:${NC}"
crontab -l | grep -E "(Portfolio Tracker|fetch-stock-data)" || echo "No Portfolio Tracker cron jobs found."
echo ""

# Test the script
echo -e "${BLUE}Testing the fetch script...${NC}"
if php "$FETCH_SCRIPT" --stats; then
    echo -e "${GREEN}✅ Fetch script test successful!${NC}"
else
    echo -e "${RED}❌ Fetch script test failed. Please check the configuration.${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}🎉 Setup complete!${NC}"
echo ""
echo "The background data fetching system is now active. Stock data will be"
echo "automatically updated according to the schedule above."
echo ""
echo "To manually run the data fetch:"
echo "  php $FETCH_SCRIPT"
echo ""
echo "To view data freshness statistics:"
echo "  php $FETCH_SCRIPT --stats"
echo ""
echo "To remove the cron jobs:"
echo "  crontab -e"
echo "  (then delete the Portfolio Tracker lines)"
