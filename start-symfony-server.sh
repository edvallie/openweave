#!/bin/bash
# Script to start Symfony CLI server with increased memory limit

# Check if Symfony CLI is installed
if command -v symfony &> /dev/null; then
    echo "Starting Symfony server with increased memory limit..."
    php -d memory_limit=512M $(which symfony) server:start --port=8080
else
    echo "Symfony CLI not found. Using PHP built-in server..."
    php -c php.ini -d memory_limit=512M -S localhost:8080 -t public
fi
