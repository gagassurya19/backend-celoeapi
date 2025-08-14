#!/bin/bash

# CeloeAPI Auto-Setup Script
# This script automatically sets up celoeapi database if it doesn't exist

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Helper functions
info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Database configuration
DB_HOST=${DB_HOST:-"db"}
DB_USERNAME=${DB_USERNAME:-"moodleuser"}
DB_PASSWORD=${DB_PASSWORD:-"moodlepass"}
DB_NAME=${DB_NAME:-"celoeapi"}

# Lock file to prevent multiple instances
LOCK_FILE="/tmp/celoeapi-setup.lock"

# Cleanup function
cleanup() {
    if [ -f "$LOCK_FILE" ]; then
        rm -f "$LOCK_FILE"
    fi
}

# Set trap for cleanup
trap cleanup EXIT

# Check if already running
if [ -f "$LOCK_FILE" ]; then
    info "Setup already running, waiting..."
    exit 0
fi

# Create lock file
touch "$LOCK_FILE"

# Wait for database to be ready
wait_for_database() {
    info "Waiting for database to be ready..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 -e "SELECT 1;" >/dev/null 2>&1; then
            success "Database connection successful!"
            return 0
        fi
        
        info "Attempt $attempt/$max_attempts: Database not ready, waiting 2 seconds..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    error "Database not ready after $max_attempts attempts"
    return 1
}

# Check if database exists
check_database_exists() {
    info "Checking if database '$DB_NAME' exists..."
    
    if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 -e "USE \`$DB_NAME\`;" >/dev/null 2>&1; then
        info "Database '$DB_NAME' already exists"
        return 0
    else
        info "Database '$DB_NAME' does not exist"
        return 1
    fi
}

# Create database
create_database() {
    info "Creating database '$DB_NAME'..."
    
    if mysql -h "$DB_HOST" -u root -proot -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; then
        success "Database '$DB_NAME' created successfully!"
        
        # Grant privileges to moodleuser
        info "Granting privileges to user '$DB_USERNAME'..."
        if mysql -h "$DB_HOST" -u root -proot -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USERNAME'@'%'; FLUSH PRIVILEGES;"; then
            success "Privileges granted successfully!"
        else
            error "Failed to grant privileges!"
            return 1
        fi
    else
        error "Failed to create database '$DB_NAME'!"
        return 1
    fi
}

# Setup tables
setup_tables() {
    info "Setting up database tables..."
    
    # Check if tables already exist
    local table_count=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 -e "USE \`$DB_NAME\`; SHOW TABLES;" 2>/dev/null | wc -l)
    
    if [ "$table_count" -gt 1 ]; then
        info "Tables already exist, skipping table creation"
        return 0
    fi
    
    # Create tables from schema file
    if [ -f "/var/www/html/etl_database_schema.sql" ]; then
        info "Creating tables from schema file..."
        if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 "$DB_NAME" < /var/www/html/etl_database_schema.sql; then
            success "Tables created successfully!"
        else
            error "Failed to create tables!"
            return 1
        fi
    else
        warning "Schema file not found, creating basic tables..."
        create_basic_tables
    fi
}

# Create basic tables if schema file not found
create_basic_tables() {
    info "Creating basic tables..."
    
    local sql="
    CREATE TABLE IF NOT EXISTS \`etl_status\` (
        \`id\` int(11) NOT NULL AUTO_INCREMENT,
        \`status\` varchar(50) NOT NULL,
        \`message\` text,
        \`created_at\` timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (\`id\`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    INSERT INTO \`etl_status\` (\`status\`, \`message\`) VALUES ('ready', 'Database initialized');
    "
    
    if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 "$DB_NAME" -e "$sql"; then
        success "Basic tables created successfully!"
    else
        error "Failed to create basic tables!"
        return 1
    fi
}

# Main function
main() {
    info "Starting CeloeAPI database setup..."
    
    # Wait for database
    if ! wait_for_database; then
        exit 1
    fi
    
    # Check if database exists
    if ! check_database_exists; then
        # Create database
        if ! create_database; then
            exit 1
        fi
    fi
    
    # Setup tables
    if ! setup_tables; then
        exit 1
    fi
    
    success "CeloeAPI database setup completed successfully!"
}

# Run main function
main
