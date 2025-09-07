#!/bin/bash

# UkkeyCoin Daemon Setup Script
# This script helps set up UkkeyCoin daemon for the mining pool

set -e

echo "=== UkkeyCoin Daemon Setup ==="
echo "This script will help you set up UkkeyCoin daemon for your mining pool."
echo ""

# Configuration variables
UKY_USER="uky"
UKY_HOME="/home/$UKY_USER"
UKY_DATA_DIR="$UKY_HOME/.ukkeycoin"
UKY_CONFIG_FILE="$UKY_DATA_DIR/ukkeycoin.conf"
UKY_SERVICE_FILE="/etc/systemd/system/ukkeycoin.service"
UKY_RPC_PORT="9984"
UKY_RPC_USER="uky_rpc_user"
UKY_RPC_PASSWORD=$(openssl rand -base64 32)

echo "Configuration:"
echo "  User: $UKY_USER"
echo "  Data Directory: $UKY_DATA_DIR"
echo "  RPC Port: $UKY_RPC_PORT"
echo "  RPC User: $UKY_RPC_USER"
echo "  RPC Password: $UKY_RPC_PASSWORD"
echo ""

# Create UkkeyCoin user
echo "Creating UkkeyCoin user..."
if ! id "$UKY_USER" &>/dev/null; then
    sudo useradd -r -s /bin/false -d "$UKY_HOME" -m "$UKY_USER"
    echo "User $UKY_USER created."
else
    echo "User $UKY_USER already exists."
fi

# Create data directory
echo "Creating data directory..."
sudo mkdir -p "$UKY_DATA_DIR"
sudo chown "$UKY_USER:$UKY_USER" "$UKY_DATA_DIR"
sudo chmod 700 "$UKY_DATA_DIR"

# Download UkkeyCoin Core (latest release)
echo "Downloading UkkeyCoin Core..."
UKY_VERSION="0.13.2.0"

# Try different possible archive names
POSSIBLE_ARCHIVES=(
    "UkkeyCoin-${UKY_VERSION}-x86_64-linux-gnu.tar.gz"
    "ukkeycoin-${UKY_VERSION}-x86_64-linux-gnu.tar.gz"
    "UkkeyCoin-${UKY_VERSION}-linux64.tar.gz"
    "ukkeycoin-${UKY_VERSION}-linux64.tar.gz"
    "UkkeyCoin-${UKY_VERSION}.tar.gz"
    "ukkeycoin-${UKY_VERSION}.tar.gz"
)

cd /tmp
DOWNLOADED=false

for ARCHIVE in "${POSSIBLE_ARCHIVES[@]}"; do
    UKY_URL="https://github.com/ukkeyHG/UkkeyCoin/releases/download/${UKY_VERSION}/${ARCHIVE}"
    echo "Trying to download: $UKY_URL"
    
    if wget "$UKY_URL" 2>/dev/null; then
        echo "Successfully downloaded: $ARCHIVE"
        UKY_ARCHIVE="$ARCHIVE"
        DOWNLOADED=true
        break
    else
        echo "Failed to download: $ARCHIVE"
        rm -f "$ARCHIVE" 2>/dev/null
    fi
done

if [ "$DOWNLOADED" = false ]; then
    echo "Error: Could not download UkkeyCoin Core from any of the expected URLs."
    echo ""
    echo "Please manually download UkkeyCoin Core from:"
    echo "https://github.com/ukkeyHG/UkkeyCoin/releases/tag/${UKY_VERSION}"
    echo ""
    echo "Then:"
    echo "1. Download the appropriate Linux archive"
    echo "2. Extract it to /tmp"
    echo "3. Run this script again"
    echo ""
    echo "Alternatively, you can try building from source:"
    echo "git clone https://github.com/ukkeyHG/UkkeyCoin.git"
    echo "cd UkkeyCoin"
    echo "make"
    echo ""
    exit 1
fi

# Extract and install
echo "Installing UkkeyCoin Core..."
tar -xzf "$UKY_ARCHIVE"

# Find the extracted directory
EXTRACTED_DIR=$(find . -maxdepth 1 -type d -name "*UkkeyCoin*" -o -name "*ukkeycoin*" | head -1)

if [ -z "$EXTRACTED_DIR" ]; then
    echo "Error: Could not find extracted UkkeyCoin directory."
    echo "Please check the downloaded archive and extract it manually."
    exit 1
fi

echo "Found extracted directory: $EXTRACTED_DIR"

# Copy binaries
if [ -f "$EXTRACTED_DIR/bin/ukkeycoin-cli" ] && [ -f "$EXTRACTED_DIR/bin/ukkeycoind" ]; then
    sudo cp "$EXTRACTED_DIR/bin/ukkeycoin-cli" /usr/local/bin/
    sudo cp "$EXTRACTED_DIR/bin/ukkeycoind" /usr/local/bin/
elif [ -f "$EXTRACTED_DIR/ukkeycoin-cli" ] && [ -f "$EXTRACTED_DIR/ukkeycoind" ]; then
    sudo cp "$EXTRACTED_DIR/ukkeycoin-cli" /usr/local/bin/
    sudo cp "$EXTRACTED_DIR/ukkeycoind" /usr/local/bin/
else
    echo "Error: Could not find UkkeyCoin binaries in the extracted directory."
    echo "Please check the downloaded archive structure."
    exit 1
fi

sudo chmod +x /usr/local/bin/ukkeycoin-*

# Create configuration file
echo "Creating UkkeyCoin configuration..."
sudo tee "$UKY_CONFIG_FILE" > /dev/null <<EOF
# UkkeyCoin Configuration for Mining Pool
# Generated on $(date)

# Network settings
server=1
daemon=1
listen=1
maxconnections=100

# RPC settings
rpcuser=$UKY_RPC_USER
rpcpassword=$UKY_RPC_PASSWORD
rpcport=$UKY_RPC_PORT
rpcallowip=127.0.0.1
rpcallowip=10.0.0.0/8
rpcallowip=172.16.0.0/12
rpcallowip=192.168.0.0/16

# Mining settings
gen=0
miningrequirespeers=1

# Logging
debug=0
logtimestamps=1
logips=1

# Performance
dbcache=256
maxmempool=64

# Security
rpcbind=127.0.0.1
rpcbind=0.0.0.0

# Additional settings
txindex=1
addressindex=1
timestampindex=1
spentindex=1
EOF

sudo chown "$UKY_USER:$UKY_USER" "$UKY_CONFIG_FILE"
sudo chmod 600 "$UKY_CONFIG_FILE"

# Create systemd service
echo "Creating systemd service..."
sudo tee "$UKY_SERVICE_FILE" > /dev/null <<EOF
[Unit]
Description=UkkeyCoin Core Daemon
After=network.target

[Service]
Type=forking
User=$UKY_USER
Group=$UKY_USER
ExecStart=/usr/local/bin/ukkeycoind -conf=$UKY_CONFIG_FILE -datadir=$UKY_DATA_DIR
ExecStop=/usr/local/bin/ukkeycoin-cli -conf=$UKY_CONFIG_FILE -datadir=$UKY_DATA_DIR stop
ExecReload=/bin/kill -HUP \$MAINPID
KillMode=process
Restart=on-failure
RestartSec=30
TimeoutStartSec=300
TimeoutStopSec=300

# Security settings
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=$UKY_DATA_DIR

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd and enable service
echo "Enabling UkkeyCoin service..."
sudo systemctl daemon-reload
sudo systemctl enable ukkeycoin

# Update pool configuration
echo "Updating pool configuration..."
POOL_CONFIG_FILE="config/config.json"

if [ -f "$POOL_CONFIG_FILE" ]; then
    # Backup original config
    cp "$POOL_CONFIG_FILE" "$POOL_CONFIG_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Update UkkeyCoin configuration
    jq --arg host "localhost" \
       --arg port "$UKY_RPC_PORT" \
       --arg user "$UKY_RPC_USER" \
       --arg password "$UKY_RPC_PASSWORD" \
       '.ukkeycoin.daemon_host = $host | 
        .ukkeycoin.daemon_port = ($port | tonumber) | 
        .ukkeycoin.daemon_user = $user | 
        .ukkeycoin.daemon_password = $password' \
       "$POOL_CONFIG_FILE" > "$POOL_CONFIG_FILE.tmp" && mv "$POOL_CONFIG_FILE.tmp" "$POOL_CONFIG_FILE"
    
    echo "Pool configuration updated."
else
    echo "Warning: Pool configuration file not found at $POOL_CONFIG_FILE"
    echo "Please manually update your configuration with:"
    echo "  daemon_host: localhost"
    echo "  daemon_port: $UKY_RPC_PORT"
    echo "  daemon_user: $UKY_RPC_USER"
    echo "  daemon_password: $UKY_RPC_PASSWORD"
fi

# Create wallet
echo "Starting UkkeyCoin daemon to create wallet..."
sudo systemctl start ukkeycoin

# Wait for daemon to start
echo "Waiting for daemon to start..."
sleep 10

# Check if daemon is running
if sudo systemctl is-active --quiet ukkeycoin; then
    echo "UkkeyCoin daemon is running."
    
    # Create wallet if it doesn't exist
    echo "Creating wallet..."
    sudo -u "$UKY_USER" /usr/local/bin/ukkeycoin-cli -conf="$UKY_CONFIG_FILE" -datadir="$UKY_DATA_DIR" createwallet "pool_wallet" false false "" false false true
    
    # Get new address for pool
    echo "Generating pool address..."
    POOL_ADDRESS=$(sudo -u "$UKY_USER" /usr/local/bin/ukkeycoin-cli -conf="$UKY_CONFIG_FILE" -datadir="$UKY_DATA_DIR" getnewaddress "pool")
    
    echo ""
    echo "=== Setup Complete ==="
    echo "UkkeyCoin daemon is now running."
    echo "Pool address: $POOL_ADDRESS"
    echo ""
    echo "Next steps:"
    echo "1. Update your pool configuration with the pool address:"
    echo "   \"wallet_address\": \"$POOL_ADDRESS\""
    echo ""
    echo "2. Run the database migration:"
    echo "   mysql -u root -p yenten_pool < database/migrations/002_add_ukkeycoin_support.sql"
    echo ""
    echo "3. Restart your mining pool server"
    echo ""
    echo "4. Miners can connect to:"
    echo "   stratum+tcp://your-pool-domain:6666"
    echo ""
    echo "Service management:"
    echo "  Start:   sudo systemctl start ukkeycoin"
    echo "  Stop:    sudo systemctl stop ukkeycoin"
    echo "  Status:  sudo systemctl status ukkeycoin"
    echo "  Logs:    sudo journalctl -u ukkeycoin -f"
    
else
    echo "Error: Failed to start UkkeyCoin daemon."
    echo "Check logs with: sudo journalctl -u ukkeycoin -f"
    exit 1
fi

echo ""
echo "Setup completed successfully!"
