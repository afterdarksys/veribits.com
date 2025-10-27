#!/bin/bash
# VeriBits CLI Test Script
# Tests all three CLI implementations
# © After Dark Systems, LLC

set -e

echo "🧪 VeriBits CLI Test Suite"
echo "=========================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PASS_COUNT=0
FAIL_COUNT=0

# Test function
test_command() {
    local cli_name=$1
    local command=$2
    local expected=$3

    echo -n "  Testing $command... "

    if output=$($command 2>&1); then
        if [[ -z "$expected" ]] || echo "$output" | grep -q "$expected"; then
            echo -e "${GREEN}✅ PASS${NC}"
            ((PASS_COUNT++))
            return 0
        else
            echo -e "${RED}❌ FAIL${NC} (unexpected output)"
            echo "    Expected: $expected"
            echo "    Got: ${output:0:100}"
            ((FAIL_COUNT++))
            return 1
        fi
    else
        echo -e "${RED}❌ FAIL${NC} (command failed)"
        ((FAIL_COUNT++))
        return 1
    fi
}

# ==================
# Test Node.js CLI
# ==================
echo -e "${BLUE}Testing Node.js CLI (vb)${NC}"

if ! command -v vb &> /dev/null; then
    echo -e "${RED}❌ vb command not found. Run 'npm link' in cli/nodejs/${NC}"
    exit 1
fi

test_command "nodejs" "vb --version" "1.0.0"
test_command "nodejs" "vb hash test --algorithm sha256" "9f86d081"
test_command "nodejs" "vb url-encode test" "test"
test_command "nodejs" "vb base64 test" "dGVzdA=="
test_command "nodejs" "vb --help" "Commands:"

echo ""

# ==================
# Test Python CLI
# ==================
echo -e "${BLUE}Testing Python CLI (veribits)${NC}"

if ! command -v veribits &> /dev/null; then
    echo -e "${YELLOW}⚠️  veribits command not found, using direct script${NC}"
    PYTHON_CMD="python3 cli/python/veribits.py"
else
    PYTHON_CMD="veribits"
fi

test_command "python" "$PYTHON_CMD --version" "1.0.0"
test_command "python" "$PYTHON_CMD hash test --algorithm sha256" "9f86d081"
test_command "python" "$PYTHON_CMD url-encode test" "test"
test_command "python" "$PYTHON_CMD base64 test" "dGVzdA=="
test_command "python" "$PYTHON_CMD --help" "VeriBits CLI"

echo ""

# ==================
# Test PHP CLI
# ==================
echo -e "${BLUE}Testing PHP CLI (veribits.php)${NC}"

if [[ ! -f "cli/veribits.php" ]]; then
    echo -e "${RED}❌ cli/veribits.php not found${NC}"
    exit 1
fi

test_command "php" "php cli/veribits.php health" "status"
test_command "php" "php cli/veribits.php help" "VeriBits CLI"

echo ""

# ==================
# Feature Count Test
# ==================
echo -e "${BLUE}Feature Count Verification${NC}"

echo -n "  Node.js commands: "
NODE_COUNT=$(vb --help 2>&1 | grep -c "^\s*[a-z-]" || true)
echo -e "${GREEN}$NODE_COUNT${NC}"

echo -n "  Python commands: "
PYTHON_COUNT=$($PYTHON_CMD --help 2>&1 | grep -o "{[^}]*}" | tr ',' '\n' | wc -l | tr -d ' ')
echo -e "${GREEN}$PYTHON_COUNT${NC}"

echo -n "  PHP commands: "
PHP_COUNT=$(grep -c "case '" cli/veribits.php)
echo -e "${GREEN}$PHP_COUNT${NC}"

echo ""

# ==================
# Summary
# ==================
echo "=========================="
echo "Test Summary:"
echo -e "  ${GREEN}Passed: $PASS_COUNT${NC}"
echo -e "  ${RED}Failed: $FAIL_COUNT${NC}"
echo ""

if [[ $FAIL_COUNT -eq 0 ]]; then
    echo -e "${GREEN}🎉 All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}❌ Some tests failed${NC}"
    exit 1
fi
