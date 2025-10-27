#!/bin/bash
# Test authentication endpoints after Apache rewrite fix
# This script validates that all auth endpoints work correctly

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
API_URL="${API_URL:-https://api.veribits.com}"
TEST_EMAIL="straticus1@gmail.com"
TEST_PASSWORD="TestPassword123!"
ENTERPRISE_EMAIL="enterprise@veribits.com"
ENTERPRISE_PASSWORD="EnterpriseDemo2025!"

echo -e "${BLUE}======================================${NC}"
echo -e "${BLUE}VeriBits Authentication Test Suite${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo "Testing API: $API_URL"
echo ""

# Test 1: Health check
echo -e "${YELLOW}Test 1: Health Check${NC}"
HEALTH_RESPONSE=$(curl -s -w "\n%{http_code}" "${API_URL}/api/v1/health")
HEALTH_CODE=$(echo "$HEALTH_RESPONSE" | tail -n 1)
HEALTH_BODY=$(echo "$HEALTH_RESPONSE" | head -n -1)

if [ "$HEALTH_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Health check passed${NC}"
else
    echo -e "${RED}✗ Health check failed (HTTP $HEALTH_CODE)${NC}"
    echo "$HEALTH_BODY"
    exit 1
fi
echo ""

# Test 2: Debug endpoint (verify POST body is received)
echo -e "${YELLOW}Test 2: Debug Endpoint (POST body verification)${NC}"
DEBUG_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/debug/request" \
  -H "Content-Type: application/json" \
  -d '{"test":"data","email":"test@example.com"}')
DEBUG_CODE=$(echo "$DEBUG_RESPONSE" | tail -n 1)
DEBUG_BODY=$(echo "$DEBUG_RESPONSE" | head -n -1)

if [ "$DEBUG_CODE" = "200" ]; then
    # Check if php_input contains our test data
    if echo "$DEBUG_BODY" | grep -q "test@example.com"; then
        echo -e "${GREEN}✓ Debug endpoint receives POST body correctly${NC}"
    else
        echo -e "${RED}✗ Debug endpoint did not receive POST body${NC}"
        echo "$DEBUG_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Debug endpoint failed (HTTP $DEBUG_CODE)${NC}"
    echo "$DEBUG_BODY"
    exit 1
fi
echo ""

# Test 3: Request helper test endpoint
echo -e "${YELLOW}Test 3: Request Helper Test${NC}"
HELPER_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/test/request-helper" \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"testpass"}')
HELPER_CODE=$(echo "$HELPER_RESPONSE" | tail -n 1)
HELPER_BODY=$(echo "$HELPER_RESPONSE" | head -n -1)

if [ "$HELPER_CODE" = "200" ]; then
    if echo "$HELPER_BODY" | grep -q "has_email.*true" && echo "$HELPER_BODY" | grep -q "has_password.*true"; then
        echo -e "${GREEN}✓ Request helper successfully parses JSON body${NC}"
    else
        echo -e "${RED}✗ Request helper failed to parse JSON body${NC}"
        echo "$HELPER_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Request helper test failed (HTTP $HELPER_CODE)${NC}"
    echo "$HELPER_BODY"
    exit 1
fi
echo ""

# Test 4: Login with test account
echo -e "${YELLOW}Test 4: Login (Free Tier Account)${NC}"
LOGIN_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${TEST_EMAIL}\",\"password\":\"${TEST_PASSWORD}\"}")
LOGIN_CODE=$(echo "$LOGIN_RESPONSE" | tail -n 1)
LOGIN_BODY=$(echo "$LOGIN_RESPONSE" | head -n -1)

if [ "$LOGIN_CODE" = "200" ]; then
    ACCESS_TOKEN=$(echo "$LOGIN_BODY" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    if [ -n "$ACCESS_TOKEN" ]; then
        echo -e "${GREEN}✓ Login successful${NC}"
        echo "  Access token: ${ACCESS_TOKEN:0:20}..."
    else
        echo -e "${RED}✗ Login succeeded but no access token returned${NC}"
        echo "$LOGIN_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Login failed (HTTP $LOGIN_CODE)${NC}"
    echo "$LOGIN_BODY"
    exit 1
fi
echo ""

# Test 5: Profile retrieval
echo -e "${YELLOW}Test 5: Profile Retrieval${NC}"
PROFILE_RESPONSE=$(curl -s -w "\n%{http_code}" -X GET "${API_URL}/api/v1/auth/profile" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}")
PROFILE_CODE=$(echo "$PROFILE_RESPONSE" | tail -n 1)
PROFILE_BODY=$(echo "$PROFILE_RESPONSE" | head -n -1)

if [ "$PROFILE_CODE" = "200" ]; then
    if echo "$PROFILE_BODY" | grep -q "$TEST_EMAIL"; then
        echo -e "${GREEN}✓ Profile retrieval successful${NC}"
    else
        echo -e "${RED}✗ Profile retrieved but email mismatch${NC}"
        echo "$PROFILE_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Profile retrieval failed (HTTP $PROFILE_CODE)${NC}"
    echo "$PROFILE_BODY"
    exit 1
fi
echo ""

# Test 6: Token refresh
echo -e "${YELLOW}Test 6: Token Refresh${NC}"
REFRESH_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/refresh" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}")
REFRESH_CODE=$(echo "$REFRESH_RESPONSE" | tail -n 1)
REFRESH_BODY=$(echo "$REFRESH_RESPONSE" | head -n -1)

if [ "$REFRESH_CODE" = "200" ]; then
    NEW_TOKEN=$(echo "$REFRESH_BODY" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    if [ -n "$NEW_TOKEN" ] && [ "$NEW_TOKEN" != "$ACCESS_TOKEN" ]; then
        echo -e "${GREEN}✓ Token refresh successful${NC}"
        echo "  New token: ${NEW_TOKEN:0:20}..."
        ACCESS_TOKEN=$NEW_TOKEN
    else
        echo -e "${RED}✗ Token refresh failed - no new token or same token returned${NC}"
        echo "$REFRESH_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Token refresh failed (HTTP $REFRESH_CODE)${NC}"
    echo "$REFRESH_BODY"
    exit 1
fi
echo ""

# Test 7: Logout
echo -e "${YELLOW}Test 7: Logout${NC}"
LOGOUT_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/logout" \
  -H "Authorization: Bearer ${ACCESS_TOKEN}")
LOGOUT_CODE=$(echo "$LOGOUT_RESPONSE" | tail -n 1)
LOGOUT_BODY=$(echo "$LOGOUT_RESPONSE" | head -n -1)

if [ "$LOGOUT_CODE" = "200" ]; then
    echo -e "${GREEN}✓ Logout successful${NC}"
else
    echo -e "${RED}✗ Logout failed (HTTP $LOGOUT_CODE)${NC}"
    echo "$LOGOUT_BODY"
    exit 1
fi
echo ""

# Test 8: Login with enterprise account
echo -e "${YELLOW}Test 8: Login (Enterprise Account)${NC}"
ENTERPRISE_LOGIN_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${ENTERPRISE_EMAIL}\",\"password\":\"${ENTERPRISE_PASSWORD}\"}")
ENTERPRISE_LOGIN_CODE=$(echo "$ENTERPRISE_LOGIN_RESPONSE" | tail -n 1)
ENTERPRISE_LOGIN_BODY=$(echo "$ENTERPRISE_LOGIN_RESPONSE" | head -n -1)

if [ "$ENTERPRISE_LOGIN_CODE" = "200" ]; then
    ENTERPRISE_TOKEN=$(echo "$ENTERPRISE_LOGIN_BODY" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    if [ -n "$ENTERPRISE_TOKEN" ]; then
        echo -e "${GREEN}✓ Enterprise login successful${NC}"
        echo "  Access token: ${ENTERPRISE_TOKEN:0:20}..."
    else
        echo -e "${RED}✗ Enterprise login succeeded but no access token returned${NC}"
        echo "$ENTERPRISE_LOGIN_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Enterprise login failed (HTTP $ENTERPRISE_LOGIN_CODE)${NC}"
    echo "$ENTERPRISE_LOGIN_BODY"
    exit 1
fi
echo ""

# Test 9: Demo token endpoint
echo -e "${YELLOW}Test 9: Demo Token Generation${NC}"
TOKEN_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/token" \
  -H "Content-Type: application/json" \
  -d '{"user":"demo-user"}')
TOKEN_CODE=$(echo "$TOKEN_RESPONSE" | tail -n 1)
TOKEN_BODY=$(echo "$TOKEN_RESPONSE" | head -n -1)

if [ "$TOKEN_CODE" = "200" ]; then
    DEMO_TOKEN=$(echo "$TOKEN_BODY" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)
    DEMO_API_KEY=$(echo "$TOKEN_BODY" | grep -o '"api_key":"[^"]*"' | cut -d'"' -f4)
    if [ -n "$DEMO_TOKEN" ] && [ -n "$DEMO_API_KEY" ]; then
        echo -e "${GREEN}✓ Demo token generation successful${NC}"
        echo "  Demo token: ${DEMO_TOKEN:0:20}..."
        echo "  API key: ${DEMO_API_KEY:0:20}..."
    else
        echo -e "${RED}✗ Demo token generation failed - missing token or API key${NC}"
        echo "$TOKEN_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Demo token generation failed (HTTP $TOKEN_CODE)${NC}"
    echo "$TOKEN_BODY"
    exit 1
fi
echo ""

# Test 10: Registration (new random user)
RANDOM_EMAIL="test-$(date +%s)@veribits.test"
RANDOM_PASSWORD="TestPass123!@#"
echo -e "${YELLOW}Test 10: User Registration${NC}"
echo "  Email: $RANDOM_EMAIL"
REGISTER_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${API_URL}/api/v1/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"${RANDOM_EMAIL}\",\"password\":\"${RANDOM_PASSWORD}\"}")
REGISTER_CODE=$(echo "$REGISTER_RESPONSE" | tail -n 1)
REGISTER_BODY=$(echo "$REGISTER_RESPONSE" | head -n -1)

if [ "$REGISTER_CODE" = "200" ]; then
    NEW_API_KEY=$(echo "$REGISTER_BODY" | grep -o '"api_key":"[^"]*"' | cut -d'"' -f4)
    NEW_USER_ID=$(echo "$REGISTER_BODY" | grep -o '"user_id":[0-9]*' | cut -d':' -f2)
    if [ -n "$NEW_API_KEY" ] && [ -n "$NEW_USER_ID" ]; then
        echo -e "${GREEN}✓ Registration successful${NC}"
        echo "  User ID: $NEW_USER_ID"
        echo "  API key: ${NEW_API_KEY:0:20}..."
    else
        echo -e "${RED}✗ Registration succeeded but missing data${NC}"
        echo "$REGISTER_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗ Registration failed (HTTP $REGISTER_CODE)${NC}"
    echo "$REGISTER_BODY"
    exit 1
fi
echo ""

echo -e "${BLUE}======================================${NC}"
echo -e "${GREEN}All authentication tests passed!${NC}"
echo -e "${BLUE}======================================${NC}"
echo ""
echo "Summary:"
echo "  ✓ Health check working"
echo "  ✓ POST body received correctly"
echo "  ✓ Request helper parsing JSON"
echo "  ✓ Free tier login working"
echo "  ✓ Enterprise login working"
echo "  ✓ Profile retrieval working"
echo "  ✓ Token refresh working"
echo "  ✓ Logout working"
echo "  ✓ Demo token generation working"
echo "  ✓ User registration working"
echo ""
echo -e "${GREEN}Authentication system is fully operational!${NC}"
