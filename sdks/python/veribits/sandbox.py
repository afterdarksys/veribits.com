"""VeriBits Sandbox API"""

from typing import Dict, Any, Optional, BinaryIO
import os


class SandboxAPI:
    """Malware sandbox analysis API"""

    def __init__(self, client):
        self.client = client

    def submit(
        self,
        file_path: str = None,
        file_obj: BinaryIO = None,
        analysis_type: str = "full"
    ) -> Dict[str, Any]:
        """
        Submit file for sandbox analysis

        Args:
            file_path: Path to file to analyze
            file_obj: File-like object to analyze
            analysis_type: 'static', 'dynamic', or 'full'

        Returns:
            Submission result with task_id
        """
        if file_path:
            with open(file_path, 'rb') as f:
                files = {'file': (os.path.basename(file_path), f)}
                return self.client.request(
                    'POST',
                    '/api/v1/sandbox/submit',
                    files=files,
                    data={'analysis_type': analysis_type}
                )
        elif file_obj:
            files = {'file': file_obj}
            return self.client.request(
                'POST',
                '/api/v1/sandbox/submit',
                files=files,
                data={'analysis_type': analysis_type}
            )
        else:
            raise ValueError("Either file_path or file_obj must be provided")

    def status(self, task_id: str) -> Dict[str, Any]:
        """Get analysis status"""
        return self.client.get(f'/api/v1/sandbox/status/{task_id}')

    def result(self, task_id: str) -> Dict[str, Any]:
        """Get analysis results"""
        return self.client.get(f'/api/v1/sandbox/result/{task_id}')

    def list_submissions(
        self,
        limit: int = 50,
        offset: int = 0,
        status: Optional[str] = None
    ) -> Dict[str, Any]:
        """List sandbox submissions"""
        params = {'limit': limit, 'offset': offset}
        if status:
            params['status'] = status
        return self.client.get('/api/v1/sandbox/submissions', params=params)
