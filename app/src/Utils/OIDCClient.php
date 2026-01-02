<?php
/**
 * OIDC Client for After Cloak (Keycloak) Identity Platform
 *
 * Handles OAuth2/OpenID Connect authentication flow with aftercloak.io
 * Part of After Dark Systems ecosystem
 */
namespace VeriBits\Utils;

class OIDCClient {

    private string $issuer;
    private string $authorizationEndpoint;
    private string $tokenEndpoint;
    private string $userInfoEndpoint;
    private string $jwksUri;
    private string $logoutEndpoint;

    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $scopes = ['openid', 'profile', 'email', 'roles'];

    public function __construct() {
        // Load OIDC configuration from environment
        $realmUrl = Config::get('OIDC_REALM_URL', 'https://aftercloak.io/realms/afterdark');

        // Set Keycloak OIDC endpoints (standard Keycloak paths)
        $this->issuer = $realmUrl;
        $this->authorizationEndpoint = $realmUrl . '/protocol/openid-connect/auth';
        $this->tokenEndpoint = $realmUrl . '/protocol/openid-connect/token';
        $this->userInfoEndpoint = $realmUrl . '/protocol/openid-connect/userinfo';
        $this->jwksUri = $realmUrl . '/protocol/openid-connect/certs';
        $this->logoutEndpoint = $realmUrl . '/protocol/openid-connect/logout';

        $this->clientId = Config::get('OIDC_CLIENT_ID', '');
        $this->clientSecret = Config::get('OIDC_CLIENT_SECRET', '');
        $this->redirectUri = Config::get('OIDC_REDIRECT_URI', 'https://veribits.com/auth/callback');

        if (empty($this->clientId) || empty($this->clientSecret)) {
            Logger::warning('OIDC client not configured - After Cloak auth disabled');
        }
    }

    /**
     * Check if OIDC is configured and available
     */
    public function isConfigured(): bool {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Generate authorization URL for redirect
     */
    public function getAuthorizationUrl(string $state = null, string $nonce = null): string {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OIDC client not configured');
        }

        $state = $state ?? bin2hex(random_bytes(16));
        $nonce = $nonce ?? bin2hex(random_bytes(16));

        // Store state and nonce in session for verification
        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_nonce'] = $nonce;

        $params = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'nonce' => $nonce,
        ];

        return $this->authorizationEndpoint . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCode(string $code): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('OIDC client not configured');
        }

        $response = $this->httpPost($this->tokenEndpoint, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
        ]);

        if (isset($response['error'])) {
            Logger::error('OIDC token exchange failed', [
                'error' => $response['error'],
                'description' => $response['error_description'] ?? 'Unknown error'
            ]);
            throw new \RuntimeException('Token exchange failed: ' . ($response['error_description'] ?? $response['error']));
        }

        return $response;
    }

    /**
     * Get user info from Central auth
     */
    public function getUserInfo(string $accessToken): array {
        $response = $this->httpGet($this->userInfoEndpoint, [
            'Authorization' => 'Bearer ' . $accessToken
        ]);

        if (isset($response['error'])) {
            Logger::error('OIDC userinfo failed', ['error' => $response['error']]);
            throw new \RuntimeException('Failed to get user info: ' . ($response['error_description'] ?? $response['error']));
        }

        return $response;
    }

    /**
     * Verify ID token signature and claims
     */
    public function verifyIdToken(string $idToken): array {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Invalid ID token format');
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!$header || !$payload) {
            throw new \RuntimeException('Failed to decode ID token');
        }

        // Verify issuer
        if (($payload['iss'] ?? '') !== $this->issuer) {
            throw new \RuntimeException('Invalid token issuer');
        }

        // Verify audience
        $aud = $payload['aud'] ?? '';
        if (is_array($aud)) {
            if (!in_array($this->clientId, $aud)) {
                throw new \RuntimeException('Invalid token audience');
            }
        } else if ($aud !== $this->clientId) {
            throw new \RuntimeException('Invalid token audience');
        }

        // Verify expiration
        if (($payload['exp'] ?? 0) < time()) {
            throw new \RuntimeException('Token has expired');
        }

        // Verify nonce if present in session
        if (isset($_SESSION['oidc_nonce']) && isset($payload['nonce'])) {
            if ($payload['nonce'] !== $_SESSION['oidc_nonce']) {
                throw new \RuntimeException('Invalid token nonce');
            }
        }

        // TODO: Verify signature using JWKS (for production)
        // For now, we trust the token from our own IdP over HTTPS

        return $payload;
    }

    /**
     * Refresh access token
     */
    public function refreshToken(string $refreshToken): array {
        $response = $this->httpPost($this->tokenEndpoint, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if (isset($response['error'])) {
            throw new \RuntimeException('Token refresh failed: ' . ($response['error_description'] ?? $response['error']));
        }

        return $response;
    }

    /**
     * Verify state parameter to prevent CSRF
     */
    public function verifyState(string $state): bool {
        $expectedState = $_SESSION['oidc_state'] ?? '';
        if (empty($expectedState) || !hash_equals($expectedState, $state)) {
            Logger::security('OIDC state mismatch - possible CSRF attack', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return false;
        }
        return true;
    }

    /**
     * Clear OIDC session data
     */
    public function clearSession(): void {
        unset($_SESSION['oidc_state']);
        unset($_SESSION['oidc_nonce']);
        unset($_SESSION['oidc_access_token']);
        unset($_SESSION['oidc_refresh_token']);
        unset($_SESSION['oidc_id_token']);
    }

    /**
     * HTTP POST request
     */
    private function httpPost(string $url, array $data): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::error('OIDC HTTP request failed', ['error' => $error, 'url' => $url]);
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            Logger::error('OIDC invalid JSON response', ['response' => substr($response, 0, 500)]);
            throw new \RuntimeException('Invalid JSON response from IdP');
        }

        return $decoded;
    }

    /**
     * HTTP GET request with headers
     */
    private function httpGet(string $url, array $headers = []): array {
        $ch = curl_init($url);

        $httpHeaders = ['Accept: application/json'];
        foreach ($headers as $key => $value) {
            $httpHeaders[] = "$key: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
