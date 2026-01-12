#!/bin/bash
set -e

# VeriBits GitHub Action Entrypoint Script
# Scans files and artifacts for security issues

echo "🛡️  VeriBits Security Scan"
echo "=========================="
echo ""

# Validate API key
if [ -z "$VERIBITS_API_KEY" ]; then
    echo "❌ Error: VERIBITS_API_KEY is required"
    exit 1
fi

API_URL="${VERIBITS_API_URL:-https://api.veribits.com}"
SCAN_TYPE="${SCAN_TYPE:-all}"
FILE_PATHS="${FILE_PATHS:-**/*}"
FAIL_ON_THREAT="${FAIL_ON_THREAT:-true}"
THREAT_THRESHOLD="${THREAT_THRESHOLD:-50}"
GENERATE_SBOM="${GENERATE_SBOM:-false}"
SBOM_FORMAT="${SBOM_FORMAT:-cyclonedx}"

echo "📊 Configuration:"
echo "   API URL: $API_URL"
echo "   Scan Type: $SCAN_TYPE"
echo "   Paths: $FILE_PATHS"
echo "   Fail on Threat: $FAIL_ON_THREAT"
echo "   Threat Threshold: $THREAT_THRESHOLD"
echo ""

# Create results directory
mkdir -p /tmp/veribits-results

# Initialize counters
THREATS_FOUND=0
MAX_THREAT_SCORE=0
SCAN_STATUS="pass"

# Function to scan a file
scan_file() {
    local file=$1
    echo "🔍 Scanning: $file"

    # Calculate file hash
    local hash=$(sha256sum "$file" | awk '{print $1}')

    # Call VeriBits threat intelligence API
    response=$(curl -s -X POST "$API_URL/api/v1/threat-intel/lookup" \
        -H "Authorization: Bearer $VERIBITS_API_KEY" \
        -H "Content-Type: application/json" \
        -d "{\"hash\": \"$hash\", \"sources\": [\"virustotal\", \"malwarebazaar\"]}" \
        || echo '{"error": "API call failed"}')

    # Parse response
    threat_score=$(echo "$response" | jq -r '.threat_score // 0')
    is_malicious=$(echo "$response" | jq -r '.is_malicious // false')

    echo "   Hash: $hash"
    echo "   Threat Score: $threat_score"
    echo "   Malicious: $is_malicious"
    echo ""

    # Update counters
    if [ "$is_malicious" = "true" ]; then
        THREATS_FOUND=$((THREATS_FOUND + 1))
    fi

    if [ "$threat_score" -gt "$MAX_THREAT_SCORE" ]; then
        MAX_THREAT_SCORE=$threat_score
    fi

    # Save result
    echo "$response" >> /tmp/veribits-results/scan-results.json
}

# Find and scan files
echo "🔎 Finding files to scan..."
files=$(find . -type f -name "$FILE_PATHS" 2>/dev/null || echo "")

if [ -z "$files" ]; then
    echo "⚠️  No files found matching pattern: $FILE_PATHS"
else
    file_count=$(echo "$files" | wc -l)
    echo "📁 Found $file_count files to scan"
    echo ""

    # Scan each file
    while IFS= read -r file; do
        if [ -f "$file" ]; then
            scan_file "$file"
        fi
    done <<< "$files"
fi

# Generate SBOM if requested
if [ "$GENERATE_SBOM" = "true" ]; then
    echo "📦 Generating SBOM ($SBOM_FORMAT format)..."

    # Call SBOM generation endpoint
    sbom_response=$(curl -s -X POST "$API_URL/api/v1/ci/sbom/generate" \
        -H "Authorization: Bearer $VERIBITS_API_KEY" \
        -H "Content-Type: application/json" \
        -d "{\"format\": \"$SBOM_FORMAT\", \"directory\": \".\"}" \
        || echo '{"error": "SBOM generation failed"}')

    echo "$sbom_response" > /tmp/veribits-results/sbom.$SBOM_FORMAT.json
    sbom_url=$(echo "$sbom_response" | jq -r '.url // "N/A"')
    echo "   SBOM URL: $sbom_url"
    echo ""
fi

# Determine scan status
if [ "$MAX_THREAT_SCORE" -ge "$THREAT_THRESHOLD" ]; then
    SCAN_STATUS="fail"
elif [ "$THREATS_FOUND" -gt 0 ]; then
    SCAN_STATUS="warning"
fi

# Output results summary
echo "═══════════════════════════"
echo "📊 Scan Summary"
echo "═══════════════════════════"
echo "Status: $SCAN_STATUS"
echo "Threats Found: $THREATS_FOUND"
echo "Max Threat Score: $MAX_THREAT_SCORE"
echo "Threshold: $THREAT_THRESHOLD"
echo ""

# Set GitHub Action outputs
echo "scan_status=$SCAN_STATUS" >> $GITHUB_OUTPUT
echo "threats_found=$THREATS_FOUND" >> $GITHUB_OUTPUT
echo "max_threat_score=$MAX_THREAT_SCORE" >> $GITHUB_OUTPUT
echo "scan_url=$API_URL/scans/latest" >> $GITHUB_OUTPUT

if [ "$GENERATE_SBOM" = "true" ]; then
    echo "sbom_url=$sbom_url" >> $GITHUB_OUTPUT
fi

# Upload artifacts if requested
if [ "$UPLOAD_ARTIFACTS" = "true" ]; then
    echo "📤 Uploading scan results as artifacts..."
    # Results will be available via GitHub Actions artifacts
fi

# Fail build if threats detected and fail_on_threat is true
if [ "$FAIL_ON_THREAT" = "true" ] && [ "$SCAN_STATUS" = "fail" ]; then
    echo "❌ Build failed: Threat score ($MAX_THREAT_SCORE) exceeds threshold ($THREAT_THRESHOLD)"
    exit 1
fi

echo "✅ Scan complete!"
exit 0
