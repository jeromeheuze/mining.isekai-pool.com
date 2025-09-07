# Manual AdventureCoin Setup Guide

Since the automatic download is failing, here's how to manually set up AdventureCoin on your VPS:

## Step 1: Download AdventureCoin Core Manually

```bash
# SSH into your VPS
ssh your-username@your-vps-ip

# Navigate to your pool directory
cd /var/www/yenten-pool

# Create a temporary directory
mkdir -p /tmp/advc-setup
cd /tmp/advc-setup

# Try to download from the GitHub releases page
# Visit: https://github.com/AdventureCoin-ADVC/AdventureCoin/releases/tag/5.0.0.2
# Look for the Linux binary files and download them manually

# Alternative: Try to find the correct download URL
curl -s https://api.github.com/repos/AdventureCoin-ADVC/AdventureCoin/releases/tags/5.0.0.2 | grep "browser_download_url" | grep -i linux
```

## Step 2: Manual Installation

If you can't find the pre-built binaries, build from source:

```bash
# Install dependencies
sudo apt update
sudo apt install -y build-essential libtool autotools-dev automake pkg-config libssl-dev libevent-dev bsdmainutils python3 libboost-all-dev

# Clone the repository
git clone https://github.com/AdventureCoin-ADVC/AdventureCoin.git
cd AdventureCoin

# Build from source
./autogen.sh
./configure
make -j$(nproc)

# Install
sudo make install
```

## Step 3: Create AdventureCoin User and Configuration

```bash
# Create AdventureCoin user
sudo useradd -r -s /bin/false -d /home/advc -m advc

# Create data directory
sudo mkdir -p /home/advc/.adventurecoin
sudo chown advc:advc /home/advc/.adventurecoin
sudo chmod 700 /home/advc/.adventurecoin

# Generate RPC password
ADVC_RPC_PASSWORD=$(openssl rand -base64 32)
echo "RPC Password: $ADVC_RPC_PASSWORD"

# Create configuration file
sudo tee /home/advc/.adventurecoin/adventurecoin.conf > /dev/null <<EOF
# AdventureCoin Configuration for Mining Pool
server=1
daemon=1
listen=1
maxconnections=100
rpcuser=advc_rpc_user
rpcpassword=$ADVC_RPC_PASSWORD
rpcport=9984
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
txindex=1
addressindex=1
timestampindex=1
spentindex=1
dbcache=256
maxmempool=64
EOF

sudo chown advc:advc /home/advc/.adventurecoin/adventurecoin.conf
sudo chmod 600 /home/advc/.adventurecoin/adventurecoin.conf
```

## Step 4: Create Systemd Service

```bash
# Create systemd service file
sudo tee /etc/systemd/system/adventurecoin.service > /dev/null <<EOF
[Unit]
Description=AdventureCoin Core Daemon
After=network.target

[Service]
Type=forking
User=advc
Group=advc
ExecStart=/usr/local/bin/adventurecoind -conf=/home/advc/.adventurecoin/adventurecoin.conf -datadir=/home/advc/.adventurecoin
ExecStop=/usr/local/bin/adventurecoin-cli -conf=/home/advc/.adventurecoin/adventurecoin.conf -datadir=/home/advc/.adventurecoin stop
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
ReadWritePaths=/home/advc/.adventurecoin

[Install]
WantedBy=multi-user.target
EOF

# Reload systemd and enable service
sudo systemctl daemon-reload
sudo systemctl enable adventurecoin
```

## Step 5: Start AdventureCoin and Create Wallet

```bash
# Start AdventureCoin daemon
sudo systemctl start adventurecoin

# Wait for daemon to start
sleep 10

# Check if daemon is running
sudo systemctl status adventurecoin

# Create wallet
sudo -u advc /usr/local/bin/adventurecoin-cli -conf=/home/advc/.adventurecoin/adventurecoin.conf -datadir=/home/advc/.adventurecoin createwallet "pool_wallet" false false "" false false true

# Get new address for pool
POOL_ADDRESS=$(sudo -u advc /usr/local/bin/adventurecoin-cli -conf=/home/advc/.adventurecoin/adventurecoin.conf -datadir=/home/advc/.adventurecoin getnewaddress "pool")
echo "Pool address: $POOL_ADDRESS"
```

## Step 6: Update Pool Configuration

```bash
# Navigate back to your pool directory
cd /var/www/yenten-pool

# Update your config.json with AdventureCoin settings
# You'll need to add this section:
```

Add this to your `config/config.json`:

```json
{
  "adventurecoin": {
    "daemon_host": "localhost",
    "daemon_port": 9984,
    "daemon_user": "advc_rpc_user",
    "daemon_password": "YOUR_GENERATED_PASSWORD",
    "wallet_address": "YOUR_GENERATED_ADDRESS"
  },
  "pool": {
    "stratum_ports": [3333, 4444, 5555, 6666]
  }
}
```

## Step 7: Apply Database Migration

```bash
# Apply the database migration
mysql -u root -p yenten_pool < database/migrations/002_add_adventurecoin_support.sql
```

## Step 8: Restart Your Pool Server

```bash
# Restart your mining pool service
sudo systemctl restart your-pool-service

# Check if everything is working
sudo systemctl status adventurecoin
sudo systemctl status your-pool-service
```

## Step 9: Test the Setup

```bash
# Test RPC connection
sudo -u advc /usr/local/bin/adventurecoin-cli -conf=/home/advc/.adventurecoin/adventurecoin.conf -datadir=/home/advc/.adventurecoin getblockchaininfo

# Check if port 6666 is listening
sudo netstat -tlnp | grep 6666

# Test pool connection (from another machine)
# telnet your-vps-ip 6666
```

## Troubleshooting

If you encounter issues:

```bash
# Check AdventureCoin logs
sudo journalctl -u adventurecoin -f

# Check if daemon is synced
sudo -u advc /usr/local/bin/adventurecoin-cli -conf=/home/advc/.adventurecoin/adventurecoin.conf -datadir=/home/advc/.adventurecoin getblockchaininfo

# Check pool server logs
tail -f /var/www/yenten-pool/logs/stratum.log
```

## Alternative: Use a Different Coin

If AdventureCoin continues to have issues, you could consider adding a different coin that has better release management, such as:

- **Monero (XMR)** - XMRig algorithm
- **Litecoin (LTC)** - Scrypt algorithm  
- **Dogecoin (DOGE)** - Scrypt algorithm
- **Ravencoin (RVN)** - X16R algorithm

These coins typically have more reliable release processes and better documentation.

## Next Steps

Once AdventureCoin is running:

1. **Test Mining**: Connect a test miner to port 6666
2. **Monitor Performance**: Watch logs and statistics
3. **Scale as Needed**: Add more stratum servers if you get high miner count
4. **Backup Configuration**: Save your working configuration

Your mining pool should now support AdventureCoin alongside Yenten and Koto! 🚀
