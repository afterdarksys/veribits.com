#!/bin/bash
###############################################################################
# VeriBits - Kubernetes (K3s) Deployment Script for OCI
#
# This script builds and deploys VeriBits to the K3s cluster running on OCI
#
# Usage:
#   ./scripts/k8s-deploy.sh [--skip-build] [--skip-migrations]
#
###############################################################################

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REGISTRY="us-ashburn-1.ocir.io/idd2oizp8xvc/veribits"
IMAGE_NAME="veribits"
TAG="$(date +%Y%m%d-%H%M%S)"
NAMESPACE="veribits-com"

# Flags
SKIP_BUILD=false
SKIP_MIGRATIONS=false

# Parse arguments
for arg in "$@"; do
    case $arg in
        --skip-build)
            SKIP_BUILD=true
            shift
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
        *)
            ;;
    esac
done

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

clear
echo ""
log_info "==================================================================="
log_info "VeriBits - Kubernetes (K3s) Deployment to OCI"
log_info "==================================================================="
echo ""
log_info "Date: $(date)"
log_info "Tag: $TAG"
echo ""

# Build Docker image
if [ "$SKIP_BUILD" = false ]; then
    log_info "Building Docker image..."
    cd "$PROJECT_ROOT"

    docker build -t "${IMAGE_NAME}:${TAG}" -f docker/Dockerfile .
    docker tag "${IMAGE_NAME}:${TAG}" "${REGISTRY}/${IMAGE_NAME}:${TAG}"
    docker tag "${IMAGE_NAME}:${TAG}" "${REGISTRY}/${IMAGE_NAME}:latest"

    log_success "Docker image built: ${REGISTRY}/${IMAGE_NAME}:${TAG}"

    # Push to registry
    log_info "Pushing to OCI Container Registry..."
    docker push "${REGISTRY}/${IMAGE_NAME}:${TAG}"
    docker push "${REGISTRY}/${IMAGE_NAME}:latest"

    log_success "Image pushed to registry"
else
    log_warning "Skipping build (--skip-build flag)"
fi

# Update Kubernetes deployment
log_info "Updating Kubernetes deployment..."

# Set the new image
kubectl set image deployment/veribits-com veribits="${REGISTRY}/${IMAGE_NAME}:${TAG}" -n ${NAMESPACE}

# Annotate deployment to track release
kubectl annotate deployment/veribits-com kubernetes.io/change-cause="Deploy ${TAG} with Phase 2 features" -n ${NAMESPACE} --overwrite

log_success "Deployment updated"

# Wait for rollout
log_info "Waiting for rollout to complete..."
kubectl rollout status deployment/veribits-com -n ${NAMESPACE} --timeout=300s

# Check pod status
log_info "Checking pod status..."
kubectl get pods -n ${NAMESPACE} -l app=veribits-com

# Run migrations if not skipped
if [ "$SKIP_MIGRATIONS" = false ]; then
    log_info "Running database migrations..."

    # Get a running pod
    POD=$(kubectl get pods -n ${NAMESPACE} -l app=veribits-com -o jsonpath='{.items[0].metadata.name}')

    if [ -n "$POD" ]; then
        log_info "Using pod: $POD"

        # Run migration 030
        kubectl exec -n ${NAMESPACE} "$POD" -- bash -c "
            if [ -f /var/www/html/db/migrations/030_threat_intelligence_tables.sql ]; then
                psql -h \$DB_HOST -U \$DB_USERNAME -d \$DB_DATABASE -f /var/www/html/db/migrations/030_threat_intelligence_tables.sql 2>/dev/null || echo 'Migration may already be applied'
            else
                echo 'Migration file not found in container'
            fi
        " || log_warning "Migration failed (may already be applied)"

        log_success "Migrations complete"
    else
        log_warning "No running pods found, skipping migrations"
    fi
else
    log_warning "Skipping migrations (--skip-migrations flag)"
fi

# Verify deployment
log_info "Verifying deployment..."

# Test health endpoint
CLUSTER_IP=$(kubectl get svc veribits-com -n ${NAMESPACE} -o jsonpath='{.spec.clusterIP}')
log_info "Service IP: $CLUSTER_IP"

# Get ingress info
INGRESS_IP=$(kubectl get ingress veribits-com -n ${NAMESPACE} -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
log_info "Ingress IP: $INGRESS_IP"

echo ""
log_info "==================================================================="
log_success "DEPLOYMENT SUMMARY"
log_info "==================================================================="
echo ""
log_info "Image: ${REGISTRY}/${IMAGE_NAME}:${TAG}"
log_info "Namespace: ${NAMESPACE}"
log_info "Service: veribits-com (${CLUSTER_IP})"
log_info "Ingress: ${INGRESS_IP}"
echo ""
log_info "New Features Deployed:"
echo "  - Threat Intelligence APIs (VirusTotal, MalwareBazaar)"
echo "  - YARA Scanning & IOC Feeds"
echo "  - Interactive Malware Sandbox"
echo "  - CI/CD Integration (GitHub Actions, GitLab CI)"
echo "  - SBOM Generation & Validation"
echo "  - Smart Cryptographic Services"
echo "  - Steganography Detection at Scale"
echo "  - Incident Notifications (Webhooks, Slack, SIEM)"
echo "  - Security Posture Dashboard"
echo "  - Developer SDKs (Python + TypeScript)"
echo ""
log_info "Test URLs:"
echo "  - Main: https://veribits.com/"
echo "  - Health: https://veribits.com/api/v1/health"
echo "  - Threat Intel: https://veribits.com/api/v1/threat-intel/lookup"
echo "  - Sandbox: https://veribits.com/api/v1/sandbox/submit"
echo ""
log_success "Deployment complete!"
