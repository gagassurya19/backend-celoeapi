#!/bin/bash

# Swagger Auto-Setup Script for CeloeAPI
# This script automatically generates Swagger documentation

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

# Check if we're in the right directory
if [ ! -d "celoeapi-dev" ]; then
    error "Please run this script from the moodle-docker root directory"
    exit 1
fi

info "Starting Swagger setup for CeloeAPI..."

# Check if container is running
if ! docker ps | grep -q "celoe-api"; then
    error "CeloeAPI container is not running. Please start it first with: docker-compose up -d"
    exit 1
fi

# Get container ID
CONTAINER_ID=$(docker ps | grep "celoe-api" | awk '{print $1}')

if [ -z "$CONTAINER_ID" ]; then
    error "Could not find CeloeAPI container"
    exit 1
fi

info "Found CeloeAPI container: $CONTAINER_ID"

# Create necessary directories in container
info "Creating necessary directories..."
docker exec $CONTAINER_ID mkdir -p /var/www/html/application/views/swagger

# Copy Swagger files to container
info "Copying Swagger files to container..."

# Copy Swagger Generator library
if [ -f "celoeapi-dev/application/libraries/Swagger_Generator.php" ]; then
    docker cp celoeapi-dev/application/libraries/Swagger_Generator.php $CONTAINER_ID:/var/www/html/application/libraries/
    success "Swagger Generator library copied"
else
    error "Swagger Generator library not found"
    exit 1
fi

# Copy Swagger controller
if [ -f "celoeapi-dev/application/controllers/Swagger.php" ]; then
    docker cp celoeapi-dev/application/controllers/Swagger.php $CONTAINER_ID:/var/www/html/application/controllers/
    success "Swagger controller copied"
else
    error "Swagger controller not found"
    exit 1
fi

# Copy Swagger view
if [ -f "celoeapi-dev/application/views/swagger/index.php" ]; then
    docker cp celoeapi-dev/application/views/swagger/index.php $CONTAINER_ID:/var/www/html/application/views/swagger/
    success "Swagger view copied"
else
    error "Swagger view not found"
    exit 1
fi

# Copy updated config files
info "Copying updated configuration files..."

# Copy autoload.php
if [ -f "celoeapi-dev/application/config/autoload.php" ]; then
    docker cp celoeapi-dev/application/config/autoload.php $CONTAINER_ID:/var/www/html/application/config/
    success "Autoload configuration updated"
fi

# Copy routes.php
if [ -f "celoeapi-dev/application/config/routes.php" ]; then
    docker cp celoeapi-dev/application/config/routes.php $CONTAINER_ID:/var/www/html/application/config/
    success "Routes configuration updated"
fi

# Set proper permissions
info "Setting proper permissions..."
docker exec $CONTAINER_ID chown -R www-data:www-data /var/www/html/application/libraries/Swagger_Generator.php
docker exec $CONTAINER_ID chown -R www-data:www-data /var/www/html/application/controllers/Swagger.php
docker exec $CONTAINER_ID chown -R www-data:www-data /var/www/html/application/views/swagger/

# Wait for files to be processed
info "Waiting for files to be processed..."
sleep 5

# Test Swagger endpoint
info "Testing Swagger endpoint..."
if curl -s -f "http://localhost:8081/swagger" > /dev/null; then
    success "Swagger UI is accessible at: http://localhost:8081/swagger"
else
    warning "Swagger UI might not be accessible yet. Please wait a moment and try again."
    info "You can access it at: http://localhost:8081/swagger"
fi

# Generate initial documentation
info "Generating initial Swagger documentation..."
if curl -s -f "http://localhost:8081/swagger/generate" > /dev/null; then
    success "Initial Swagger documentation generated"
else
    warning "Could not generate initial documentation. Please try manually at: http://localhost:8081/swagger/generate"
fi

# Test Swagger JSON endpoint
info "Testing Swagger JSON endpoint..."
if curl -s -f "http://localhost:8081/swagger/docs" > /dev/null; then
    success "Swagger JSON endpoint is working"
    
    # Show sample of generated documentation
    info "Sample of generated documentation:"
    curl -s "http://localhost:8081/swagger/docs" | head -10
else
    warning "Swagger JSON endpoint might not be working yet"
fi

echo ""
success "Swagger setup completed!"
echo ""
info "Access Swagger UI at: http://localhost:8081/swagger"
info "Regenerate docs at: http://localhost:8081/swagger/generate"
info "Download JSON at: http://localhost:8081/swagger/download"
info "View JSON at: http://localhost:8081/swagger/docs"
echo ""
info "The Swagger documentation will automatically detect all endpoints from your controllers."
info "Make sure your controllers follow the REST_Controller pattern with methods like:"
info "  - index_get()"
info "  - courses_get()"
info "  - export_post()"
info "  - etc."
echo ""
success "Setup completed successfully!"
