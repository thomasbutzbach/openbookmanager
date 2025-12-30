#!/usr/bin/env bash

#############################################################################
# OpenBookManager - Docker Stop Script
#############################################################################
#
# This script stops the OpenBookManager Docker containers.
#
# Usage:
#   ./stop-bookmanager.sh [options]
#
# Options:
#   -v, --volumes         Remove volumes (DATABASE WILL BE DELETED!)
#   -h, --help            Show this help message
#
#############################################################################

set -e

# Auto-launch in terminal if not already running in one
# This allows double-clicking the script in file managers
if [ ! -t 0 ] && [ -z "$RUNNING_IN_TERMINAL" ]; then
    export RUNNING_IN_TERMINAL=1

    # Try different terminal emulators (ordered by preference)
    if command -v konsole &> /dev/null; then
        konsole -e "$0" "$@"
        exit 0
    elif command -v gnome-terminal &> /dev/null; then
        gnome-terminal -- "$0" "$@"
        exit 0
    elif command -v xfce4-terminal &> /dev/null; then
        xfce4-terminal -e "$0 $@"
        exit 0
    elif command -v xterm &> /dev/null; then
        xterm -e "$0 $@"
        exit 0
    elif command -v x-terminal-emulator &> /dev/null; then
        x-terminal-emulator -e "$0 $@"
        exit 0
    fi
    # If no terminal found, continue anyway (headless mode)
fi

# Default configuration
REMOVE_VOLUMES=false
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

show_help() {
    sed -n '/^# OpenBookManager/,/^#####/p' "$0" | sed 's/^# \?//'
    exit 0
}

wait_on_error() {
    echo ""
    echo "Press Enter to close this window..."
    read -r
}

check_docker_compose() {
    # Determine which command to use
    if docker compose version &> /dev/null 2>&1; then
        COMPOSE_CMD="docker compose"
    elif command -v docker-compose &> /dev/null; then
        COMPOSE_CMD="docker-compose"
    else
        print_error "Docker Compose is not installed"
        wait_on_error
        exit 1
    fi
}

stop_containers() {
    print_info "Stopping Docker containers..."

    cd "$SCRIPT_DIR"

    # Check if containers are running
    if ! $COMPOSE_CMD ps | grep -q "Up"; then
        print_warning "No containers are running"
        return
    fi

    # Stop containers
    $COMPOSE_CMD down

    print_success "Containers stopped"
}

remove_volumes() {
    print_warning "⚠️  WARNING: This will DELETE all database data!"
    echo ""
    read -p "Are you sure you want to remove volumes? (yes/no): " -r
    echo ""

    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        print_info "Removing volumes..."

        cd "$SCRIPT_DIR"
        $COMPOSE_CMD down -v

        print_success "Volumes removed"
        print_info "Next start will create a fresh database"
    else
        print_info "Volume removal cancelled"
    fi
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -v|--volumes)
            REMOVE_VOLUMES=true
            shift
            ;;
        -h|--help)
            show_help
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use --help for usage information"
            wait_on_error
            exit 1
            ;;
    esac
done

# Main execution
echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║     OpenBookManager - Docker Stop                     ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

check_docker_compose

if [ "$REMOVE_VOLUMES" = true ]; then
    remove_volumes
else
    stop_containers
fi

echo ""
print_success "Done! Terminal will close in 3 seconds..."
for i in 3 2 1; do
    echo -n "$i... "
    sleep 1
done
echo ""
