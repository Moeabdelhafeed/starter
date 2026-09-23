#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                                                           ║"
echo "║            Laravel Inertia Starter Installer              ║"
echo "║                                                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Check if .env exists, if not copy from .env.example
if [ ! -f .env ]; then
    echo -e "${YELLOW}Creating .env file from .env.example...${NC}"
    cp .env.example .env
    echo -e "${GREEN}✓ .env file created${NC}"
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi

# Step 1: Composer Install
echo ""
echo -e "${BLUE}[1/6] Installing Composer dependencies...${NC}"
composer install
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Composer dependencies installed${NC}"
else
    echo -e "${RED}✗ Composer install failed${NC}"
    exit 1
fi

# Step 2: NPM Install
echo ""
echo -e "${BLUE}[2/6] Installing NPM dependencies...${NC}"
npm install
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ NPM dependencies installed${NC}"
else
    echo -e "${RED}✗ NPM install failed${NC}"
    exit 1
fi

# Step 3: Generate App Key
echo ""
echo -e "${BLUE}[3/6] Generating application key...${NC}"
php artisan key:generate
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Application key generated${NC}"
else
    echo -e "${RED}✗ Key generation failed${NC}"
    exit 1
fi

# Step 4: Database Configuration
echo ""
echo -e "${BLUE}[4/6] Database Configuration${NC}"
echo -e "${YELLOW}Please enter your database name:${NC}"
read -p "Database name: " DB_NAME

if [ -z "$DB_NAME" ]; then
    echo -e "${RED}✗ Database name cannot be empty${NC}"
    exit 1
fi

# Update .env file with database name
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
else
    # Linux
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
fi

echo -e "${GREEN}✓ Database name set to: $DB_NAME${NC}"

# Optional: Ask for database username and password
echo ""
read -p "Database username (press Enter for 'root'): " DB_USER
DB_USER=${DB_USER:-root}

if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
else
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
fi

read -sp "Database password (press Enter for empty): " DB_PASS
echo ""

if [[ "$OSTYPE" == "darwin"* ]]; then
    sed -i '' "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
else
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
fi

echo -e "${GREEN}✓ Database credentials configured${NC}"

# Step 5: Run Migrations and Seed
echo ""
echo -e "${BLUE}[5/6] Running database migrations and seeders...${NC}"
php artisan migrate --seed
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Database migrated and seeded${NC}"
else
    echo -e "${RED}✗ Migration failed. Please check your database connection.${NC}"
    echo -e "${YELLOW}Make sure the database '$DB_NAME' exists and credentials are correct.${NC}"
    exit 1
fi

# Step 6: Build assets
echo ""
echo -e "${BLUE}[6/6] Building frontend assets...${NC}"
npm run build
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Frontend assets built${NC}"
else
    echo -e "${RED}✗ Build failed${NC}"
    exit 1
fi

# Success message
echo ""
echo -e "${GREEN}"
echo "╔═══════════════════════════════════════════════════════════╗"
echo "║                                                           ║"
echo "║              Installation Complete!                       ║"
echo "║                                                           ║"
echo "╚═══════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Conventions reminder
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo -e "${YELLOW}  Conventions:${NC}"
echo -e "${GREEN}  • Timezones:${NC} the DB always stores UTC. For any user-set"
echo -e "    date/time field, add the ${GREEN}HasUserTimezone${NC} trait to the model and"
echo -e "    list the columns in ${GREEN}\$userTimezoneDates${NC}. It converts the admin's"
echo -e "    timezone (X-Timezone header) to UTC on save; the frontend"
echo -e "    (${GREEN}useDateFormat${NC}) converts back for display. Keep APP_TIMEZONE=UTC."
echo -e "    See CLAUDE.md → Traits → HasUserTimezone."
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${BLUE}Starting the development server...${NC}"
echo ""
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo -e "${YELLOW}  To run the server again later, use:${NC}"
echo ""
echo -e "${GREEN}    composer dev${NC}"
echo ""
echo -e "${YELLOW}  Or separately:${NC}"
echo -e "${GREEN}    php artisan serve${NC}     (Backend)"
echo -e "${GREEN}    npm run dev${NC}           (Frontend with hot reload)"
echo -e "${YELLOW}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Start the development server
composer dev
