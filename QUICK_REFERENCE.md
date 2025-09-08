# Mining Pool - Quick Reference Guide

## Essential Commands

### Daemon Management
```bash
# Start daemons
yentend -daemon
kotod -daemon
ukkeyd -conf=/root/.ukkeycoin/ukkeycoin.conf -datadir=/root/.ukkeycoin -daemon

# Check daemon status
ps aux | grep -E "(yentend|kotod|ukkeyd)"

# Check daemon logs
tail -f ~/.yenten/debug.log
tail -f ~/.koto/debug.log
tail -f ~/.ukkeycoin/debug.log

# Get blockchain info
yenten-cli getblockchaininfo
koto-cli getblockchaininfo
ukkey-cli -conf=/root/.ukkeycoin/ukkeycoin.conf -datadir=/root/.ukkeycoin getblockchaininfo
```

### Stratum Server Management
```bash
# Start stratum server
cd /var/www/yenten-pool
./scripts/start_stratum.sh

# Stop stratum server
./scripts/stop_stratum.sh

# Check stratum server status
ps aux | grep stratum
netstat -tlnp | grep -E "(3333|4444|5555|6666)"

# View stratum logs
tail -f logs/stratum.log
```

### Web Server Management
```bash
# Restart Apache
systemctl restart apache2

# Check Apache status
systemctl status apache2

# View Apache logs
tail -f /var/log/apache2/error.log
```

### Database Management
```bash
# Connect to database
mysql -u pool_user -p yenten_pool

# Check database status
mysql -u pool_user -p -e "SHOW TABLES;" yenten_pool
```

## API Endpoints

### Blockchain Status
```bash
curl -k "https://your-domain.com/api/blockchain-status.php?coin=yenten"
curl -k "https://your-domain.com/api/blockchain-status.php?coin=koto"
curl -k "https://your-domain.com/api/blockchain-status.php?coin=ukkeycoin"
```

### Pool Statistics
```bash
curl -k "https://your-domain.com/api/pool-stats.php"
```

### Miner Information
```bash
curl -k "https://your-domain.com/api/miners.php"
```

### Block Information
```bash
curl -k "https://your-domain.com/api/blocks.php"
```

## Configuration Files

### Main Configuration
- `/var/www/yenten-pool/config/config.json`

### Daemon Configurations
- `~/.yenten/yenten.conf`
- `~/.koto/koto.conf`
- `~/.ukkeycoin/ukkeycoin.conf`

### Web Server
- `/etc/apache2/sites-available/000-default.conf`

## Ports

- **22**: SSH
- **80**: HTTP
- **443**: HTTPS
- **3333**: Yenten Stratum
- **4444**: KOTO Stratum
- **5555**: Additional Stratum
- **6666**: UkkeyCoin Stratum
- **9982**: Yenten RPC
- **8432**: KOTO RPC
- **9985**: UkkeyCoin RPC

## Log Files

### Daemon Logs
- `~/.yenten/debug.log`
- `~/.koto/debug.log`
- `~/.ukkeycoin/debug.log`

### Pool Logs
- `/var/www/yenten-pool/logs/stratum.log`
- `/var/www/yenten-pool/logs/stratum_output.log`

### System Logs
- `/var/log/apache2/error.log`
- `/var/log/mysql/error.log`

## Troubleshooting

### Common Issues

1. **Daemon not responding**
   ```bash
   # Check if daemon is running
   ps aux | grep daemon_name
   
   # Check daemon logs
   tail -f ~/.daemon_name/debug.log
   
   # Restart daemon
   pkill -f daemon_name
   daemon_name -daemon
   ```

2. **Stratum server not starting**
   ```bash
   # Check stratum logs
   tail -f /var/www/yenten-pool/logs/stratum.log
   
   # Check file permissions
   ls -la /var/www/yenten-pool/scripts/
   
   # Restart stratum server
   ./scripts/stop_stratum.sh
   ./scripts/start_stratum.sh
   ```

3. **Web interface not accessible**
   ```bash
   # Check Apache status
   systemctl status apache2
   
   # Check file permissions
   ls -la /var/www/yenten-pool/public/
   
   # Check symlink
   ls -la /var/www/html/yenten-pool
   ```

4. **Database connection issues**
   ```bash
   # Test database connection
   mysql -u pool_user -p yenten_pool
   
   # Check MySQL status
   systemctl status mysql
   ```

### Performance Monitoring

```bash
# Check system resources
htop
df -h
free -h

# Check network connections
netstat -tlnp
ss -tlnp

# Check process status
ps aux | grep -E "(yentend|kotod|ukkeyd|stratum)"
```

## Security Checklist

- [ ] Change default passwords
- [ ] Use strong RPC passwords
- [ ] Keep system updated
- [ ] Monitor logs regularly
- [ ] Use HTTPS in production
- [ ] Configure firewall properly
- [ ] Regular backups
- [ ] Monitor resource usage

## Backup Commands

```bash
# Backup database
mysqldump -u pool_user -p yenten_pool > backup_$(date +%Y%m%d).sql

# Backup configuration
tar -czf config_backup_$(date +%Y%m%d).tar.gz /var/www/yenten-pool/config/

# Backup wallet files
tar -czf wallet_backup_$(date +%Y%m%d).tar.gz ~/.yenten/ ~/.koto/ ~/.ukkeycoin/
```

## Maintenance

### Daily
- Check daemon status
- Monitor logs for errors
- Check system resources

### Weekly
- Update system packages
- Check disk space
- Review security logs

### Monthly
- Backup database and configs
- Review performance metrics
- Update documentation
