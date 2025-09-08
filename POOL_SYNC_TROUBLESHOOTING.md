# Pool Sync Troubleshooting Guide

## Problem: Pool Not Syncing While Desktop Wallets Are Fine

If your desktop wallets are synced but the mining pool is not, here are the steps to diagnose and fix the issue.

## Quick Diagnosis

### Step 1: Install Required Scripts
```bash
# Run this on your VPS as root/sudo
sudo ./scripts/install-wrapper-scripts.sh
```

### Step 2: Test Daemon Connections
```bash
# Test all daemon connections
sudo test-daemon-connection

# Or test with PHP directly
php scripts/test-rpc-connections.php
```

### Step 3: Check Pool Status
```bash
# Check if stratum server is running
ps aux | grep stratum_server.php

# Check stratum server logs
tail -f /var/www/yenten-pool/logs/stratum_output.log
```

## Common Issues and Solutions

### Issue 1: Missing Wrapper Script
**Symptoms:** API returns "Failed to connect to daemon" errors
**Solution:** 
```bash
sudo ./scripts/install-wrapper-scripts.sh
```

### Issue 2: Wrong RPC Port
**Symptoms:** Connection refused errors
**Check:** Your config shows Yenten on port 9982, but template shows 9981
**Solution:** Verify your daemon is actually running on the configured port:
```bash
# Check what's running on Yenten ports
netstat -tlnp | grep 998
```

### Issue 3: RPC Authentication Issues
**Symptoms:** "RPC Error" or authentication failures
**Solution:** Verify RPC credentials in your daemon config files:
```bash
# Check Yenten daemon config
cat ~/.yenten/yenten.conf

# Look for these lines:
# rpcuser=yenten_rpc_user
# rpcpassword=4rlcawahlfrovIchEtrlcre0huWakephl0
# rpcport=9982
# rpcallowip=127.0.0.1
```

### Issue 4: Daemon Not Fully Synced
**Symptoms:** Pool shows "not synced" status
**Solution:** Wait for daemon to sync or restart it:
```bash
# Check daemon sync status
yenten-cli getblockchaininfo

# If stuck, restart daemon
sudo systemctl restart yentend
# or
yenten-cli stop
yentend -daemon
```

### Issue 5: Firewall Blocking RPC
**Symptoms:** Connection timeouts
**Solution:** Ensure RPC ports are accessible locally:
```bash
# Test local connection
curl -X POST -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":"1","method":"getblockchaininfo","params":[]}' \
  http://127.0.0.1:9982
```

## Pool Configuration Issues

### Check Your Current Config
Your current config shows:
- Yenten daemon: localhost:9982
- RPC user: yenten_rpc_user
- RPC password: 4rlcawahlfrovIchEtrlcre0huWakephl0

### Verify Daemon Configuration
Your Yenten daemon should have these settings in `~/.yenten/yenten.conf`:
```
rpcuser=yenten_rpc_user
rpcpassword=4rlcawahlfrovIchEtrlcre0huWakephl0
rpcport=9982
rpcallowip=127.0.0.1
server=1
daemon=1
```

## Restart Pool Services

If you make configuration changes:

```bash
# Stop stratum server
sudo ./scripts/stop_stratum.sh

# Start stratum server
sudo ./scripts/start_stratum.sh
```

## Monitoring Commands

### Check Pool Status
```bash
# View real-time logs
tail -f /var/www/yenten-pool/logs/stratum_output.log

# Check if miners are connecting
netstat -an | grep :3333
```

### Check Daemon Status
```bash
# Yenten daemon status
yenten-cli getblockchaininfo
yenten-cli getmininginfo

# Check if daemon is running
ps aux | grep yentend
```

## API Testing

Test the blockchain status API:
```bash
# Test Yenten status
curl "https://mining.isekai-pool.com/api/blockchain-status.php?coin=yenten"

# Test Koto status  
curl "https://mining.isekai-pool.com/api/blockchain-status.php?coin=koto"

# Test UkkeyCoin status
curl "https://mining.isekai-pool.com/api/blockchain-status.php?coin=ukkeycoin"
```

## Next Steps

1. Run the installation script: `sudo ./scripts/install-wrapper-scripts.sh`
2. Test connections: `sudo test-daemon-connection`
3. Check daemon sync status
4. Restart pool services if needed
5. Monitor logs for any remaining issues

If problems persist, check the specific error messages in the logs and verify your daemon configuration matches the pool configuration.
