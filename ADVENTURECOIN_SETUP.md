# AdventureCoin Integration Guide

This guide explains how to add AdventureCoin (ADVC) support to your mining pool.

## Overview

AdventureCoin uses the **YesPowerADVC** algorithm and has been integrated into your existing mining pool infrastructure. The integration includes:

- AdventureCoin RPC client for daemon communication
- Stratum server support on port 6666
- Database schema updates for multi-coin support
- Configuration management for AdventureCoin settings

## Prerequisites

1. **AdventureCoin Core Wallet**: Download from [GitHub Releases](https://github.com/AdventureCoin-ADVC/AdventureCoin/releases/tag/5.0.0.2)
2. **System Requirements**: 
   - Linux server (Ubuntu 20.04+ recommended)
   - At least 2GB RAM
   - 20GB+ free disk space
   - Root/sudo access

## Quick Setup

### 1. Run the Setup Script

```bash
# Make the script executable
chmod +x scripts/setup_adventurecoin.sh

# Run the setup script
./scripts/setup_adventurecoin.sh
```

This script will:
- Create a dedicated AdventureCoin user
- Download and install AdventureCoin Core
- Configure the daemon with secure RPC settings
- Create a systemd service
- Generate a pool wallet address
- Update your pool configuration

### 2. Update Pool Configuration

The setup script automatically updates your `config/config.json` file. Verify the AdventureCoin section:

```json
{
  "adventurecoin": {
    "daemon_host": "localhost",
    "daemon_port": 9984,
    "daemon_user": "advc_rpc_user",
    "daemon_password": "generated_secure_password",
    "wallet_address": "your_generated_pool_address"
  }
}
```

### 3. Run Database Migration

```bash
# Apply the AdventureCoin database migration
mysql -u root -p yenten_pool < database/migrations/002_add_adventurecoin_support.sql
```

### 4. Restart Mining Pool

```bash
# Restart your mining pool server
sudo systemctl restart your-pool-service
```

## Manual Setup (Alternative)

If you prefer manual setup or the script doesn't work:

### 1. Install AdventureCoin Core

```bash
# Download AdventureCoin Core
wget https://github.com/AdventureCoin-ADVC/AdventureCoin/releases/download/5.0.0.2/AdventureCoin-5.0.0.2-x86_64-linux-gnu.tar.gz

# Extract and install
tar -xzf AdventureCoin-5.0.0.2-x86_64-linux-gnu.tar.gz
sudo cp AdventureCoin-5.0.0.2/bin/* /usr/local/bin/
```

### 2. Create Configuration

Create `~/.adventurecoin/adventurecoin.conf`:

```ini
server=1
daemon=1
rpcuser=advc_rpc_user
rpcpassword=your_secure_password
rpcport=9984
rpcallowip=127.0.0.1
rpcbind=127.0.0.1
txindex=1
```

### 3. Start Daemon

```bash
# Start AdventureCoin daemon
adventurecoind -daemon

# Create wallet
adventurecoin-cli createwallet "pool_wallet"

# Get pool address
adventurecoin-cli getnewaddress "pool"
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
   SRBMiner-MULTI.exe --algorithm yespoweradvc --pool stratum+tcp://your-pool-domain:6666 --wallet YOUR_ADVC_ADDRESS --password x
   ```

2. **Sugarmaker**:
   ```
   sugarmaker -o stratum+tcp://your-pool-domain:6666 -u YOUR_ADVC_ADDRESS -p x -a yespoweradvc
   ```

3. **GUI-CPU Miner**:
   - Algorithm: YesPowerADVC
   - Pool: stratum+tcp://your-pool-domain:6666
   - Username: Your AdventureCoin address
   - Password: x

### Pool Configuration

Your pool now supports multiple coins:

- **Port 3333**: Yenten (YescryptR16)
- **Port 4444**: Koto (YescryptR16)
- **Port 5555**: Yenten (YescryptR16) - Secondary
- **Port 6666**: AdventureCoin (YesPowerADVC)

## Database Schema

The integration adds AdventureCoin support to your existing database:

### New Tables/Columns

- `pool_work.coin` - Tracks which coin the work is for
- AdventureCoin-specific configuration in `pool_config`
- Multi-coin statistics views

### Database Views

- `adventurecoin_stats` - AdventureCoin-specific statistics
- `multi_coin_pool_summary` - Overview of all supported coins
- Updated `miner_stats` - Includes preferred coin information

## Monitoring and Management

### Service Management

```bash
# Check AdventureCoin daemon status
sudo systemctl status adventurecoin

# View logs
sudo journalctl -u adventurecoin -f

# Restart daemon
sudo systemctl restart adventurecoin
```

### Pool Monitoring

```sql
-- Check AdventureCoin statistics
SELECT * FROM adventurecoin_stats;

-- View multi-coin summary
SELECT * FROM multi_coin_pool_summary;

-- Check AdventureCoin workers
SELECT * FROM workers WHERE coin = 'adventurecoin';
```

### RPC Commands

```bash
# Check blockchain info
adventurecoin-cli getblockchaininfo

# Get mining info
adventurecoin-cli getmininginfo

# Check balance
adventurecoin-cli getbalance
```

## Troubleshooting

### Common Issues

1. **Daemon won't start**:
   - Check logs: `sudo journalctl -u adventurecoin -f`
   - Verify configuration file permissions
   - Ensure port 9984 is not in use

2. **RPC connection failed**:
   - Verify RPC credentials in config
   - Check firewall settings
   - Ensure daemon is fully synced

3. **Pool not accepting connections**:
   - Verify port 6666 is open
   - Check pool server logs
   - Ensure AdventureCoin RPC is working

4. **Database errors**:
   - Run the migration script again
   - Check database permissions
   - Verify MySQL is running

### Log Files

- AdventureCoin daemon: `sudo journalctl -u adventurecoin -f`
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

- **AdventureCoin Discord**: [Join Community](https://discord.com/invite/your-invite-link)
- **GitHub Issues**: [AdventureCoin Repository](https://github.com/AdventureCoin-ADVC/AdventureCoin)
- **Pool Support**: Contact your pool administrator

## Configuration Reference

### Complete AdventureCoin Config

```json
{
  "adventurecoin": {
    "daemon_host": "localhost",
    "daemon_port": 9984,
    "daemon_user": "advc_rpc_user",
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
# AdventureCoin daemon configuration
server=1
daemon=1
listen=1
maxconnections=100
rpcuser=advc_rpc_user
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

Your mining pool now supports AdventureCoin alongside Yenten and Koto! 🎉
