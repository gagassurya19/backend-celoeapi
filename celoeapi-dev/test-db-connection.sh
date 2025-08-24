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
COMPOSE_FILE="${SCRIPT_DIR}/docker-compose.celoeapi-only.yml"

# Load environment if exists
if [ -f "${SCRIPT_DIR}/.env.production" ]; then
    source "${SCRIPT_DIR}/.env.production"
    info "Loaded environment from .env.production"
fi

# Defaults
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

test_host_mysql() {
    info "Testing MySQL connection from HOST machine..."
    
    # Test Moodle database
    if mysql -h localhost -P"${MOODLE_DB_PORT}" -u"${MOODLE_DB_USER}" -p"${MOODLE_DB_PASS}" -e "USE ${MOODLE_DB_NAME}; SELECT COUNT(*) FROM mdl_course;" >/dev/null 2>&1; then
        success "✓ Host → Moodle DB: OK"
    else
        error "✗ Host → Moodle DB: FAILED"
        return 1
    fi
    
    # Test CeloeAPI database
    if mysql -h localhost -P"${CELOEAPI_DB_PORT}" -u"${CELOEAPI_DB_USER}" -p"${CELOEAPI_DB_PASS}" -e "USE ${CELOEAPI_DB_NAME}; SELECT 1;" >/dev/null 2>&1; then
        success "✓ Host → CeloeAPI DB: OK"
    else
        warning "⚠ Host → CeloeAPI DB: FAILED (database mungkin belum dibuat)"
    fi
}

test_container_mysql() {
    info "Testing MySQL connection from CONTAINER..."
    
    # Check if container is running
    if ! docker-compose -f "$COMPOSE_FILE" ps | grep -q "celoeapi.*Up"; then
        error "CeloeAPI container is not running. Start it first with: ./celoeapi-service.sh start"
        return 1
    fi
    
    # Test Moodle database from container
    info "Testing Moodle DB connection from container..."
    if docker-compose -f "$COMPOSE_FILE" exec -T celoeapi php -r "
        require_once 'index.php';
        \$CI =& get_instance();
        \$CI->load->database('moodle');
        if (\$CI->db->simple_query('SELECT COUNT(*) FROM mdl_course')) {
            echo 'OK';
        } else {
            echo 'FAILED: ' . \$CI->db->error()['message'];
        }
    " | grep -q "OK"; then
        success "✓ Container → Moodle DB: OK"
    else
        error "✗ Container → Moodle DB: FAILED"
        return 1
    fi
    
    # Test CeloeAPI database from container
    info "Testing CeloeAPI DB connection from container..."
    if docker-compose -f "$COMPOSE_FILE" exec -T celoeapi php -r "
        require_once 'index.php';
        \$CI =& get_instance();
        \$CI->load->database('default');
        if (\$CI->db->simple_query('SELECT 1')) {
            echo 'OK';
        } else {
            echo 'FAILED: ' . \$CI->db->error()['message'];
        }
    " | grep -q "OK"; then
        success "✓ Container → CeloeAPI DB: OK"
    else
        error "✗ Container → CeloeAPI DB: FAILED"
        return 1
    fi
}

test_etl_tables() {
    info "Testing ETL tables access..."
    
    # Test Moodle tables
    info "Testing Moodle tables access..."
    if docker-compose -f "$COMPOSE_FILE" exec -T celoeapi php -r "
        require_once 'index.php';
        \$CI =& get_instance();
        \$CI->load->database('moodle');
        
        \$tables = ['mdl_course', 'mdl_user', 'mdl_logstore_standard_log'];
        foreach (\$tables as \$table) {
            if (\$CI->db->table_exists(\$table)) {
                echo '✓ ' . \$table . ' exists' . PHP_EOL;
            } else {
                echo '✗ ' . \$table . ' missing' . PHP_EOL;
            }
        }
    " | grep -q "✓"; then
        success "✓ Moodle tables: Accessible"
    else
        error "✗ Moodle tables: Some tables missing"
    fi
    
    # Test CeloeAPI tables (if they exist)
    info "Testing CeloeAPI tables access..."
    if docker-compose -f "$COMPOSE_FILE" exec -T celoeapi php -r "
        require_once 'index.php';
        \$CI =& get_instance();
        \$CI->load->database('default');
        
        \$tables = ['cp_etl_logs', 'sas_etl_logs'];
        foreach (\$tables as \$table) {
            if (\$CI->db->table_exists(\$table)) {
                echo '✓ ' . \$table . ' exists' . PHP_EOL;
            } else {
                echo '⚠ ' . \$table . ' not yet created (run migrations first)' . PHP_EOL;
            }
        }
    " | grep -q "✓"; then
        success "✓ CeloeAPI tables: Accessible"
    else
        warning "⚠ CeloeAPI tables: Not yet created (run migrations first)"
    fi
}

show_connection_info() {
    info "Connection Information:"
    echo ""
    echo "Host Machine:"
    echo "  Moodle DB: localhost:${MOODLE_DB_PORT}/${MOODLE_DB_NAME}"
    echo "  CeloeAPI DB: localhost:${CELOEAPI_DB_PORT}/${CELOEAPI_DB_NAME}"
    echo ""
    echo "Container:"
    echo "  Moodle DB: ${MOODLE_DB_HOST}:${MOODLE_DB_PORT}/${MOODLE_DB_NAME}"
    echo "  CeloeAPI DB: ${CELOEAPI_DB_HOST}:${CELOEAPI_DB_PORT}/${CELOEAPI_DB_NAME}"
    echo ""
    echo "Environment:"
    echo "  CI_ENV: ${CI_ENV:-production}"
    echo "  Network Mode: host (container access host directly)"
    echo ""
}

main() {
    info "Testing Database Connections..."
    echo ""
    
    show_connection_info
    
    # Test host connections
    test_host_mysql
    echo ""
    
    # Test container connections
    test_container_mysql
    echo ""
    
    # Test ETL tables
    test_etl_tables
    echo ""
    
    success "Database connection tests completed!"
    echo ""
    info "If all tests pass, your setup is ready for ETL operations."
    echo "If some tests fail, check:"
    echo "  1. MySQL service is running on host"
    echo "  2. Database credentials are correct"
    echo "  3. CeloeAPI container is running"
    echo "  4. Network configuration is correct"
}

# Run main function
main "$@"
