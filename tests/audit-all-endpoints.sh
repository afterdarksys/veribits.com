#!/bin/bash

# Comprehensive API Endpoint Audit Script
# Tests all endpoints on VeriBits platform for 4xx/5xx errors

BASE_URL="${1:-https://veribits.com}"
RESULTS_FILE="/tmp/veribits-audit-$(date +%Y%m%d-%H%M%S).txt"

echo "========================================="
echo "VeriBits API Endpoint Audit"
echo "========================================="
echo "Base URL: $BASE_URL"
echo "Results: $RESULTS_FILE"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
TOTAL=0
PASSED=0
FAILED=0
BROKEN=0

# Test function
test_endpoint() {
    local method=$1
    local path=$2
    local data=$3
    local description=$4
    local expect_auth=${5:-false}

    TOTAL=$((TOTAL + 1))

    echo -n "Testing: $description ... "

    if [ "$method" == "GET" ]; then
        response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$path" -H "Accept: application/json")
    else
        response=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "$BASE_URL$path" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "$data")
    fi

    # Determine if this is a success
    if [ $expect_auth == "true" ] && [ $response -eq 401 ]; then
        echo -e "${GREEN}✓${NC} (401 - Auth Required)"
        PASSED=$((PASSED + 1))
        echo "✓ $method $path -> $response (Expected auth)" >> "$RESULTS_FILE"
    elif [ $response -lt 400 ]; then
        echo -e "${GREEN}✓${NC} ($response)"
        PASSED=$((PASSED + 1))
        echo "✓ $method $path -> $response" >> "$RESULTS_FILE"
    elif [ $response -eq 422 ]; then
        echo -e "${YELLOW}○${NC} (422 - Validation Error - Expected)"
        PASSED=$((PASSED + 1))
        echo "○ $method $path -> $response (Validation)" >> "$RESULTS_FILE"
    elif [ $response -eq 429 ]; then
        echo -e "${YELLOW}○${NC} (429 - Rate Limit - Expected)"
        PASSED=$((PASSED + 1))
        echo "○ $method $path -> $response (Rate Limited)" >> "$RESULTS_FILE"
    elif [ $response -ge 500 ]; then
        echo -e "${RED}✗${NC} ($response - SERVER ERROR)"
        BROKEN=$((BROKEN + 1))
        echo "✗ $method $path -> $response *** SERVER ERROR ***" >> "$RESULTS_FILE"
    else
        echo -e "${YELLOW}!${NC} ($response)"
        FAILED=$((FAILED + 1))
        echo "! $method $path -> $response" >> "$RESULTS_FILE"
    fi
}

echo "Starting endpoint audit..." > "$RESULTS_FILE"
echo "" >> "$RESULTS_FILE"

# Health & System Endpoints
echo -e "\n${YELLOW}=== Health & System Endpoints ===${NC}"
test_endpoint "GET" "/api/v1/health" "" "Health Check"
test_endpoint "GET" "/api/v1/limits/anonymous" "" "Anonymous Limits"

# Authentication Endpoints
echo -e "\n${YELLOW}=== Authentication Endpoints ===${NC}"
test_endpoint "POST" "/api/v1/auth/register" '{"email":"test@example.com","password":"Test123!"}' "Registration"
test_endpoint "POST" "/api/v1/auth/login" '{"email":"test@example.com","password":"wrong"}' "Login (Bad Creds)"
test_endpoint "POST" "/api/v1/auth/logout" '{}' "Logout" true
test_endpoint "GET" "/api/v1/auth/profile" "" "Get Profile" true

# Crypto Validation
echo -e "\n${YELLOW}=== Cryptocurrency Validation ===${NC}"
test_endpoint "POST" "/api/v1/crypto/validate" '{"address":"1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa","chain":"bitcoin"}' "Generic Crypto Validation"
test_endpoint "POST" "/api/v1/crypto/validate/bitcoin" '{"address":"1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa"}' "Bitcoin Validation"
test_endpoint "POST" "/api/v1/crypto/validate/ethereum" '{"address":"0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb"}' "Ethereum Validation"

# SSL/TLS Tools
echo -e "\n${YELLOW}=== SSL/TLS Tools ===${NC}"
test_endpoint "POST" "/api/v1/ssl/validate" '{"domain":"google.com"}' "SSL Validation"
test_endpoint "POST" "/api/v1/ssl/generate-csr" '{"commonName":"test.com","country":"US"}' "Generate CSR"
test_endpoint "POST" "/api/v1/ssl/resolve-chain" '{"certificate":"-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----"}' "Resolve SSL Chain"

# Email Verification
echo -e "\n${YELLOW}=== Email Verification Tools ===${NC}"
test_endpoint "POST" "/api/v1/email/check-disposable" '{"email":"test@gmail.com"}' "Check Disposable Email"
test_endpoint "POST" "/api/v1/email/analyze-spf" '{"domain":"google.com"}' "Analyze SPF"
test_endpoint "POST" "/api/v1/email/analyze-dmarc" '{"domain":"google.com"}' "Analyze DMARC"
test_endpoint "POST" "/api/v1/email/analyze-mx" '{"domain":"google.com"}' "Analyze MX"

# DNS Tools
echo -e "\n${YELLOW}=== DNS Tools ===${NC}"
test_endpoint "POST" "/api/v1/dns/check" '{"domain":"google.com"}' "DNS Check"
test_endpoint "POST" "/api/v1/verify/dns" '{"domain":"google.com"}' "DNS Verify"

# JWT Tools
echo -e "\n${YELLOW}=== JWT Tools ===${NC}"
test_endpoint "POST" "/api/v1/jwt/decode" '{"token":"eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U"}' "JWT Decode"
test_endpoint "POST" "/api/v1/jwt/validate" '{"token":"test"}' "JWT Validate"

# Developer Tools
echo -e "\n${YELLOW}=== Developer Tools ===${NC}"
test_endpoint "POST" "/api/v1/tools/regex-test" '{"pattern":"test","text":"testing"}' "Regex Tester"
test_endpoint "POST" "/api/v1/tools/generate-hash" '{"data":"test","algorithm":"sha256"}' "Generate Hash"
test_endpoint "POST" "/api/v1/tools/url-encoder" '{"data":"test data"}' "URL Encoder"
test_endpoint "POST" "/api/v1/tools/hash-validator" '{"hash":"test","data":"test"}' "Hash Validator"

# Network Tools
echo -e "\n${YELLOW}=== Network Tools ===${NC}"
test_endpoint "POST" "/api/v1/tools/ip-calculate" '{"cidr":"192.168.1.0/24"}' "IP Calculator"
test_endpoint "POST" "/api/v1/tools/whois" '{"domain":"google.com"}' "WHOIS Lookup"

# BGP Tools
echo -e "\n${YELLOW}=== BGP Intelligence ===${NC}"
test_endpoint "POST" "/api/v1/bgp/asn" '{"asn":"AS13335"}' "BGP AS Lookup"
test_endpoint "POST" "/api/v1/bgp/prefix" '{"prefix":"1.1.1.0/24"}' "BGP Prefix Lookup"

# Security Scanning Tools (NEW)
echo -e "\n${YELLOW}=== Security Scanning Tools ===${NC}"
test_endpoint "POST" "/api/v1/security/iam-policy/analyze" '{"policy_name":"Test","policy_document":{"Version":"2012-10-17","Statement":[{"Effect":"Allow","Action":"*","Resource":"*"}]}}' "IAM Policy Analyzer"
test_endpoint "POST" "/api/v1/security/secrets/scan" '{"content":"AWS_KEY=AKIAIOSFODNN7EXAMPLE","source_name":"test.js"}' "Secrets Scanner"
test_endpoint "POST" "/api/v1/security/db-connection/audit" '{"connection_string":"postgresql://admin:password123@db.example.com:5432/mydb"}' "DB Connection Auditor"

# Security Headers
echo -e "\n${YELLOW}=== Security Headers ===${NC}"
test_endpoint "POST" "/api/v1/tools/security-headers" '{"url":"https://google.com"}' "Security Headers Analyzer"

# Have I Been Pwned
echo -e "\n${YELLOW}=== Have I Been Pwned ===${NC}"
test_endpoint "POST" "/api/v1/hibp/check-password" '{"password":"password123"}' "Check Password"
test_endpoint "GET" "/api/v1/hibp/stats" "" "HIBP Stats"

# Tool Search
echo -e "\n${YELLOW}=== Tool Search ===${NC}"
test_endpoint "GET" "/api/v1/tools/list" "" "List All Tools"
test_endpoint "GET" "/api/v1/tools/search?q=ssl" "" "Search Tools"

# Protected Endpoints (should require auth)
echo -e "\n${YELLOW}=== Protected Endpoints ===${NC}"
test_endpoint "GET" "/api/v1/api-keys" "" "List API Keys" true
test_endpoint "GET" "/api/v1/verifications" "" "List Verifications" true
test_endpoint "GET" "/api/v1/audit/logs" "" "Audit Logs" true
test_endpoint "GET" "/api/v1/billing/account" "" "Billing Account" true

# Summary
echo ""
echo "========================================="
echo "Audit Complete!"
echo "========================================="
echo -e "Total Endpoints: $TOTAL"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${YELLOW}Client Errors: $FAILED${NC}"
echo -e "${RED}SERVER ERRORS (500): $BROKEN${NC}"
echo ""
echo "Detailed results: $RESULTS_FILE"
echo ""

if [ $BROKEN -gt 0 ]; then
    echo -e "${RED}⚠ WARNING: $BROKEN endpoints returning 500 errors!${NC}"
    echo "Broken endpoints:"
    grep "SERVER ERROR" "$RESULTS_FILE"
    exit 1
else
    echo -e "${GREEN}✓ All endpoints operational!${NC}"
    exit 0
fi
