#!/bin/bash

# Yenten Mining Pool Database Setup Script
# Run this script on your VPS to set up the database

echo "Setting up Yenten Mining Pool Database..."

# Create database user
echo "Creating database user..."
mysql -u root -p -e "CREATE USER IF NOT EXISTS 'pool_user'@'localhost' IDENTIFIED BY 'secure_password_123';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON yenten_pool.* TO 'pool_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Test database connection
echo "Testing database connection..."
mysql -u pool_user -p'secure_password_123' yenten_pool -e "SHOW TABLES;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "Database setup completed successfully!"
    echo "You can now test the website at https://mining.isekai-pool.com"
else
    echo "Database setup failed. Please check the credentials and try again."
fi
