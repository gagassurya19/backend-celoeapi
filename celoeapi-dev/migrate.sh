#!/bin/bash

# CLI Migration Wrapper Script
# Provides unified interface for migration commands

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

# Check if running inside Docker container
if [ -f /.dockerenv ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
    # Running inside container
    info "Running migration inside Docker container..."
    php index.php migrate "$@"
else
    # Running outside container
    info "Running migration in Docker container with saputra user..."
    docker exec -u saputra celoe-api php index.php migrate "$@"
fi