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

# Check if compose file exists
if [ ! -f "$COMPOSE_FILE" ]; then
    error "Docker compose file not found: $COMPOSE_FILE"
    exit 1
fi

# Load environment if exists
if [ -f "${SCRIPT_DIR}/.env.production" ]; then
    source "${SCRIPT_DIR}/.env.production"
    info "Loaded environment from .env.production"
fi

show_usage() {
    echo "Usage: $0 {start|stop|restart|status|logs|build|clean}"
    echo ""
    echo "Commands:"
    echo "  start   - Start CeloeAPI service"
    echo "  stop    - Stop CeloeAPI service"
    echo "  restart - Restart CeloeAPI service"
    echo "  status  - Show service status"
    echo "  logs    - Show service logs"
    echo "  build   - Build and start service"
    echo "  clean   - Stop and remove containers"
    echo ""
    echo "Environment variables:"
    echo "  CI_ENV, CELOEAPI_DB_*, MOODLE_DB_*"
    echo ""
    echo "Examples:"
    echo "  $0 start                    # Start service"
    echo "  $0 status                   # Check status"
    echo "  $0 logs                     # Show logs"
    echo "  $0 restart                  # Restart service"
}

start_service() {
    info "Starting CeloeAPI service..."
    
    # Set environment variables
    export CI_ENV=${CI_ENV:-production}
    export CELOEAPI_DB_HOST=${CELOEAPI_DB_HOST:-host.docker.internal}
    export CELOEAPI_DB_PORT=${CELOEAPI_DB_PORT:-3306}
    export CELOEAPI_DB_USER=${CELOEAPI_DB_USER:-moodleuser}
    export CELOEAPI_DB_PASS=${CELOEAPI_DB_PASS:-moodlepass}
    export CELOEAPI_DB_NAME=${CELOEAPI_DB_NAME:-celoeapi}
    export MOODLE_DB_HOST=${MOODLE_DB_HOST:-host.docker.internal}
    export MOODLE_DB_PORT=${MOODLE_DB_PORT:-3306}
    export MOODLE_DB_USER=${MOODLE_DB_USER:-moodleuser}
    export MOODLE_DB_PASS=${MOODLE_DB_PASS:-moodlepass}
    export MOODLE_DB_NAME=${MOODLE_DB_NAME:-moodle}
    
    docker-compose -f "$COMPOSE_FILE" up -d
    success "CeloeAPI service started"
    
    # Wait for service to be ready
    info "Waiting for service to be ready..."
    sleep 5
    
    if curl -s http://localhost:8081/ >/dev/null 2>&1; then
        success "Service is ready at http://localhost:8081"
    else
        warning "Service may still be starting up"
    fi
}

stop_service() {
    info "Stopping CeloeAPI service..."
    docker-compose -f "$COMPOSE_FILE" down
    success "CeloeAPI service stopped"
}

restart_service() {
    info "Restarting CeloeAPI service..."
    stop_service
    sleep 2
    start_service
    success "CeloeAPI service restarted"
}

show_status() {
    info "CeloeAPI Service Status:"
    echo ""
    
    # Show containers
    docker-compose -f "$COMPOSE_FILE" ps
    
    echo ""
    
    # Show service info
    if curl -s http://localhost:8081/ >/dev/null 2>&1; then
        success "Service: http://localhost:8081 (RUNNING)"
    else
        error "Service: http://localhost:8081 (NOT RESPONDING)"
    fi
    
    if curl -s http://localhost:8081/swagger >/dev/null 2>&1; then
        success "Swagger: http://localhost:8081/swagger (ACCESSIBLE)"
    else
        warning "Swagger: http://localhost:8081/swagger (NOT ACCESSIBLE)"
    fi
    
    echo ""
    info "Environment:"
    echo "  CI_ENV: ${CI_ENV:-production}"
    echo "  CeloeAPI DB: ${CELOEAPI_DB_HOST:-localhost}:${CELOEAPI_DB_PORT:-3306}/${CELOEAPI_DB_NAME:-celoeapi}"
    echo "  Moodle DB: ${MOODLE_DB_HOST:-localhost}:${MOODLE_DB_PORT:-3306}/${MOODLE_DB_NAME:-moodle}"
}

show_logs() {
    info "Showing CeloeAPI logs (Press Ctrl+C to exit)..."
    docker-compose -f "$COMPOSE_FILE" logs -f celoeapi
}

build_service() {
    info "Building and starting CeloeAPI service..."
    
    # Set environment variables
    export CI_ENV=${CI_ENV:-production}
    export CELOEAPI_DB_HOST=${CELOEAPI_DB_HOST:-host.docker.internal}
    export CELOEAPI_DB_PORT=${CELOEAPI_DB_PORT:-3306}
    export CELOEAPI_DB_USER=${CELOEAPI_DB_USER:-moodleuser}
    export CELOEAPI_DB_PASS=${CELOEAPI_DB_PASS:-moodlepass}
    export CELOEAPI_DB_NAME=${CELOEAPI_DB_NAME:-celoeapi}
    export MOODLE_DB_HOST=${MOODLE_DB_HOST:-host.docker.internal}
    export MOODLE_DB_PORT=${MOODLE_DB_PORT:-3306}
    export MOODLE_DB_USER=${MOODLE_DB_USER:-moodleuser}
    export MOODLE_DB_PASS=${MOODLE_DB_PASS:-moodlepass}
    export MOODLE_DB_NAME=${MOODLE_DB_NAME:-moodle}
    
    docker-compose -f "$COMPOSE_FILE" up -d --build
    success "CeloeAPI service built and started"
}

clean_service() {
    info "Cleaning CeloeAPI service..."
    docker-compose -f "$COMPOSE_FILE" down --volumes --remove-orphans
    success "CeloeAPI service cleaned"
}

# Main logic
case "${1:-}" in
    start)
        start_service
        ;;
    stop)
        stop_service
        ;;
    restart)
        restart_service
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs
        ;;
    build)
        build_service
        ;;
    clean)
        clean_service
        ;;
    *)
        show_usage
        exit 1
        ;;
esac
