# UkkeyCoin Integration Guide

This guide explains how to add UkkeyCoin (UKY) support to your mining pool.

## Overview

UkkeyCoin uses the **YesPoWer** algorithm and has been integrated into your existing mining pool infrastructure. The integration includes:

- UkkeyCoin RPC client for daemon communication
- Stratum server support on port 6666
- Database schema updates for multi-coin support
- Configuration management for UkkeyCoin settings

## Prerequisites

1. **UkkeyCoin Core Wallet**: Download from [GitHub Releases](https://github.com/ukkeyHG/UkkeyCoin/releases/tag/0.13.2.0)
2. **System Requirements**: 
   - Linux server (Ubuntu 20.04+ recommended)
   - At least 2GB RAM
   - 20GB+ free disk space
   - Root/sudo access

## Quick Setup

### 1. Run the Setup Script

```bash
# Make the script executable
chmod +x scripts/setup_ukkeycoin.sh

# Run the setup script
sudo ./scripts/setup_ukkeycoin.sh
```

This script will:
- Create a dedicated UkkeyCoin user
- Download and install UkkeyCoin Core
- Configure the daemon with secure RPC settings
- Create a systemd service
- Generate a pool wallet address
- Update your pool configuration

### 2. Update Pool Configuration

The setup script automatically updates your `config/config.json` file. Verify the UkkeyCoin section:

```json
{
  "ukkeycoin": {
    "daemon_host": "localhost",
    "daemon_port": 9984,
    "daemon_user": "uky_rpc_user",
    "daemon_password": "generated_secure_password",
    "wallet_address": "your_generated_pool_address"
  }
}
```

### 3. Run Database Migration

```bash
# Apply the UkkeyCoin database migration
mysql -u root -p yenten_pool < database/migrations/002_add_ukkeycoin_support.sql
```

### 4. Restart Mining Pool

```bash
# Restart your mining pool server
sudo systemctl restart your-pool-service
```

## Manual Setup (Alternative)

If you prefer manual setup or the script doesn't work:

### 1. Install UkkeyCoin Core

```bash
# Download UkkeyCoin Core
wget https://github.com/ukkeyHG/UkkeyCoin/releases/download/0.13.2.0/UkkeyCoin-0.13.2.0-x86_64-linux-gnu.tar.gz

# Extract and install
tar -xzf UkkeyCoin-0.13.2.0-x86_64-linux-gnu.tar.gz
sudo cp UkkeyCoin-0.13.2.0/bin/* /usr/local/bin/
```

### 2. Create Configuration

Create `~/.ukkeycoin/ukkeycoin.conf`:

```ini
server=1
daemon=1
rpcuser=uky_rpc_user
rpcpassword=your_secure_password
rpcport=9984
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
txindex=1
```

### 3. Start Daemon

```bash
# Start UkkeyCoin daemon
ukkeycoind -daemon

# Create wallet
ukkeycoin-cli createwallet "pool_wallet"

# Get pool address
ukkeycoin-cli getnewaddress "pool"
```

## Mining Configuration

### For Miners

Miners can connect to your pool using:

```
stratum+tcp://your-pool-domain:6666
```

**Mining Software Configuration:**

1. **SRBMiner** (Recommended):
   ```
   SRBMiner-MULTI.exe --algorithm yespower --pool stratum+tcp://your-pool-domain:6666 --wallet YOUR_UKY_ADDRESS --password x
   ```

2. **Sugarmaker**:
   ```
   sugarmaker -o stratum+tcp://your-pool-domain:6666 -u YOUR_UKY_ADDRESS -p x -a yespower
   ```

3. **GUI-CPU Miner**:
   - Algorithm: YesPoWer
   - Pool: stratum+tcp://your-pool-domain:6666
   - Username: Your UkkeyCoin address
   - Password: x

### Pool Configuration

Your pool now supports multiple coins:

- **Port 3333**: Yenten (YescryptR16)
- **Port 4444**: Koto (YescryptR16)
- **Port 5555**: Yenten (YescryptR16) - Secondary
- **Port 6666**: UkkeyCoin (YesPoWer)

## Database Schema

The integration adds UkkeyCoin support to your existing database:

### New Tables/Columns

- `pool_work.coin` - Tracks which coin the work is for
- UkkeyCoin-specific configuration in `pool_config`
- Multi-coin statistics views

### Database Views

- `ukkeycoin_stats` - UkkeyCoin-specific statistics
- `multi_coin_pool_summary` - Overview of all supported coins
- Updated `miner_stats` - Includes preferred coin information

## Monitoring and Management

### Service Management

```bash
# Check UkkeyCoin daemon status
sudo systemctl status ukkeycoin

# View logs
sudo journalctl -u ukkeycoin -f

# Restart daemon
sudo systemctl restart ukkeycoin
```

### Pool Monitoring

```sql
-- Check UkkeyCoin statistics
SELECT * FROM ukkeycoin_stats;

-- View multi-coin summary
SELECT * FROM multi_coin_pool_summary;

-- Check UkkeyCoin workers
SELECT * FROM workers WHERE coin = 'ukkeycoin';
```

### RPC Commands

```bash
# Check blockchain info
ukkeycoin-cli getblockchaininfo

# Get mining info
ukkeycoin-cli getmininginfo

# Check balance
ukkeycoin-cli getbalance
```

## Troubleshooting

### Common Issues

1. **Daemon won't start**:
   - Check logs: `sudo journalctl -u ukkeycoin -f`
   - Verify configuration file permissions
   - Ensure port 9984 is not in use

2. **RPC connection failed**:
   - Verify RPC credentials in config
   - Check firewall settings
   - Ensure daemon is fully synced

3. **Pool not accepting connections**:
   - Verify port 6666 is open
   - Check pool server logs
   - Ensure UkkeyCoin RPC is working

4. **Database errors**:
   - Run the migration script again
   - Check database permissions
   - Verify MySQL is running

### Log Files

- UkkeyCoin daemon: `sudo journalctl -u ukkeycoin -f`
- Pool server: `logs/stratum.log`
- Work manager: `logs/work_manager.log`

## Security Considerations

1. **RPC Security**:
   - Use strong RPC passwords
   - Restrict RPC access to localhost
   - Regularly rotate credentials

2. **Firewall**:
   - Only open necessary ports (6666 for stratum)
   - Block direct RPC access from internet

3. **Wallet Security**:
   - Use dedicated pool wallet
   - Regular backups
   - Consider hardware wallet for large amounts

## Performance Optimization

1. **Database**:
   - Regular maintenance and optimization
   - Monitor query performance
   - Consider read replicas for high load

2. **Daemon**:
   - Adjust `dbcache` based on available RAM
   - Monitor disk I/O
   - Consider SSD storage for better performance

3. **Pool Server**:
   - Monitor memory usage
   - Adjust worker limits
   - Consider load balancing for high traffic

## Support and Community

- **UkkeyCoin Discord**: [Join Community](https://discord.com/invite/W7mwMudVf8)
- **GitHub Issues**: [UkkeyCoin Repository](https://github.com/ukkeyHG/UkkeyCoin)
- **Pool Support**: Contact your pool administrator

## Configuration Reference

### Complete UkkeyCoin Config

```json
{
  "ukkeycoin": {
    "daemon_host": "localhost",
    "daemon_port": 9984,
    "daemon_user": "uky_rpc_user",
    "daemon_password": "secure_password_here",
    "wallet_address": "your_pool_wallet_address"
  },
  "pool": {
    "stratum_ports": [3333, 4444, 5555, 6666]
  }
}
```

### Daemon Configuration

```ini
# UkkeyCoin daemon configuration
server=1
daemon=1
listen=1
maxconnections=100
rpcuser=uky_rpc_user
rpcpassword=secure_password
rpcport=9984
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
txindex=1
addressindex=1
timestampindex=1
spentindex=1
dbcache=256
maxmempool=64
```

## Next Steps

1. **Test the Integration**: Connect a test miner to verify everything works
2. **Monitor Performance**: Watch logs and statistics for any issues
3. **Scale as Needed**: Add more stratum servers if you get high miner count
4. **Backup Configuration**: Save your working configuration
5. **Update Documentation**: Keep this guide updated with any customizations

Your mining pool now supports UkkeyCoin alongside Yenten and Koto! 🎉

## Mining Pool Stats

According to [MiningPoolStats](https://miningpoolstats.stream/ukkeycoin), UkkeyCoin is actively mined with:
- **Algorithm**: YesPoWer
- **Active Pools**: Multiple pools available
- **Network Hashrate**: Available on the stats page
- **Block Time**: Varies based on network difficulty

This makes UkkeyCoin a good choice for your mining pool as it has an active mining community and reliable infrastructure.
