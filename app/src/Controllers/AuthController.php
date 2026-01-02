<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Jwt;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\Database;
use VeriBits\Utils\Config;
use VeriBits\Utils\Logger;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Request;
use VeriBits\Services\EmailService;

class AuthController {
    public function register(): void {
        $clientIp = $this->getClientIp();

        if (!RateLimit::check("register:$clientIp", 5, 300)) {
            Response::error('Registration rate limit exceeded', 429);
            return;
        }

        // CRITICAL WORKAROUND: Read php://input directly
        $rawBody = @file_get_contents('php://input') ?: '';
        $rawBody = ltrim($rawBody, "\xEF\xBB\xBF");
        $rawBody = trim($rawBody);

        // FIX: Handle curl/shell escaping quirks
        $rawBody = preg_replace('/\\\\([^"\\/bfnrtu])/', '$1', $rawBody);

        $body = json_decode($rawBody, true) ?: [];

        if (empty($body)) {
            $body = Request::getJsonBody();
        }

        $validator = new Validator($body);

        $validator->required('email')->email('email')->string('email', 5, 320)
                  ->required('password')->string('password', 8, 128);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $email = $validator->sanitize('email');
        $password = $body['password'];

        try {
            if (Database::exists('users', ['email' => $email])) {
                Response::error('Email already registered', 409);
                return;
            }

            $passwordHash = Auth::hashPassword($password);
            $userId = Database::insert('users', [
                'email' => $email,
                'password_hash' => $passwordHash,
                'status' => 'active'
            ]);

            $apiKey = Auth::generateApiKey();
            Database::insert('api_keys', [
                'user_id' => $userId,
                'key' => $apiKey,
                'name' => 'Default API Key'
            ]);

            Database::insert('billing_accounts', [
                'user_id' => $userId,
                'plan' => 'free'
            ]);

            Database::insert('quotas', [
                'user_id' => $userId,
                'period' => 'monthly',
                'allowance' => 1000,
                'used' => 0
            ]);

            Logger::info('User registered successfully', [
                'user_id' => $userId,
                'email' => $email
            ]);

            // Send welcome email
            try {
                $emailService = new EmailService();
                $username = explode('@', $email)[0]; // Extract username from email
                $emailService->sendWelcomeEmail($email, $username);
                Logger::info('Welcome email sent', ['email' => $email]);
            } catch (\Exception $e) {
                Logger::warning('Welcome email failed', [
                    'email' => $email,
                    'error' => $e->getMessage()
                ]);
                // Don't fail registration if email fails
            }

            Response::success([
                'user_id' => $userId,
                'email' => $email,
                'api_key' => $apiKey
            ], 'Registration successful');

        } catch (\Exception $e) {
            Logger::error('User registration failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            Response::error('Registration failed', 500);
        }
    }

    public function login(): void {
        $clientIp = $this->getClientIp();

        if (!RateLimit::check("login:$clientIp", 10, 300)) {
            Response::error('Login rate limit exceeded', 429);
            return;
        }

        // CRITICAL WORKAROUND: Read php://input directly BEFORE Request helper
        // For some reason Request::getJsonBody() returns empty for THIS endpoint only
        // This direct read works reliably in production
        $rawBody = @file_get_contents('php://input') ?: '';
        error_log("DEBUG [AuthController::login]: Direct php://input length = " . strlen($rawBody));
        error_log("DEBUG [AuthController::login]: Direct php://input = " . substr($rawBody, 0, 200));

        // Check global cache
        $globalBody = $GLOBALS['__RAW_POST_BODY__'] ?? '';
        error_log("DEBUG [AuthController::login]: Global cache length = " . strlen($globalBody));
        error_log("DEBUG [AuthController::login]: Global cache = " . substr($globalBody, 0, 200));

        $rawBody = ltrim($rawBody, "\xEF\xBB\xBF"); // Strip BOM
        $rawBody = trim($rawBody);

        // FIX: Handle curl/shell escaping quirks like \! which creates invalid JSON
        // Replace invalid escape sequences before JSON decode
        $rawBody = preg_replace('/\\\\([^"\\/bfnrtu])/', '$1', $rawBody);

        $body = json_decode($rawBody, true);

        if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
            error_log("DEBUG [AuthController::login]: JSON DECODE FAILED!");
            error_log("DEBUG [AuthController::login]: JSON error: " . json_last_error_msg());
            error_log("DEBUG [AuthController::login]: JSON error code: " . json_last_error());
            error_log("DEBUG [AuthController::login]: Raw body hex: " . bin2hex($rawBody));
            $body = [];
        } else if (!is_array($body)) {
            $body = [];
        }

        error_log("DEBUG [AuthController::login]: Decoded body = " . json_encode($body));

        // Fallback to Request helper if direct read fails
        if (empty($body)) {
            error_log("DEBUG [AuthController::login]: Body empty, trying Request helper");
            $body = Request::getJsonBody();
            error_log("DEBUG [AuthController::login]: Request helper returned = " . json_encode($body));
        }

        // Also try using the global cache
        if (empty($body) && !empty($globalBody)) {
            error_log("DEBUG [AuthController::login]: Trying global cache");
            $body = json_decode(ltrim(trim($globalBody), "\xEF\xBB\xBF"), true) ?: [];
            error_log("DEBUG [AuthController::login]: Global cache decoded = " . json_encode($body));
        }

        $validator = new Validator($body);

        $validator->required('email')->email('email')
                  ->required('password')->string('password');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $email = $validator->sanitize('email');
        $password = $body['password'];

        error_log("DEBUG [AuthController::login]: About to query database for email = " . $email);

        try {
            $user = Database::fetch(
                "SELECT id, email, password_hash, status FROM users WHERE email = :email",
                ['email' => $email]
            );

            error_log("DEBUG [AuthController::login]: Database query completed");
            error_log("DEBUG [AuthController::login]: User found = " . (!empty($user) ? 'YES' : 'NO'));
            error_log("DEBUG [AuthController::login]: Has password_hash = " . (isset($user['password_hash']) && !empty($user['password_hash']) ? 'YES' : 'NO'));
            error_log("DEBUG [AuthController::login]: password_hash_from_db = " . ($user['password_hash'] ?? 'NULL'));
            error_log("DEBUG [AuthController::login]: password_hash_hex = " . bin2hex($user['password_hash'] ?? ''));

            // DEBUG: Log user lookup result
            Logger::info('User lookup result', [
                'email' => $email,
                'found' => !empty($user),
                'user_id' => $user['id'] ?? 'null',
                'has_password_hash' => isset($user['password_hash']) && !empty($user['password_hash']),
                'password_hash_length' => isset($user['password_hash']) ? strlen($user['password_hash']) : 0,
                'password_hash_preview' => isset($user['password_hash']) ? substr($user['password_hash'], 0, 20) . '...' : 'NULL',
                'status' => $user['status'] ?? 'null'
            ]);

            if (!$user || !Auth::verifyPassword($password, $user['password_hash'])) {
                Logger::security('Failed login attempt', [
                    'email' => $email,
                    'ip' => $clientIp
                ]);
                Response::error('Invalid credentials', 401);
                return;
            }

            if ($user['status'] !== 'active') {
                Response::error('Account disabled', 403);
                return;
            }

            $token = Jwt::sign([
                'sub' => $user['id'],
                'email' => $user['email'],
                'scopes' => ['verify:*', 'profile:read'],
                'exp' => time() + 3600
            ], Config::getRequired('JWT_SECRET'));

            Logger::info('User logged in successfully', [
                'user_id' => $user['id'],
                'email' => $email
            ]);

            Response::success([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email']
                ]
            ], 'Login successful');

        } catch (\Exception $e) {
            Logger::error('Login failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            Response::error('Login failed', 500);
        }
    }

    public function logout(): void {
        $claims = Auth::requireBearer();

        Auth::logout($claims);

        Logger::info('User logged out', [
            'user_id' => $claims['sub'] ?? 'unknown'
        ]);

        Response::success([], 'Logged out successfully');
    }

    public function token(): void {
        $clientIp = $this->getClientIp();

        if (!RateLimit::check("token:$clientIp", 20, 300)) {
            Response::error('Token request rate limit exceeded', 429);
            return;
        }

        $body = Request::getJsonBody();
        $validator = new Validator($body);

        $validator->required('user')->string('user', 1, 100);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $user = $validator->sanitize('user');

        try {
            $token = Jwt::sign([
                'sub' => $user,
                'scopes' => ['verify:*'],
                'exp' => time() + 3600
            ], Config::getRequired('JWT_SECRET'));

            $apiKey = Auth::generateApiKey();

            Logger::info('Demo token created', [
                'user' => $user,
                'ip' => $clientIp
            ]);

            Response::success([
                'api_key' => $apiKey,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => 3600,
                'note' => 'Demo token - register for full features'
            ]);

        } catch (\Exception $e) {
            Logger::error('Token creation failed', [
                'user' => $user,
                'error' => $e->getMessage()
            ]);
            Response::error('Token creation failed', 500);
        }
    }

    public function refresh(): void {
        $claims = Auth::requireBearer();

        try {
            $newToken = Jwt::sign([
                'sub' => $claims['sub'],
                'email' => $claims['email'] ?? null,
                'scopes' => $claims['scopes'] ?? ['verify:*'],
                'exp' => time() + 3600
            ], Config::getRequired('JWT_SECRET'));

            Auth::logout($claims);

            Response::success([
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => 3600
            ], 'Token refreshed');

        } catch (\Exception $e) {
            Logger::error('Token refresh failed', [
                'user_id' => $claims['sub'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            Response::error('Token refresh failed', 500);
        }
    }

    public function profile(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $user = Database::fetch(
                "SELECT id, email, created_at, status FROM users WHERE id = :id",
                ['id' => $userId]
            );

            if (!$user) {
                Response::error('User not found', 404);
                return;
            }

            $quotas = Database::fetchAll(
                "SELECT period, allowance, used FROM quotas WHERE user_id = :user_id",
                ['user_id' => $userId]
            );

            $apiKeys = Database::fetchAll(
                "SELECT id, name, created_at, revoked FROM api_keys WHERE user_id = :user_id",
                ['user_id' => $userId]
            );

            Response::success([
                'user' => $user,
                'quotas' => $quotas,
                'api_keys' => $apiKeys
            ]);

        } catch (\Exception $e) {
            Logger::error('Profile fetch failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to fetch profile', 500);
        }
    }

    private function getClientIp(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
