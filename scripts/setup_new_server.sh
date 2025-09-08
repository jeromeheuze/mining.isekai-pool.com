#!/bin/bash

# Mining Pool - New Server Setup Script
# This script automates the setup of a fresh mining pool server

set -e

echo "=========================================="
echo "Mining Pool - New Server Setup"
echo "=========================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run as root"
    exit 1
fi

# Step 1: System Update
print_status "Updating system packages..."
apt update && apt upgrade -y

# Step 2: Install Required Packages
print_status "Installing required packages..."
apt install -y apache2 mysql-server php php-mysql php-curl php-json php-cli git curl jq ufw

# Step 3: Enable Services
print_status "Enabling Apache and MySQL..."
systemctl enable apache2 mysql
systemctl start apache2 mysql

# Step 4: Configure Firewall
print_status "Configuring firewall..."
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 3333
ufw allow 4444
ufw allow 5555
ufw allow 6666
ufw --force enable

# Step 5: Clone Repository
print_status "Cloning repository..."
cd /var/www
if [ -d "yenten-pool" ]; then
    print_warning "yenten-pool directory already exists. Removing..."
    rm -rf yenten-pool
fi

git clone https://github.com/jeromeheuze/mining.isekai-pool.com.git yenten-pool
cd yenten-pool
chown -R www-data:www-data /var/www/yenten-pool

# Step 6: Database Setup
print_status "Setting up database..."
print_warning "You will need to set MySQL root password and create database user manually"
echo "Please run the following commands:"
echo "mysql_secure_installation"
echo "mysql -u root -p"
echo "CREATE DATABASE yenten_pool;"
echo "CREATE USER 'pool_user'@'localhost' IDENTIFIED BY 'D|Hm3\"K12<Zv';"
echo "GRANT ALL PRIVILEGES ON yenten_pool.* TO 'pool_user'@'localhost';"
echo "FLUSH PRIVILEGES;"
echo "EXIT;"
echo ""
read -p "Press Enter when database setup is complete..."

# Step 7: Import Database Schema
print_status "Importing database schema..."
mysql -u pool_user -p yenten_pool < database/schema.sql
mysql -u pool_user -p yenten_pool < database/migrations/001_add_pool_work_table.sql
mysql -u pool_user -p yenten_pool < database/migrations/002_add_ukkeycoin_support.sql

# Step 8: Install Cryptocurrency Daemons
print_status "Installing cryptocurrency daemons..."

# Yenten
print_status "Installing Yenten..."
cd /tmp
wget -q https://github.com/yentencoin/yenten/releases/download/v0.16.3.0/yenten-0.16.3.0-linux.tar.gz
tar -xzf yenten-0.16.3.0-linux.tar.gz
cp yenten-0.16.3.0/bin/* /usr/local/bin/
rm -rf yenten-0.16.3.0*

# KOTO
print_status "Installing KOTO..."
wget -q https://github.com/KotoDevelopers/koto/releases/download/v0.8.1.0/koto-0.8.1.0-linux.tar.gz
tar -xzf koto-0.8.1.0-linux.tar.gz
cp koto-0.8.1.0/bin/* /usr/local/bin/
rm -rf koto-0.8.1.0*

# UkkeyCoin
print_status "Installing UkkeyCoin..."
wget -q https://github.com/ukkeyHG/UkkeyCoin/releases/download/0.13.2.0/ukkeycoin-0.13.2.0-linux.tar.gz
tar -xzf ukkeycoin-0.13.2.0-linux.tar.gz
cp ukkeycoin-0.13.2.0/bin/* /usr/local/bin/
rm -rf ukkeycoin-0.13.2.0*

# Step 9: Configure Daemons
print_status "Configuring daemons..."

# Yenten Configuration
mkdir -p ~/.yenten
cat > ~/.yenten/yenten.conf << EOF
rpcuser=ytn_rpc_user
rpcpassword=Kt+X0O0J+xXDFkZ0VbznLrMoGJMZJZETNZTPBwrSrSA=
rpcport=9982
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
server=1
daemon=1
EOF

# KOTO Configuration
mkdir -p ~/.koto
cat > ~/.koto/koto.conf << EOF
rpcuser=koto_rpc_user
rpcpassword=Kt+X0O0J+xXDFkZ0VbznLrMoGJMZJZETNZTPBwrSrSA=
rpcport=8432
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
server=1
daemon=1
EOF

# UkkeyCoin Configuration
mkdir -p ~/.ukkeycoin
cat > ~/.ukkeycoin/ukkeycoin.conf << EOF
rpcuser=uky_rpc_user
rpcpassword=Kt+X0O0J+xXDFkZ0VbznLrMoGJMZJZETNZTPBwrSrSA=
rpcport=9985
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
server=1
daemon=1
rpcworkqueue=64
maxconnections=32
maxmempool=300
EOF

# Step 10: Start Daemons
print_status "Starting daemons..."
yentend -daemon
kotod -daemon
ukkeyd -conf=/root/.ukkeycoin/ukkeycoin.conf -datadir=/root/.ukkeycoin -daemon

# Wait for daemons to start
sleep 10

# Step 11: Create Wallets and Get Addresses
print_status "Creating wallets and getting addresses..."

# Yenten wallet
YTN_ADDRESS=$(yenten-cli getnewaddress "pool_wallet")
print_status "Yenten wallet address: $YTN_ADDRESS"

# KOTO wallet
KOTO_ADDRESS=$(koto-cli getnewaddress "pool_wallet")
print_status "KOTO wallet address: $KOTO_ADDRESS"

# UkkeyCoin wallet
UKY_ADDRESS=$(ukkey-cli -conf=/root/.ukkeycoin/ukkeycoin.conf -datadir=/root/.ukkeycoin getnewaddress "pool_wallet")
print_status "UkkeyCoin wallet address: $UKY_ADDRESS"

# Step 12: Update Configuration
print_status "Updating configuration file..."
cd /var/www/yenten-pool
cp config/config.template.json config/config.json

# Update config with wallet addresses
jq --arg ytn "$YTN_ADDRESS" --arg koto "$KOTO_ADDRESS" --arg uky "$UKY_ADDRESS" '
.yenten.wallet_address = $ytn |
.koto.wallet_address = $koto |
.ukkeycoin.wallet_address = $uky
' config/config.json > config/config.temp.json && mv config/config.temp.json config/config.json

# Step 13: Web Server Setup
print_status "Setting up web server..."
ln -sf /var/www/yenten-pool/public /var/www/html/yenten-pool
chown -R www-data:www-data /var/www/yenten-pool/public/
chmod -R 755 /var/www/yenten-pool/public/
systemctl restart apache2

# Step 14: Start Stratum Server
print_status "Starting stratum server..."
chmod +x scripts/*.sh
./scripts/start_stratum.sh

# Step 15: Verify Setup
print_status "Verifying setup..."

# Check daemons
print_status "Checking daemon status..."
ps aux | grep -E "(yentend|kotod|ukkeyd)" | grep -v grep

# Check stratum server
print_status "Checking stratum server..."
ps aux | grep stratum | grep -v grep

# Check ports
print_status "Checking listening ports..."
netstat -tlnp | grep -E "(3333|4444|5555|6666)"

# Step 16: Final Status
print_status "Setup completed successfully!"
echo ""
echo "=========================================="
echo "Setup Summary"
echo "=========================================="
echo "Yenten wallet address: $YTN_ADDRESS"
echo "KOTO wallet address: $KOTO_ADDRESS"
echo "UkkeyCoin wallet address: $UKY_ADDRESS"
echo ""
echo "Web interface: https://your-domain.com/"
echo "API endpoints:"
echo "  - https://your-domain.com/api/blockchain-status.php?coin=yenten"
echo "  - https://your-domain.com/api/blockchain-status.php?coin=koto"
echo "  - https://your-domain.com/api/blockchain-status.php?coin=ukkeycoin"
echo ""
echo "Stratum ports:"
echo "  - 3333: Yenten"
echo "  - 4444: KOTO"
echo "  - 5555: Additional"
echo "  - 6666: UkkeyCoin"
echo ""
echo "Next steps:"
echo "1. Wait for daemons to sync"
echo "2. Test API endpoints"
echo "3. Configure monitoring"
echo "4. Set up automated payouts"
echo "=========================================="

print_status "Setup completed! Check the logs if you encounter any issues."
print_status "Daemon logs: ~/.yenten/debug.log, ~/.koto/debug.log, ~/.ukkeycoin/debug.log"
print_status "Stratum logs: /var/www/yenten-pool/logs/stratum.log"
