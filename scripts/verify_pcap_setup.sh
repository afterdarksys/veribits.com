#!/bin/bash

# PCAP Analyzer Setup Verification Script
# Checks all dependencies and files are in place

echo "========================================"
echo "PCAP Analyzer Setup Verification"
echo "========================================"
echo ""

ERRORS=0
WARNINGS=0

# Check Python
echo -n "Checking Python 3... "
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version)
    echo "✓ Found: $PYTHON_VERSION"
else
    echo "✗ NOT FOUND"
    echo "  Install with: brew install python3"
    ((ERRORS++))
fi

# Check scapy
echo -n "Checking scapy library... "
if python3 -c "import scapy" 2>/dev/null; then
    echo "✓ Installed"
else
    echo "✗ NOT INSTALLED"
    echo "  Install with: pip3 install scapy"
    ((ERRORS++))
fi

# Check PHP
echo -n "Checking PHP... "
if command -v php &> /dev/null; then
    PHP_VERSION=$(php --version | head -n 1)
    echo "✓ Found: $PHP_VERSION"
else
    echo "✗ NOT FOUND"
    ((ERRORS++))
fi

# Check curl extension
echo -n "Checking PHP curl extension... "
if php -m 2>/dev/null | grep -q curl; then
    echo "✓ Installed"
else
    echo "✗ NOT INSTALLED"
    ((WARNINGS++))
fi

echo ""
echo "Files Check:"
echo "------------"

BASE_DIR="/Users/ryan/development/veribits.com"

# Check Python scripts
FILES=(
    "scripts/pcap_analyzer.py"
    "scripts/generate_sample_pcap.py"
    "app/src/Controllers/PcapAnalyzerController.php"
    "app/public/tool/pcap-analyzer.php"
    "app/.env"
)

for file in "${FILES[@]}"; do
    echo -n "Checking $file... "
    if [ -f "$BASE_DIR/$file" ]; then
        echo "✓ Exists"
    else
        echo "✗ MISSING"
        ((ERRORS++))
    fi
done

# Check if route was added to index.php
echo -n "Checking API route in index.php... "
if grep -q "PcapAnalyzerController" "$BASE_DIR/app/public/index.php" 2>/dev/null; then
    echo "✓ Found"
else
    echo "✗ NOT FOUND"
    ((ERRORS++))
fi

# Check OpenAI API key
echo ""
echo "Configuration Check:"
echo "-------------------"
echo -n "Checking OpenAI API key... "
if grep -q "^OPENAI_API_KEY=sk-" "$BASE_DIR/app/.env" 2>/dev/null; then
    echo "✓ Configured"
elif grep -q "^OPENAI_API_KEY=$" "$BASE_DIR/app/.env" 2>/dev/null; then
    echo "⚠ NOT SET (AI insights will not work)"
    echo "  Add your key to app/.env: OPENAI_API_KEY=sk-your-key-here"
    ((WARNINGS++))
else
    echo "✗ MISSING"
    ((ERRORS++))
fi

# Check for sample PCAP files
echo -n "Checking sample PCAP files... "
if [ -d "$BASE_DIR/samples/pcap" ]; then
    COUNT=$(find "$BASE_DIR/samples/pcap" -name "*.pcap" 2>/dev/null | wc -l | tr -d ' ')
    if [ "$COUNT" -gt 0 ]; then
        echo "✓ Found $COUNT files"
    else
        echo "⚠ Directory exists but no files"
        echo "  Generate with: python3 scripts/generate_sample_pcap.py"
        ((WARNINGS++))
    fi
else
    echo "⚠ NOT GENERATED"
    echo "  Generate with: python3 scripts/generate_sample_pcap.py"
    ((WARNINGS++))
fi

# Test Python script
echo ""
echo "Functionality Check:"
echo "-------------------"
echo -n "Testing Python analyzer script... "
if python3 "$BASE_DIR/scripts/pcap_analyzer.py" 2>&1 | grep -q "Usage:"; then
    echo "✓ Executable"
else
    echo "✗ FAILED"
    ((ERRORS++))
fi

echo ""
echo "========================================"
echo "Summary:"
echo "========================================"
if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo "✓ ALL CHECKS PASSED!"
    echo ""
    echo "🚀 Ready to use the PCAP Analyzer!"
    echo ""
    echo "Next steps:"
    echo "1. Access tool at: http://localhost/tool/pcap-analyzer.php"
    echo "2. Upload a PCAP file or use samples from: samples/pcap/"
    echo "3. Enable AI insights for intelligent analysis"
    echo ""
    echo "For demo preparation, see: PCAP_ANALYZER_QUICKSTART.md"
elif [ $ERRORS -eq 0 ]; then
    echo "⚠ SETUP COMPLETE WITH WARNINGS"
    echo "  Errors: $ERRORS"
    echo "  Warnings: $WARNINGS"
    echo ""
    echo "The tool will work, but some features may be limited."
    echo "Review warnings above for optional improvements."
else
    echo "✗ SETUP INCOMPLETE"
    echo "  Errors: $ERRORS"
    echo "  Warnings: $WARNINGS"
    echo ""
    echo "Please fix the errors above before using the tool."
fi
echo "========================================"
