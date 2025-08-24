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
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Compose wrapper
dc() { (cd "$ROOT_DIR" && docker-compose "$@"); }

# Defaults (align with docker-compose.yml and application/config/database.php)
DB_NAME=${DB_NAME:-celoeapi}
DB_USER=${DB_USER:-moodleuser}
DB_PASS=${DB_PASS:-moodlepass}
DB_ROOT_PASS=${DB_ROOT_PASS:-root}

check_dependencies() {
  info "Checking dependencies..."
  command -v docker >/dev/null 2>&1 || { error "docker not found"; exit 1; }
  command -v docker-compose >/dev/null 2>&1 || { error "docker-compose not found"; exit 1; }
  command -v curl >/dev/null 2>&1 || warning "curl not found (optional for checks)"
  success "Dependencies OK"
}

start_services() {
  info "Starting required services (db, celoeapi)..."
  dc up -d db
  dc up -d celoeapi
  success "Services started"
}

wait_for_db() {
  info "Waiting for MySQL to be healthy..."
  local attempts=0
  local max_attempts=60
  until dc exec -T db mysqladmin ping -h localhost -u"${DB_USER}" -p"${DB_PASS}" --silent; do
    attempts=$((attempts+1))
    if [ "$attempts" -ge "$max_attempts" ]; then
      error "MySQL is not ready after ${max_attempts} attempts"
      exit 1
    fi
    sleep 2
  done
  success "MySQL is ready"
}

ensure_database() {
  info "Ensuring database '${DB_NAME}' exists and privileges are set..."
  dc exec -T db sh -lc "mysql -h localhost -uroot -p'${DB_ROOT_PASS}' -e \"CREATE DATABASE IF NOT EXISTS \\\\`${DB_NAME}\\\\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \\\\`${DB_NAME}\\\\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;\""
  success "Database '${DB_NAME}' ensured"
}

build_api_if_needed() {
  info "Building API image (if needed)..."
  dc build celoeapi
  dc up -d celoeapi
  success "API service ready"
}

run_migrations() {
  info "Running ETL migrations inside API container..."
  dc exec -T celoeapi php run_migrations.php || {
    error "Migrations failed"; exit 1;
  }
  success "Migrations completed"
  
  info "Adding optimization indexes..."
  dc exec -T celoeapi php -r "
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
  info "Smoke testing endpoints..."
  local attempts=0
  local max_attempts=30
  until curl -fsS "http://localhost:8081/swagger/spec" >/dev/null 2>&1; do
    attempts=$((attempts+1))
    if [ "$attempts" -ge "$max_attempts" ]; then
      error "Swagger spec not reachable on :8081"
      exit 1
    fi
    sleep 2
  done
  success "Swagger spec OK: http://localhost:8081/swagger/spec"

  if curl -fsS "http://localhost:8081/" >/dev/null 2>&1; then
    success "Welcome page OK: http://localhost:8081/"
  else
    warning "Welcome page not reachable yet"
  fi
}

main() {
  echo "============================================"
  echo "           CeloeAPI Quick Setup"
  echo "============================================"
  check_dependencies
  start_services
  wait_for_db
  ensure_database
  build_api_if_needed
  run_migrations
  smoke_test
  echo ""
  success "Setup complete!"
  echo "- API base     : http://localhost:8081"
  echo "- Swagger UI   : http://localhost:8081/swagger"
  echo "- Swagger Spec : http://localhost:8081/swagger/spec"
}

main "$@"


