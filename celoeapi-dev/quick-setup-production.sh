#!/bin/bash

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()    { echo -e "${BLUE}[INFO]${NC} $1"; }
success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; }

# Resolve paths
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Defaults untuk production
# Container akan connect ke host MySQL via host.docker.internal
CELOEAPI_DB_HOST=${CELOEAPI_DB_HOST:-host.docker.internal}
CELOEAPI_DB_PORT=${CELOEAPI_DB_PORT:-3306}
CELOEAPI_DB_USER=${CELOEAPI_DB_USER:-moodleuser}
CELOEAPI_DB_PASS=${CELOEAPI_DB_PASS:-moodlepass}
CELOEAPI_DB_NAME=${CELOEAPI_DB_NAME:-celoeapi}

MOODLE_DB_HOST=${MOODLE_DB_HOST:-host.docker.internal}
MOODLE_DB_PORT=${MOODLE_DB_PORT:-3306}
MOODLE_DB_USER=${MOODLE_DB_USER:-moodleuser}
MOODLE_DB_PASS=${MOODLE_DB_PASS:-moodlepass}
MOODLE_DB_NAME=${MOODLE_DB_NAME:-moodle}

check_dependencies() {
  info "Checking dependencies..."
  command -v docker >/dev/null 2>&1 || { error "docker not found"; exit 1; }
  command -v docker-compose >/dev/null 2>&1 || { error "docker-compose not found"; exit 1; }
  command -v mysql >/dev/null 2>&1 || { error "mysql client not found"; exit 1; }
  success "Dependencies OK"
}

check_moodle_database() {
  info "Checking Moodle database connection..."
  if mysql -h"${MOODLE_DB_HOST}" -P"${MOODLE_DB_PORT}" -u"${MOODLE_DB_USER}" -p"${MOODLE_DB_PASS}" -e "USE ${MOODLE_DB_NAME}; SELECT COUNT(*) FROM mdl_course;" >/dev/null 2>&1; then
    success "Moodle database connection OK"
  else
    error "Cannot connect to Moodle database. Please check credentials and ensure Moodle is running."
    exit 1
  fi
}

create_celoeapi_database() {
  info "Creating CeloeAPI database '${CELOEAPI_DB_NAME}'..."
  
  # Try to create database using Moodle credentials
  if mysql -h"${CELOEAPI_DB_HOST}" -P"${CELOEAPI_DB_PORT}" -u"${CELOEAPI_DB_USER}" -p"${CELOEAPI_DB_PASS}" -e "CREATE DATABASE IF NOT EXISTS \`${CELOEAPI_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null; then
    success "Database '${CELOEAPI_DB_NAME}' created successfully"
  else
    warning "Could not create database with current credentials. You may need to create it manually:"
    echo "mysql -u root -p -e \"CREATE DATABASE \`${CELOEAPI_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
    echo "mysql -u root -p -e \"GRANT ALL PRIVILEGES ON \`${CELOEAPI_DB_NAME}\`.* TO '${CELOEAPI_DB_USER}'@'%';\""
    echo "mysql -u root -p -e \"FLUSH PRIVILEGES;\""
    
    # Ask user to continue
    read -p "Press Enter after creating the database manually, or Ctrl+C to abort..."
  fi
}

start_celoeapi_service() {
  info "Starting CeloeAPI service..."
  
  # Set environment variables
  export CI_ENV=production
  export CELOEAPI_DB_HOST CELOEAPI_DB_PORT CELOEAPI_DB_USER CELOEAPI_DB_PASS CELOEAPI_DB_NAME
  export MOODLE_DB_HOST MOODLE_DB_PORT MOODLE_DB_USER MOODLE_DB_PASS MOODLE_DB_NAME
  
  # Start service using production docker-compose
  docker-compose -f docker-compose.celoeapi-only.yml up -d --build
  
  success "CeloeAPI service started"
}

wait_for_service() {
  info "Waiting for CeloeAPI service to be ready..."
  local attempts=0
  local max_attempts=30
  
  until curl -s http://localhost:8081/ >/dev/null 2>&1; do
    attempts=$((attempts+1))
    if [ "$attempts" -ge "$max_attempts" ]; then
      error "CeloeAPI service is not ready after ${max_attempts} attempts"
      exit 1
    fi
    sleep 2
  done
  
  success "CeloeAPI service is ready"
}

run_migrations() {
  info "Running ETL migrations..."
  
  # Run migrations inside container
  docker-compose -f docker-compose.celoeapi-only.yml exec -T celoeapi php run_migrations.php || {
    error "Migrations failed"; exit 1;
  }
  
  success "Migrations completed"
  
  info "Adding optimization indexes..."
  docker-compose -f docker-compose.celoeapi-only.yml exec -T celoeapi php -r "
    require_once 'index.php';
    \$CI =& get_instance();
    \$CI->load->database();
    \$CI->load->library('migration');
    
    // Run migration 007 for optimization indexes
    if (\$CI->migration->version(7) === FALSE) {
      echo 'Optimization indexes failed: ' . \$CI->migration->error_string() . PHP_EOL;
      exit(1);
    }
    echo 'Optimization indexes added successfully!' . PHP_EOL;
  " || {
    error "Optimization indexes failed"; exit 1;
  }
  
  success "Optimization indexes added"
}

smoke_test() {
  info "Testing endpoints..."
  
  # Test welcome page
  if curl -s http://localhost:8081/ | grep -q "Welcome"; then
    success "Welcome page OK"
  else
    warning "Welcome page may have issues"
  fi
  
  # Test Swagger
  if curl -s http://localhost:8081/swagger >/dev/null; then
    success "Swagger UI OK"
  else
    warning "Swagger UI may have issues"
  fi
  
  success "Smoke test completed"
}

show_status() {
  info "CeloeAPI Status:"
  echo "  Service: http://localhost:8081"
  echo "  Swagger: http://localhost:8081/swagger"
  echo "  Database: ${CELOEAPI_DB_NAME}@${CELOEAPI_DB_HOST}:${CELOEAPI_DB_PORT}"
  echo "  Moodle DB: ${MOODLE_DB_NAME}@${MOODLE_DB_HOST}:${MOODLE_DB_PORT}"
  
  # Show running containers
  echo ""
  info "Running containers:"
  docker-compose -f docker-compose.celoeapi-only.yml ps
}

main() {
  info "Starting CeloeAPI Production Setup..."
  echo ""
  
  check_dependencies
  check_moodle_database
  create_celoeapi_database
  start_celoeapi_service
  wait_for_service
  run_migrations
  smoke_test
  
  echo ""
  success "CeloeAPI Production Setup completed successfully!"
  echo ""
  show_status
}

# Run main function
main "$@"
