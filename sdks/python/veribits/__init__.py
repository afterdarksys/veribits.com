"""
VeriBits Python SDK
===================

Official Python client for VeriBits API

Installation:
    pip install veribits

Usage:
    from veribits import VeriBits

    client = VeriBits(api_key="your_api_key")

    # Threat intelligence lookup
    result = client.threat_intel.lookup("sha256_hash")

    # Scan file
    scan = client.sandbox.submit("/path/to/file")

    # Generate SBOM
    sbom = client.cicd.generate_sbom(format="cyclonedx")
"""

__version__ = "1.0.0"

from .client import VeriBits
from .exceptions import (
    VeriBitsError,
    APIError,
    AuthenticationError,
    RateLimitError,
    ValidationError
)

__all__ = [
    'VeriBits',
    'VeriBitsError',
    'APIError',
    'AuthenticationError',
    'RateLimitError',
    'ValidationError'
]
