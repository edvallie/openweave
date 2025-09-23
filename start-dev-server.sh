#!/bin/bash
# Script to start Symfony development server with increased memory limit

export MEMORY_LIMIT="1024M"
php -c php.ini -d memory_limit=$MEMORY_LIMIT -S localhost:8080 -t public
