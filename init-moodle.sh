#!/bin/bash

# Moodle Auto-Initialization Script
# This script is called by docker-entrypoint.sh to setup Moodle automatically

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
DB_NAME=${DB_NAME:-"moodle"}

# Wait for database to be ready
wait_for_database() {
    info "Waiting for database to be ready..."
    
    local max_attempts=60
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 -e "SELECT 1;" >/dev/null 2>&1; then
            success "Database is ready!"
            return 0
        fi
        
        info "Attempt $attempt/$max_attempts: Database not ready yet, waiting 5 seconds..."
        sleep 5
        attempt=$((attempt + 1))
    done
    
    error "Database failed to become ready after $max_attempts attempts"
    return 1
}

# Check if Moodle is already installed
check_moodle_installed() {
    info "Checking if Moodle is already installed..."
    
    if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 -e "USE $DB_NAME; SHOW TABLES LIKE 'mdl_config';" 2>/dev/null | grep -q "mdl_config"; then
        return 0  # Moodle is installed
    else
        return 1  # Moodle is not installed
    fi
}

# Install Moodle database
install_moodle() {
    info "Installing Moodle database..."
    
    # Create database if it doesn't exist
    mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --ssl=0 -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    # Run Moodle installation
    if php admin/cli/install_database.php \
        --adminpass=admin123 \
        --adminemail=admin@example.com \
        --fullname="Moodle Site" \
        --shortname="Moodle" \
        --agree-license; then
        
        success "Moodle database installed successfully!"
        return 0
    else
        error "Failed to install Moodle database"
        return 1
    fi
}

# Main execution
main() {
    echo "============================================"
    echo "    Moodle Auto-Initialization"
    echo "============================================"
    echo ""
    
    # Wait for database
    if ! wait_for_database; then
        exit 1
    fi
    
    # Check if Moodle is already installed
    if check_moodle_installed; then
        warning "Moodle is already installed!"
        info "Skipping installation..."
    else
        info "Moodle is not installed. Starting installation..."
        
        if install_moodle; then
            success "Moodle setup completed successfully!"
            info "You can now access Moodle at: http://localhost:8080"
            info "Login with: admin / admin123"
        else
            error "Moodle setup failed!"
            exit 1
        fi
    fi
    
    echo ""
    success "Initialization completed!"
}

# Run main function
main
