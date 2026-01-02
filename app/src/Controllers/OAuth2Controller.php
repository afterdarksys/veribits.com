<?php

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;

class OAuth2Controller extends BaseController
{
    /**
     * OAuth2 Authorization endpoint
     * GET /api/v1/oauth/authorize
     */
    public function authorize(): void
    {
        $clientId = $_GET['client_id'] ?? null;
        $redirectUri = $_GET['redirect_uri'] ?? null;
        $state = $_GET['state'] ?? null;
        $scope = $_GET['scope'] ?? 'read write';

        if (!$clientId || !$redirectUri) {
            Response::error('Missing required parameters', 400);
            return;
        }

        // Verify client
        $client = $this->getClient($clientId);
        if (!$client) {
            Response::error('Invalid client_id', 401);
            return;
        }

        // Verify redirect URI
        if (!$this->isValidRedirectUri($redirectUri, $client['redirect_uris'])) {
            Response::error('Invalid redirect_uri', 400);
            return;
        }

        // Check if user is logged in
        $user = Auth::getUser();
        if (!$user) {
            // Redirect to login
            header('Location: /login.php?return=' . urlencode($_SERVER['REQUEST_URI']));
            exit;
        }

        // Generate authorization code
        $authCode = bin2hex(random_bytes(32));

        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO oauth_authorization_codes
            (code, client_id, user_id, redirect_uri, scope, expires_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
        ');
        $stmt->execute([$authCode, $clientId, $user['id'], $redirectUri, $scope]);

        // Redirect back with code
        $separator = strpos($redirectUri, '?') === false ? '?' : '&';
        $redirectUrl = $redirectUri . $separator . http_build_query([
            'code' => $authCode,
            'state' => $state
        ]);

        header('Location: ' . $redirectUrl);
        exit;
    }

    /**
     * OAuth2 Token endpoint
     * POST /api/v1/oauth/token
     */
    public function token(): void
    {
        $grantType = $_POST['grant_type'] ?? null;
        $clientId = $_POST['client_id'] ?? null;
        $clientSecret = $_POST['client_secret'] ?? null;

        if (!$grantType || !$clientId || !$clientSecret) {
            Response::error('Missing required parameters', 400);
            return;
        }

        // Verify client credentials
        $client = $this->getClient($clientId);
        if (!$client || !password_verify($clientSecret, $client['client_secret'])) {
            Response::error('Invalid client credentials', 401);
            return;
        }

        if ($grantType === 'authorization_code') {
            $this->handleAuthorizationCodeGrant($client);
        } elseif ($grantType === 'refresh_token') {
            $this->handleRefreshTokenGrant($client);
        } else {
            Response::error('Unsupported grant_type', 400);
        }
    }

    /**
     * Handle authorization code grant
     */
    private function handleAuthorizationCodeGrant(array $client): void
    {
        $code = $_POST['code'] ?? null;
        $redirectUri = $_POST['redirect_uri'] ?? null;

        if (!$code || !$redirectUri) {
            Response::error('Missing code or redirect_uri', 400);
            return;
        }

        $db = Database::getInstance();

        // Verify and consume authorization code
        $stmt = $db->prepare('
            SELECT user_id, scope, expires_at
            FROM oauth_authorization_codes
            WHERE code = ? AND client_id = ? AND redirect_uri = ? AND used = 0
        ');
        $stmt->execute([$code, $client['client_id'], $redirectUri]);
        $authCode = $stmt->fetch();

        if (!$authCode) {
            Response::error('Invalid authorization code', 400);
            return;
        }

        // Check expiration
        if (strtotime($authCode['expires_at']) < time()) {
            Response::error('Authorization code expired', 400);
            return;
        }

        // Mark code as used
        $stmt = $db->prepare('UPDATE oauth_authorization_codes SET used = 1 WHERE code = ?');
        $stmt->execute([$code]);

        // Generate tokens
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));

        // Store access token
        $stmt = $db->prepare('
            INSERT INTO oauth_access_tokens
            (token, client_id, user_id, scope, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ');
        $stmt->execute([$accessToken, $client['client_id'], $authCode['user_id'], $authCode['scope']]);

        // Store refresh token
        $stmt = $db->prepare('
            INSERT INTO oauth_refresh_tokens
            (token, client_id, user_id, scope, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))
        ');
        $stmt->execute([$refreshToken, $client['client_id'], $authCode['user_id'], $authCode['scope']]);

        Response::success([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refreshToken,
            'scope' => $authCode['scope']
        ]);
    }

    /**
     * Handle refresh token grant
     */
    private function handleRefreshTokenGrant(array $client): void
    {
        $refreshToken = $_POST['refresh_token'] ?? null;

        if (!$refreshToken) {
            Response::error('Missing refresh_token', 400);
            return;
        }

        $db = Database::getInstance();

        // Verify refresh token
        $stmt = $db->prepare('
            SELECT user_id, scope
            FROM oauth_refresh_tokens
            WHERE token = ? AND client_id = ? AND expires_at > NOW() AND revoked = 0
        ');
        $stmt->execute([$refreshToken, $client['client_id']]);
        $refresh = $stmt->fetch();

        if (!$refresh) {
            Response::error('Invalid refresh token', 400);
            return;
        }

        // Generate new access token
        $accessToken = bin2hex(random_bytes(32));

        $stmt = $db->prepare('
            INSERT INTO oauth_access_tokens
            (token, client_id, user_id, scope, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
        ');
        $stmt->execute([$accessToken, $client['client_id'], $refresh['user_id'], $refresh['scope']]);

        Response::success([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => $refresh['scope']
        ]);
    }

    /**
     * Revoke token
     * POST /api/v1/oauth/revoke
     */
    public function revoke(): void
    {
        $token = $_POST['token'] ?? null;
        $tokenTypeHint = $_POST['token_type_hint'] ?? 'access_token';

        if (!$token) {
            Response::error('Missing token', 400);
            return;
        }

        $db = Database::getInstance();

        if ($tokenTypeHint === 'access_token') {
            $stmt = $db->prepare('DELETE FROM oauth_access_tokens WHERE token = ?');
            $stmt->execute([$token]);
        } elseif ($tokenTypeHint === 'refresh_token') {
            $stmt = $db->prepare('UPDATE oauth_refresh_tokens SET revoked = 1 WHERE token = ?');
            $stmt->execute([$token]);
        }

        Response::success(['revoked' => true]);
    }

    /**
     * Register OAuth client (for Zapier/n8n/etc)
     * POST /api/v1/oauth/register
     */
    public function register(): void
    {
        $user = Auth::requireAuth();

        $input = $this->getJsonInput();
        $name = $input['name'] ?? null;
        $redirectUris = $input['redirect_uris'] ?? [];

        if (!$name || empty($redirectUris)) {
            Response::error('Name and redirect_uris required', 400);
            return;
        }

        // Generate client credentials
        $clientId = 'vb_' . bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));
        $clientSecretHash = password_hash($clientSecret, PASSWORD_BCRYPT);

        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO oauth_clients
            (client_id, client_secret, user_id, name, redirect_uris, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $clientId,
            $clientSecretHash,
            $user['id'],
            $name,
            json_encode($redirectUris)
        ]);

        Response::success([
            'client_id' => $clientId,
            'client_secret' => $clientSecret, // Only shown once!
            'name' => $name,
            'redirect_uris' => $redirectUris
        ]);
    }

    /**
     * Get client by ID
     */
    private function getClient(string $clientId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT client_id, client_secret, name, redirect_uris
            FROM oauth_clients
            WHERE client_id = ?
        ');
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        if ($client) {
            $client['redirect_uris'] = json_decode($client['redirect_uris'], true);
        }

        return $client ?: null;
    }

    /**
     * Validate redirect URI
     */
    private function isValidRedirectUri(string $uri, array $allowedUris): bool
    {
        return in_array($uri, $allowedUris);
    }
}
