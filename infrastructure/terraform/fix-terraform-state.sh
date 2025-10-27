#!/bin/bash
# VeriBits Terraform State Drift Fix Script
# This script imports existing AWS resources that are missing from Terraform state
# Created: 2025-10-26
# Account: 515966511618
# Region: us-east-1

set -e  # Exit on any error

REGION="us-east-1"
ACCOUNT_ID="515966511618"

echo "=========================================="
echo "VeriBits Terraform State Import"
echo "=========================================="
echo "Region: $REGION"
echo "Account: $ACCOUNT_ID"
echo ""
echo "WARNING: This script will modify Terraform state."
echo "Ensure you have a backup of terraform.tfstate"
echo ""
read -p "Continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo "Starting import process..."
echo ""

# Counter for tracking
IMPORTED=0
FAILED=0

# Function to import resource
import_resource() {
    local resource_address=$1
    local resource_id=$2
    local description=$3

    echo "--------------------------------------"
    echo "Importing: $description"
    echo "Address: $resource_address"
    echo "ID: $resource_id"

    if terraform import "$resource_address" "$resource_id" 2>&1; then
        echo "✓ SUCCESS: $description"
        ((IMPORTED++))
    else
        echo "✗ FAILED: $description"
        ((FAILED++))
    fi
    echo ""
}

# 1. ElastiCache Redis Cluster
# CRITICAL: This is causing the "Cache cluster already exists" error
import_resource \
    "aws_elasticache_cluster.redis" \
    "veribits-redis" \
    "ElastiCache Redis Cluster"

# 2. ECS Task Definition
# Note: Task definitions use family:revision format
import_resource \
    "aws_ecs_task_definition.api" \
    "arn:aws:ecs:us-east-1:515966511618:task-definition/veribits-api:2" \
    "ECS Task Definition (veribits-api:2)"

# 3. ECS Service
# CRITICAL NAME MISMATCH: AWS resource is "veribits-api" but Terraform expects "veribits-api-svc"
# This import will FAIL because of name mismatch - needs manual fix in Terraform config
echo "--------------------------------------"
echo "CRITICAL ISSUE DETECTED:"
echo "ECS Service name mismatch"
echo "  Terraform config: veribits-api-svc"
echo "  AWS actual name: veribits-api"
echo ""
echo "Import will fail. Manual fix required:"
echo "  1. Edit afterdarksys.tf line 420"
echo "  2. Change 'name = \"veribits-api-svc\"' to 'name = \"veribits-api\"'"
echo "  3. Re-run this script"
echo ""
read -p "Skip ECS service import? (yes/no): " skip_ecs

if [ "$skip_ecs" != "yes" ]; then
    import_resource \
        "aws_ecs_service.api" \
        "veribits-cluster/veribits-api" \
        "ECS Service (veribits-api) - EXPECTED TO FAIL"
fi

echo ""
echo "=========================================="
echo "Import Summary"
echo "=========================================="
echo "Successfully imported: $IMPORTED"
echo "Failed imports: $FAILED"
echo ""

if [ $FAILED -gt 0 ]; then
    echo "⚠ WARNINGS:"
    echo ""
    echo "1. ECS Service Import Failed (Expected)"
    echo "   Root cause: Name mismatch in Terraform config"
    echo "   Fix: Edit afterdarksys.tf line 420"
    echo "   Change: name = \"veribits-api-svc\" → name = \"veribits-api\""
    echo ""
    echo "2. After fixing, run:"
    echo "   terraform import aws_ecs_service.api veribits-cluster/veribits-api"
    echo ""
fi

echo "Next steps:"
echo "1. Run: terraform plan"
echo "2. Review changes carefully"
echo "3. Expected: No changes needed (state matches reality)"
echo ""
echo "=========================================="
