#!/bin/bash

# Define base log directory
LOG_BASE_DIR="/var/www/html/approval_live/storage/autosendlog"

# Get current year and month (YYYY-MM)
CURRENT_MONTH=$(date +"%Y-%m-%d")

# Create a folder for the current month if it doesn't exist
LOG_DIR="$LOG_BASE_DIR/$CURRENT_MONTH"
mkdir -p "$LOG_DIR"

# Run the curl command and capture the HTTP status code
http_code=$(curl -s -o /dev/null -w "%{http_code}" https://ifca.kurakurabali.com/approval_live/api/autosend)

# Get the current date and time
timestamp=$(date)

# Append a new line, HTTP status code, and timestamp to the success log
{
    echo ""
    echo "$timestamp: $http_code"
} >> "$LOG_DIR/autosend_success.log" 2>> "$LOG_DIR/autosend_error.log" || {
    echo ""
    echo "$timestamp: Failed"
} >> "$LOG_DIR/autosend_failed.log"
