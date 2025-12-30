#!/usr/bin/env bash

#############################################################################
# OpenBookManager - Docker Startup Script
#############################################################################
#
# This script starts the OpenBookManager application using Docker containers.
#
# Usage:
#   ./start-bookmanager.sh [options]
#
# Options:
#   -n, --no-browser      Don't automatically open the browser
#   -f, --foreground      Run in foreground (for debugging, blocks terminal)
#   -r, --rebuild         Force rebuild of Docker images
#   -h, --help            Show this help message
#
# What this script does:
#   1. Checks if Docker and Docker Compose are installed
#   2. Sets up the configuration file for Docker
#   3. Builds and starts the containers
#   4. Opens the application in your browser (unless -n flag is used)
#
# Services:
#   - Web:        http://localhost:8000 (OpenBookManager)
#   - phpMyAdmin: http://localhost:8080 (Database management)
#   - Database:   localhost:3307 (MariaDB)
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
AUTO_OPEN_BROWSER=true
DETACH=true
REBUILD=false
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

check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed or not in PATH"
        echo ""
        echo "Please install Docker:"
        echo "  - Visit: https://docs.docker.com/engine/install/"
        echo ""
        wait_on_error
        exit 1
    fi

    if ! docker info &> /dev/null; then
        print_error "Docker daemon is not running"
        echo ""
        echo "Please start Docker:"
        echo "  - Linux: sudo systemctl start docker"
        echo "  - macOS/Windows: Start Docker Desktop"
        echo ""
        wait_on_error
        exit 1
    fi

    DOCKER_VERSION=$(docker --version | cut -d' ' -f3 | tr -d ',')
    print_success "Docker is running (version: $DOCKER_VERSION)"
}

check_docker_compose() {
    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null 2>&1; then
        print_error "Docker Compose is not installed"
        echo ""
        echo "Please install Docker Compose:"
        echo "  - Visit: https://docs.docker.com/compose/install/"
        echo ""
        wait_on_error
        exit 1
    fi

    # Determine which command to use
    if docker compose version &> /dev/null 2>&1; then
        COMPOSE_CMD="docker compose"
    else
        COMPOSE_CMD="docker-compose"
    fi

    print_success "Docker Compose is available"
}

setup_config() {
    print_info "Setting up configuration..."

    # Create config directory if it doesn't exist
    mkdir -p "$SCRIPT_DIR/config"

    # Copy Docker config if config.php doesn't exist
    if [ ! -f "$SCRIPT_DIR/config/config.php" ]; then
        if [ -f "$SCRIPT_DIR/config/config.docker.php" ]; then
            cp "$SCRIPT_DIR/config/config.docker.php" "$SCRIPT_DIR/config/config.php"
            print_success "Configuration file created from Docker template"
        else
            print_warning "Docker config template not found"
            print_info "Using example config..."
            if [ -f "$SCRIPT_DIR/config/config.example.php" ]; then
                cp "$SCRIPT_DIR/config/config.example.php" "$SCRIPT_DIR/config/config.php"
                print_warning "Please edit config/config.php with Docker database settings:"
                echo "  - host: 'db'"
                echo "  - username: 'bookmanager'"
                echo "  - password: 'bookmanager123'"
            else
                print_error "No configuration template found"
                wait_on_error
                exit 1
            fi
        fi
    else
        print_info "Configuration file already exists"
    fi

    # Ensure uploads directory exists with correct permissions
    mkdir -p "$SCRIPT_DIR/public/uploads"
    chmod 775 "$SCRIPT_DIR/public/uploads"
}

start_containers() {
    print_info "Starting Docker containers..."
    echo ""

    cd "$SCRIPT_DIR"

    # Build options
    BUILD_ARGS=""
    if [ "$REBUILD" = true ]; then
        BUILD_ARGS="--build"
        print_info "Forcing rebuild of Docker images..."
    fi

    # Run options
    RUN_ARGS="-d"
    if [ "$DETACH" = false ]; then
        print_info "Starting in foreground mode (Ctrl+C to stop)..."
        RUN_ARGS=""
    fi

    # Start containers
    if [ -n "$RUN_ARGS" ]; then
        $COMPOSE_CMD up $BUILD_ARGS $RUN_ARGS
    else
        $COMPOSE_CMD up $BUILD_ARGS
    fi

    # If detached, wait for services to be ready and check status
    if [ "$DETACH" = true ]; then
        print_info "Waiting for services to be ready..."
        sleep 3

        # Check if containers are running
        if $COMPOSE_CMD ps | grep -q "Up"; then
            print_success "Containers are running"
        else
            print_error "Failed to start containers"
            echo ""
            echo "Check logs with: $COMPOSE_CMD logs"
            wait_on_error
            exit 1
        fi
    fi
}

open_browser() {
    local URL="http://localhost:8000"

    print_info "Opening browser at $URL"

    # Try different methods to open browser
    if command -v xdg-open &> /dev/null; then
        (sleep 2 && xdg-open "$URL") &> /dev/null &
    elif command -v gnome-open &> /dev/null; then
        (sleep 2 && gnome-open "$URL") &> /dev/null &
    elif command -v kde-open &> /dev/null; then
        (sleep 2 && kde-open "$URL") &> /dev/null &
    elif [ -n "$BROWSER" ]; then
        (sleep 2 && $BROWSER "$URL") &> /dev/null &
    else
        print_warning "Could not detect default browser"
        print_info "Please open manually: $URL"
    fi
}

show_info() {
    echo ""
    echo "╔═══════════════════════════════════════════════════════╗"
    echo "║     OpenBookManager is now running!                   ║"
    echo "╚═══════════════════════════════════════════════════════╝"
    echo ""
    echo "Services:"
    echo "  📚 OpenBookManager:  http://localhost:8000"
    echo "  🗄️  phpMyAdmin:      http://localhost:8080"
    echo "  💾 MariaDB:          localhost:3307"
    echo ""
    echo "Default Login:"
    echo "  Username: admin"
    echo "  Password: admin123"
    echo ""
    echo "Useful commands:"
    echo "  Stop:     ./stop-bookmanager.sh"
    echo "  Logs:     $COMPOSE_CMD logs -f"
    echo "  Restart:  $COMPOSE_CMD restart"
    echo ""
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -n|--no-browser)
            AUTO_OPEN_BROWSER=false
            shift
            ;;
        -f|--foreground)
            DETACH=false
            shift
            ;;
        -r|--rebuild)
            REBUILD=true
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
echo "║     OpenBookManager - Docker Startup                  ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

check_docker
check_docker_compose
setup_config
start_containers

# Show info and open browser (only in detached mode)
if [ "$DETACH" = true ]; then
    show_info

    if [ "$AUTO_OPEN_BROWSER" = true ]; then
        open_browser
    fi

    # Auto-close terminal after brief pause (for .desktop launches)
    echo ""
    print_success "All done! Terminal will close in 3 seconds..."
    for i in 3 2 1; do
        echo -n "$i... "
        sleep 1
    done
    echo ""
else
    # Foreground mode: script stays attached to docker-compose
    # Info is shown by docker-compose logs
    # Ctrl+C will stop the containers
    print_info "Running in foreground mode. Press Ctrl+C to stop."
fi
