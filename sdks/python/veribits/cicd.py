"""VeriBits CI/CD Integration API"""

from typing import Dict, Any, List, Optional


class CICDAPI:
    """CI/CD security integration API"""

    def __init__(self, client):
        self.client = client

    def generate_sbom(
        self,
        directory: str = ".",
        format: str = "cyclonedx",
        include_dev: bool = False
    ) -> Dict[str, Any]:
        """
        Generate Software Bill of Materials (SBOM)

        Args:
            directory: Project directory to scan
            format: Output format ('cyclonedx' or 'spdx')
            include_dev: Include dev dependencies

        Returns:
            Generated SBOM
        """
        return self.client.post('/api/v1/ci/sbom/generate', json={
            'directory': directory,
            'format': format,
            'include_dev': include_dev
        })

    def scan_dependencies(
        self,
        manifest_path: str,
        fail_on_critical: bool = True
    ) -> Dict[str, Any]:
        """
        Scan dependencies for vulnerabilities

        Args:
            manifest_path: Path to package manifest (package.json, requirements.txt, etc.)
            fail_on_critical: Fail if critical vulnerabilities found

        Returns:
            Vulnerability scan results
        """
        return self.client.post('/api/v1/ci/dependencies/scan', json={
            'manifest_path': manifest_path,
            'fail_on_critical': fail_on_critical
        })

    def scan_secrets(
        self,
        directory: str = ".",
        patterns: Optional[List[str]] = None
    ) -> Dict[str, Any]:
        """
        Scan for hardcoded secrets

        Args:
            directory: Directory to scan
            patterns: Custom regex patterns to check

        Returns:
            Secret scan results
        """
        return self.client.post('/api/v1/ci/secrets/scan', json={
            'directory': directory,
            'patterns': patterns or []
        })

    def get_policy(self, policy_id: str) -> Dict[str, Any]:
        """Get security policy"""
        return self.client.get(f'/api/v1/ci/policies/{policy_id}')

    def evaluate_policy(
        self,
        policy_id: str,
        scan_results: Dict[str, Any]
    ) -> Dict[str, Any]:
        """
        Evaluate scan results against policy

        Args:
            policy_id: Policy to evaluate against
            scan_results: Results from a scan operation

        Returns:
            Policy evaluation result (pass/fail with details)
        """
        return self.client.post(f'/api/v1/ci/policies/{policy_id}/evaluate', json={
            'scan_results': scan_results
        })
