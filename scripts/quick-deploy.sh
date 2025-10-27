#!/bin/bash

# Quick deploy script for VeriBits
# Syncs files directly to the server

set -e

echo "🚀 VeriBits Quick Deploy"
echo "========================"

# Get EC2 instance IP
echo "📡 Finding VeriBits server..."
SERVER_IP=$(aws ec2 describe-instances \
    --filters "Name=tag:Name,Values=veribits-web" "Name=instance-state-name,Values=running" \
    --query 'Reservations[0].Instances[0].PublicIpAddress' \
    --output text 2>/dev/null || echo "")

if [ -z "$SERVER_IP" ] || [ "$SERVER_IP" == "None" ]; then
    echo "❌ Could not find running VeriBits server"
    echo "Checking ECS service..."

    # Try ECS approach
    TASK_ARN=$(aws ecs list-tasks --cluster veribits-cluster --service-name veribits-web-service --query 'taskArns[0]' --output text 2>/dev/null || echo "")

    if [ -z "$TASK_ARN" ] || [ "$TASK_ARN" == "None" ]; then
        echo "❌ No running ECS tasks found"
        echo "Please check AWS console or start the service first"
        exit 1
    fi

    echo "✅ Found ECS task: $TASK_ARN"
    echo "Note: Using ECS deployment (container-based)"

    # For ECS, we need to update the task definition and force new deployment
    echo "🔄 Updating ECS service..."
    aws ecs update-service \
        --cluster veribits-cluster \
        --service veribits-web-service \
        --force-new-deployment \
        --region us-east-1

    echo "✅ ECS service update initiated"
    echo "⏳ Deployment will take 2-3 minutes"
    echo "Monitor at: https://console.aws.amazon.com/ecs/v2/clusters/veribits-cluster/services/veribits-web-service"

else
    echo "✅ Found server: $SERVER_IP"

    # Sync files to server (EC2 approach)
    echo "📤 Syncing files..."
    rsync -avz --exclude 'node_modules' --exclude '.git' --exclude 'vendor' \
        --exclude 'tests/node_modules' --exclude 'cli/nodejs/node_modules' \
        app/ ec2-user@$SERVER_IP:/var/www/veribits/app/

    echo "🔄 Restarting services on server..."
    ssh ec2-user@$SERVER_IP "sudo systemctl restart php-fpm && sudo systemctl restart nginx"

    echo "✅ Deployment complete!"
    echo "🌐 Visit: https://www.veribits.com"
fi

echo ""
echo "📋 Deployment Summary:"
echo "  - New docs page: https://www.veribits.com/docs.php"
echo "  - CLI console mode documentation added"
echo "  - Terraform/Ansible integration docs added"
echo "  - Navigation updated with Docs link"
echo ""
echo "✨ Done!"
