#!/bin/bash

# Yenten Mining Pool Configuration Setup Script

echo "Setting up Yenten Mining Pool Configuration..."

# Check if config already exists
if [ -f "config/config.json" ]; then
    echo "Configuration file already exists: config/config.json"
    read -p "Do you want to overwrite it? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Configuration setup cancelled."
        exit 0
    fi
fi

# Copy template to config
if [ -f "config/config.template.json" ]; then
    cp config/config.template.json config/config.json
    echo "Configuration template copied to config/config.json"
else
    echo "Error: config/config.template.json not found!"
    exit 1
fi

# Get database password
echo ""
echo "Database Configuration:"
read -p "Enter database password for 'pool_user': " -s db_password
echo

# Get Yenten daemon password
echo ""
echo "Yenten Daemon Configuration:"
read -p "Enter Yenten daemon RPC password: " -s yenten_password
echo

# Get pool wallet address
echo ""
read -p "Enter pool wallet address: " wallet_address

# Update configuration file
echo ""
echo "Updating configuration file..."

# Use sed to replace passwords (works on most systems)
if command -v sed &> /dev/null; then
    sed -i "s/CHANGE_THIS_PASSWORD/$db_password/g" config/config.json
    sed -i "s/YOUR_POOL_WALLET_ADDRESS/$wallet_address/g" config/config.json
    
    # Update Yenten daemon password (this is trickier with sed)
    # We'll use a more robust approach with a temporary file
    python3 -c "
import json
import sys

try:
    with open('config/config.json', 'r') as f:
        config = json.load(f)
    
    config['yenten']['daemon_password'] = '$yenten_password'
    
    with open('config/config.json', 'w') as f:
        json.dump(config, f, indent=4)
    
    print('Configuration updated successfully!')
except Exception as e:
    print(f'Error updating configuration: {e}')
    sys.exit(1)
" 2>/dev/null || {
    echo "Python not available, please manually update the Yenten daemon password in config/config.json"
    echo "Set 'daemon_password' to: $yenten_password"
}

else
    echo "Please manually update the following in config/config.json:"
    echo "1. Set 'password' in database section to: $db_password"
    echo "2. Set 'daemon_password' in yenten section to: $yenten_password"
    echo "3. Set 'wallet_address' in yenten section to: $wallet_address"
fi

# Set proper permissions
chmod 600 config/config.json
chown www-data:www-data config/config.json

echo ""
echo "Configuration setup complete!"
echo "File: config/config.json"
echo "Permissions: 600 (read/write for owner only)"
echo ""
echo "Next steps:"
echo "1. Review the configuration: cat config/config.json"
echo "2. Set up the database: ./scripts/setup_database.sh"
echo "3. Start the stratum server: ./scripts/start_stratum.sh"
