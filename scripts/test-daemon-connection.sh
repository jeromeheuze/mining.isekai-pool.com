#!/bin/bash

# Test Daemon Connection Script
# Tests connectivity to all configured daemons

echo "Testing daemon connections..."
echo "================================"

CONFIG_FILE="/var/www/yenten-pool/config/config.json"

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Error: Configuration file not found at $CONFIG_FILE"
    exit 1
fi

# Test each coin
for COIN in yenten koto ukkeycoin; do
    echo ""
    echo "Testing $COIN daemon..."
    
    # Get daemon configuration
    HOST=$(jq -r ".$COIN.daemon_host" "$CONFIG_FILE")
    PORT=$(jq -r ".$COIN.daemon_port" "$CONFIG_FILE")
    USER=$(jq -r ".$COIN.daemon_user" "$CONFIG_FILE")
    PASS=$(jq -r ".$COIN.daemon_password" "$CONFIG_FILE")
    
    echo "  Host: $HOST:$PORT"
    echo "  User: $USER"
    
    # Test basic connectivity
    if timeout 5 bash -c "</dev/tcp/$HOST/$PORT" 2>/dev/null; then
        echo "  ✓ Port is open"
        
        # Test RPC call
        RPC_URL="http://$HOST:$PORT"
        AUTH_HEADER=$(echo -n "$USER:$PASS" | base64)
        
        RESPONSE=$(curl -s -X POST \
            -H "Content-Type: application/json" \
            -H "Authorization: Basic $AUTH_HEADER" \
            -d '{"jsonrpc":"2.0","id":"1","method":"getblockchaininfo","params":[]}' \
            "$RPC_URL" 2>/dev/null)
        
        if [ $? -eq 0 ] && [ -n "$RESPONSE" ]; then
            HEIGHT=$(echo "$RESPONSE" | jq -r '.result.blocks // "unknown"')
            SYNCED=$(echo "$RESPONSE" | jq -r '.result.initialblockdownload // "unknown"')
            CHAIN=$(echo "$RESPONSE" | jq -r '.result.chain // "unknown"')
            
            echo "  ✓ RPC connection successful"
            echo "  ✓ Chain: $CHAIN"
            echo "  ✓ Block height: $HEIGHT"
            echo "  ✓ Synced: $([ "$SYNCED" = "false" ] && echo "Yes" || echo "No")"
        else
            echo "  ✗ RPC call failed"
            echo "  Response: $RESPONSE"
        fi
    else
        echo "  ✗ Port is closed or unreachable"
    fi
done

echo ""
echo "================================"
echo "Connection test complete!"
