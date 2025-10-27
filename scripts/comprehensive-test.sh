#!/bin/bash

# VeriBits Comprehensive Site Testing Script
# Tests all pages, tools, forms, and API endpoints

BASE_URL="${1:-https://www.veribits.com}"
OUTPUT_FILE="test-results-$(date +%Y%m%d-%H%M%S).json"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Results array
declare -a FAILURES

echo "==========================================="
echo "VeriBits Comprehensive Site Test"
echo "Base URL: $BASE_URL"
echo "==========================================="
echo ""

# Function to test a URL
test_url() {
    local url="$1"
    local expected_status="${2:-200}"
    local description="$3"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -L "$url")

    if [ "$HTTP_CODE" == "$expected_status" ]; then
        echo -e "${GREEN}✓${NC} $description ($url)"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        echo -e "${RED}✗${NC} $description ($url) - Got $HTTP_CODE, expected $expected_status"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        FAILURES+=("$description|$url|$HTTP_CODE|$expected_status")
        return 1
    fi
}

# Function to test POST endpoint
test_post() {
    local url="$1"
    local data="$2"
    local description="$3"
    local check_string="$4"

    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    RESPONSE=$(curl -s -X POST "$url" \
        -H "Content-Type: application/json" \
        -d "$data")

    if echo "$RESPONSE" | grep -q "$check_string"; then
        echo -e "${GREEN}✓${NC} $description"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        return 0
    else
        echo -e "${RED}✗${NC} $description - Response: $RESPONSE"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        FAILURES+=("$description|$url|POST|Failed")
        return 1
    fi
}

echo -e "${BLUE}Testing Main Pages...${NC}"
test_url "$BASE_URL/" 200 "Homepage"
test_url "$BASE_URL/tools.php" 200 "Tools listing page"
test_url "$BASE_URL/docs.php" 200 "Documentation page"
test_url "$BASE_URL/pricing.php" 200 "Pricing page"
test_url "$BASE_URL/about.php" 200 "About page"
test_url "$BASE_URL/cli.php" 200 "CLI info page"
test_url "$BASE_URL/login.php" 200 "Login page"
test_url "$BASE_URL/signup.php" 200 "Signup page"
test_url "$BASE_URL/dashboard.php" 200 "Dashboard page"
test_url "$BASE_URL/settings.php" 200 "Settings page"
echo ""

echo -e "${BLUE}Testing Developer Tools...${NC}"
test_url "$BASE_URL/tool/jwt-debugger.php" 200 "JWT Debugger"
test_url "$BASE_URL/tool/regex-tester.php" 200 "Regex Tester"
test_url "$BASE_URL/tool/url-encoder.php" 200 "URL Encoder"
test_url "$BASE_URL/tool/hash-validator.php" 200 "Hash Validator"
test_url "$BASE_URL/tool/base64-encoder.php" 200 "Base64 Encoder"
echo ""

echo -e "${BLUE}Testing Network Tools...${NC}"
test_url "$BASE_URL/tool/ip-calculator.php" 200 "IP Calculator"
test_url "$BASE_URL/tool/visual-traceroute.php" 200 "Visual Traceroute"
test_url "$BASE_URL/tool/bgp-intelligence.php" 200 "BGP Intelligence"
test_url "$BASE_URL/tool/pcap-analyzer.php" 200 "PCAP Analyzer"
echo ""

echo -e "${BLUE}Testing DNS Tools...${NC}"
test_url "$BASE_URL/tool/dns-validator.php" 200 "DNS Validator"
test_url "$BASE_URL/tool/zone-validator.php" 200 "Zone Validator"
test_url "$BASE_URL/tool/dnssec-validator.php" 200 "DNSSEC Validator"
test_url "$BASE_URL/tool/dns-propagation.php" 200 "DNS Propagation Checker"
test_url "$BASE_URL/tool/reverse-dns.php" 200 "Reverse DNS"
test_url "$BASE_URL/tool/dns-converter.php" 200 "DNS Converter"
echo ""

echo -e "${BLUE}Testing Security Tools...${NC}"
test_url "$BASE_URL/tool/ssl-generator.php" 200 "SSL Certificate Generator"
test_url "$BASE_URL/tool/code-signing.php" 200 "Code Signing"
test_url "$BASE_URL/tool/crypto-validator.php" 200 "Crypto Validator"
test_url "$BASE_URL/tool/rbl-check.php" 200 "RBL Check"
test_url "$BASE_URL/tool/smtp-relay-check.php" 200 "SMTP Relay Check"
test_url "$BASE_URL/tool/steganography.php" 200 "Steganography Detector"
test_url "$BASE_URL/tool/security-headers.php" 200 "Security Headers Analyzer"
test_url "$BASE_URL/tool/secrets-scanner.php" 200 "Secrets Scanner"
test_url "$BASE_URL/tool/pgp-validator.php" 200 "PGP Validator"
test_url "$BASE_URL/tool/docker-scanner.php" 200 "Docker Security Scanner"
test_url "$BASE_URL/tool/terraform-scanner.php" 200 "Terraform Scanner"
test_url "$BASE_URL/tool/kubernetes-validator.php" 200 "Kubernetes Validator"
test_url "$BASE_URL/tool/firewall-editor.php" 200 "Firewall Configuration Editor"
test_url "$BASE_URL/tool/iam-policy-analyzer.php" 200 "IAM Policy Analyzer"
test_url "$BASE_URL/tool/db-connection-auditor.php" 200 "DB Connection Auditor"
echo ""

echo -e "${BLUE}Testing File Tools...${NC}"
test_url "$BASE_URL/tool/file-magic.php" 200 "File Magic Number Analyzer"
test_url "$BASE_URL/tool/cert-converter.php" 200 "Certificate Converter"
echo ""

echo -e "${BLUE}Testing API Endpoints...${NC}"
test_url "$BASE_URL/api/v1/health" 200 "Health check endpoint"

# Test POST endpoints
echo ""
echo -e "${BLUE}Testing API POST Endpoints...${NC}"

# Test login with invalid credentials (should NOT return "field is required" error)
test_post "$BASE_URL/api/v1/auth/login" \
    '{"email":"test@example.com","password":"WrongPassword123!"}' \
    "Login endpoint (POST body parsing)" \
    "Invalid credentials"

# Test registration with valid data
test_post "$BASE_URL/api/v1/auth/register" \
    '{"email":"test-'$(date +%s)'@example.com","password":"TestPassword123!"}' \
    "Registration endpoint" \
    "success\|email already registered"

echo ""
echo "==========================================="
echo "Test Summary"
echo "==========================================="
echo -e "Total Tests: ${BLUE}$TOTAL_TESTS${NC}"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"
echo ""

if [ $FAILED_TESTS -gt 0 ]; then
    echo -e "${RED}Failed Tests:${NC}"
    for failure in "${FAILURES[@]}"; do
        IFS='|' read -r desc url code expected <<< "$failure"
        echo -e "  ${RED}✗${NC} $desc"
        echo -e "    URL: $url"
        echo -e "    Status: $code (expected: $expected)"
    done
    echo ""
    exit 1
else
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
fi
