#!/bin/bash

# Blockchain Info Wrapper Script
# Safely gets blockchain information from daemons

COIN=$1

if [ -z "$COIN" ]; then
    echo '{"error": "Coin parameter required"}'
    exit 1
fi

# Load configuration
CONFIG_FILE="/var/www/yenten-pool/config/config.json"
if [ ! -f "$CONFIG_FILE" ]; then
    echo '{"error": "Configuration file not found"}'
    exit 1
fi

# Get daemon configuration based on coin
case $COIN in
    "yenten")
        HOST=$(jq -r '.yenten.daemon_host' "$CONFIG_FILE")
        PORT=$(jq -r '.yenten.daemon_port' "$CONFIG_FILE")
        USER=$(jq -r '.yenten.daemon_user' "$CONFIG_FILE")
        PASS=$(jq -r '.yenten.daemon_password' "$CONFIG_FILE")
        ;;
    "koto")
        HOST=$(jq -r '.koto.daemon_host' "$CONFIG_FILE")
        PORT=$(jq -r '.koto.daemon_port' "$CONFIG_FILE")
        USER=$(jq -r '.koto.daemon_user' "$CONFIG_FILE")
        PASS=$(jq -r '.koto.daemon_password' "$CONFIG_FILE")
        ;;
    "ukkeycoin")
        HOST=$(jq -r '.ukkeycoin.daemon_host' "$CONFIG_FILE")
        PORT=$(jq -r '.ukkeycoin.daemon_port' "$CONFIG_FILE")
        USER=$(jq -r '.ukkeycoin.daemon_user' "$CONFIG_FILE")
        PASS=$(jq -r '.ukkeycoin.daemon_password' "$CONFIG_FILE")
        ;;
    *)
        echo '{"error": "Invalid coin parameter"}'
        exit 1
        ;;
esac

# Check if jq is available
if ! command -v jq &> /dev/null; then
    echo '{"error": "jq command not found"}'
    exit 1
fi

# Check if curl is available
if ! command -v curl &> /dev/null; then
    echo '{"error": "curl command not found"}'
    exit 1
fi

# Make RPC call to get blockchain info
RPC_URL="http://$HOST:$PORT"
AUTH_HEADER=$(echo -n "$USER:$PASS" | base64)

RESPONSE=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -H "Authorization: Basic $AUTH_HEADER" \
    -d '{"jsonrpc":"2.0","id":"1","method":"getblockchaininfo","params":[]}' \
    "$RPC_URL" 2>/dev/null)

if [ $? -ne 0 ] || [ -z "$RESPONSE" ]; then
    echo '{"error": "Failed to connect to daemon"}'
    exit 1
fi

# Extract result from JSON-RPC response
RESULT=$(echo "$RESPONSE" | jq -r '.result // empty')

if [ -z "$RESULT" ] || [ "$RESULT" = "null" ]; then
    ERROR=$(echo "$RESPONSE" | jq -r '.error.message // "Unknown error"')
    echo "{\"error\": \"$ERROR\"}"
    exit 1
fi

echo "$RESULT"
