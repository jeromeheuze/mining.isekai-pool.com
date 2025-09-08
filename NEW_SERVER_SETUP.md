# Mining Pool - New Server Setup Guide

## Overview
This guide will help you set up a fresh mining pool server with support for Yenten, KOTO, and UkkeyCoin.

## Prerequisites
- Ubuntu 20.04+ VPS
- Root access
- At least 2GB RAM
- 20GB+ storage

## Step 1: Server Preparation

```bash
# Update system
apt update && apt upgrade -y

# Install required packages
apt install -y apache2 mysql-server php php-mysql php-curl php-json php-cli git curl jq ufw

# Enable Apache and MySQL
systemctl enable apache2 mysql

# Configure firewall
ufw allow 22
ufw allow 80
ufw allow 443
ufw allow 3333
ufw allow 4444
ufw allow 5555
ufw allow 6666
ufw --force enable
```

## Step 2: Clone Repository

```bash
cd /var/www
git clone https://github.com/jeromeheuze/mining.isekai-pool.com.git yenten-pool
cd yenten-pool
chown -R www-data:www-data /var/www/yenten-pool
```

## Step 3: Database Setup

```bash
# Secure MySQL installation
mysql_secure_installation

# Create database and user
mysql -u root -p
```

```sql
CREATE DATABASE yenten_pool;
CREATE USER 'pool_user'@'localhost' IDENTIFIED BY 'D|Hm3"K12<Zv';
GRANT ALL PRIVILEGES ON yenten_pool.* TO 'pool_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Import database schema
mysql -u pool_user -p yenten_pool < database/schema.sql
mysql -u pool_user -p yenten_pool < database/migrations/001_add_pool_work_table.sql
mysql -u pool_user -p yenten_pool < database/migrations/002_add_ukkeycoin_support.sql
```

## Step 4: Configuration

```bash
# Copy and configure config file
cp config/config.template.json config/config.json

# Edit config with your settings
nano config/config.json
```

## Step 5: Install Cryptocurrency Daemons

### Yenten
```bash
# Download and install Yenten
cd /tmp
wget https://github.com/yentencoin/yenten/releases/download/v0.16.3.0/yenten-0.16.3.0-linux.tar.gz
tar -xzf yenten-0.16.3.0-linux.tar.gz
sudo cp yenten-0.16.3.0/bin/* /usr/local/bin/
```

### KOTO
```bash
# Download and install KOTO
wget https://github.com/KotoDevelopers/koto/releases/download/v0.8.1.0/koto-0.8.1.0-linux.tar.gz
tar -xzf koto-0.8.1.0-linux.tar.gz
sudo cp koto-0.8.1.0/bin/* /usr/local/bin/
```

### UkkeyCoin
```bash
# Download and install UkkeyCoin
wget https://github.com/ukkeyHG/UkkeyCoin/releases/download/0.13.2.0/ukkeycoin-0.13.2.0-linux.tar.gz
tar -xzf ukkeycoin-0.13.2.0-linux.tar.gz
sudo cp ukkeycoin-0.13.2.0/bin/* /usr/local/bin/
```

## Step 6: Configure Daemons

### Yenten Configuration
```bash
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
```

### KOTO Configuration
```bash
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
```

### UkkeyCoin Configuration
```bash
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
```

## Step 7: Start Daemons

```bash
# Start all daemons
yentend -daemon
kotod -daemon
ukkeyd -conf=/root/.ukkeycoin/ukkeycoin.conf -datadir=/root/.ukkeycoin -daemon

# Check status
ps aux | grep -E "(yentend|kotod|ukkeyd)"
```

## Step 8: Create Wallets and Get Addresses

```bash
# Yenten wallet
yenten-cli getnewaddress "pool_wallet"

# KOTO wallet
koto-cli getnewaddress "pool_wallet"

# UkkeyCoin wallet
ukkey-cli -conf=/root/.ukkeycoin/ukkeycoin.conf -datadir=/root/.ukkeycoin getnewaddress "pool_wallet"
```

## Step 9: Update Configuration

Update `config/config.json` with the wallet addresses you just created.

## Step 10: Web Server Setup

```bash
# Create symlink for web access
ln -sf /var/www/yenten-pool/public /var/www/html/yenten-pool

# Set proper permissions
chown -R www-data:www-data /var/www/yenten-pool/public/
chmod -R 755 /var/www/yenten-pool/public/

# Restart Apache
systemctl restart apache2
```

## Step 11: Start Stratum Server

```bash
cd /var/www/yenten-pool
chmod +x scripts/*.sh
./scripts/start_stratum.sh

# Check if running
ps aux | grep stratum
netstat -tlnp | grep -E "(3333|4444|5555|6666)"
```

## Step 12: Verify Setup

```bash
# Test API endpoints
curl -k "https://your-domain.com/api/blockchain-status.php?coin=yenten"
curl -k "https://your-domain.com/api/blockchain-status.php?coin=koto"
curl -k "https://your-domain.com/api/blockchain-status.php?coin=ukkeycoin"

# Check web interface
curl -k "https://your-domain.com/"
```

## Step 13: Create Systemd Services (Optional)

Create systemd services for automatic startup:

```bash
# Copy service files
cp scripts/yenten-stratum.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable yenten-stratum
systemctl start yenten-stratum
```

## Troubleshooting

### Check Daemon Status
```bash
# Check if daemons are running
ps aux | grep -E "(yentend|kotod|ukkeyd)"

# Check daemon logs
tail -f ~/.yenten/debug.log
tail -f ~/.koto/debug.log
tail -f ~/.ukkeycoin/debug.log
```

### Check Stratum Server
```bash
# Check stratum server logs
tail -f /var/www/yenten-pool/logs/stratum.log

# Check if ports are listening
netstat -tlnp | grep -E "(3333|4444|5555|6666)"
```

### Check Web Server
```bash
# Check Apache logs
tail -f /var/log/apache2/error.log

# Check file permissions
ls -la /var/www/yenten-pool/public/
```

## Security Notes

1. Change all default passwords
2. Use strong RPC passwords
3. Keep system updated
4. Monitor logs regularly
5. Use HTTPS in production

## Support

If you encounter issues:
1. Check the logs first
2. Verify all daemons are running
3. Check network connectivity
4. Verify configuration files
5. Check file permissions

## Next Steps

After successful setup:
1. Monitor daemon sync progress
2. Test mining connections
3. Configure monitoring
4. Set up automated payouts
5. Implement additional features
