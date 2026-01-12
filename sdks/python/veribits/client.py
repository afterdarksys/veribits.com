"""VeriBits API Client"""

import requests
from typing import Optional, Dict, Any

from .threat_intel import ThreatIntelAPI
from .sandbox import SandboxAPI
from .cicd import CICDAPI
from .crypto import CryptoAPI


class VeriBits:
    """Main VeriBits API client"""

    def __init__(self, api_key: str, api_url: str = "https://api.veribits.com"):
        """
        Initialize VeriBits client

        Args:
            api_key: Your VeriBits API key
            api_url: API base URL (default: https://api.veribits.com)
        """
        self.api_key = api_key
        self.api_url = api_url.rstrip('/')
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {api_key}',
            'User-Agent': 'veribits-python/1.0.0'
        })

        # Initialize API modules
        self.threat_intel = ThreatIntelAPI(self)
        self.sandbox = SandboxAPI(self)
        self.cicd = CICDAPI(self)
        self.crypto = CryptoAPI(self)

    def request(self, method: str, endpoint: str, **kwargs) -> Dict[str, Any]:
        """Make API request"""
        url = f"{self.api_url}{endpoint}"
        response = self.session.request(method, url, **kwargs)
        response.raise_for_status()
        return response.json()

    def get(self, endpoint: str, **kwargs) -> Dict[str, Any]:
        """GET request"""
        return self.request('GET', endpoint, **kwargs)

    def post(self, endpoint: str, **kwargs) -> Dict[str, Any]:
        """POST request"""
        return self.request('POST', endpoint, **kwargs)
