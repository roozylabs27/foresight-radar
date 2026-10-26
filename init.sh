#!/usr/bin/env bash
# ==============================================================================
# Foresight Radar — Safe Harness Initialization Script
# Based on WalkingLabs Harness Engineering Subsystem 2 & 3 (Tools & Environment)
# ==============================================================================
set -euo pipefail

# ANSI Color Codes
BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[0;33m"
RED="\033[0;31m"
BLUE="\033[0;34m"
NC="\033[0m"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${REPO_ROOT}"

echo -e "${BOLD}${BLUE}================================================================${NC}"
echo -e "${BOLD}${BLUE}   Foresight Radar — Harness Environment Initializer            ${NC}"
echo -e "${BOLD}${BLUE}================================================================${NC}"
echo -e "Repository Root: ${BOLD}${REPO_ROOT}${NC}"
echo -e "Current Branch : ${BOLD}$(git -c safe.directory="*" rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'Unknown')${NC}"
echo -e "Latest Commit  : ${BOLD}$(git -c safe.directory="*" log -1 --oneline 2>/dev/null || echo 'Unknown')${NC}"
echo ""

FAILED_CHECKS=0

check_pass() {
    echo -e "  [${GREEN}✓ PASS${NC}] $1"
}

check_warn() {
    echo -e "  [${YELLOW}! WARN${NC}] $1"
}

check_fail() {
    echo -e "  [${RED}✗ FAIL${NC}] $1"
    FAILED_CHECKS=$((FAILED_CHECKS + 1))
}

# ------------------------------------------------------------------------------
# 1. Host Runtime Requirements (Node.js & NPM)
# ------------------------------------------------------------------------------
echo -e "${BOLD}[1/5] Checking Host Runtime Dependencies...${NC}"

if command -v node >/dev/null 2>&1; then
    NODE_VERSION=$(node -v)
    NODE_MAJOR=$(node -v | sed -E 's/v([0-9]+).*/\1/')
    if [ "${NODE_MAJOR}" -ge 18 ]; then
        check_pass "Node.js ${NODE_VERSION} (Requirement: >= 18.0.0)"
    else
        check_fail "Node.js ${NODE_VERSION} is installed, but >= 18.0.0 is required."
    fi
else
    check_fail "Node.js is not found on host PATH."
fi

if command -v npm >/dev/null 2>&1; then
    NPM_VERSION=$(npm -v)
    check_pass "NPM v${NPM_VERSION}"
else
    check_fail "NPM is not found on host PATH."
fi

# ------------------------------------------------------------------------------
# 2. Containerized Application Runtime (Docker / Laravel App)
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}[2/5] Checking Docker & Container Runtime...${NC}"

USE_DOCKER=false
if command -v docker >/dev/null 2>&1; then
    if docker ps >/dev/null 2>&1; then
        USE_DOCKER=true
        check_pass "Docker daemon is running and accessible."

        # Check required service containers
        for container in laravel_app laravel_mysql laravel_nginx laravel_queue; do
            if docker ps --format '{{.Names}}' | grep -q "^${container}\$"; then
                check_pass "Container '${container}' is active."
            else
                check_warn "Container '${container}' is not running (may be stopped or using different prefix)."
            fi
        done

        # Check PHP inside laravel_app container
        if docker exec laravel_app php -v >/dev/null 2>&1; then
            PHP_VER=$(docker exec laravel_app php -r "echo PHP_VERSION;")
            check_pass "Container PHP v${PHP_VER} (Requirement: PHP 8.2)"
        else
            check_warn "Unable to query PHP version inside 'laravel_app' container."
        fi
    else
        check_warn "Docker is installed but daemon is not responding. Checking host PHP fallback..."
    fi
else
    check_warn "Docker is not found on PATH. Checking host PHP fallback..."
fi

# Fallback: Host PHP if Docker is unavailable
if [ "${USE_DOCKER}" = false ]; then
    if command -v php >/dev/null 2>&1; then
        HOST_PHP_VER=$(php -r "echo PHP_VERSION;")
        check_pass "Host PHP v${HOST_PHP_VER} detected."
    else
        check_fail "Neither Docker container 'laravel_app' nor Host PHP is available."
    fi
fi

# ------------------------------------------------------------------------------
# 3. Environment Configuration (.env Integrity)
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}[3/5] Verifying Environment Configuration (.env)...${NC}"

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        check_warn "Created '.env' from '.env.example'. Please ensure APP_KEY and credentials are set."
        if [ "${USE_DOCKER}" = true ]; then
            docker exec laravel_app php artisan key:generate --quiet || true
        elif command -v php >/dev/null 2>&1; then
            php artisan key:generate --quiet || true
        fi
    else
        check_fail "Neither '.env' nor '.env.example' was found."
    fi
else
    check_pass "Configuration file '.env' is present."
fi

# Verify required keys without leaking values
if [ -f .env ]; then
    for key in APP_NAME APP_ENV APP_KEY DB_CONNECTION; do
        if grep -E "^${key}=" .env >/dev/null 2>&1; then
            # Verify it is not completely empty
            VAL=$(grep -E "^${key}=" .env | cut -d '=' -f2- | tr -d ' "')
            if [ -n "${VAL}" ]; then
                check_pass "Environment key '${key}' is configured."
            else
                check_warn "Environment key '${key}' exists but is empty."
            fi
        else
            check_warn "Environment key '${key}' is missing from '.env'."
        fi
    done
fi

# ------------------------------------------------------------------------------
# 4. Dependency Stores & Directory Permissions
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}[4/5] Checking Installed Dependencies & Directory Integrity...${NC}"

if [ -d "node_modules" ]; then
    check_pass "Frontend dependency store 'node_modules/' exists."
else
    check_warn "Frontend 'node_modules/' is missing. Run 'npm ci' to install."
fi

if [ -d "vendor" ]; then
    check_pass "Backend dependency store 'vendor/' exists."
else
    check_warn "Backend 'vendor/' is missing. Run 'composer install' to install."
fi

for dir in storage bootstrap/cache; do
    if [ -d "${dir}" ]; then
        check_pass "Directory '${dir}' exists."
    else
        mkdir -p "${dir}"
        check_pass "Directory '${dir}' created."
    fi
done

# ------------------------------------------------------------------------------
# 5. Baseline Smoke Check
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}[5/5] Executing Baseline Smoke Check...${NC}"

if [ "${USE_DOCKER}" = true ]; then
    if docker exec laravel_app php artisan --version >/dev/null 2>&1; then
        ARTISAN_VER=$(docker exec laravel_app php artisan --version)
        check_pass "Artisan runtime: ${ARTISAN_VER}"
    else
        check_fail "Failed to execute 'php artisan' inside 'laravel_app'."
    fi

    if docker exec laravel_app php artisan migrate:status >/dev/null 2>&1; then
        check_pass "Database connectivity & migration status verified."
    else
        check_warn "Database connectivity check failed or tables unmigrated."
    fi
elif command -v php >/dev/null 2>&1; then
    if php artisan --version >/dev/null 2>&1; then
        ARTISAN_VER=$(php artisan --version)
        check_pass "Host Artisan runtime: ${ARTISAN_VER}"
    else
        check_fail "Failed to execute 'php artisan' on host."
    fi
fi

# ------------------------------------------------------------------------------
# Summary & Result
# ------------------------------------------------------------------------------
echo ""
echo -e "${BOLD}${BLUE}================================================================${NC}"
if [ "${FAILED_CHECKS}" -eq 0 ]; then
    echo -e "${BOLD}${GREEN}   Initialization SUCCESS: Environment is ready and verified.   ${NC}"
    echo -e "${BOLD}${BLUE}================================================================${NC}"
    echo -e "Next recommended action: Run '${BOLD}./verify.sh${NC}' or start development."
    exit 0
else
    echo -e "${BOLD}${RED}   Initialization FAILED: ${FAILED_CHECKS} critical check(s) failed.      ${NC}"
    echo -e "${BOLD}${BLUE}================================================================${NC}"
    echo -e "Please address the failed items above before continuing."
    exit 1
fi

