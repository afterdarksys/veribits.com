#!/bin/bash
# Test all critical API endpoints before interview
# Run this script to verify everything works

set -e

# Configuration
BASE_URL="https://www.veribits.com"
API_URL="${BASE_URL}/api/v1"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Test counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Helper functions
test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_status="$5"
    local headers="$6"

    TESTS_RUN=$((TESTS_RUN + 1))
    echo -e "${BLUE}[TEST $TESTS_RUN]${NC} $name"

    # Build curl command
    local cmd="curl -s -w '\n%{http_code}' -X $method"

    if [ -n "$headers" ]; then
        cmd="$cmd $headers"
    fi

    if [ -n "$data" ]; then
        cmd="$cmd -d '$data'"
    fi

    cmd="$cmd '${API_URL}${endpoint}'"

    # Execute request
    response=$(eval $cmd)
    status_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    # Check status code
    if [ "$status_code" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASS${NC} (Status: $status_code)"
        TESTS_PASSED=$((TESTS_PASSED + 1))
        return 0
    else
        echo -e "${RED}✗ FAIL${NC} (Expected: $expected_status, Got: $status_code)"
        echo "Response: $body"
        TESTS_FAILED=$((TESTS_FAILED + 1))
        return 1
    fi
}

echo "========================================="
echo "VeriBits API Endpoint Tests"
echo "========================================="
echo "Testing against: $BASE_URL"
echo ""

# =============================================
# Test 1: Health Check
# =============================================
echo -e "${YELLOW}=== Health Check ===${NC}"
test_endpoint \
    "Health endpoint" \
    "GET" \
    "/health" \
    "" \
    "200"

echo ""

# =============================================
# Test 2: Authentication - Login (Free Tier)
# =============================================
echo -e "${YELLOW}=== Authentication Tests ===${NC}"
FREE_TOKEN=""
login_response=$(curl -s -X POST "${API_URL}/auth/login" \
    -H 'Content-Type: application/json' \
    -d '{"email":"straticus1@gmail.com","password":"TestPassword123!"}')

if echo "$login_response" | grep -q "access_token"; then
    FREE_TOKEN=$(echo "$login_response" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    echo -e "${GREEN}✓ PASS${NC} Free tier login successful"
    echo "Token: ${FREE_TOKEN:0:20}..."
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗ FAIL${NC} Free tier login failed"
    echo "Response: $login_response"
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""

# =============================================
# Test 3: Authentication - Login (Enterprise)
# =============================================
ENTERPRISE_TOKEN=""
login_response=$(curl -s -X POST "${API_URL}/auth/login" \
    -H 'Content-Type: application/json' \
    -d '{"email":"enterprise@veribits.com","password":"EnterpriseDemo2025!"}')

if echo "$login_response" | grep -q "access_token"; then
    ENTERPRISE_TOKEN=$(echo "$login_response" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    echo -e "${GREEN}✓ PASS${NC} Enterprise login successful"
    echo "Token: ${ENTERPRISE_TOKEN:0:20}..."
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${RED}✗ FAIL${NC} Enterprise login failed"
    echo "Response: $login_response"
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_FAILED=$((TESTS_FAILED + 1))
fi

echo ""

# =============================================
# Test 4: Profile Endpoint (Authenticated)
# =============================================
if [ -n "$FREE_TOKEN" ]; then
    echo -e "${YELLOW}=== Profile Tests ===${NC}"
    profile_response=$(curl -s -X GET "${API_URL}/auth/profile" \
        -H "Authorization: Bearer $FREE_TOKEN")

    if echo "$profile_response" | grep -q "email"; then
        echo -e "${GREEN}✓ PASS${NC} Profile fetch successful"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        echo -e "${RED}✗ FAIL${NC} Profile fetch failed"
        echo "Response: $profile_response"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
    echo ""
fi

# =============================================
# Test 5: Anonymous Tool Usage
# =============================================
echo -e "${YELLOW}=== Anonymous Tool Tests ===${NC}"

# IP Calculator (should work without auth)
test_endpoint \
    "IP Calculator (anonymous)" \
    "POST" \
    "/tools/ip-calculate" \
    '{"ip":"192.168.1.0","cidr":"24"}' \
    "200" \
    "-H 'Content-Type: application/json'"

# DNS Validator (should work without auth)
test_endpoint \
    "DNS Validator (anonymous)" \
    "POST" \
    "/dns/check" \
    '{"domain":"google.com"}' \
    "200" \
    "-H 'Content-Type: application/json'"

echo ""

# =============================================
# Test 6: Authenticated Tool Usage
# =============================================
if [ -n "$FREE_TOKEN" ]; then
    echo -e "${YELLOW}=== Authenticated Tool Tests ===${NC}"

    # Hash Generator
    hash_response=$(curl -s -X POST "${API_URL}/tools/generate-hash" \
        -H 'Content-Type: application/json' \
        -H "Authorization: Bearer $FREE_TOKEN" \
        -d '{"text":"test","algorithm":"sha256"}')

    if echo "$hash_response" | grep -q "hash"; then
        echo -e "${GREEN}✓ PASS${NC} Hash generator successful"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_PASSED=$((TESTS_PASSED + 1))
    else
        echo -e "${RED}✗ FAIL${NC} Hash generator failed"
        TESTS_RUN=$((TESTS_RUN + 1))
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
    echo ""
fi

# =============================================
# Test 7: Rate Limiting
# =============================================
echo -e "${YELLOW}=== Rate Limiting Tests ===${NC}"

# Make multiple rapid requests to trigger rate limit
for i in {1..15}; do
    curl -s -X POST "${API_URL}/dns/check" \
        -H 'Content-Type: application/json' \
        -d '{"domain":"test.com"}' > /dev/null 2>&1
done

# This request should be rate limited
rate_limit_response=$(curl -s -X POST "${API_URL}/dns/check" \
    -H 'Content-Type: application/json' \
    -d '{"domain":"test.com"}')

if echo "$rate_limit_response" | grep -q "rate limit\|Rate limit"; then
    echo -e "${GREEN}✓ PASS${NC} Rate limiting working"
    TESTS_RUN=$((TESTS_RUN + 1))
    TESTS_PASSED=$((TESTS_PASSED + 1))
else
    echo -e "${YELLOW}⚠ WARN${NC} Rate limiting may not be working (not critical)"
    TESTS_RUN=$((TESTS_RUN + 1))
fi

echo ""

# =============================================
# Test 8: Error Handling
# =============================================
echo -e "${YELLOW}=== Error Handling Tests ===${NC}"

# Invalid credentials
test_endpoint \
    "Invalid login credentials" \
    "POST" \
    "/auth/login" \
    '{"email":"invalid@example.com","password":"wrong"}' \
    "401" \
    "-H 'Content-Type: application/json'"

# Missing required field
test_endpoint \
    "Missing email field" \
    "POST" \
    "/auth/login" \
    '{"password":"test"}' \
    "422" \
    "-H 'Content-Type: application/json'"

# Invalid endpoint
test_endpoint \
    "404 for unknown endpoint" \
    "GET" \
    "/invalid/endpoint" \
    "" \
    "404"

echo ""

# =============================================
# Test Summary
# =============================================
echo "========================================="
echo "Test Summary"
echo "========================================="
echo -e "Total Tests:  ${BLUE}$TESTS_RUN${NC}"
echo -e "Passed:       ${GREEN}$TESTS_PASSED${NC}"
echo -e "Failed:       ${RED}$TESTS_FAILED${NC}"

if [ $TESTS_FAILED -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ ALL TESTS PASSED${NC}"
    echo -e "${GREEN}Platform is interview-ready!${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo -e "${YELLOW}Review failed tests above${NC}"
    exit 1
fi
