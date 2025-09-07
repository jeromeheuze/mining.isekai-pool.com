#!/bin/bash

# AdventureCoin Daemon Setup Script
# This script helps set up AdventureCoin daemon for the mining pool

set -e

echo "=== AdventureCoin Daemon Setup ==="
echo "This script will help you set up AdventureCoin daemon for your mining pool."
echo ""

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   echo "This script should not be run as root for security reasons."
   echo "Please run as a regular user with sudo privileges."
   exit 1
fi

# Configuration variables
ADVC_USER="advc"
ADVC_HOME="/home/$ADVC_USER"
ADVC_DATA_DIR="$ADVC_HOME/.adventurecoin"
ADVC_CONFIG_FILE="$ADVC_DATA_DIR/adventurecoin.conf"
ADVC_SERVICE_FILE="/etc/systemd/system/adventurecoin.service"
ADVC_RPC_PORT="9984"
ADVC_RPC_USER="advc_rpc_user"
ADVC_RPC_PASSWORD=$(openssl rand -base64 32)

echo "Configuration:"
echo "  User: $ADVC_USER"
echo "  Data Directory: $ADVC_DATA_DIR"
echo "  RPC Port: $ADVC_RPC_PORT"
echo "  RPC User: $ADVC_RPC_USER"
echo "  RPC Password: $ADVC_RPC_PASSWORD"
echo ""

# Create AdventureCoin user
echo "Creating AdventureCoin user..."
if ! id "$ADVC_USER" &>/dev/null; then
    sudo useradd -r -s /bin/false -d "$ADVC_HOME" -m "$ADVC_USER"
    echo "User $ADVC_USER created."
else
    echo "User $ADVC_USER already exists."
fi

# Create data directory
echo "Creating data directory..."
sudo mkdir -p "$ADVC_DATA_DIR"
sudo chown "$ADVC_USER:$ADVC_USER" "$ADVC_DATA_DIR"
sudo chmod 700 "$ADVC_DATA_DIR"

# Download AdventureCoin Core (latest release)
echo "Downloading AdventureCoin Core..."
ADVC_VERSION="5.0.0.2"

# Try different possible archive names
POSSIBLE_ARCHIVES=(
    "AdventureCoin-${ADVC_VERSION}-x86_64-linux-gnu.tar.gz"
    "adventurecoin-${ADVC_VERSION}-x86_64-linux-gnu.tar.gz"
    "AdventureCoin-${ADVC_VERSION}-linux64.tar.gz"
    "adventurecoin-${ADVC_VERSION}-linux64.tar.gz"
    "AdventureCoin-${ADVC_VERSION}.tar.gz"
    "adventurecoin-${ADVC_VERSION}.tar.gz"
)

cd /tmp
DOWNLOADED=false

for ARCHIVE in "${POSSIBLE_ARCHIVES[@]}"; do
    ADVC_URL="https://github.com/AdventureCoin-ADVC/AdventureCoin/releases/download/${ADVC_VERSION}/${ARCHIVE}"
    echo "Trying to download: $ADVC_URL"
    
    if wget "$ADVC_URL" 2>/dev/null; then
        echo "Successfully downloaded: $ARCHIVE"
        ADVC_ARCHIVE="$ARCHIVE"
        DOWNLOADED=true
        break
    else
        echo "Failed to download: $ARCHIVE"
        rm -f "$ARCHIVE" 2>/dev/null
    fi
done

if [ "$DOWNLOADED" = false ]; then
    echo "Error: Could not download AdventureCoin Core from any of the expected URLs."
    echo ""
    echo "Please manually download AdventureCoin Core from:"
    echo "https://github.com/AdventureCoin-ADVC/AdventureCoin/releases/tag/${ADVC_VERSION}"
    echo ""
    echo "Then:"
    echo "1. Download the appropriate Linux archive"
    echo "2. Extract it to /tmp"
    echo "3. Run this script again"
    echo ""
    echo "Alternatively, you can try building from source:"
    echo "git clone https://github.com/AdventureCoin-ADVC/AdventureCoin.git"
    echo "cd AdventureCoin"
    echo "make"
    echo ""
    exit 1
fi

# Extract and install
echo "Installing AdventureCoin Core..."
tar -xzf "$ADVC_ARCHIVE"

# Find the extracted directory
EXTRACTED_DIR=$(find . -maxdepth 1 -type d -name "*AdventureCoin*" -o -name "*adventurecoin*" | head -1)

if [ -z "$EXTRACTED_DIR" ]; then
    echo "Error: Could not find extracted AdventureCoin directory."
    echo "Please check the downloaded archive and extract it manually."
    exit 1
fi

echo "Found extracted directory: $EXTRACTED_DIR"

# Copy binaries
if [ -f "$EXTRACTED_DIR/bin/adventurecoin-cli" ] && [ -f "$EXTRACTED_DIR/bin/adventurecoind" ]; then
    sudo cp "$EXTRACTED_DIR/bin/adventurecoin-cli" /usr/local/bin/
    sudo cp "$EXTRACTED_DIR/bin/adventurecoind" /usr/local/bin/
elif [ -f "$EXTRACTED_DIR/adventurecoin-cli" ] && [ -f "$EXTRACTED_DIR/adventurecoind" ]; then
    sudo cp "$EXTRACTED_DIR/adventurecoin-cli" /usr/local/bin/
    sudo cp "$EXTRACTED_DIR/adventurecoind" /usr/local/bin/
else
    echo "Error: Could not find AdventureCoin binaries in the extracted directory."
    echo "Please check the downloaded archive structure."
    exit 1
fi

sudo chmod +x /usr/local/bin/adventurecoin-*

# Create configuration file
echo "Creating AdventureCoin configuration..."
sudo tee "$ADVC_CONFIG_FILE" > /dev/null <<EOF
# AdventureCoin Configuration for Mining Pool
# Generated on $(date)

# Network settings
server=1
daemon=1
listen=1
maxconnections=100

# RPC settings
rpcuser=$ADVC_RPC_USER
rpcpassword=$ADVC_RPC_PASSWORD
rpcport=$ADVC_RPC_PORT
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

sudo chown "$ADVC_USER:$ADVC_USER" "$ADVC_CONFIG_FILE"
sudo chmod 600 "$ADVC_CONFIG_FILE"

# Create systemd service
echo "Creating systemd service..."
sudo tee "$ADVC_SERVICE_FILE" > /dev/null <<EOF
[Unit]
Description=AdventureCoin Core Daemon
After=network.target

[Service]
Type=forking
User=$ADVC_USER
Group=$ADVC_USER
ExecStart=/usr/local/bin/adventurecoind -conf=$ADVC_CONFIG_FILE -datadir=$ADVC_DATA_DIR
ExecStop=/usr/local/bin/adventurecoin-cli -conf=$ADVC_CONFIG_FILE -datadir=$ADVC_DATA_DIR stop
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
ReadWritePaths=$ADVC_DATA_DIR

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd and enable service
echo "Enabling AdventureCoin service..."
sudo systemctl daemon-reload
sudo systemctl enable adventurecoin

# Update pool configuration
echo "Updating pool configuration..."
POOL_CONFIG_FILE="config/config.json"

if [ -f "$POOL_CONFIG_FILE" ]; then
    # Backup original config
    cp "$POOL_CONFIG_FILE" "$POOL_CONFIG_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    
    # Update AdventureCoin configuration
    jq --arg host "localhost" \
       --arg port "$ADVC_RPC_PORT" \
       --arg user "$ADVC_RPC_USER" \
       --arg password "$ADVC_RPC_PASSWORD" \
       '.adventurecoin.daemon_host = $host | 
        .adventurecoin.daemon_port = ($port | tonumber) | 
        .adventurecoin.daemon_user = $user | 
        .adventurecoin.daemon_password = $password' \
       "$POOL_CONFIG_FILE" > "$POOL_CONFIG_FILE.tmp" && mv "$POOL_CONFIG_FILE.tmp" "$POOL_CONFIG_FILE"
    
    echo "Pool configuration updated."
else
    echo "Warning: Pool configuration file not found at $POOL_CONFIG_FILE"
    echo "Please manually update your configuration with:"
    echo "  daemon_host: localhost"
    echo "  daemon_port: $ADVC_RPC_PORT"
    echo "  daemon_user: $ADVC_RPC_USER"
    echo "  daemon_password: $ADVC_RPC_PASSWORD"
fi

# Create wallet
echo "Starting AdventureCoin daemon to create wallet..."
sudo systemctl start adventurecoin

# Wait for daemon to start
echo "Waiting for daemon to start..."
sleep 10

# Check if daemon is running
if sudo systemctl is-active --quiet adventurecoin; then
    echo "AdventureCoin daemon is running."
    
    # Create wallet if it doesn't exist
    echo "Creating wallet..."
    sudo -u "$ADVC_USER" /usr/local/bin/adventurecoin-cli -conf="$ADVC_CONFIG_FILE" -datadir="$ADVC_DATA_DIR" createwallet "pool_wallet" false false "" false false true
    
    # Get new address for pool
    echo "Generating pool address..."
    POOL_ADDRESS=$(sudo -u "$ADVC_USER" /usr/local/bin/adventurecoin-cli -conf="$ADVC_CONFIG_FILE" -datadir="$ADVC_DATA_DIR" getnewaddress "pool")
    
    echo ""
    echo "=== Setup Complete ==="
    echo "AdventureCoin daemon is now running."
    echo "Pool address: $POOL_ADDRESS"
    echo ""
    echo "Next steps:"
    echo "1. Update your pool configuration with the pool address:"
    echo "   \"wallet_address\": \"$POOL_ADDRESS\""
    echo ""
    echo "2. Run the database migration:"
    echo "   mysql -u root -p yenten_pool < database/migrations/002_add_adventurecoin_support.sql"
    echo ""
    echo "3. Restart your mining pool server"
    echo ""
    echo "4. Miners can connect to:"
    echo "   stratum+tcp://your-pool-domain:6666"
    echo ""
    echo "Service management:"
    echo "  Start:   sudo systemctl start adventurecoin"
    echo "  Stop:    sudo systemctl stop adventurecoin"
    echo "  Status:  sudo systemctl status adventurecoin"
    echo "  Logs:    sudo journalctl -u adventurecoin -f"
    
else
    echo "Error: Failed to start AdventureCoin daemon."
    echo "Check logs with: sudo journalctl -u adventurecoin -f"
    exit 1
fi

echo ""
echo "Setup completed successfully!"
