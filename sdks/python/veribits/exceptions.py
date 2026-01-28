"""VeriBits SDK Exceptions"""


class VeriBitsError(Exception):
    """Base exception for VeriBits SDK"""
    pass


class APIError(VeriBitsError):
    """API request failed"""
    def __init__(self, message: str, status_code: int = None, response: dict = None):
        super().__init__(message)
        self.status_code = status_code
        self.response = response


class AuthenticationError(VeriBitsError):
    """Authentication failed - invalid or expired API key"""
    pass


class RateLimitError(VeriBitsError):
    """Rate limit exceeded"""
    def __init__(self, message: str = "Rate limit exceeded", retry_after: int = None):
        super().__init__(message)
        self.retry_after = retry_after


class ValidationError(VeriBitsError):
    """Invalid input data"""
    pass
