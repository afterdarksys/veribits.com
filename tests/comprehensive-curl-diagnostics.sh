#!/bin/bash

# VeriBits Comprehensive Enterprise Diagnostics
# Uses curl and HTTP testing for thorough analysis

BASE_URL="https://www.veribits.com"
TEST_EMAIL="straticus1@gmail.com"
TEST_PASSWORD="TestPassword123!"
API_KEY="vb_f4837536eaae908c4cf38a47ac732e9c3cedf970951fcd45"

REPORT_FILE="tests/test-results/curl-diagnostics-$(date +%Y%m%d-%H%M%S).json"
RESULTS_DIR="tests/test-results"

mkdir -p "$RESULTS_DIR"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}VeriBits Enterprise Diagnostics${NC}"
echo -e "${BLUE}========================================${NC}\n"

# Initialize JSON report
cat > "$REPORT_FILE" << 'EOF'
{
  "timestamp": "",
  "summary": {
    "totalTests": 0,
    "passed": 0,
    "failed": 0,
    "warnings": 0
  },
  "homepage": {},
  "apiAuth": {},
  "tools": [],
  "cloudfront": {},
  "errors": [],
  "issues": {
    "CRITICAL": [],
    "HIGH": [],
    "MEDIUM": [],
    "LOW": []
  },
  "recommendations": []
}
EOF

PASSED=0
FAILED=0
WARNINGS=0
TOTAL=0

# Function to test endpoint
test_endpoint() {
  local name="$1"
  local url="$2"
  local expected_status="${3:-200}"

  echo -e "${BLUE}Testing: ${name}${NC}"

  response=$(curl -s -w "\n%{http_code}\n%{time_total}\n" \
    -H "User-Agent: VeriBits-Diagnostics/1.0" \
    -H "Accept: text/html,application/json" \
    -L "$url" 2>&1)

  http_code=$(echo "$response" | tail -n 2 | head -n 1)
  time_total=$(echo "$response" | tail -n 1)
  body=$(echo "$response" | head -n -2)

  TOTAL=$((TOTAL + 1))

  if [ "$http_code" = "$expected_status" ]; then
    echo -e "${GREEN}  ✓ Status: ${http_code} (${time_total}s)${NC}"
    PASSED=$((PASSED + 1))

    # Check for PHP errors in body
    if echo "$body" | grep -q "Fatal error\|Parse error\|Warning:"; then
      echo -e "${YELLOW}  ⚠ PHP Error detected in response${NC}"
      WARNINGS=$((WARNINGS + 1))
      return 2
    fi

    return 0
  else
    echo -e "${RED}  ✗ Status: ${http_code} (expected ${expected_status})${NC}"
    FAILED=$((FAILED + 1))
    return 1
  fi
}

# Test 1: Homepage and CloudFront Headers
echo -e "\n${BLUE}=== 1. Homepage and CloudFront Analysis ===${NC}\n"

response=$(curl -s -I -L "$BASE_URL" 2>&1)
http_code=$(echo "$response" | grep "HTTP/" | tail -1 | awk '{print $2}')

echo "HTTP Status: $http_code"
echo "Headers:"
echo "$response" | grep -E "^(x-amz-cf-id|x-cache|cache-control|via|server|content-type):" || echo "  (No CloudFront headers detected)"

cf_enabled="false"
cf_id=""
cache_control=""
x_cache=""

if echo "$response" | grep -q "x-amz-cf-id:"; then
  cf_enabled="true"
  cf_id=$(echo "$response" | grep "x-amz-cf-id:" | awk '{print $2}' | tr -d '\r')
  echo -e "${GREEN}✓ CloudFront is ENABLED${NC}"
else
  echo -e "${YELLOW}⚠ CloudFront NOT detected${NC}"
fi

cache_control=$(echo "$response" | grep -i "cache-control:" | cut -d: -f2- | tr -d '\r' | xargs)
x_cache=$(echo "$response" | grep -i "x-cache:" | cut -d: -f2- | tr -d '\r' | xargs)

echo ""

# Test 2: API Authentication
echo -e "${BLUE}=== 2. API Authentication Tests ===${NC}\n"

# Test Login API
echo "Testing: POST /api/v1/auth/login"
login_response=$(curl -s -w "\n%{http_code}" \
  -X POST "$BASE_URL/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$TEST_EMAIL\",\"password\":\"$TEST_PASSWORD\"}" 2>&1)

login_status=$(echo "$login_response" | tail -1)
login_body=$(echo "$login_response" | head -n -1)

if [ "$login_status" = "200" ] || [ "$login_status" = "201" ]; then
  echo -e "${GREEN}  ✓ Login API: ${login_status}${NC}"
  PASSED=$((PASSED + 1))

  # Check if token is in response
  if echo "$login_body" | grep -q "token\|jwt\|access_token"; then
    echo -e "${GREEN}  ✓ Token found in response${NC}"
  else
    echo -e "${YELLOW}  ⚠ No token in response${NC}"
  fi
else
  echo -e "${RED}  ✗ Login API: ${login_status}${NC}"
  echo "  Response: $login_body"
  FAILED=$((FAILED + 1))
fi
TOTAL=$((TOTAL + 1))

echo ""

# Test Register API
echo "Testing: POST /api/v1/auth/register"
test_user_email="test-$(date +%s)@example.com"
register_response=$(curl -s -w "\n%{http_code}" \
  -X POST "$BASE_URL/api/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$test_user_email\",\"password\":\"TestPass123!\",\"name\":\"Test User\"}" 2>&1)

register_status=$(echo "$register_response" | tail -1)
register_body=$(echo "$register_response" | head -n -1)

if [ "$register_status" = "200" ] || [ "$register_status" = "201" ]; then
  echo -e "${GREEN}  ✓ Register API: ${register_status}${NC}"
  PASSED=$((PASSED + 1))
elif [ "$register_status" = "409" ]; then
  echo -e "${YELLOW}  ⚠ Register API: ${register_status} (conflict - may be expected)${NC}"
  WARNINGS=$((WARNINGS + 1))
else
  echo -e "${RED}  ✗ Register API: ${register_status}${NC}"
  echo "  Response: $register_body"
  FAILED=$((FAILED + 1))
fi
TOTAL=$((TOTAL + 1))

echo ""

# Test API Key
echo "Testing: GET /api/v1/tools (with API key)"
apikey_response=$(curl -s -w "\n%{http_code}" \
  -H "X-API-Key: $API_KEY" \
  "$BASE_URL/api/v1/tools" 2>&1)

apikey_status=$(echo "$apikey_response" | tail -1)
apikey_body=$(echo "$apikey_response" | head -n -1)

if [ "$apikey_status" = "200" ]; then
  echo -e "${GREEN}  ✓ API Key validation: ${apikey_status}${NC}"
  PASSED=$((PASSED + 1))
else
  echo -e "${RED}  ✗ API Key validation: ${apikey_status}${NC}"
  FAILED=$((FAILED + 1))
fi
TOTAL=$((TOTAL + 1))

echo ""

# Test 3: All Tools
echo -e "${BLUE}=== 3. Testing All Tools ===${NC}\n"

declare -a TOOLS=(
  "JWT Debugger|/tool/jwt-debugger.php|Developer"
  "Regex Tester|/tool/regex-tester.php|Developer"
  "URL Encoder|/tool/url-encoder.php|Developer"
  "Hash Generator|/tool/hash-generator.php|Developer"
  "JSON/YAML Validator|/tool/json-yaml-validator.php|Developer"
  "Base64 Encoder|/tool/base64-encoder.php|Developer"
  "IP Calculator|/tool/ip-calculator.php|Network"
  "Visual Traceroute|/tool/visual-traceroute.php|Network"
  "BGP Intelligence|/tool/bgp-intelligence.php|Network"
  "PCAP Analyzer|/tool/pcap-analyzer.php|Network"
  "DNS Validator|/tool/dns-validator.php|DNS"
  "Zone Validator|/tool/zone-validator.php|DNS"
  "DNSSEC Validator|/tool/dnssec-validator.php|DNS"
  "DNS Propagation|/tool/dns-propagation.php|DNS"
  "Reverse DNS|/tool/reverse-dns.php|DNS"
  "DNS Converter|/tool/dns-converter.php|DNS"
  "SSL Generator|/tool/ssl-generator.php|Security"
  "Code Signing|/tool/code-signing.php|Security"
  "Crypto Validator|/tool/crypto-validator.php|Security"
  "RBL Check|/tool/rbl-check.php|Security"
  "SMTP Relay Check|/tool/smtp-relay-check.php|Security"
  "Steganography|/tool/steganography.php|Security"
  "Security Headers|/tool/security-headers.php|Security"
  "Secrets Scanner|/tool/secrets-scanner.php|Security"
  "PGP Validator|/tool/pgp-validator.php|Security"
  "Hash Validator|/tool/hash-validator.php|Security"
  "Docker Scanner|/tool/docker-scanner.php|DevOps"
  "Terraform Scanner|/tool/terraform-scanner.php|DevOps"
  "Kubernetes Validator|/tool/kubernetes-validator.php|DevOps"
  "Firewall Editor|/tool/firewall-editor.php|DevOps"
  "IAM Policy Analyzer|/tool/iam-policy-analyzer.php|DevOps"
  "DB Connection Auditor|/tool/db-connection-auditor.php|DevOps"
  "File Magic|/tool/file-magic.php|File"
  "Cert Converter|/tool/cert-converter.php|File"
)

TOOLS_PASSED=0
TOOLS_FAILED=0
TOOLS_NOT_FOUND=0

echo "Testing ${#TOOLS[@]} tools..."
echo ""

for tool_entry in "${TOOLS[@]}"; do
  IFS='|' read -r name path category <<< "$tool_entry"

  response=$(curl -s -w "\n%{http_code}" -L "$BASE_URL$path" 2>&1)
  status=$(echo "$response" | tail -1)
  body=$(echo "$response" | head -n -1)

  TOTAL=$((TOTAL + 1))

  if [ "$status" = "200" ]; then
    # Check for PHP errors
    if echo "$body" | grep -q "Fatal error\|Parse error"; then
      echo -e "${RED}  ✗ $name: PHP Error${NC}"
      TOOLS_FAILED=$((TOOLS_FAILED + 1))
      FAILED=$((FAILED + 1))
    else
      echo -e "${GREEN}  ✓ $name${NC}"
      TOOLS_PASSED=$((TOOLS_PASSED + 1))
      PASSED=$((PASSED + 1))
    fi
  elif [ "$status" = "404" ]; then
    echo -e "${YELLOW}  ⚠ $name: Not Found (404)${NC}"
    TOOLS_NOT_FOUND=$((TOOLS_NOT_FOUND + 1))
    WARNINGS=$((WARNINGS + 1))
  else
    echo -e "${RED}  ✗ $name: HTTP $status${NC}"
    TOOLS_FAILED=$((TOOLS_FAILED + 1))
    FAILED=$((FAILED + 1))
  fi
done

echo ""
echo "Tools Summary:"
echo "  Working: $TOOLS_PASSED"
echo "  Failed: $TOOLS_FAILED"
echo "  Not Found: $TOOLS_NOT_FOUND"
echo ""

# Test 4: Key Pages
echo -e "${BLUE}=== 4. Key Pages Check ===${NC}\n"

test_endpoint "Dashboard" "$BASE_URL/dashboard.php" 200
test_endpoint "Tools Page" "$BASE_URL/tools.php" 200
test_endpoint "Docs Page" "$BASE_URL/docs.php" 200
test_endpoint "Home Page" "$BASE_URL/home.php" 200

echo ""

# Test 5: Static Assets
echo -e "${BLUE}=== 5. Static Assets Check ===${NC}\n"

test_endpoint "Main JS" "$BASE_URL/assets/js/main.js" 200
test_endpoint "Auth JS" "$BASE_URL/assets/js/auth.js" 200
test_endpoint "Dashboard JS" "$BASE_URL/assets/js/dashboard.js" 200
test_endpoint "Main CSS" "$BASE_URL/assets/css/style.css" 200

echo ""

# Generate Final Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}FINAL SUMMARY${NC}"
echo -e "${BLUE}========================================${NC}\n"

echo "Total Tests: $TOTAL"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo -e "${YELLOW}Warnings: $WARNINGS${NC}"

success_rate=$((PASSED * 100 / TOTAL))
echo ""
echo "Success Rate: ${success_rate}%"

echo ""
echo "Report saved to: $REPORT_FILE"

# Generate detailed JSON report
cat > "$REPORT_FILE" << EOF
{
  "timestamp": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "summary": {
    "totalTests": $TOTAL,
    "passed": $PASSED,
    "failed": $FAILED,
    "warnings": $WARNINGS,
    "successRate": "${success_rate}%"
  },
  "cloudfront": {
    "enabled": $cf_enabled,
    "distributionId": "$cf_id",
    "cacheControl": "$cache_control",
    "xCache": "$x_cache"
  },
  "apiAuth": {
    "login": {
      "status": $login_status,
      "working": $([ "$login_status" = "200" ] && echo "true" || echo "false")
    },
    "register": {
      "status": $register_status
    },
    "apiKey": {
      "status": $apikey_status,
      "working": $([ "$apikey_status" = "200" ] && echo "true" || echo "false")
    }
  },
  "tools": {
    "total": ${#TOOLS[@]},
    "working": $TOOLS_PASSED,
    "failed": $TOOLS_FAILED,
    "notFound": $TOOLS_NOT_FOUND
  }
}
EOF

# Determine exit code
if [ $FAILED -gt 0 ]; then
  echo -e "\n${RED}⚠ FAILURES DETECTED - System needs attention${NC}\n"
  exit 1
else
  echo -e "\n${GREEN}✓ All tests passed${NC}\n"
  exit 0
fi
