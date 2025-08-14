#!/bin/bash

# ETL Database Creation Script
# Colors for output
RED="\033[0;31m"
GREEN="\033[0;32m"
YELLOW="\033[1;33m"
BLUE="\033[0;34m"
NC="\033[0m"

info() { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Default values
DB_HOST=${DB_HOST:-"db"}
DB_USERNAME=${DB_USERNAME:-"moodleuser"}
DB_PASSWORD=${DB_PASSWORD:-"moodlepass"}
ETL_DATABASE=${ETL_DATABASE:-"celoeapi"}
FORCE_MODE=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --force) FORCE_MODE=true; shift ;;
        -h|--help)
            echo "Usage: $0 [--force]"
            echo "Environment: DB_HOST, DB_USERNAME, DB_PASSWORD, ETL_DATABASE"
            exit 0 ;;
        *) error "Unknown option: $1"; exit 1 ;;
    esac
done

echo "============================================"
echo "    ETL Database Creation Script"
echo "============================================"

# Test connection
info "Testing database connection..."
if docker exec moodle-docker-db-1 mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1;" >/dev/null 2>&1; then
    success "Database connection successful!"
else
    error "Failed to connect to database!"
    exit 1
fi

# Check if database exists
info "Checking if database \"$ETL_DATABASE\" exists..."
if docker exec moodle-docker-db-1 mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "USE \`$ETL_DATABASE\`;" >/dev/null 2>&1; then
    info "Database \"$ETL_DATABASE\" already exists."
    if [ "$FORCE_MODE" = true ]; then
        info "Force mode enabled. Dropping existing database..."
        docker exec moodle-docker-db-1 mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS \`$ETL_DATABASE\`;"
        success "Database \"$ETL_DATABASE\" dropped successfully!"
    else
        warning "Database already exists!"
        read -p "Do you want to drop and recreate? (y/N): " -n 1 -r
        echo ""
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            docker exec moodle-docker-db-1 mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "DROP DATABASE IF EXISTS \`$ETL_DATABASE\`;"
            success "Database \"$ETL_DATABASE\" dropped successfully!"
        else
            info "Database creation cancelled."
            exit 0
        fi
    fi
else
    info "Database \"$ETL_DATABASE\" does not exist."
fi

# Create database
info "Creating database \"$ETL_DATABASE\"..."
if docker exec moodle-docker-db-1 mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "CREATE DATABASE \`$ETL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; then
    success "Database \"$ETL_DATABASE\" created successfully!"
else
    error "Failed to create database \"$ETL_DATABASE\"!"
    exit 1
fi

echo ""
info "Database Information:"
echo "  Host: $DB_HOST"
echo "  Username: $DB_USERNAME"
echo "  Database: $ETL_DATABASE"
echo "  Character Set: utf8mb4"
echo "  Collation: utf8mb4_unicode_ci"

echo ""
success "Database setup completed!"
info "You can now run migrations with: ./migrate.sh run"
