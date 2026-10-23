#!/usr/bin/env bash
# ==============================================================================
# Foresight Radar — Standard Verification Contract Script
# Based on WalkingLabs Harness Engineering Subsystem 2 & 5 (Tools & Feedback)
# ==============================================================================
set -u

# ANSI Color Codes
BOLD="\033[1m"
GREEN="\033[0;32m"
YELLOW="\033[0;33m"
RED="\033[0;31m"
BLUE="\033[0;34m"
CYAN="\033[0;36m"
NC="\033[0m"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "${REPO_ROOT}"

# Parse command line flags
RUN_BACKEND=true
RUN_FRONTEND=true
RUN_PHPUNIT=true
RUN_E2E=false

for arg in "$@"; do
    case "${arg}" in
        --quick)
            RUN_PHPUNIT=false
            ;;
        --backend-only)
            RUN_FRONTEND=false
            ;;
        --frontend-only)
            RUN_BACKEND=false
            ;;
        --e2e|--smoke)
            RUN_E2E=true
            ;;
        --help|-h)
            echo "Usage: ./verify.sh [OPTIONS]"
            echo "Options:"
            echo "  --quick          Skip full PHPUnit test suite (Pint, PHPStan, Build, Audit)"
            echo "  --backend-only   Run only backend quality checks (Pint, PHPStan, PHPUnit)"
            echo "  --frontend-only  Run only frontend quality checks (Vite Build, NPM Audit)"
            echo "  --e2e, --smoke   Include Playwright headless E2E smoke tests"
            echo "  --help, -h       Display this help message"
            exit 0
            ;;
        *)
            echo "Unknown argument: ${arg}"
            echo "Run './verify.sh --help' for usage."
            exit 1
            ;;
    esac
done

echo -e "${BOLD}${BLUE}================================================================${NC}"
echo -e "${BOLD}${BLUE}   Foresight Radar — Quality Contract Verification Engine       ${NC}"
echo -e "${BOLD}${BLUE}================================================================${NC}"
echo -e "Repository Root: ${BOLD}${REPO_ROOT}${NC}"
echo -e "Current Branch : ${BOLD}$(git -c safe.directory="*" rev-parse --abbrev-ref HEAD 2>/dev/null || echo 'Unknown')${NC}"
echo -e "Latest Commit  : ${BOLD}$(git -c safe.directory="*" log -1 --oneline 2>/dev/null || echo 'Unknown')${NC}"
echo -e "Start Time     : $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# Execution tracking
CHECK_NAMES=()
CHECK_STATUSES=()
CHECK_DURATIONS=()
OVERALL_SUCCESS=true

run_check() {
    local name="$1"
    local command="$2"
    local skip="${3:-false}"

    CHECK_NAMES+=("${name}")

    if [ "${skip}" = "true" ]; then
        echo -e "${CYAN}⊘ [SKIPPED]${NC} ${BOLD}${name}${NC}"
        CHECK_STATUSES+=("SKIPPED")
        CHECK_DURATIONS+=("-")
        return 0
    fi

    echo -e "${BLUE}▶ [RUNNING]${NC} ${BOLD}${name}${NC}..."
    local start_time
    start_time=$(date +%s)

    local exit_code=0
    # Run command and capture exit code
    eval "${command}" || exit_code=$?

    local end_time
    end_time=$(date +%s)
    local duration=$((end_time - start_time))

    if [ "${exit_code}" -eq 0 ]; then
        echo -e "  [${GREEN}✓ PASS${NC}] ${name} (${duration}s)"
        CHECK_STATUSES+=("PASS")
        CHECK_DURATIONS+=("${duration}s")
    else
        echo -e "  [${RED}✗ FAIL${NC}] ${name} (${duration}s, exit code ${exit_code})"
        CHECK_STATUSES+=("FAIL")
        CHECK_DURATIONS+=("${duration}s")
        OVERALL_SUCCESS=false
    fi
    echo ""
}

# Helper to run npm transparently across Linux CI, WSL, and Windows
run_npm() {
    if command -v cmd.exe >/dev/null 2>&1 && [ ! -d "node_modules/@rollup/rollup-linux-x64-gnu" ]; then
        cmd.exe /c "npm $*"
    else
        npm "$@"
    fi
}

# ------------------------------------------------------------------------------
# 1. PHP Code Style (Laravel Pint)
# ------------------------------------------------------------------------------
if [ "${RUN_BACKEND}" = true ]; then
    run_check "PHP Code Style (Pint)" \
        "docker exec laravel_app ./vendor/bin/pint --test"
else
    run_check "PHP Code Style (Pint)" "" true
fi

# ------------------------------------------------------------------------------
# 2. PHP Static Analysis (Larastan Level 5)
# ------------------------------------------------------------------------------
if [ "${RUN_BACKEND}" = true ]; then
    run_check "Static Analysis (Larastan Level 5)" \
        "docker exec laravel_app ./vendor/bin/phpstan analyse --memory-limit=2G"
else
    run_check "Static Analysis (Larastan Level 5)" "" true
fi

# ------------------------------------------------------------------------------
# 3. Automated Backend Tests (PHPUnit Suite)
# ------------------------------------------------------------------------------
if [ "${RUN_BACKEND}" = true ] && [ "${RUN_PHPUNIT}" = true ]; then
    run_check "Backend Test Suite (PHPUnit)" \
        "docker exec laravel_app php artisan test"
else
    run_check "Backend Test Suite (PHPUnit)" "" true
fi

# ------------------------------------------------------------------------------
# 4. TypeScript Typecheck (tsc)
# ------------------------------------------------------------------------------
if [ "${RUN_FRONTEND}" = true ]; then
    run_check "TypeScript Typecheck (tsc)" \
        "run_npm run typecheck"
else
    run_check "TypeScript Typecheck (tsc)" "" true
fi

# ------------------------------------------------------------------------------
# 5. Frontend Production Build (Vite)
# ------------------------------------------------------------------------------
if [ "${RUN_FRONTEND}" = true ]; then
    run_check "Frontend Production Build (Vite)" \
        "run_npm run build"
else
    run_check "Frontend Production Build (Vite)" "" true
fi

# ------------------------------------------------------------------------------
# 5. Security Audit Gate (NPM Critical Vulnerabilities)
# ------------------------------------------------------------------------------
if [ "${RUN_FRONTEND}" = true ]; then
    run_check "Security Audit Gate (NPM Critical)" \
        "run_npm audit --audit-level=critical"
else
    run_check "Security Audit Gate (NPM Critical)" "" true
fi

# ------------------------------------------------------------------------------
# 6. E2E Headless Smoke Test (Optional)
# ------------------------------------------------------------------------------
if [ "${RUN_E2E}" = true ]; then
    run_check "E2E Smoke Tests (Playwright)" \
        "run_npm run test:e2e:smoke"
else
    run_check "E2E Smoke Tests (Playwright)" "" true
fi

# ------------------------------------------------------------------------------
# Summary Table
# ------------------------------------------------------------------------------
echo -e "${BOLD}${BLUE}================================================================${NC}"
echo -e "${BOLD}${BLUE}                  VERIFICATION SUMMARY TABLE                    ${NC}"
echo -e "${BOLD}${BLUE}================================================================${NC}"
printf "%-38s %-12s %-10s\n" "Quality Check Gate" "Status" "Duration"
echo "----------------------------------------------------------------"

for i in "${!CHECK_NAMES[@]}"; do
    name="${CHECK_NAMES[$i]}"
    status="${CHECK_STATUSES[$i]}"
    dur="${CHECK_DURATIONS[$i]}"

    case "${status}" in
        PASS)
            STATUS_STR="${GREEN}PASS${NC}"
            ;;
        FAIL)
            STATUS_STR="${RED}FAIL${NC}"
            ;;
        SKIPPED)
            STATUS_STR="${CYAN}SKIPPED${NC}"
            ;;
        *)
            STATUS_STR="${status}"
            ;;
    esac

    printf "%-38s %-21b %-10s\n" "${name}" "${STATUS_STR}" "${dur}"
done

echo -e "${BOLD}${BLUE}================================================================${NC}"

if [ "${OVERALL_SUCCESS}" = true ]; then
    echo -e "${BOLD}${GREEN}   VERIFICATION PASSED: All executed quality gates satisfied.   ${NC}"
    echo -e "${BOLD}${BLUE}================================================================${NC}"
    exit 0
else
    echo -e "${BOLD}${RED}   VERIFICATION FAILED: One or more quality gates failed.       ${NC}"
    echo -e "${BOLD}${BLUE}================================================================${NC}"
    exit 1
fi

