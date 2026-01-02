#!/bin/bash

# Script to create tar.bz2 of veribits-system-client and upload to S3

set -e

# Configuration
CLIENT_DIR="veribits-system-client-1.0"
ARCHIVE_NAME="veribits-system-client-1.0.tar.bz2"
S3_BUCKET="veribits-deployment-production-20251026"
S3_REGION="us-east-1"

# Check if directory exists
if [ ! -d "$CLIENT_DIR" ]; then
    echo "Error: Directory $CLIENT_DIR not found"
    exit 1
fi

# Create tar.bz2 archive
echo "Creating archive $ARCHIVE_NAME..."
tar -cjf "$ARCHIVE_NAME" "$CLIENT_DIR"
echo "Archive created successfully"

# Upload to S3 with public-read ACL
echo "Uploading to S3..."
aws s3 cp "$ARCHIVE_NAME" "s3://$S3_BUCKET/$ARCHIVE_NAME" \
    --acl public-read \
    --region "$S3_REGION"

# Generate public URL
PUBLIC_URL="https://$S3_BUCKET.s3.$S3_REGION.amazonaws.com/$ARCHIVE_NAME"

echo ""
echo "Upload complete!"
echo "Public URL: $PUBLIC_URL"
echo ""
echo "You can also access via:"
echo "https://s3.$S3_REGION.amazonaws.com/$S3_BUCKET/$ARCHIVE_NAME"
