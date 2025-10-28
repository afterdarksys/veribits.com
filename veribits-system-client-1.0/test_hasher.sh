#!/bin/bash
# Quick test of file_hasher on current directory
# Safe to run without sudo

echo "=== File Hasher Test ==="
echo "Scanning current directory with both SHA256 and SHA512..."
echo ""

./file_hasher.py \
  --root . \
  --hash sha256 sha512 \
  --threads 4 \
  --output test_output.json

if [ $? -eq 0 ]; then
    echo ""
    echo "=== Test Complete ==="
    echo "Output saved to: test_output.json"
    echo ""
    echo "Quick stats:"
    cat test_output.json | python3 -m json.tool | grep -E "(system_name|total_files|total_errors|hash_algorithms)" | head -10
    echo ""
    echo "Full output available in test_output.json"
else
    echo "Test failed!"
    exit 1
fi
