#!/bin/bash
# © After Dark Systems
# VeriBits Firewall Configuration Retrieval Script
#
# This script retrieves firewall configurations from VeriBits and can optionally apply them
# Usage: ./get-firewall-config.sh [options]

set -e

# Configuration
VERIBITS_API_KEY="${VERIBITS_API_KEY:-}"
VERIBITS_ACCOUNT="${VERIBITS_ACCOUNT:-}"
VERIBITS_DEVICE="${VERIBITS_DEVICE:-}"
VERIBITS_VERSION="${VERIBITS_VERSION:-latest}"
VERIBITS_URL="${VERIBITS_URL:-https://veribits.com}"
OUTPUT_FORMAT="${OUTPUT_FORMAT:-text}"
APPLY_CONFIG="${APPLY_CONFIG:-false}"
BACKUP_CURRENT="${BACKUP_CURRENT:-true}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Help function
show_help() {
    cat << EOF
VeriBits Firewall Configuration Retrieval Tool

Usage: $0 [OPTIONS]

Options:
    -k, --api-key KEY       VeriBits API key (or set VERIBITS_API_KEY env var)
    -a, --account ID        Account ID (optional)
    -d, --device NAME       Device name to retrieve config for
    -v, --version NUM       Version number (default: latest)
    -o, --output FORMAT     Output format: text or json (default: text)
    -f, --file PATH         Save configuration to file instead of stdout
    -A, --apply             Apply configuration after retrieval (requires root)
    -B, --no-backup         Skip backing up current firewall rules
    -u, --url URL           VeriBits API URL (default: https://veribits.com)
    -h, --help              Show this help message

Environment Variables:
    VERIBITS_API_KEY        Your VeriBits API key
    VERIBITS_ACCOUNT        Your account ID
    VERIBITS_DEVICE         Device name
    VERIBITS_VERSION        Config version
    VERIBITS_URL            API URL

Examples:
    # Retrieve latest config for web-server-01
    $0 -k YOUR_API_KEY -d web-server-01

    # Retrieve specific version and save to file
    $0 -k YOUR_API_KEY -d web-server-01 -v 5 -f /tmp/firewall.rules

    # Retrieve and apply configuration (requires root)
    sudo $0 -k YOUR_API_KEY -d web-server-01 --apply

    # Get config in JSON format
    $0 -k YOUR_API_KEY -d web-server-01 -o json

    # Using environment variables
    export VERIBITS_API_KEY=your_key_here
    export VERIBITS_DEVICE=web-server-01
    $0

EOF
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -k|--api-key)
            VERIBITS_API_KEY="$2"
            shift 2
            ;;
        -a|--account)
            VERIBITS_ACCOUNT="$2"
            shift 2
            ;;
        -d|--device)
            VERIBITS_DEVICE="$2"
            shift 2
            ;;
        -v|--version)
            VERIBITS_VERSION="$2"
            shift 2
            ;;
        -o|--output)
            OUTPUT_FORMAT="$2"
            shift 2
            ;;
        -f|--file)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        -A|--apply)
            APPLY_CONFIG=true
            shift
            ;;
        -B|--no-backup)
            BACKUP_CURRENT=false
            shift
            ;;
        -u|--url)
            VERIBITS_URL="$2"
            shift 2
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            echo -e "${RED}Error: Unknown option: $1${NC}"
            show_help
            exit 1
            ;;
    esac
done

# Validate required parameters
if [[ -z "$VERIBITS_API_KEY" ]]; then
    echo -e "${RED}Error: API key is required${NC}"
    echo "Provide via -k option or VERIBITS_API_KEY environment variable"
    exit 1
fi

# Build API URL
API_URL="${VERIBITS_URL}/get-iptables.php?key=${VERIBITS_API_KEY}"

if [[ -n "$VERIBITS_ACCOUNT" ]]; then
    API_URL="${API_URL}&account=${VERIBITS_ACCOUNT}"
fi

if [[ -n "$VERIBITS_DEVICE" ]]; then
    API_URL="${API_URL}&device=${VERIBITS_DEVICE}"
fi

if [[ "$VERIBITS_VERSION" != "latest" ]]; then
    API_URL="${API_URL}&version=${VERIBITS_VERSION}"
fi

API_URL="${API_URL}&output=${OUTPUT_FORMAT}"

# Log info
echo -e "${BLUE}VeriBits Firewall Configuration Retrieval${NC}"
echo -e "${BLUE}==========================================${NC}"
echo "Device: ${VERIBITS_DEVICE:-any}"
echo "Version: ${VERIBITS_VERSION}"
echo "Format: ${OUTPUT_FORMAT}"
echo ""

# Retrieve configuration
echo -e "${YELLOW}Retrieving configuration from VeriBits...${NC}"

if [[ -n "${OUTPUT_FILE:-}" ]]; then
    # Save to file
    HTTP_CODE=$(curl -s -w "%{http_code}" -o "${OUTPUT_FILE}" "${API_URL}")

    if [[ "$HTTP_CODE" -eq 200 ]]; then
        echo -e "${GREEN}✓ Configuration saved to: ${OUTPUT_FILE}${NC}"

        # Show config type from headers
        CONFIG_TYPE=$(curl -s -I "${API_URL}" | grep -i "X-Config-Type:" | cut -d' ' -f2 | tr -d '\r')
        CONFIG_VERSION=$(curl -s -I "${API_URL}" | grep -i "X-Config-Version:" | cut -d' ' -f2 | tr -d '\r')
        DEVICE_NAME=$(curl -s -I "${API_URL}" | grep -i "X-Device-Name:" | cut -d' ' -f2- | tr -d '\r')

        echo -e "${GREEN}✓ Config Type: ${CONFIG_TYPE}${NC}"
        echo -e "${GREEN}✓ Version: ${CONFIG_VERSION}${NC}"
        echo -e "${GREEN}✓ Device: ${DEVICE_NAME}${NC}"
    else
        echo -e "${RED}✗ Failed to retrieve configuration (HTTP ${HTTP_CODE})${NC}"
        cat "${OUTPUT_FILE}"
        rm -f "${OUTPUT_FILE}"
        exit 1
    fi
else
    # Output to stdout
    RESPONSE=$(curl -s "${API_URL}")
    HTTP_CODE=$?

    if [[ $HTTP_CODE -eq 0 ]]; then
        echo "$RESPONSE"

        # Check if response is an error
        if echo "$RESPONSE" | grep -q '"error"'; then
            echo -e "${RED}✗ Error retrieving configuration${NC}"
            exit 1
        fi
    else
        echo -e "${RED}✗ Failed to connect to VeriBits API${NC}"
        exit 1
    fi
fi

# Apply configuration if requested
if [[ "$APPLY_CONFIG" == true ]]; then
    echo ""
    echo -e "${YELLOW}Applying firewall configuration...${NC}"

    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        echo -e "${RED}✗ Error: Applying firewall rules requires root privileges${NC}"
        echo "Run with sudo: sudo $0 $@"
        exit 1
    fi

    # Determine config file
    if [[ -n "${OUTPUT_FILE:-}" ]]; then
        CONFIG_FILE="${OUTPUT_FILE}"
    else
        CONFIG_FILE="/tmp/veribits-firewall-$$.rules"
        echo "$RESPONSE" > "${CONFIG_FILE}"
    fi

    # Detect firewall type from config
    if grep -q "ip6tables" "${CONFIG_FILE}"; then
        FIREWALL_CMD="ip6tables-restore"
        FIREWALL_SAVE="ip6tables-save"
        BACKUP_FILE="/root/firewall-backup-ip6tables-$(date +%Y%m%d-%H%M%S).rules"
    elif grep -q "ebtables" "${CONFIG_FILE}"; then
        FIREWALL_CMD="ebtables-restore"
        FIREWALL_SAVE="ebtables-save"
        BACKUP_FILE="/root/firewall-backup-ebtables-$(date +%Y%m%d-%H%M%S).rules"
    else
        FIREWALL_CMD="iptables-restore"
        FIREWALL_SAVE="iptables-save"
        BACKUP_FILE="/root/firewall-backup-iptables-$(date +%Y%m%d-%H%M%S).rules"
    fi

    # Backup current rules
    if [[ "$BACKUP_CURRENT" == true ]]; then
        echo -e "${YELLOW}Backing up current firewall rules...${NC}"
        $FIREWALL_SAVE > "${BACKUP_FILE}"
        echo -e "${GREEN}✓ Backup saved to: ${BACKUP_FILE}${NC}"
    fi

    # Apply new rules
    echo -e "${YELLOW}Applying new firewall rules...${NC}"

    # Remove comment lines before applying
    grep -v "^#" "${CONFIG_FILE}" | $FIREWALL_CMD

    if [[ $? -eq 0 ]]; then
        echo -e "${GREEN}✓ Firewall rules applied successfully${NC}"
        echo ""
        echo -e "${GREEN}Current firewall status:${NC}"

        if [[ "$FIREWALL_CMD" == "ip6tables-restore" ]]; then
            ip6tables -L -n -v --line-numbers
        elif [[ "$FIREWALL_CMD" == "ebtables-restore" ]]; then
            ebtables -L
        else
            iptables -L -n -v --line-numbers
        fi
    else
        echo -e "${RED}✗ Failed to apply firewall rules${NC}"

        if [[ "$BACKUP_CURRENT" == true ]]; then
            echo -e "${YELLOW}Restoring backup...${NC}"
            $FIREWALL_CMD < "${BACKUP_FILE}"
            echo -e "${GREEN}✓ Backup restored${NC}"
        fi
        exit 1
    fi

    # Clean up temp file if created
    if [[ -z "${OUTPUT_FILE:-}" ]]; then
        rm -f "${CONFIG_FILE}"
    fi
fi

echo ""
echo -e "${GREEN}✓ Done${NC}"
