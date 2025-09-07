# Yenten Mining Pool Setup Guide

This guide will help you set up the Yenten Mining Pool on your server.

## Prerequisites

- Ubuntu 22.04 LTS (or similar Linux distribution)
- Root or sudo access
- Domain name pointing to your server
- Yenten daemon running and synced

## Quick Setup

### 1. Clone and Deploy

```bash
# Clone the repository
git clone https://github.com/jeromeheuze/mining.isekai-pool.com.git
cd mining.isekai-pool.com

# Deploy to web directory
sudo cp -r * /var/www/yenten-pool/
cd /var/www/yenten-pool
```

### 2. Configure the Pool

```bash
# Set up configuration
chmod +x scripts/setup_config.sh
./scripts/setup_config.sh
```

This will:
- Copy the configuration template
- Prompt for database password
- Prompt for Yenten daemon RPC password
- Prompt for pool wallet address
- Set proper file permissions

### 3. Set Up Database

```bash
# Create database and user
chmod +x scripts/setup_database.sh
./scripts/setup_database.sh

# Import the database schema
mysql -u root -p yenten_pool < database/schema.sql

# Import additional migrations
mysql -u root -p yenten_pool < database/migrations/001_add_pool_work_table.sql
```

### 4. Start the Stratum Server

```bash
# Start the stratum server
chmod +x scripts/start_stratum.sh
./scripts/start_stratum.sh
```

### 5. Verify Setup

```bash
# Check if stratum server is running
ps aux | grep stratum_server.php

# Check logs
tail -f logs/stratum.log

# Test the web interface
curl https://your-domain.com
```

## Configuration

### Database Configuration

The pool uses MySQL/MariaDB. Make sure you have:

- Database: `yenten_pool`
- User: `pool_user`
- Password: (set during setup)

### Yenten Daemon Configuration

Your Yenten daemon should be configured with:

```conf
# yenten.conf
rpcuser=your_rpc_user
rpcpassword=your_rpc_password
rpcport=9981
rpcallowip=127.0.0.1
server=1
daemon=1
```

### Pool Configuration

Edit `config/config.json` to customize:

- Pool name and URL
- Fee percentage
- Minimum payout amounts
- Stratum ports
- Difficulty settings

## Mining Configuration

### For Miners

**Stratum Server:** `your-domain.com`
**Ports:**
- Port 3333 - Low difficulty
- Port 4444 - Medium difficulty  
- Port 5555 - High difficulty

**Username:** Your Yenten wallet address
**Password:** x (or any password)

### Example Mining Commands

**ccminer:**
```bash
ccminer -a yescryptr16 -o stratum+tcp://your-domain.com:3333 -u YOUR_YTN_ADDRESS -p x
```

**cpuminer:**
```bash
./cpuminer -a yescryptr16 -o stratum+tcp://your-domain.com:3333 -u YOUR_YTN_ADDRESS -p x
```

## Management Commands

### Start/Stop Stratum Server

```bash
# Start server
./scripts/start_stratum.sh

# Stop server
./scripts/stop_stratum.sh

# Check status
ps aux | grep stratum_server.php
```

### View Logs

```bash
# Stratum server logs
tail -f logs/stratum.log

# Work manager logs
tail -f logs/work_manager.log

# Web server logs
tail -f /var/log/apache2/yenten-pool-error.log
```

### Database Management

```bash
# Connect to database
mysql -u pool_user -p yenten_pool

# View pool statistics
mysql -u pool_user -p yenten_pool -e "SELECT * FROM pool_summary;"

# View active miners
mysql -u pool_user -p yenten_pool -e "SELECT * FROM miner_stats;"
```

## Security Considerations

1. **Firewall:** Only open necessary ports (80, 443, 3333-5555)
2. **SSL:** Use HTTPS for the web interface
3. **Database:** Use strong passwords and limit access
4. **Updates:** Keep the system and dependencies updated
5. **Monitoring:** Monitor logs for suspicious activity

## Troubleshooting

### Common Issues

**Stratum server won't start:**
- Check if ports are available: `netstat -tulpn | grep :3333`
- Check logs: `tail -f logs/stratum.log`
- Verify database connection

**Miners can't connect:**
- Check firewall settings
- Verify stratum server is running
- Check network connectivity

**Database errors:**
- Verify database credentials in config
- Check if database exists and is accessible
- Review database logs

### Getting Help

1. Check the logs first
2. Verify configuration settings
3. Test database connectivity
4. Check network and firewall settings

## API Endpoints

The pool provides RESTful API endpoints:

- `GET /api/pool/stats` - Pool statistics
- `GET /api/miner/{address}/stats` - Miner statistics
- `GET /api/blocks` - Recent blocks
- `GET /api/payments/{address}` - Payment history

## Development

### Project Structure

```
yenten-pool/
├── public/           # Web interface
├── src/             # PHP classes
├── config/          # Configuration files
├── database/        # Database schemas
├── scripts/         # Management scripts
└── logs/            # Log files
```

### Adding Features

1. Create new classes in `src/Classes/`
2. Add database migrations in `database/migrations/`
3. Update API endpoints in `public/api/`
4. Add management scripts in `scripts/`

## License

This project is licensed under the MIT License.
