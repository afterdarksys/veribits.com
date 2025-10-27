# VeriBits Terraform Provider & Ansible Module

## Overview

Creating Terraform and Ansible integrations for VeriBits would allow security tools to be used in Infrastructure-as-Code (IaC) workflows.

## Difficulty Assessment

### Terraform Provider: **Moderate** (3-5 days)
✅ Well-documented SDK (terraform-plugin-sdk)
✅ Go-based (straightforward)
✅ Clear patterns to follow
⚠️ Requires understanding Terraform lifecycle

### Ansible Module: **Easy** (1-2 days)
✅ Python-based (we already have Python CLI)
✅ Simple module structure
✅ Can reuse existing Python code
✅ Well-documented module development guide

---

## Terraform Provider Design

### Structure

```
terraform-provider-veribits/
├── main.go                    # Provider entrypoint
├── veribits/
│   ├── provider.go            # Provider configuration
│   ├── resource_iam_policy.go # IAM policy validation resource
│   ├── resource_ssl_cert.go   # SSL certificate resource
│   ├── data_source_dns.go     # DNS validation data source
│   ├── data_source_whois.go   # WHOIS lookup data source
│   └── client.go              # API client wrapper
├── examples/
│   └── main.tf                # Usage examples
└── docs/
    └── resources/             # Resource documentation
```

### Example Usage

```hcl
terraform {
  required_providers {
    veribits = {
      source  = "afterdarksystems/veribits"
      version = "~> 2.0"
    }
  }
}

provider "veribits" {
  api_key = var.veribits_api_key
  api_url = "https://veribits.com/api/v1"
}

# Validate IAM policy before deployment
resource "veribits_iam_policy_validation" "my_policy" {
  policy_document = file("${path.module}/policy.json")
  policy_name     = "my-s3-bucket-policy"

  # Fail if risk level is high or critical
  max_risk_level = "medium"
}

# Check SSL certificate expiration
data "veribits_ssl_check" "api_cert" {
  host = "api.example.com"
  port = 443
}

output "cert_expires_in_days" {
  value = data.veribits_ssl_check.api_cert.expires_in_days
}

# Validate DNS before creating Route53 records
data "veribits_dns_validate" "domain" {
  domain = "example.com"
  type   = "A"
}

# Check secrets in deployment files
resource "veribits_secrets_scan" "deployment_yaml" {
  source_file = "${path.module}/k8s/deployment.yaml"
  source_type = "file"

  # Fail deployment if secrets found
  fail_on_secrets = true
}

# Verify email domain before SES setup
data "veribits_email_spf" "domain" {
  domain = "example.com"
}

output "spf_valid" {
  value = data.veribits_email_spf.domain.valid
}
```

### Key Resources to Implement

#### Data Sources (Read-only)
1. `veribits_ssl_check` - SSL certificate validation
2. `veribits_dns_validate` - DNS record validation
3. `veribits_whois` - WHOIS lookup
4. `veribits_email_spf` - SPF record check
5. `veribits_email_dmarc` - DMARC policy check
6. `veribits_ip_calc` - IP subnet calculation
7. `veribits_rbl_check` - RBL/DNSBL check

#### Resources (Stateful)
1. `veribits_iam_policy_validation` - IAM policy analysis
2. `veribits_secrets_scan` - Secrets scanning
3. `veribits_security_headers` - HTTP headers check
4. `veribits_cloud_storage_scan` - Cloud storage security

### Implementation Steps

1. **Setup** (Day 1)
   ```bash
   go mod init github.com/afterdarksystems/terraform-provider-veribits
   go get github.com/hashicorp/terraform-plugin-sdk/v2
   ```

2. **Provider Skeleton** (Day 1)
   - Create provider.go with API key configuration
   - Implement API client wrapper
   - Setup basic authentication

3. **Implement Data Sources** (Day 2)
   - Start with simple ones (DNS, WHOIS, SSL)
   - Add schema definitions
   - Implement Read functions

4. **Implement Resources** (Day 3)
   - IAM policy validation
   - Secrets scanning
   - CRUD operations

5. **Testing & Documentation** (Day 4-5)
   - Unit tests
   - Acceptance tests
   - Documentation generation
   - Example configurations

---

## Ansible Module Design

### Structure

```
ansible-collection-veribits/
├── plugins/
│   └── modules/
│       ├── iam_policy_validate.py
│       ├── secrets_scan.py
│       ├── ssl_check.py
│       ├── email_verify.py
│       └── dns_validate.py
├── playbooks/
│   └── examples/
│       └── security_audit.yml
└── galaxy.yml
```

### Example Usage

```yaml
---
- name: VeriBits Security Audit
  hosts: localhost
  tasks:
    # Validate IAM policies
    - name: Check IAM policy security
      afterdarksystems.veribits.iam_policy_validate:
        policy_file: /path/to/policy.json
        api_key: "{{ veribits_api_key }}"
      register: iam_result

    - name: Fail if IAM policy has critical issues
      fail:
        msg: "IAM policy has {{ iam_result.findings | length }} critical issues"
      when: iam_result.risk_level == "critical"

    # Scan for secrets in deployment files
    - name: Scan Kubernetes manifests for secrets
      afterdarksystems.veribits.secrets_scan:
        source_file: /path/to/k8s/deployment.yaml
        api_key: "{{ veribits_api_key }}"
      register: secrets_result

    - name: Fail if secrets found
      fail:
        msg: "Secrets found in deployment files!"
      when: secrets_result.secrets_found > 0

    # Verify SSL certificates
    - name: Check SSL certificate expiration
      afterdarksystems.veribits.ssl_check:
        host: "{{ item }}"
        port: 443
        api_key: "{{ veribits_api_key }}"
      loop:
        - api.example.com
        - www.example.com
        - admin.example.com
      register: ssl_results

    - name: Alert on expiring certificates
      debug:
        msg: "Certificate for {{ item.host }} expires in {{ item.expires_in_days }} days"
      loop: "{{ ssl_results.results }}"
      when: item.expires_in_days < 30

    # Email domain verification
    - name: Verify email domain configuration
      afterdarksystems.veribits.email_verify:
        domain: example.com
        checks:
          - spf
          - dmarc
          - mx
        api_key: "{{ veribits_api_key }}"
      register: email_result

    - name: Display email configuration status
      debug:
        msg: "Email score: {{ email_result.deliverability_score }}/100"

    # DNS validation
    - name: Validate DNS records
      afterdarksystems.veribits.dns_validate:
        domain: example.com
        record_type: A
        api_key: "{{ veribits_api_key }}"
      register: dns_result

    # Cloud storage security scan
    - name: Scan S3 buckets for security issues
      afterdarksystems.veribits.cloud_storage_scan:
        bucket: my-s3-bucket
        provider: aws
        api_key: "{{ veribits_api_key }}"
      register: storage_result

    - name: Fail if bucket is publicly accessible
      fail:
        msg: "S3 bucket {{ storage_result.bucket }} is publicly accessible!"
      when: storage_result.public_access == true
```

### Module Implementation Example

```python
#!/usr/bin/python
# -*- coding: utf-8 -*-

# Copyright: (c) After Dark Systems, LLC
# GNU General Public License v3.0+

from ansible.module_utils.basic import AnsibleModule
import requests

DOCUMENTATION = '''
---
module: iam_policy_validate
short_description: Validate AWS IAM policies for security issues
description:
  - Analyzes IAM policies for security misconfigurations
  - Returns risk score and findings
  - Can fail playbook based on risk level
options:
  policy_file:
    description: Path to IAM policy JSON file
    required: true
    type: path
  api_key:
    description: VeriBits API key
    required: false
    type: str
  max_risk_level:
    description: Maximum acceptable risk level (low, medium, high, critical)
    required: false
    default: high
    type: str
'''

EXAMPLES = '''
- name: Validate IAM policy
  afterdarksystems.veribits.iam_policy_validate:
    policy_file: /path/to/policy.json
    api_key: "{{ veribits_api_key }}"
    max_risk_level: medium
'''

def run_module():
    module = AnsibleModule(
        argument_spec=dict(
            policy_file=dict(type='path', required=True),
            api_key=dict(type='str', required=False, no_log=True),
            max_risk_level=dict(type='str', default='high'),
        ),
        supports_check_mode=True
    )

    # Read policy file
    with open(module.params['policy_file'], 'r') as f:
        policy = json.load(f)

    # Call VeriBits API
    headers = {}
    if module.params['api_key']:
        headers['Authorization'] = f"Bearer {module.params['api_key']}"

    response = requests.post(
        'https://veribits.com/api/v1/security/iam-policy/analyze',
        json={'policy_document': policy},
        headers=headers
    )

    result = response.json()

    # Determine if we should fail
    risk_levels = ['low', 'medium', 'high', 'critical']
    max_level_index = risk_levels.index(module.params['max_risk_level'])
    current_level_index = risk_levels.index(result['data']['risk_level'])

    changed = False
    failed = current_level_index > max_level_index

    module.exit_json(
        changed=changed,
        failed=failed,
        risk_score=result['data']['risk_score'],
        risk_level=result['data']['risk_level'],
        findings=result['data']['findings']
    )

def main():
    run_module()

if __name__ == '__main__':
    main()
```

### Implementation Steps

1. **Setup Collection** (Day 1 - Morning)
   ```bash
   ansible-galaxy collection init afterdarksystems.veribits
   ```

2. **Create Core Modules** (Day 1 - Afternoon)
   - `iam_policy_validate.py`
   - `secrets_scan.py`
   - `ssl_check.py`

3. **Add More Modules** (Day 2)
   - Email verification modules
   - DNS validation modules
   - Cloud security modules

4. **Testing & Documentation** (Day 2)
   - Integration tests
   - Example playbooks
   - README and documentation

---

## Use Cases

### CI/CD Pipeline Integration

#### Terraform Example
```hcl
# Pre-deployment security checks
resource "veribits_secrets_scan" "pre_deploy" {
  source_file = "${path.module}/app/config.yaml"
  fail_on_secrets = true
}

resource "veribits_iam_policy_validation" "role_policy" {
  policy_document = aws_iam_policy.app_policy.policy
  max_risk_level = "medium"
}

# Proceed with deployment only if checks pass
resource "aws_lambda_function" "app" {
  depends_on = [
    veribits_secrets_scan.pre_deploy,
    veribits_iam_policy_validation.role_policy
  ]
  # ... lambda configuration
}
```

#### Ansible Example
```yaml
- name: Pre-deployment security audit
  hosts: localhost
  tasks:
    - name: Scan for exposed secrets
      afterdarksystems.veribits.secrets_scan:
        source_file: "{{ playbook_dir }}/vars/secrets.yml"
      register: scan_result
      failed_when: scan_result.secrets_found > 0

    - name: Deploy application
      # Only runs if secrets scan passed
      docker_container:
        name: myapp
        image: myapp:latest
```

---

## Benefits

### For DevOps Teams

1. **Shift-Left Security** - Catch issues before deployment
2. **Automated Compliance** - Built into IaC workflows
3. **Consistent Checks** - Same security validation everywhere
4. **Fail Fast** - Block deployments with security issues
5. **Audit Trail** - Security checks in version control

### For Security Teams

1. **Policy Enforcement** - Security gates in deployment pipelines
2. **Visibility** - Track security checks across infrastructure
3. **Automation** - Reduce manual security reviews
4. **Integration** - Works with existing tools

---

## Publishing

### Terraform Provider
```bash
# Publish to Terraform Registry
terraform registry publish afterdarksystems/veribits

# Users install with:
terraform {
  required_providers {
    veribits = {
      source = "afterdarksystems/veribits"
    }
  }
}
```

### Ansible Collection
```bash
# Publish to Ansible Galaxy
ansible-galaxy collection publish afterdarksystems-veribits-2.0.0.tar.gz

# Users install with:
ansible-galaxy collection install afterdarksystems.veribits

# Use in playbooks:
- hosts: all
  collections:
    - afterdarksystems.veribits
```

---

## Estimated Effort

| Component | Difficulty | Time | Skills Required |
|-----------|-----------|------|-----------------|
| **Terraform Provider** | Moderate | 3-5 days | Go, Terraform SDK |
| **Ansible Module** | Easy | 1-2 days | Python, Ansible |
| **Combined Total** | | **4-7 days** | |

---

## Conclusion

**Recommended Approach:**

1. **Start with Ansible** (easier, faster ROI)
   - Reuse existing Python CLI code
   - Quick to implement and test
   - Immediate value for automation teams

2. **Follow with Terraform** (broader adoption)
   - More complex but widely used
   - Better for infrastructure provisioning
   - Essential for IaC workflows

Both integrations would significantly increase VeriBits adoption by embedding security directly into infrastructure workflows.

---

**© After Dark Systems, LLC**
**VeriBits IaC Integration Design**
