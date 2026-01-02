<?php
/**
 * Central Auth Controller
 *
 * Handles OAuth2/OIDC authentication with After Dark Systems Central Auth
 * (login.afterdarksys.com)
 */
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use VeriBits\Utils\Jwt;
use VeriBits\Utils\OIDCClient;

class CentralAuthController {

    private OIDCClient $oidc;

    public function __construct() {
        $this->oidc = new OIDCClient();
    }

    /**
     * Check if Central auth is available
     * GET /api/v1/auth/central/status
     */
    public function status(): void {
        Response::success([
            'central_auth_enabled' => $this->oidc->isConfigured(),
            'issuer' => 'https://login.afterdarksys.com',
            'provider' => 'After Dark Systems'
        ]);
    }

    /**
     * Initiate Central auth flow - redirect to login.afterdarksys.com
     * GET /api/v1/auth/central/login
     */
    public function login(): void {
        if (!$this->oidc->isConfigured()) {
            Response::error('Central authentication not configured', 503);
            return;
        }

        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        try {
            $authUrl = $this->oidc->getAuthorizationUrl();

            Logger::info('Central auth initiated', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // Redirect to Central auth
            header('Location: ' . $authUrl);
            exit;

        } catch (\Exception $e) {
            Logger::error('Central auth initiation failed', ['error' => $e->getMessage()]);
            Response::error('Failed to initiate authentication', 500);
        }
    }

    /**
     * Handle OAuth callback from Central auth
     * GET /api/v1/auth/central/callback
     */
    public function callback(): void {
        if (!$this->oidc->isConfigured()) {
            Response::error('Central authentication not configured', 503);
            return;
        }

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $code = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;
        $error = $_GET['error'] ?? null;

        // Handle error from IdP
        if ($error) {
            $errorDesc = $_GET['error_description'] ?? 'Unknown error';
            Logger::error('Central auth error from IdP', [
                'error' => $error,
                'description' => $errorDesc
            ]);
            // Redirect to login page with error
            header('Location: /login.php?error=' . urlencode($errorDesc));
            exit;
        }

        // Verify state to prevent CSRF
        if (!$state || !$this->oidc->verifyState($state)) {
            Logger::security('Central auth state verification failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            header('Location: /login.php?error=' . urlencode('Security verification failed'));
            exit;
        }

        if (!$code) {
            Logger::error('Central auth callback missing code');
            header('Location: /login.php?error=' . urlencode('Authorization code missing'));
            exit;
        }

        try {
            // Exchange code for tokens
            $tokens = $this->oidc->exchangeCode($code);

            $accessToken = $tokens['access_token'] ?? null;
            $idToken = $tokens['id_token'] ?? null;
            $refreshToken = $tokens['refresh_token'] ?? null;

            if (!$accessToken || !$idToken) {
                throw new \RuntimeException('Missing tokens in response');
            }

            // Verify ID token
            $idTokenClaims = $this->oidc->verifyIdToken($idToken);

            // Get user info
            $userInfo = $this->oidc->getUserInfo($accessToken);

            $email = $userInfo['email'] ?? $idTokenClaims['email'] ?? null;
            $name = $userInfo['name'] ?? $idTokenClaims['name'] ?? null;
            $centralUserId = $userInfo['sub'] ?? $idTokenClaims['sub'] ?? null;

            if (!$email || !$centralUserId) {
                throw new \RuntimeException('Missing user information');
            }

            // Find or create local user
            $user = $this->findOrCreateUser($email, $name, $centralUserId);

            // Generate local JWT
            $localToken = Jwt::sign([
                'sub' => $user['id'],
                'email' => $user['email'],
                'central_sub' => $centralUserId,
                'scopes' => ['verify:*', 'profile:read'],
                'exp' => time() + 3600
            ], Config::getRequired('JWT_SECRET'));

            // Store tokens in session
            $_SESSION['oidc_access_token'] = $accessToken;
            $_SESSION['oidc_refresh_token'] = $refreshToken;
            $_SESSION['oidc_id_token'] = $idToken;
            $_SESSION['user_id'] = $user['id'];

            Logger::info('Central auth successful', [
                'user_id' => $user['id'],
                'email' => $email,
                'central_sub' => $centralUserId
            ]);

            // Redirect to dashboard with token
            // The frontend will store the token in localStorage
            header('Location: /dashboard.php?token=' . urlencode($localToken) . '&auth=central');
            exit;

        } catch (\Exception $e) {
            Logger::error('Central auth callback failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->oidc->clearSession();
            header('Location: /login.php?error=' . urlencode('Authentication failed: ' . $e->getMessage()));
            exit;
        }
    }

    /**
     * Get current user info from Central auth
     * GET /api/v1/auth/central/userinfo
     */
    public function userinfo(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $accessToken = $_SESSION['oidc_access_token'] ?? null;

        if (!$accessToken) {
            Response::error('Not authenticated with Central auth', 401);
            return;
        }

        try {
            $userInfo = $this->oidc->getUserInfo($accessToken);
            Response::success([
                'user' => $userInfo,
                'provider' => 'After Dark Systems Central'
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get Central user info', ['error' => $e->getMessage()]);
            Response::error('Failed to get user info', 500);
        }
    }

    /**
     * Logout from Central auth
     * POST /api/v1/auth/central/logout
     */
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->oidc->clearSession();
        session_destroy();

        Logger::info('Central auth logout', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        Response::success(['message' => 'Logged out from Central auth']);
    }

    /**
     * Link existing VeriBits account to Central auth
     * POST /api/v1/auth/central/link
     */
    public function link(): void {
        // Require existing VeriBits authentication
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.*)/i', $hdr, $m)) {
            Response::error('Authentication required', 401);
            return;
        }

        $token = trim($m[1]);
        $payload = Jwt::verify($token, Config::getRequired('JWT_SECRET'));

        if (!$payload) {
            Response::error('Invalid token', 401);
            return;
        }

        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            Response::error('Invalid user', 401);
            return;
        }

        // Get Central auth ID from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $accessToken = $_SESSION['oidc_access_token'] ?? null;
        if (!$accessToken) {
            Response::error('Central auth session required - login with Central first', 400);
            return;
        }

        try {
            $userInfo = $this->oidc->getUserInfo($accessToken);
            $centralUserId = $userInfo['sub'] ?? null;

            if (!$centralUserId) {
                throw new \RuntimeException('Failed to get Central user ID');
            }

            // Update local user with Central auth link
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("
                UPDATE users
                SET central_auth_id = :central_id, updated_at = NOW()
                WHERE id = :user_id
            ");
            $stmt->execute([
                'central_id' => $centralUserId,
                'user_id' => $userId
            ]);

            Logger::info('Account linked to Central auth', [
                'user_id' => $userId,
                'central_id' => $centralUserId
            ]);

            Response::success([
                'linked' => true,
                'central_id' => $centralUserId,
                'email' => $userInfo['email'] ?? null
            ], 'Account linked successfully');

        } catch (\Exception $e) {
            Logger::error('Failed to link account', ['error' => $e->getMessage()]);
            Response::error('Failed to link account: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Find existing user or create new one from Central auth
     */
    private function findOrCreateUser(string $email, ?string $name, string $centralUserId): array {
        $pdo = Database::getConnection();

        // First, check if user exists by Central auth ID
        $stmt = $pdo->prepare("SELECT * FROM users WHERE central_auth_id = :central_id LIMIT 1");
        $stmt->execute(['central_id' => $centralUserId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user) {
            return $user;
        }

        // Check if user exists by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user) {
            // Link existing user to Central auth
            $stmt = $pdo->prepare("
                UPDATE users
                SET central_auth_id = :central_id, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['central_id' => $centralUserId, 'id' => $user['id']]);
            $user['central_auth_id'] = $centralUserId;

            Logger::info('Existing user linked to Central auth', [
                'user_id' => $user['id'],
                'email' => $email
            ]);

            return $user;
        }

        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, status, central_auth_id, created_at, updated_at)
            VALUES (:email, :password_hash, 'active', :central_id, NOW(), NOW())
            RETURNING id, email, status, central_auth_id, created_at
        ");
        // Generate random password hash for Central-only users (they won't use it)
        $randomHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID);
        $stmt->execute([
            'email' => $email,
            'password_hash' => $randomHash,
            'central_id' => $centralUserId
        ]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Create default API key
        $apiKey = 'vb_' . bin2hex(random_bytes(24));
        Database::insert('api_keys', [
            'user_id' => $user['id'],
            'key' => $apiKey,
            'name' => 'Default API Key'
        ]);

        // Create billing account
        Database::insert('billing_accounts', [
            'user_id' => $user['id'],
            'plan' => 'free'
        ]);

        // Create quota
        Database::insert('quotas', [
            'user_id' => $user['id'],
            'period' => 'monthly',
            'allowance' => 1000,
            'used' => 0
        ]);

        Logger::info('New user created from Central auth', [
            'user_id' => $user['id'],
            'email' => $email,
            'central_id' => $centralUserId
        ]);

        return $user;
    }
}
