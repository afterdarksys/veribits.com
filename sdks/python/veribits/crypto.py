"""VeriBits Cryptographic Services API"""

from typing import Dict, Any, Optional


class CryptoAPI:
    """Cryptographic services API"""

    def __init__(self, client):
        self.client = client

    def publish_key(
        self,
        key_data: str,
        key_type: str = "pgp",
        metadata: Optional[Dict[str, Any]] = None
    ) -> Dict[str, Any]:
        """
        Publish a public key

        Args:
            key_data: The public key data (PEM or armored format)
            key_type: Key type ('pgp', 'ssh', 'x509')
            metadata: Optional metadata to attach

        Returns:
            Published key info with key_id
        """
        return self.client.post('/api/v1/crypto/keys/publish', json={
            'key_data': key_data,
            'key_type': key_type,
            'metadata': metadata or {}
        })

    def lookup_key(
        self,
        identifier: str,
        key_type: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Lookup a public key

        Args:
            identifier: Email, fingerprint, or key ID
            key_type: Optional filter by key type

        Returns:
            Key data and metadata
        """
        params = {'identifier': identifier}
        if key_type:
            params['key_type'] = key_type
        return self.client.get('/api/v1/crypto/keys/lookup', params=params)

    def verify_signature(
        self,
        data: str,
        signature: str,
        key_id: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Verify a cryptographic signature

        Args:
            data: The signed data
            signature: The signature to verify
            key_id: Optional specific key to verify against

        Returns:
            Verification result
        """
        return self.client.post('/api/v1/crypto/verify', json={
            'data': data,
            'signature': signature,
            'key_id': key_id
        })

    def validate_certificate(
        self,
        certificate: str,
        check_revocation: bool = True
    ) -> Dict[str, Any]:
        """
        Validate an X.509 certificate

        Args:
            certificate: PEM-encoded certificate
            check_revocation: Check OCSP/CRL for revocation

        Returns:
            Validation result with chain info
        """
        return self.client.post('/api/v1/crypto/certificates/validate', json={
            'certificate': certificate,
            'check_revocation': check_revocation
        })
