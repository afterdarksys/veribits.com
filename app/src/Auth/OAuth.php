<?php

namespace VeriBits\Auth;

/**
 * Authentik OAuth Integration for PHP
 *
 * Handles OAuth 2.0 authentication flow with Authentik SSO provider
 */
class OAuth
{
    private string $issuer;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $scopes;

    public function __construct()
    {
        $this->issuer = getenv('AUTHENTIK_ISSUER') ?: '';
        $this->clientId = getenv('AUTHENTIK_CLIENT_ID') ?: '';
        $this->clientSecret = getenv('AUTHENTIK_CLIENT_SECRET') ?: '';
        $this->redirectUri = getenv('AUTHENTIK_REDIRECT_URI') ?: '';
        $this->scopes = explode(',', getenv('AUTHENTIK_SCOPES') ?: 'openid,profile,email');
    }

    /**
     * Generate random state for CSRF protection
     */
    public function generateState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Build authorization URL
     */
    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
        ]);

        return $this->issuer . 'authorize?' . $params;
    }

    /**
     * Exchange authorization code for tokens
     */
    public function exchangeCodeForTokens(string $code): array
    {
        $ch = curl_init($this->issuer . 'token');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirectUri,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Token exchange failed: " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Get user info from access token
     */
    public function getUserInfo(string $accessToken): array
    {
        $ch = curl_init($this->issuer . 'userinfo');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $accessToken",
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Failed to get user info: " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $ch = curl_init($this->issuer . 'token');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("Token refresh failed: " . $response);
        }

        return json_decode($response, true);
    }

    /**
     * Get logout URL
     */
    public function getLogoutUrl(?string $idToken = null): string
    {
        $params = [];

        if ($idToken) {
            $params['id_token_hint'] = $idToken;
        }

        $params['post_logout_redirect_uri'] = rtrim($this->redirectUri, '/callback');

        return $this->issuer . 'end-session?' . http_build_query($params);
    }
}
