#!/bin/bash

# Create ETL Database Script
# This script creates the celoeapi database if it doesn't exist

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
ETL_DATABASE=${ETL_DATABASE:-"celoeapi"}

# Check if running inside Docker container
if [ -f /.dockerenv ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
    # Running inside container
    MYSQL_CMD="mysql -h ${DB_HOST} -u ${DB_USERNAME} -p${DB_PASSWORD}"
else
    # Running outside container - use docker exec
    DB_CONTAINER="moodle-docker-db-1"
    MYSQL_CMD="docker exec -i ${DB_CONTAINER} mysql -u ${DB_USERNAME} -p${DB_PASSWORD}"
fi

# Function to check if database exists
check_database_exists() {
    local db_name=$1
    info "Checking if database '${db_name}' exists..."
    
    local result=$(echo "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${db_name}';" | ${MYSQL_CMD} 2>/dev/null | grep -v SCHEMA_NAME | grep "${db_name}")
    
    if [ -n "$result" ]; then
        return 0  # Database exists
    else
        return 1  # Database does not exist
    fi
}

# Function to create database
create_database() {
    local db_name=$1
    info "Creating database '${db_name}'..."
    
    if echo "CREATE DATABASE IF NOT EXISTS \`${db_name}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | ${MYSQL_CMD} 2>/dev/null; then
        success "Database '${db_name}' created successfully!"
        return 0
    else
        error "Failed to create database '${db_name}'"
        return 1
    fi
}

# Function to test database connection
test_connection() {
    info "Testing database connection..."
    
    if echo "SELECT 1;" | ${MYSQL_CMD} >/dev/null 2>&1; then
        success "Database connection successful!"
        return 0
    else
        error "Database connection failed!"
        error "Please check if MySQL service is running and credentials are correct."
        return 1
    fi
}

# Function to show database info
show_database_info() {
    local db_name=$1
    info "Database Information:"
    echo "  Host: ${DB_HOST}"
    echo "  Username: ${DB_USERNAME}"
    echo "  Database: ${db_name}"
    echo "  Character Set: utf8mb4"
    echo "  Collation: utf8mb4_unicode_ci"
}

# Main execution
main() {
    echo "============================================"
    echo "    ETL Database Creation Script"
    echo "============================================"
    echo ""
    
    # Test connection first
    if ! test_connection; then
        exit 1
    fi
    
    # Check if database exists
    if check_database_exists "${ETL_DATABASE}"; then
        warning "Database '${ETL_DATABASE}' already exists!"
        show_database_info "${ETL_DATABASE}"
        
        # Ask user if they want to recreate
        echo ""
        read -p "Do you want to recreate the database? This will DELETE all existing data! (y/N): " -n 1 -r
        echo ""
        
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            warning "Dropping existing database '${ETL_DATABASE}'..."
            if echo "DROP DATABASE IF EXISTS \`${ETL_DATABASE}\`;" | ${MYSQL_CMD} 2>/dev/null; then
                success "Database '${ETL_DATABASE}' dropped successfully!"
            else
                error "Failed to drop database '${ETL_DATABASE}'"
                exit 1
            fi
            
            # Create database
            if create_database "${ETL_DATABASE}"; then
                show_database_info "${ETL_DATABASE}"
            else
                exit 1
            fi
        else
            info "Keeping existing database."
        fi
    else
        # Database doesn't exist, create it
        info "Database '${ETL_DATABASE}' does not exist."
        
        if create_database "${ETL_DATABASE}"; then
            show_database_info "${ETL_DATABASE}"
        else
            exit 1
        fi
    fi
    
    echo ""
    success "Database setup completed!"
    info "You can now run migrations with: ./migrate.sh run"
    echo ""
}

# Help function
show_help() {
    echo "ETL Database Creation Script"
    echo ""
    echo "Usage: $0 [options]"
    echo ""
    echo "Options:"
    echo "  -h, --help     Show this help message"
    echo "  --force        Force recreate database without confirmation"
    echo ""
    echo "Environment Variables:"
    echo "  DB_HOST        Database host (default: db)"
    echo "  DB_USERNAME    Database username (default: moodleuser)"
    echo "  DB_PASSWORD    Database password (default: moodlepass)"
    echo "  ETL_DATABASE   ETL database name (default: celoeapi)"
    echo ""
    echo "Examples:"
    echo "  $0                     # Create database with default settings"
    echo "  $0 --force             # Force recreate database"
    echo "  ETL_DATABASE=myetl $0  # Use custom database name"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        --force)
            FORCE_RECREATE=true
            shift
            ;;
        *)
            error "Unknown option: $1"
            show_help
            exit 1
            ;;
    esac
done

# Handle force recreate option
if [ "$FORCE_RECREATE" = true ]; then
    # Modify the main function to skip confirmation
    main() {
        echo "============================================"
        echo "    ETL Database Creation Script (FORCE)"
        echo "============================================"
        echo ""
        
        # Test connection first
        if ! test_connection; then
            exit 1
        fi
        
        # Force drop and recreate
        warning "Force recreating database '${ETL_DATABASE}'..."
        
        if echo "DROP DATABASE IF EXISTS \`${ETL_DATABASE}\`;" | ${MYSQL_CMD} 2>/dev/null; then
            success "Database '${ETL_DATABASE}' dropped successfully!"
        else
            error "Failed to drop database '${ETL_DATABASE}'"
            exit 1
        fi
        
        # Create database
        if create_database "${ETL_DATABASE}"; then
            show_database_info "${ETL_DATABASE}"
        else
            exit 1
        fi
        
        echo ""
        success "Database setup completed!"
        info "You can now run migrations with: ./migrate.sh run"
        echo ""
    }
fi

# Run main function
main 