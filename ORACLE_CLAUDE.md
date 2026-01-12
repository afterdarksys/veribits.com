# Oracle Support Meeting - OKE Node Pool Scaling Issue

**Meeting**: Tomorrow at noon (2026-01-09 12:00 PM)
**Contact**: John Trudelle <john.trudelle@oracle.com>

**Zoom Details**:
- **Join URL**: https://oracle.zoom.us/j/7550032176?pwd=MWxrcE9TRmlENTBqNEducWY0QnpTZz09&omn=93533409829
- **Meeting ID**: 755 003 2176
- **Password**: 6zJVXPPP

## Problem Summary

OKE cluster node pools cannot scale. All `NODEPOOL_UPDATE` operations fail immediately with no error message or logs. Node pools show "UPDATING" state but work requests fail within seconds.

## Current State

- **Available A1 Flex capacity**: 227 cores (only 14 used)
- **Node pool config size**: 6 nodes (main), 2 nodes (high-mem)
- **Actual compute instances**: 3 nodes (main), 1 node (high-mem)
- **Kubernetes sees**: 5 nodes total
- **Cluster utilization**: 76-100% CPU on existing nodes

## Cluster Details

**Cluster OCID**:
```
ocid1.cluster.oc1.iad.aaaaaaaavajsiqwbr4w5dye3llkywayrmyjy2l27fs7c5ge4lcc5dcl3l4na
```

**Cluster Name**: `undateable-oke-cluster`
**Region**: us-ashburn-1
**Kubernetes Version**: v1.31.1

### Main Node Pool

**Node Pool OCID**:
```
ocid1.nodepool.oc1.iad.aaaaaaaavqvryrvg2ryqqwuqaihm55irl6jqmoiijg62vxr6bn43lmqa6wvq
```

**Name**: `undateable-node-pool`
**Shape**: VM.Standard.A1.Flex (4 OCPUs, 16GB RAM per node)
**Current State**: UPDATING (stuck)
**Configured Size**: 6
**Actual Instances**: 3

### High-Memory Pool

**Node Pool OCID**:
```
ocid1.nodepool.oc1.iad.aaaaaaaat7o6nmpedzztrl22nlqi4sinkk5bfiqsjijb7gqydnctd5aameza
```

**Name**: `undateable-high-mem-pool`
**Shape**: VM.Standard.A1.Flex (8 OCPUs, 64GB RAM per node)
**Current State**: UPDATING (stuck)
**Configured Size**: 2
**Actual Instances**: 1

## Failed Work Requests

All scaling attempts fail with no error logs:

**Most Recent Failed Work Request**:
```
ocid1.clustersworkrequest.oc1.iad.amaaaaaakta7lgya5lj5k7wlft3dukzirfkgbzlrmesoi3jutmqr7y66xnzq
```

**Timeline**:
- `2026-01-08 14:42:31` - NODEPOOL_UPDATE: FAILED (main pool, OCI CLI attempt)
- `2026-01-08 14:42:32` - NODEPOOL_UPDATE: FAILED (high-mem pool, OCI CLI attempt)
- `2026-01-08 14:43:41` - NODEPOOL_RECONCILE: FAILED (system auto-reconcile for main)
- `2026-01-08 14:43:41` - NODEPOOL_RECONCILE: FAILED (system auto-reconcile for high-mem)
- `2026-01-08 14:46:17` - NODEPOOL_UPDATE: FAILED (another CLI attempt)

All requests complete in 5-10 seconds with "FAILED" status but **no error messages in logs**.

## What We Tried

1. **OCI CLI scaling** (`oci ce node-pool update --size 6`) → FAILED
2. **OCI Console scaling** (GUI edit to size 6) → FAILED
3. **Verified capacity** (227 A1 cores available) → OK
4. **Checked service limits** → No quota issues found

## Infrastructure Notes

- Node pools are tagged with `ManagedBy: Terraform`
- Terraform config found in repo but doesn't manage this cluster
- Direct OCI CLI/Console operations should work but don't

## Questions for Oracle

1. Why do NODEPOOL_UPDATE operations fail with no error logs?
2. Is there a hidden service limit or policy blocking scaling?
3. How do we get detailed error logs for failed work requests?
4. Is the Terraform tag causing issues? Should we remove it?
5. Why does the node pool stay in UPDATING state when all work requests fail?

## Business Impact

- Cluster at 76-100% CPU capacity
- Unable to deploy new services (ShipShack.io deployment blocked)
- 6 pods stuck in Pending/ImagePullBackOff state
- Need capacity for production workloads

## Working Services (Not Affected)

- VeriBits.com: ✅ Fully operational (deployed successfully before this issue)
- Database: ✅ Running (veribits-db)
- Redis: ✅ Running
- All health checks: ✅ Passing

## Contact Info

**Tenancy OCID**:
```
ocid1.tenancy.oc1..aaaaaaaaiqfc57o25y3424skethbodacbasc2zy3yp2b423zj6qkhcwjkqta
```

**User OCID**:
```
ocid1.user.oc1..aaaaaaaawmyxjvsqqbr56pivzavxdtm2torhypkdd2dqtvemy4b4vefit52q
```

**Compartment OCID**:
```
ocid1.compartment.oc1..aaaaaaaai6bnxkfo3qizo7kur3emh3ah7exosyvxsruniogiynsumb7rt4nq
```

---

## Quick Commands for Oracle Support

Check node pool status:
```bash
oci ce node-pool get --node-pool-id ocid1.nodepool.oc1.iad.aaaaaaaavqvryrvg2ryqqwuqaihm55irl6jqmoiijg62vxr6bn43lmqa6wvq
```

Check failed work request:
```bash
oci ce work-request get --work-request-id ocid1.clustersworkrequest.oc1.iad.amaaaaaakta7lgya5lj5k7wlft3dukzirfkgbzlrmesoi3jutmqr7y66xnzq
```

List all work requests:
```bash
oci ce work-request list --compartment-id ocid1.compartment.oc1..aaaaaaaai6bnxkfo3qizo7kur3emh3ah7exosyvxsruniogiynsumb7rt4nq --resource-id ocid1.nodepool.oc1.iad.aaaaaaaavqvryrvg2ryqqwuqaihm55irl6jqmoiijg62vxr6bn43lmqa6wvq --all
```

---

**Expected Outcome**: Oracle identifies why scaling is blocked and provides fix or workaround to add 3+ nodes to cluster.
