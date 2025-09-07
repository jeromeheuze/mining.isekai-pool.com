#!/bin/bash

# Yenten Mining Pool Stratum Server Startup Script

echo "Starting Yenten Mining Pool Stratum Server..."

# Check if already running
if pgrep -f "stratum_server.php" > /dev/null; then
    echo "Stratum server is already running!"
    echo "Use 'stop_stratum.sh' to stop it first."
    exit 1
fi

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed or not in PATH"
    exit 1
fi

# Check if the script exists
if [ ! -f "/var/www/yenten-pool/scripts/stratum_server.php" ]; then
    echo "Error: Stratum server script not found"
    exit 1
fi

# Create logs directory if it doesn't exist
mkdir -p /var/www/yenten-pool/logs

# Set proper permissions
chown -R www-data:www-data /var/www/yenten-pool/logs
chmod 755 /var/www/yenten-pool/logs

# Start the stratum server
echo "Starting stratum server in background..."
cd /var/www/yenten-pool

# Run as www-data user
sudo -u www-data nohup php scripts/stratum_server.php > logs/stratum_output.log 2>&1 &

# Get the PID
PID=$!
echo "Stratum server started with PID: $PID"
echo "PID saved to: /var/www/yenten-pool/logs/stratum.pid"
echo $PID > logs/stratum.pid

# Wait a moment and check if it's still running
sleep 2
if kill -0 $PID 2>/dev/null; then
    echo "Stratum server is running successfully!"
    echo "Logs: /var/www/yenten-pool/logs/stratum.log"
    echo "Output: /var/www/yenten-pool/logs/stratum_output.log"
    echo ""
    echo "To stop the server, run: ./scripts/stop_stratum.sh"
    echo "To view logs: tail -f /var/www/yenten-pool/logs/stratum.log"
else
    echo "Error: Stratum server failed to start!"
    echo "Check the logs for details:"
    echo "tail -f /var/www/yenten-pool/logs/stratum_output.log"
    exit 1
fi
