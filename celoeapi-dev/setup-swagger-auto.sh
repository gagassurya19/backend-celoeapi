#!/bin/bash

# Auto-Setup Swagger for CeloeAPI
# This script runs automatically when the container starts

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

# Wait for CodeIgniter to be ready
wait_for_codeigniter() {
    info "Waiting for CodeIgniter to be ready..."
    
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f "http://localhost" > /dev/null 2>&1; then
            success "CodeIgniter is ready!"
            return 0
        fi
        
        info "Attempt $attempt/$max_attempts: CodeIgniter not ready, waiting 2 seconds..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    error "CodeIgniter not ready after $max_attempts attempts"
    return 1
}

# Setup Swagger files
setup_swagger_files() {
    info "Setting up Swagger files..."
    
    # Create necessary directories
    mkdir -p /var/www/html/application/views/swagger
    
    # Check if Swagger files exist
    if [ ! -f "/var/www/html/application/libraries/Swagger_Generator.php" ]; then
        warning "Swagger Generator library not found, skipping Swagger setup"
        return 1
    fi
    
    if [ ! -f "/var/www/html/application/controllers/Swagger.php" ]; then
        warning "Swagger controller not found, skipping Swagger setup"
        return 1
    fi
    
    if [ ! -f "/var/www/html/application/views/swagger/index.php" ]; then
        warning "Swagger view not found, skipping Swagger setup"
        return 1
    fi
    
    # Set proper permissions
    chown -R www-data:www-data /var/www/html/application/libraries/Swagger_Generator.php
    chown -R www-data:www-data /var/www/html/application/controllers/Swagger.php
    chown -R www-data:www-data /var/www/html/application/views/swagger/
    
    success "Swagger files setup completed"
    return 0
}

# Test Swagger endpoints
test_swagger() {
    info "Testing Swagger endpoints..."
    
    # Test Swagger UI
    if curl -s -f "http://localhost/swagger" > /dev/null 2>&1; then
        success "Swagger UI is accessible"
    else
        warning "Swagger UI not accessible yet"
    fi
    
    # Test Swagger docs
    if curl -s -f "http://localhost/swagger/docs" > /dev/null 2>&1; then
        success "Swagger docs endpoint is working"
    else
        warning "Swagger docs endpoint not working yet"
    fi
}

# Main function
main() {
    info "Starting automatic Swagger setup..."
    
    # Wait for CodeIgniter
    if ! wait_for_codeigniter; then
        warning "Skipping Swagger setup due to CodeIgniter not being ready"
        return 1
    fi
    
    # Setup Swagger files
    if setup_swagger_files; then
        # Wait a bit for files to be processed
        sleep 3
        
        # Test Swagger
        test_swagger
        
        success "Automatic Swagger setup completed"
    else
        warning "Swagger setup skipped"
    fi
}

# Run main function
main
