#!/bin/bash

# Yenten Mining Pool Stratum Server Stop Script

echo "Stopping Yenten Mining Pool Stratum Server..."

# Check if PID file exists
if [ -f "/var/www/yenten-pool/logs/stratum.pid" ]; then
    PID=$(cat /var/www/yenten-pool/logs/stratum.pid)
    
    if kill -0 $PID 2>/dev/null; then
        echo "Stopping stratum server (PID: $PID)..."
        kill $PID
        
        # Wait for graceful shutdown
        for i in {1..10}; do
            if ! kill -0 $PID 2>/dev/null; then
                echo "Stratum server stopped successfully!"
                rm -f /var/www/yenten-pool/logs/stratum.pid
                exit 0
            fi
            echo "Waiting for graceful shutdown... ($i/10)"
            sleep 1
        done
        
        # Force kill if still running
        echo "Force killing stratum server..."
        kill -9 $PID
        rm -f /var/www/yenten-pool/logs/stratum.pid
        echo "Stratum server force stopped!"
    else
        echo "Stratum server is not running (PID file exists but process not found)"
        rm -f /var/www/yenten-pool/logs/stratum.pid
    fi
else
    echo "No PID file found. Checking for running processes..."
    
    # Kill any running stratum server processes
    PIDS=$(pgrep -f "stratum_server.php")
    if [ -n "$PIDS" ]; then
        echo "Found running stratum server processes: $PIDS"
        kill $PIDS
        echo "Stratum server processes killed!"
    else
        echo "No stratum server processes found running."
    fi
fi
