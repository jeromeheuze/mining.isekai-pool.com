# Yenten Mining Pool Development Guide
*DigitalOcean + Cursor Development Workflow*

## Table of Contents
1. [VPS Setup & Code Deployment](#vps-setup--code-deployment)
2. [Development Workflow](#development-workflow)
3. [Cursor Prompts Library](#cursor-prompts-library)
4. [Project Structure](#project-structure)
5. [Testing & Debugging](#testing--debugging)
6. [Security Checklist](#security-checklist)

---

## VPS Setup & Code Deployment

### Initial VPS Setup

**1. Connect to Your DigitalOcean Droplet:**
```bash
# SSH into your VPS (replace YOUR_IP with actual IP)
ssh root@YOUR_VPS_IP

# Update system
apt update && apt upgrade -y
```

**2. Install Required Software:**
```bash
# Install LAMP stack + extras
apt install -y apache2 mysql-server php8.1 php8.1-mysql php8.1-curl php8.1-json php8.1-mbstring php8.1-xml php8.1-zip php8.1-gd php8.1-cli

# Install Node.js and Redis
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
apt install -y nodejs redis-server git htop nano

# Install Composer (PHP package manager)
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

**3. Configure Web Server:**
```bash
# Enable Apache modules
a2enmod rewrite
a2enmod ssl

# Create project directory
mkdir -p /var/www/yenten-pool
chown www-data:www-data /var/www/yenten-pool

# Configure Apache virtual host
nano /etc/apache2/sites-available/yenten-pool.conf
```

**Apache Virtual Host Configuration:**
```apache
<VirtualHost *:80>
    ServerName pool.isekai-pool.com
    DocumentRoot /var/www/yenten-pool/public
    
    <Directory /var/www/yenten-pool/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/yenten-pool-error.log
    CustomLog ${APACHE_LOG_DIR}/yenten-pool-access.log combined
</VirtualHost>
```

```bash
# Enable the site
a2ensite yenten-pool.conf
a2dissite 000-default.conf
systemctl reload apache2
```

### Code Deployment Methods

#### **Method 1: Direct Git Deployment (Recommended)**

**1. Set up Git repository on VPS:**
```bash
# On your VPS, create a bare repository
mkdir -p /var/repos/yenten-pool.git
cd /var/repos/yenten-pool.git
git init --bare

# Create post-receive hook for automatic deployment
nano hooks/post-receive
```

**Post-receive hook script:**
```bash
#!/bin/bash
cd /var/www/yenten-pool
git --git-dir=/var/repos/yenten-pool.git --work-tree=/var/www/yenten-pool checkout -f
chown -R www-data:www-data /var/www/yenten-pool
systemctl reload apache2
echo "Deployment completed successfully!"
```

```bash
# Make hook executable
chmod +x hooks/post-receive
```

**2. On your local machine (where you're using Cursor):**
```bash
# Initialize local repository
mkdir yenten-pool
cd yenten-pool
git init

# Add VPS as remote
git remote add production root@YOUR_VPS_IP:/var/repos/yenten-pool.git

# Create initial files and push
git add .
git commit -m "Initial commit"
git push production main
```

#### **Method 2: SCP/RSYNC (Simple)**

**For quick file transfers:**
```bash
# From your local machine
scp -r ./yenten-pool/* root@YOUR_VPS_IP:/var/www/yenten-pool/

# Or using rsync (better for updates)
rsync -avz --delete ./yenten-pool/ root@YOUR_VPS_IP:/var/www/yenten-pool/
```

#### **Method 3: GitHub Integration**

**1. Set up GitHub repository and deploy via VPS:**
```bash
# On VPS
cd /var/www/yenten-pool
git clone https://github.com/yourusername/yenten-pool.git .

# For updates
git pull origin main
```

**2. Create deployment script:**
```bash
# Create deploy.sh on VPS
nano /root/deploy.sh
```

```bash
#!/bin/bash
cd /var/www/yenten-pool
git pull origin main
chown -R www-data:www-data /var/www/yenten-pool
systemctl reload apache2
echo "Deployment completed!"
```

```bash
chmod +x /root/deploy.sh
# Run deployment: ./deploy.sh
```

---

## Development Workflow

### Recommended Workflow

1. **Develop locally** using Cursor IDE
2. **Test components** individually 
3. **Commit changes** to Git
4. **Deploy to VPS** using chosen method
5. **Test on VPS** with real environment
6. **Iterate and improve**

### Local Development Setup

**1. Install local LAMP stack:**
- **Windows:** XAMPP or WAMP
- **Mac:** MAMP or Docker
- **Linux:** Native LAMP installation

**2. Cursor project structure:**
```
yenten-pool/
├── public/           # Web accessible files
├── src/             # PHP classes and logic
├── config/          # Configuration files
├── database/        # SQL schemas and migrations
├── scripts/         # Background scripts
├── templates/       # HTML templates
├── assets/          # CSS, JS, images
└── tests/           # Unit tests
```

---

## Cursor Prompts Library

### Phase 1: Environment & Database

**Database Schema Design:**
```
"Create a complete MySQL database schema for a Yenten mining pool with the following tables:
1. users (miner addresses, registration, settings)
2. workers (individual mining rigs per user)
3. shares (submitted mining shares with validation)
4. blocks (found blocks and confirmations)
5. payouts (payment history and pending)
6. pool_stats (historical statistics)
Include proper indexes, foreign keys, and data types for optimal performance."
```

**Configuration System:**
```
"Build a PHP configuration management system that:
- Loads settings from config.json file
- Supports different environments (development, production)
- Includes database credentials, pool settings, Yenten daemon connection
- Has validation for required settings
- Provides easy access throughout the application
- Includes error handling for missing configs"
```

### Phase 2: Core Mining Pool Logic

**Stratum Server Foundation:**
```
"Create a PHP socket server class for Yenten mining pool stratum protocol that:
- Listens on multiple ports (3333, 4444, 5555) for different difficulties
- Handles JSON-RPC communication with miners
- Manages client connections and authentication
- Implements proper error handling and logging
- Supports concurrent connections using socket_select()
- Includes methods for sending work and receiving shares"
```

**Share Validation Engine:**
```
"Build a PHP class for validating Yenten (YescryptR16) mining shares that:
- Verifies share difficulty meets requirements
- Validates proof-of-work using YescryptR16 algorithm
- Checks for duplicate shares and replay attacks
- Calculates share value and miner contribution
- Updates database with share statistics
- Handles both valid and stale shares appropriately"
```

**Work Manager:**
```
"Create a PHP work distribution system that:
- Connects to Yenten daemon via JSON-RPC
- Generates unique work for each miner
- Manages block templates and updates
- Handles difficulty adjustments per worker
- Tracks work distribution to prevent duplicates
- Implements work timeout and refresh logic"
```

### Phase 3: Payment & Rewards

**PPLNS Payout Calculator:**
```
"Implement a PHP PPLNS (Pay Per Last N Shares) reward system that:
- Calculates earnings based on last N shares when block is found
- Handles varying share difficulties fairly
- Manages pool fees and operator rewards
- Stores payout calculations in database
- Provides transparency reports for miners
- Includes variance protection and minimum payout thresholds"
```

**Automated Payment Processor:**
```
"Build an automated Yenten payout system that:
- Processes pending payments above minimum threshold
- Batches multiple payments into single transactions
- Integrates with Yenten daemon for sending coins
- Handles transaction confirmation tracking
- Manages failed payments and retries
- Includes manual override capabilities for admin
- Logs all payment activities with detailed records"
```

### Phase 4: Web Interface

**Real-time Dashboard:**
```
"Create a responsive mining pool dashboard using Bootstrap 5 that displays:
- Pool statistics (hashrate, miners, blocks found, efficiency)
- Recent blocks table with confirmations
- Top miners leaderboard
- Network statistics (difficulty, height, price)
- Live updating charts using Chart.js
- Mobile-friendly design with dark/light themes
- AJAX updates every 30 seconds without page refresh"
```

**Miner Statistics Page:**
```
"Build a comprehensive miner statistics interface that shows:
- Individual miner hashrate and worker status
- Earnings history and pending payments
- Share acceptance rate and efficiency
- Worker performance graphs over time
- Payout history with transaction links
- Configurable alerts and notifications
- Downloadable CSV reports"
```

**API Endpoints:**
```
"Create RESTful API endpoints for mining pool data:
- GET /api/pool/stats (pool hashrate, miners, last block)
- GET /api/miner/{address}/stats (individual miner data)
- GET /api/blocks (recent blocks with confirmations)
- GET /api/payments/{address} (payout history)
- GET /api/workers/{address} (worker status and performance)
Include rate limiting, CORS headers, and proper error responses"
```

### Phase 5: Administration & Monitoring

**Admin Panel:**
```
"Build an administrative interface for pool management that includes:
- Pool configuration management (fees, thresholds, ports)
- User and worker management
- Manual payout processing and overrides
- System monitoring and health checks
- Block and payment history review
- Database maintenance tools
- Security monitoring and alerts"
```

**Monitoring & Alerts:**
```
"Create a monitoring system that tracks:
- Pool server health and performance
- Yenten daemon connectivity
- Database performance and errors
- Unusual mining activity or potential attacks
- Block finding efficiency
- Payment processing status
- Email/SMS alerts for critical issues"
```

### Phase 6: Security & Optimization

**Security Hardening:**
```
"Implement comprehensive security measures:
- SQL injection prevention using prepared statements
- XSS protection with input sanitization
- CSRF tokens for form submissions
- Rate limiting for API endpoints
- DDoS protection and connection limits
- Secure session management
- Input validation for all user data
- Audit logging for administrative actions"
```

**Performance Optimization:**
```
"Optimize mining pool performance with:
- Database query optimization and indexing
- Redis caching for frequently accessed data
- Connection pooling for database and daemon
- Efficient share processing algorithms
- Background job processing for heavy tasks
- Memory usage optimization
- Code profiling and bottleneck identification"
```

---

## Project Structure

### Recommended Directory Layout

```
/var/www/yenten-pool/
├── public/                 # Web accessible directory
│   ├── index.php          # Main dashboard
│   ├── api/               # API endpoints
│   ├── assets/            # CSS, JS, images
│   └── .htaccess          # Apache rules
├── src/                   # PHP application code
│   ├── Classes/           # Core PHP classes
│   │   ├── StratumServer.php
│   │   ├── ShareValidator.php
│   │   ├── PayoutManager.php
│   │   └── YentenRPC.php
│   ├── Database/          # Database operations
│   ├── Utils/             # Utility functions
│   └── Config/            # Configuration management
├── config/                # Configuration files
│   ├── config.json        # Main configuration
│   ├── database.json      # Database settings
│   └── yenten.conf        # Yenten daemon config
├── database/              # Database schemas
│   ├── schema.sql         # Initial database schema
│   └── migrations/        # Database updates
├── scripts/               # Background scripts
│   ├── stratum_server.php # Main stratum server
│   ├── payout_processor.php
│   └── block_monitor.php
├── templates/             # HTML templates
│   ├── dashboard.php
│   ├── miner_stats.php
│   └── admin/
├── logs/                  # Application logs
└── vendor/                # Composer dependencies
```

### File Deployment Checklist

**Before deploying to VPS:**
- [ ] Test all code locally
- [ ] Check for syntax errors
- [ ] Validate database connections
- [ ] Review security measures
- [ ] Update configuration for production

**After deployment:**
- [ ] Check file permissions (`chown www-data:www-data`)
- [ ] Test web interface functionality
- [ ] Verify database connectivity
- [ ] Check Apache error logs
- [ ] Test API endpoints

---

## Testing & Debugging

### Local Testing

**1. PHP Syntax Check:**
```bash
# Check syntax before deployment
php -l filename.php
```

**2. Local Web Server:**
```bash
# PHP built-in server for testing
cd /path/to/yenten-pool/public
php -S localhost:8000
```

### VPS Debugging

**1. Check Apache Logs:**
```bash
# Error logs
tail -f /var/log/apache2/yenten-pool-error.log

# Access logs
tail -f /var/log/apache2/yenten-pool-access.log
```

**2. PHP Error Logging:**
```php
// Enable error reporting in development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

**3. Test Database Connection:**
```bash
# MySQL connection test
mysql -u username -p -h localhost database_name
```

### Performance Monitoring

**1. Server Monitoring:**
```bash
# Check system resources
htop
df -h
free -m

# Monitor network connections
netstat -tulpn | grep :3333
```

**2. Process Management:**
```bash
# Background script management
nohup php scripts/stratum_server.php > logs/stratum.log 2>&1 &

# Check running processes
ps aux | grep php
```

---

## Security Checklist

### Server Security

- [ ] **Firewall Configuration:**
```bash
# UFW firewall setup
ufw allow ssh
ufw allow 80
ufw allow 443
ufw allow 3333:5555/tcp  # Mining ports
ufw enable
```

- [ ] **SSH Hardening:**
```bash
# Disable root login, use key authentication
nano /etc/ssh/sshd_config
# Set: PermitRootLogin no
# Set: PasswordAuthentication no
```

- [ ] **Regular Updates:**
```bash
# Automated security updates
apt install unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

### Application Security

- [ ] **Input Validation:** All user inputs sanitized
- [ ] **SQL Injection Prevention:** Prepared statements only
- [ ] **XSS Protection:** Output encoding
- [ ] **CSRF Tokens:** For all forms
- [ ] **Rate Limiting:** API and mining connections
- [ ] **Secure Sessions:** HTTP-only, secure cookies
- [ ] **Error Handling:** No sensitive info in errors
- [ ] **File Permissions:** Proper ownership and permissions

### Monitoring & Alerts

- [ ] **Log Monitoring:** Regular log review
- [ ] **Uptime Monitoring:** External monitoring service
- [ ] **Performance Alerts:** CPU, memory, disk usage
- [ ] **Security Alerts:** Failed login attempts, unusual activity
- [ ] **Backup Strategy:** Regular database and code backups

---

## Quick Reference Commands

### Deployment Commands
```bash
# Quick deployment via git
git add . && git commit -m "Update" && git push production main

# Manual file sync
rsync -avz --delete ./ root@YOUR_VPS_IP:/var/www/yenten-pool/

# Restart services
systemctl reload apache2
systemctl restart mysql
```

### Maintenance Commands
```bash
# Check disk space
df -h

# Monitor processes
htop

# View recent logs
tail -f /var/log/apache2/error.log

# Database backup
mysqldump -u root -p yenten_pool > backup_$(date +%Y%m%d).sql
```

### Debugging Commands
```bash
# Test PHP syntax
find . -name "*.php" -exec php -l {} \;

# Check Apache configuration
apache2ctl configtest

# Test database connection
mysql -u pool_user -p yenten_pool
```

---

## Next Steps

1. **Set up your DigitalOcean VPS** using the commands above
2. **Choose your deployment method** (Git recommended)
3. **Start with Cursor** using the database schema prompt
4. **Build incrementally** - test each component
5. **Deploy frequently** to catch issues early

---

*Last updated: [Current Date]*
*Repository: [Your Git Repository URL]*