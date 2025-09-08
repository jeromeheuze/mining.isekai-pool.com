#!/bin/bash

# Install Wrapper Scripts
# Installs the blockchain info wrapper script to system path

echo "Installing wrapper scripts..."

# Check if running as root or with sudo
if [ "$EUID" -ne 0 ]; then
    echo "This script needs to be run with sudo privileges"
    echo "Usage: sudo ./scripts/install-wrapper-scripts.sh"
    exit 1
fi

# Create /usr/local/bin directory if it doesn't exist
mkdir -p /usr/local/bin

# Copy the wrapper script
if [ -f "scripts/get-blockchain-info.sh" ]; then
    cp scripts/get-blockchain-info.sh /usr/local/bin/get-blockchain-info
    chmod +x /usr/local/bin/get-blockchain-info
    echo "✓ Installed get-blockchain-info wrapper script"
else
    echo "✗ get-blockchain-info.sh not found"
    exit 1
fi

# Copy the test script
if [ -f "scripts/test-daemon-connection.sh" ]; then
    cp scripts/test-daemon-connection.sh /usr/local/bin/test-daemon-connection
    chmod +x /usr/local/bin/test-daemon-connection
    echo "✓ Installed test-daemon-connection script"
else
    echo "✗ test-daemon-connection.sh not found"
    exit 1
fi

# Install dependencies if not present
echo ""
echo "Checking dependencies..."

# Check for jq
if ! command -v jq &> /dev/null; then
    echo "Installing jq..."
    if command -v apt-get &> /dev/null; then
        apt-get update && apt-get install -y jq
    elif command -v yum &> /dev/null; then
        yum install -y jq
    elif command -v dnf &> /dev/null; then
        dnf install -y jq
    else
        echo "Please install jq manually for your system"
    fi
else
    echo "✓ jq is already installed"
fi

# Check for curl
if ! command -v curl &> /dev/null; then
    echo "Installing curl..."
    if command -v apt-get &> /dev/null; then
        apt-get update && apt-get install -y curl
    elif command -v yum &> /dev/null; then
        yum install -y curl
    elif command -v dnf &> /dev/null; then
        dnf install -y curl
    else
        echo "Please install curl manually for your system"
    fi
else
    echo "✓ curl is already installed"
fi

echo ""
echo "Installation complete!"
echo ""
echo "You can now test daemon connections with:"
echo "  sudo test-daemon-connection"
echo ""
echo "The blockchain status API should now work properly."
