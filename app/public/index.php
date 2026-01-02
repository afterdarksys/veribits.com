<?php
// © After Dark Systems
declare(strict_types=1);
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// CRITICAL: Cache php://input IMMEDIATELY before ANY other code
// php://input can only be read ONCE per request, and Apache mod_rewrite can consume it
// We MUST read it at the very start of the script to guarantee we capture POST data
if (!isset($GLOBALS['__RAW_POST_BODY__'])) {
    $GLOBALS['__RAW_POST_BODY__'] = @file_get_contents('php://input') ?: '';

    // EXTREME DEBUG: Log what we captured
    error_log("DEBUG [index.php start]: POST body captured");
    error_log("DEBUG [index.php start]: Content-Type = " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    error_log("DEBUG [index.php start]: Content-Length = " . ($_SERVER['CONTENT_LENGTH'] ?? 'not set'));
    error_log("DEBUG [index.php start]: Body length = " . strlen($GLOBALS['__RAW_POST_BODY__']));
    error_log("DEBUG [index.php start]: Body preview = " . substr($GLOBALS['__RAW_POST_BODY__'], 0, 200));
    error_log("DEBUG [index.php start]: Request URI = " . ($_SERVER['REQUEST_URI'] ?? 'not set'));
    error_log("DEBUG [index.php start]: Request Method = " . ($_SERVER['REQUEST_METHOD'] ?? 'not set'));
}

// Autoload all classes
spl_autoload_register(function ($class) {
    $prefix = 'VeriBits\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use VeriBits\Utils\Response;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use VeriBits\Controllers\HealthController;
use VeriBits\Controllers\VerifyController;
use VeriBits\Controllers\BadgeController;
use VeriBits\Controllers\AuthController;
use VeriBits\Controllers\WebhookController;
use VeriBits\Controllers\BillingController;
use VeriBits\Controllers\MalwareScanController;
use VeriBits\Controllers\ArchiveInspectionController;
use VeriBits\Controllers\DNSCheckController;
use VeriBits\Controllers\SSLCheckController;
use VeriBits\Controllers\IDVerificationController;
use VeriBits\Controllers\FileMagicController;
use VeriBits\Controllers\FileSignatureController;
use VeriBits\Controllers\AnonymousLimitsController;
use VeriBits\Controllers\CryptoValidationController;
use VeriBits\Controllers\SSLGeneratorController;
use VeriBits\Controllers\SSLChainResolverController;
use VeriBits\Controllers\JWTController;
use VeriBits\Controllers\DeveloperToolsController;
use VeriBits\Controllers\CodeSigningController;
use VeriBits\Controllers\ApiKeyController;
use VeriBits\Controllers\VerificationsController;
use VeriBits\Controllers\NetworkToolsController;
use VeriBits\Controllers\AdminController;
use VeriBits\Controllers\SteganographyController;
use VeriBits\Controllers\BGPController;
use VeriBits\Controllers\ToolSearchController;
use VeriBits\Controllers\CloudStorageController;
use VeriBits\Controllers\HaveIBeenPwnedController;
use VeriBits\Controllers\EmailVerificationController;
use VeriBits\Controllers\SecurityHeadersController;
use VeriBits\Controllers\AuditLogController;
use VeriBits\Controllers\KeystoreController;
use VeriBits\Controllers\IAMPolicyController;
use VeriBits\Controllers\SecretsController;
use VeriBits\Controllers\DatabaseConnectionController;
use VeriBits\Controllers\PcapAnalyzerController;
use VeriBits\Controllers\FirewallController;
use VeriBits\Controllers\DnsConverterController;
use VeriBits\Controllers\SystemScansController;
use VeriBits\Controllers\PasswordRecoveryController;
use VeriBits\Controllers\HashLookupController;
use VeriBits\Controllers\DiskForensicsController;
use VeriBits\Controllers\OsqueryController;
use VeriBits\Controllers\NetcatController;
use VeriBits\Controllers\ProSubscriptionController;
use VeriBits\Controllers\OAuth2Controller;
use VeriBits\Controllers\WebhooksController;
use VeriBits\Controllers\MalwareDetonationController;
use VeriBits\Controllers\EmailController;

// Initialize configuration
Config::load();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([]);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Serve homepage for root path
if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/home.php';
    exit;
}

// Only handle API requests - let Apache serve static files
if (strpos($uri, '/api/') !== 0) {
    http_response_code(404);
    exit;
}

try {
    // Health check (no auth required)
    if ($uri === '/api/v1/health' && $method === 'GET') {
        (new HealthController())->status();
        exit;
    }

    // Debug endpoint
    if ($uri === '/api/v1/debug/request' && $method === 'POST') {
        $phpInput = @file_get_contents('php://input');
        Response::json([
            'php_input' => $phpInput,
            'php_input_length' => strlen($phpInput),
            '_POST' => $_POST,
            '_SERVER_keys' => array_keys($_SERVER),
            'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
            'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? null,
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
            'HTTP_CONTENT_TYPE' => $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
        ]);
        exit;
    }

    // Anonymous limits info (no auth required)
    if ($uri === '/api/v1/limits/anonymous' && $method === 'GET') {
        (new AnonymousLimitsController())->getLimits();
        exit;
    }

    // Test Request helper in index.php context
    if ($uri === '/api/v1/test/request-helper' && $method === 'POST') {
        $raw = \VeriBits\Utils\Request::getBody();
        $body = \VeriBits\Utils\Request::getJsonBody();

        // Detailed JSON decode testing
        $decoded = json_decode($raw, true);
        $jsonError = json_last_error();
        $jsonErrorMsg = json_last_error_msg();

        // Check for hidden characters
        $hexDump = bin2hex(substr($raw, 0, 100));

        Response::json([
            'test' => 'index.php context',
            'raw_body' => $raw,
            'raw_body_length' => strlen($raw),
            'raw_body_type' => gettype($raw),
            'raw_body_hex' => $hexDump,
            'json_body' => $body,
            'manual_decode' => $decoded,
            'json_error_code' => $jsonError,
            'json_error_msg' => $jsonErrorMsg,
            'has_email' => isset($body['email']),
            'has_password' => isset($body['password']),
        ]);
        exit;
    }

    // Test exact login logic inline
    if ($uri === '/api/v1/test/login-inline' && $method === 'POST') {
        $raw = \VeriBits\Utils\Request::getBody();
        $body = \VeriBits\Utils\Request::getJsonBody();

        // Manual decode for comparison
        $manualDecode = json_decode($raw, true);
        $jsonError = json_last_error();
        $jsonErrorMsg = json_last_error_msg();

        // Test WITHOUT validator first
        if (empty($body['email']) || empty($body['password'])) {
            Response::json([
                'success' => false,
                'message' => 'Manual check failed',
                'body' => $body,
                'raw_body' => $raw,
                'raw_body_length' => strlen($raw),
                'manual_decode' => $manualDecode,
                'json_error' => $jsonErrorMsg,
                'json_error_code' => $jsonError,
                'email_isset' => isset($body['email']),
                'password_isset' => isset($body['password']),
                'email_empty' => empty($body['email']),
                'password_empty' => empty($body['password']),
            ]);
            exit;
        }

        Response::json(['success' => true, 'message' => 'Manual check passed!', 'body' => $body]);
        exit;
    }

    // Authentication endpoints
    if ($uri === '/api/v1/auth/register' && $method === 'POST') {
        (new AuthController())->register();
        exit;
    }
    if ($uri === '/api/v1/auth/login' && $method === 'POST') {
        (new AuthController())->login();
        exit;
    }
    if ($uri === '/api/v1/auth/logout' && $method === 'POST') {
        (new AuthController())->logout();
        exit;
    }
    if ($uri === '/api/v1/auth/token' && $method === 'POST') {
        (new AuthController())->token();
        exit;
    }
    if ($uri === '/api/v1/auth/refresh' && $method === 'POST') {
        (new AuthController())->refresh();
        exit;
    }

    // After Dark Systems Central Auth (OIDC) endpoints
    if ($uri === '/api/v1/auth/central/status' && $method === 'GET') {
        (new \VeriBits\Controllers\CentralAuthController())->status();
        exit;
    }
    if ($uri === '/api/v1/auth/central/login' && $method === 'GET') {
        (new \VeriBits\Controllers\CentralAuthController())->login();
        exit;
    }
    if ($uri === '/api/v1/auth/central/callback' && $method === 'GET') {
        (new \VeriBits\Controllers\CentralAuthController())->callback();
        exit;
    }
    if ($uri === '/api/v1/auth/central/userinfo' && $method === 'GET') {
        (new \VeriBits\Controllers\CentralAuthController())->userinfo();
        exit;
    }
    if ($uri === '/api/v1/auth/central/logout' && $method === 'POST') {
        (new \VeriBits\Controllers\CentralAuthController())->logout();
        exit;
    }
    if ($uri === '/api/v1/auth/central/link' && $method === 'POST') {
        (new \VeriBits\Controllers\CentralAuthController())->link();
        exit;
    }

    // Admin endpoints
    if ($uri === '/api/v1/admin/migrate' && $method === 'POST') {
        (new AdminController())->runMigrations();
        exit;
    }
    if ($uri === '/api/v1/admin/test-register' && $method === 'POST') {
        (new AdminController())->testRegister();
        exit;
    }
    if ($uri === '/api/v1/admin/reset-password' && $method === 'POST') {
        (new AdminController())->resetPassword();
        exit;
    }
    if ($uri === '/api/v1/auth/profile' && $method === 'GET') {
        (new AuthController())->profile();
        exit;
    }

    // Audit log endpoints (protected)
    if ($uri === '/api/v1/audit/logs' && $method === 'GET') {
        (new AuditLogController())->getLogs();
        exit;
    }
    if ($uri === '/api/v1/audit/stats' && $method === 'GET') {
        (new AuditLogController())->getStats();
        exit;
    }
    if ($uri === '/api/v1/audit/export' && $method === 'GET') {
        (new AuditLogController())->exportCsv();
        exit;
    }
    if ($uri === '/api/v1/audit/operation-types' && $method === 'GET') {
        (new AuditLogController())->getOperationTypes();
        exit;
    }

    // Verification endpoints (protected)
    if ($uri === '/api/v1/verify/file' && $method === 'POST') {
        (new VerifyController())->file();
        exit;
    }
    if ($uri === '/api/v1/verify/email' && $method === 'POST') {
        (new VerifyController())->email();
        exit;
    }
    if ($uri === '/api/v1/verify/tx' && $method === 'POST') {
        (new VerifyController())->transaction();
        exit;
    }

    // Malware scan endpoint (protected)
    if ($uri === '/api/v1/verify/malware' && $method === 'POST') {
        (new MalwareScanController())->scan();
        exit;
    }

    // Archive inspection endpoint (protected)
    if ($uri === '/api/v1/inspect/archive' && $method === 'POST') {
        (new ArchiveInspectionController())->inspect();
        exit;
    }

    // DNS check endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/dns/check' && $method === 'POST') {
        (new DNSCheckController())->check();
        exit;
    }
    if ($uri === '/api/v1/verify/dns' && $method === 'POST') {
        (new DNSCheckController())->check();
        exit;
    }

    // SSL check endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/ssl/validate' && $method === 'POST') {
        (new SSLCheckController())->validate();
        exit;
    }
    if ($uri === '/api/v1/verify/ssl/website' && $method === 'POST') {
        (new SSLCheckController())->checkWebsite();
        exit;
    }
    if ($uri === '/api/v1/verify/ssl/certificate' && $method === 'POST') {
        (new SSLCheckController())->checkCertificate();
        exit;
    }
    if ($uri === '/api/v1/verify/ssl/key-match' && $method === 'POST') {
        (new SSLCheckController())->verifyKeyMatch();
        exit;
    }

    // ID verification endpoint (protected)
    if ($uri === '/api/v1/verify/id' && $method === 'POST') {
        (new IDVerificationController())->verify();
        exit;
    }

    // File magic number analysis endpoint (protected)
    if ($uri === '/api/v1/file-magic' && $method === 'POST') {
        (new FileMagicController())->analyze();
        exit;
    }

    // File signature verification endpoint (protected)
    if ($uri === '/api/v1/verify/file-signature' && $method === 'POST') {
        (new FileSignatureController())->verify();
        exit;
    }

    // Badge endpoints
    if (preg_match('#^/api/v1/badge/(.+)$#', $uri, $m) && $method === 'GET') {
        (new BadgeController())->get($m[1]);
        exit;
    }
    if ($uri === '/api/v1/lookup' && $method === 'GET') {
        (new BadgeController())->lookup();
        exit;
    }

    // Webhook endpoints (protected)
    if ($uri === '/api/v1/webhooks' && $method === 'POST') {
        (new WebhookController())->register();
        exit;
    }
    if ($uri === '/api/v1/webhooks' && $method === 'GET') {
        (new WebhookController())->list();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(.+)$#', $uri, $m) && $method === 'PUT') {
        $_GET['id'] = $m[1];
        (new WebhookController())->update();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(.+)$#', $uri, $m) && $method === 'DELETE') {
        $_GET['id'] = $m[1];
        (new WebhookController())->delete();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(.+)/test$#', $uri, $m) && $method === 'POST') {
        $_GET['id'] = $m[1];
        (new WebhookController())->test();
        exit;
    }

    // Billing endpoints (protected)
    if ($uri === '/api/v1/billing/account' && $method === 'GET') {
        (new BillingController())->getAccount();
        exit;
    }
    if ($uri === '/api/v1/billing/plans' && $method === 'GET') {
        (new BillingController())->getPlans();
        exit;
    }
    if ($uri === '/api/v1/billing/upgrade' && $method === 'POST') {
        (new BillingController())->upgradePlan();
        exit;
    }
    if ($uri === '/api/v1/billing/cancel' && $method === 'POST') {
        (new BillingController())->cancelSubscription();
        exit;
    }
    if ($uri === '/api/v1/billing/usage' && $method === 'GET') {
        (new BillingController())->getUsage();
        exit;
    }
    if ($uri === '/api/v1/billing/invoices' && $method === 'GET') {
        (new BillingController())->getInvoices();
        exit;
    }
    if ($uri === '/api/v1/billing/payment' && $method === 'POST') {
        (new BillingController())->processPayment();
        exit;
    }
    if ($uri === '/api/v1/billing/recommendation' && $method === 'GET') {
        (new BillingController())->getPlanRecommendation();
        exit;
    }
    if ($uri === '/api/v1/billing/webhook/stripe' && $method === 'POST') {
        (new BillingController())->webhookStripe();
        exit;
    }

    // Stripe-specific endpoints
    if ($uri === '/api/v1/billing/stripe/publishable-key' && $method === 'GET') {
        (new BillingController())->getStripePublishableKey();
        exit;
    }
    if ($uri === '/api/v1/billing/stripe/create-checkout-session' && $method === 'POST') {
        (new BillingController())->createStripeCheckout();
        exit;
    }
    if ($uri === '/api/v1/billing/stripe/create-portal-session' && $method === 'POST') {
        (new BillingController())->createStripePortal();
        exit;
    }
    if ($uri === '/api/v1/billing/stripe/cancel-subscription' && $method === 'POST') {
        (new BillingController())->cancelStripeSubscription();
        exit;
    }

    // Cryptocurrency validation endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/crypto/validate' && $method === 'POST') {
        (new CryptoValidationController())->validate();
        exit;
    }
    if ($uri === '/api/v1/crypto/validate/bitcoin' && $method === 'POST') {
        (new CryptoValidationController())->validateBitcoin();
        exit;
    }
    if ($uri === '/api/v1/crypto/validate/ethereum' && $method === 'POST') {
        (new CryptoValidationController())->validateEthereum();
        exit;
    }

    // SSL/TLS tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/ssl/generate-csr' && $method === 'POST') {
        (new SSLGeneratorController())->generate();
        exit;
    }
    if ($uri === '/api/v1/ssl/validate-csr' && $method === 'POST') {
        (new SSLGeneratorController())->validateCSR();
        exit;
    }

    // SSL Chain Resolver (supports anonymous with rate limiting)
    if ($uri === '/api/v1/ssl/resolve-chain' && $method === 'POST') {
        (new SSLChainResolverController())->resolveChain();
        exit;
    }
    if ($uri === '/api/v1/ssl/fetch-missing' && $method === 'POST') {
        (new SSLChainResolverController())->fetchMissing();
        exit;
    }
    if ($uri === '/api/v1/ssl/build-bundle' && $method === 'POST') {
        (new SSLChainResolverController())->buildBundle();
        exit;
    }
    if ($uri === '/api/v1/ssl/verify-key-pair' && $method === 'POST') {
        (new SSLChainResolverController())->verifyKeyPair();
        exit;
    }

    // Email Verification Tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/email/check-disposable' && $method === 'POST') {
        (new EmailVerificationController())->checkDisposable();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-spf' && $method === 'POST') {
        (new EmailVerificationController())->analyzeSPF();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-dkim' && $method === 'POST') {
        (new EmailVerificationController())->analyzeDKIM();
        exit;
    }
    if ($uri === '/api/v1/email/verify-dkim-signature' && $method === 'POST') {
        (new EmailVerificationController())->verifyDKIMSignature();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-dmarc' && $method === 'POST') {
        (new EmailVerificationController())->analyzeDMARC();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-mx' && $method === 'POST') {
        (new EmailVerificationController())->analyzeMX();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-headers' && $method === 'POST') {
        (new EmailVerificationController())->analyzeHeaders();
        exit;
    }
    if ($uri === '/api/v1/email/check-blacklists' && $method === 'POST') {
        (new EmailVerificationController())->checkBlacklists();
        exit;
    }
    if ($uri === '/api/v1/email/deliverability-score' && $method === 'POST') {
        (new EmailVerificationController())->deliverabilityScore();
        exit;
    }

    // JWT tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/jwt/validate' && $method === 'POST') {
        (new JWTController())->validate();
        exit;
    }
    if ($uri === '/api/v1/jwt/decode' && $method === 'POST') {
        (new JWTController())->decode();
        exit;
    }
    if ($uri === '/api/v1/jwt/sign' && $method === 'POST') {
        (new JWTController())->sign();
        exit;
    }

    // Developer tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/regex-test' && $method === 'POST') {
        (new DeveloperToolsController())->regexTest();
        exit;
    }
    if ($uri === '/api/v1/tools/validate-data' && $method === 'POST') {
        (new DeveloperToolsController())->validateData();
        exit;
    }
    if ($uri === '/api/v1/tools/scan-secrets' && $method === 'POST') {
        (new DeveloperToolsController())->scanSecrets();
        exit;
    }
    if ($uri === '/api/v1/tools/generate-hash' && $method === 'POST') {
        (new DeveloperToolsController())->generateHash();
        exit;
    }
    if ($uri === '/api/v1/tools/url-encode' && $method === 'POST') {
        (new DeveloperToolsController())->urlEncode();
        exit;
    }
    if ($uri === '/api/v1/tools/url-encoder' && $method === 'POST') {
        (new DeveloperToolsController())->urlEncode();
        exit;
    }
    if ($uri === '/api/v1/tools/base64-encoder' && $method === 'POST') {
        (new DeveloperToolsController())->base64Encode();
        exit;
    }
    if ($uri === '/api/v1/tools/security-headers' && $method === 'POST') {
        (new SecurityHeadersController())->analyze();
        exit;
    }
    if ($uri === '/api/v1/tools/pgp-validate' && $method === 'POST') {
        (new DeveloperToolsController())->validatePGP();
        exit;
    }
    if ($uri === '/api/v1/tools/hash-validator' && $method === 'POST') {
        (new DeveloperToolsController())->validateHash();
        exit;
    }

    // Keystore conversion and extraction endpoints
    if ($uri === '/api/v1/tools/keystore/jks-to-pkcs12' && $method === 'POST') {
        (new KeystoreController())->jksToPkcs12();
        exit;
    }
    if ($uri === '/api/v1/tools/keystore/pkcs12-to-jks' && $method === 'POST') {
        (new KeystoreController())->pkcs12ToJks();
        exit;
    }
    if ($uri === '/api/v1/tools/keystore/extract' && $method === 'POST') {
        (new KeystoreController())->extractPkcs();
        exit;
    }

    // Code signing endpoints
    if ($uri === '/api/v1/code-signing/sign' && $method === 'POST') {
        (new CodeSigningController())->sign();
        exit;
    }
    if ($uri === '/api/v1/code-signing/quota' && $method === 'GET') {
        (new CodeSigningController())->getQuota();
        exit;
    }

    // API Keys management (protected)
    if ($uri === '/api/v1/api-keys' && $method === 'GET') {
        (new ApiKeyController())->list();
        exit;
    }
    if ($uri === '/api/v1/api-keys' && $method === 'POST') {
        (new ApiKeyController())->create();
        exit;
    }
    if (preg_match('#^/api/v1/api-keys/(.+)$#', $uri, $m) && $method === 'DELETE') {
        (new ApiKeyController())->revoke($m[1]);
        exit;
    }

    // User profile (protected)
    if ($uri === '/api/v1/user/profile' && $method === 'GET') {
        (new AuthController())->profile();
        exit;
    }

    // Verifications history (protected)
    if ($uri === '/api/v1/verifications' && $method === 'GET') {
        (new VerificationsController())->list();
        exit;
    }

    // Network tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/dns-validate' && $method === 'POST') {
        (new NetworkToolsController())->dnsValidate();
        exit;
    }
    if ($uri === '/api/v1/tools/ip-calculate' && $method === 'POST') {
        (new NetworkToolsController())->ipCalculate();
        exit;
    }
    if ($uri === '/api/v1/tools/rbl-check' && $method === 'POST') {
        (new NetworkToolsController())->rblCheck();
        exit;
    }
    if ($uri === '/api/v1/tools/smtp-relay-check' && $method === 'POST') {
        (new NetworkToolsController())->smtpRelayCheck();
        exit;
    }
    if ($uri === '/api/v1/tools/whois' && $method === 'POST') {
        (new NetworkToolsController())->whoisLookup();
        exit;
    }
    if ($uri === '/api/v1/tools/traceroute' && $method === 'POST') {
        (new NetworkToolsController())->traceroute();
        exit;
    }
    if ($uri === '/api/v1/tools/dnssec-validate' && $method === 'POST') {
        (new NetworkToolsController())->dnssecValidate();
        exit;
    }
    if ($uri === '/api/v1/tools/dns-propagation' && $method === 'POST') {
        (new NetworkToolsController())->dnsPropagation();
        exit;
    }
    if ($uri === '/api/v1/tools/reverse-dns' && $method === 'POST') {
        (new NetworkToolsController())->reverseDns();
        exit;
    }
    if ($uri === '/api/v1/zone-validate' && $method === 'POST') {
        (new NetworkToolsController())->zoneValidate();
        exit;
    }
    if ($uri === '/api/v1/tools/cert-convert' && $method === 'POST') {
        (new NetworkToolsController())->certConvert();
        exit;
    }

    // Batch Operations API (requires authentication)
    if ($uri === '/api/v1/batch' && $method === 'POST') {
        (new \VeriBits\Controllers\BatchController())->execute();
        exit;
    }

    // OpenAPI Documentation
    if ($uri === '/api/v1/openapi.json' && $method === 'GET') {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        readfile(__DIR__ . '/api/openapi.json');
        exit;
    }

    // DNS Converter endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/dns-converter/dnscache-to-unbound' && $method === 'POST') {
        (new DnsConverterController())->convertDnscache();
        exit;
    }
    if ($uri === '/api/v1/dns-converter/bind-to-nsd' && $method === 'POST') {
        (new DnsConverterController())->convertBind();
        exit;
    }

    // Steganography detection (supports anonymous with rate limiting)
    if ($uri === '/api/v1/steganography-detect' && $method === 'POST') {
        (new SteganographyController())->detect();
        exit;
    }

    // BGP Intelligence tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/bgp/prefix' && $method === 'POST') {
        (new BGPController())->prefixLookup();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn' && $method === 'POST') {
        (new BGPController())->asLookup();
        exit;
    }

    // Tool Search endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/search' && $method === 'GET') {
        (new ToolSearchController())->search();
        exit;
    }
    if ($uri === '/api/v1/tools/list' && $method === 'GET') {
        (new ToolSearchController())->list();
        exit;
    }

    // Cloud Storage Security Auditor endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/cloud-storage/search' && $method === 'POST') {
        (new CloudStorageController())->search();
        exit;
    }
    if ($uri === '/api/v1/tools/cloud-storage/list-buckets' && $method === 'POST') {
        (new CloudStorageController())->listBuckets();
        exit;
    }
    if ($uri === '/api/v1/tools/cloud-storage/analyze-security' && $method === 'POST') {
        (new CloudStorageController())->analyzeSecurityPosture();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/prefixes' && $method === 'POST') {
        (new BGPController())->asPrefixes();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/peers' && $method === 'POST') {
        (new BGPController())->asPeers();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/upstreams' && $method === 'POST') {
        (new BGPController())->asUpstreams();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/downstreams' && $method === 'POST') {
        (new BGPController())->asDownstreams();
        exit;
    }
    if ($uri === '/api/v1/bgp/search' && $method === 'POST') {
        (new BGPController())->searchAS();
        exit;
    }

    // Have I Been Pwned endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/hibp/check-email' && $method === 'POST') {
        (new HaveIBeenPwnedController())->checkEmail();
        exit;
    }
    if ($uri === '/api/v1/hibp/check-password' && $method === 'POST') {
        (new HaveIBeenPwnedController())->checkPassword();
        exit;
    }
    if ($uri === '/api/v1/hibp/stats' && $method === 'GET') {
        (new HaveIBeenPwnedController())->getStats();
        exit;
    }

    // Security Scanning Tools
    if ($uri === '/api/v1/security/iam-policy/analyze' && $method === 'POST') {
        (new \VeriBits\Controllers\IAMPolicyController())->analyze();
        exit;
    }
    if ($uri === '/api/v1/security/iam-policy/history' && $method === 'GET') {
        (new \VeriBits\Controllers\IAMPolicyController())->getHistory();
        exit;
    }
    if ($uri === '/api/v1/security/secrets/scan' && $method === 'POST') {
        (new \VeriBits\Controllers\SecretsController())->scan();
        exit;
    }
    if ($uri === '/api/v1/security/db-connection/audit' && $method === 'POST') {
        (new \VeriBits\Controllers\DatabaseConnectionController())->audit();
        exit;
    }

    // PCAP Analyzer endpoint (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/pcap-analyze' && $method === 'POST') {
        (new PcapAnalyzerController())->analyze();
        exit;
    }

    // Firewall Configuration Management endpoints (protected)
    if ($uri === '/api/v1/firewall/upload' && $method === 'POST') {
        (new FirewallController())->upload();
        exit;
    }
    if ($uri === '/api/v1/firewall/save' && $method === 'POST') {
        (new FirewallController())->save();
        exit;
    }
    if ($uri === '/api/v1/firewall/list' && $method === 'GET') {
        (new FirewallController())->list();
        exit;
    }
    if ($uri === '/api/v1/firewall/get' && $method === 'GET') {
        (new FirewallController())->get();
        exit;
    }
    if ($uri === '/api/v1/firewall/diff' && $method === 'GET') {
        (new FirewallController())->diff();
        exit;
    }
    if ($uri === '/api/v1/firewall/export' && $method === 'GET') {
        (new FirewallController())->export();
        exit;
    }

    // System Scans endpoints (protected - requires API key)
    if ($uri === '/api/v1/system-scans' && $method === 'POST') {
        (new SystemScansController())->create();
        exit;
    }
    if ($uri === '/api/v1/system-scans' && $method === 'GET') {
        (new SystemScansController())->list();
        exit;
    }
    if (preg_match('#^/api/v1/system-scans/(\d+)$#', $uri, $m) && $method === 'GET') {
        $_GET['id'] = $m[1];
        (new SystemScansController())->get();
        exit;
    }
    if (preg_match('#^/api/v1/system-scans/(\d+)$#', $uri, $m) && $method === 'DELETE') {
        $_GET['id'] = $m[1];
        (new SystemScansController())->delete();
        exit;
    }

    // Password Recovery endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/password-recovery/remove' && $method === 'POST') {
        (new PasswordRecoveryController())->removePassword();
        exit;
    }
    if ($uri === '/api/v1/tools/password-recovery/crack' && $method === 'POST') {
        (new PasswordRecoveryController())->crackPassword();
        exit;
    }
    if ($uri === '/api/v1/tools/password-recovery/analyze' && $method === 'POST') {
        (new PasswordRecoveryController())->analyzeFile();
        exit;
    }

    // Hash Lookup endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/hash-lookup' && $method === 'POST') {
        (new HashLookupController())->lookup();
        exit;
    }
    if ($uri === '/api/v1/tools/hash-lookup/batch' && $method === 'POST') {
        (new HashLookupController())->batchLookup();
        exit;
    }
    if ($uri === '/api/v1/tools/hash-lookup/identify' && $method === 'POST') {
        (new HashLookupController())->identifyHash();
        exit;
    }
    if ($uri === '/api/v1/tools/email-extractor' && $method === 'POST') {
        (new HashLookupController())->extractEmails();
        exit;
    }

    // Netcat endpoint
    if ($uri === '/api/v1/tools/netcat' && $method === 'POST') {
        (new NetcatController())->execute();
        exit;
    }

    // Pro Subscription endpoints
    if ($uri === '/api/v1/pro/validate' && $method === 'POST') {
        (new ProSubscriptionController())->validate();
        exit;
    }
    if ($uri === '/api/v1/pro/status' && $method === 'GET') {
        (new ProSubscriptionController())->status();
        exit;
    }
    if ($uri === '/api/v1/pro/generate' && $method === 'POST') {
        (new ProSubscriptionController())->generate();
        exit;
    }

    // OAuth2 endpoints
    if ($uri === '/api/v1/oauth/authorize' && $method === 'GET') {
        (new OAuth2Controller())->authorize();
        exit;
    }
    if ($uri === '/api/v1/oauth/token' && $method === 'POST') {
        (new OAuth2Controller())->token();
        exit;
    }
    if ($uri === '/api/v1/oauth/revoke' && $method === 'POST') {
        (new OAuth2Controller())->revoke();
        exit;
    }
    if ($uri === '/api/v1/oauth/register' && $method === 'POST') {
        (new OAuth2Controller())->register();
        exit;
    }

    // Webhooks endpoints
    if ($uri === '/api/v1/webhooks' && $method === 'POST') {
        (new WebhooksController())->create();
        exit;
    }
    if ($uri === '/api/v1/webhooks' && $method === 'GET') {
        (new WebhooksController())->list();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new WebhooksController())->get();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(\d+)$#', $uri, $matches) && $method === 'PUT') {
        (new WebhooksController())->update();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(\d+)$#', $uri, $matches) && $method === 'DELETE') {
        (new WebhooksController())->delete();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(\d+)/deliveries$#', $uri, $matches) && $method === 'GET') {
        (new WebhooksController())->deliveries();
        exit;
    }

    // Malware Detonation endpoints
    if ($uri === '/api/v1/malware/submit' && $method === 'POST') {
        (new MalwareDetonationController())->submit();
        exit;
    }
    if (preg_match('#^/api/v1/malware/status/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new MalwareDetonationController())->status();
        exit;
    }
    if (preg_match('#^/api/v1/malware/report/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new MalwareDetonationController())->report();
        exit;
    }
    if (preg_match('#^/api/v1/malware/screenshots/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new MalwareDetonationController())->screenshots();
        exit;
    }
    if (preg_match('#^/api/v1/malware/pcap/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new MalwareDetonationController())->pcap();
        exit;
    }
    if (preg_match('#^/api/v1/malware/iocs/(\d+)$#', $uri, $matches) && $method === 'GET') {
        (new MalwareDetonationController())->iocs();
        exit;
    }

    // Email endpoints
    if ($uri === '/api/v1/email/test' && $method === 'POST') {
        (new EmailController())->sendTest();
        exit;
    }
    if ($uri === '/api/v1/email/welcome' && $method === 'POST') {
        (new EmailController())->sendWelcome();
        exit;
    }
    if ($uri === '/api/v1/email/stats' && $method === 'GET') {
        (new EmailController())->getStats();
        exit;
    }
    if ($uri === '/api/v1/email/broadcast' && $method === 'POST') {
        (new EmailController())->sendBroadcast();
        exit;
    }

    // Disk Forensics endpoints (requires authentication)
    if ($uri === '/api/v1/forensics/disk/upload' && $method === 'POST') {
        (new DiskForensicsController())->upload();
        exit;
    }
    if ($uri === '/api/v1/forensics/disk/analyze' && $method === 'POST') {
        (new DiskForensicsController())->analyze();
        exit;
    }
    if ($uri === '/api/v1/forensics/disk/extract' && $method === 'POST') {
        (new DiskForensicsController())->extractFile();
        exit;
    }
    if ($uri === '/api/v1/forensics/disk/cleanup' && $method === 'POST') {
        (new DiskForensicsController())->cleanup();
        exit;
    }

    // osquery endpoints (requires authentication)
    if ($uri === '/api/v1/osquery/execute' && $method === 'POST') {
        (new OsqueryController())->execute();
        exit;
    }
    if ($uri === '/api/v1/osquery/tables' && $method === 'GET') {
        (new OsqueryController())->tables();
        exit;
    }
    if ($uri === '/api/v1/osquery/schema' && $method === 'GET') {
        (new OsqueryController())->schema();
        exit;
    }
    if ($uri === '/api/v1/osquery/templates' && $method === 'GET') {
        (new OsqueryController())->templates();
        exit;
    }
    if ($uri === '/api/v1/osquery/pack' && $method === 'POST') {
        (new OsqueryController())->runPack();
        exit;
    }

    // API documentation
    if ($uri === '/api/v1/docs' && $method === 'GET') {
        header('Content-Type: text/html');
        include __DIR__ . '/../../docs/api-docs.html';
        exit;
    }

    // 404 for unknown routes
    Response::error('Endpoint not found', 404, ['path' => $uri, 'method' => $method]);

} catch (\Throwable $e) {
    Logger::critical('Unhandled exception in API', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'uri' => $uri,
        'method' => $method
    ]);

    if (Config::isDevelopment()) {
        Response::error('Internal server error: ' . $e->getMessage(), 500, [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    } else {
        Response::error('Internal server error', 500);
    }
}
