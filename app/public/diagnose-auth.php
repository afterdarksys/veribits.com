<?php
/**
 * DIAGNOSTIC SCRIPT: Identify authentication failure root cause
 *
 * This script creates a test user, retrieves the hash from database,
 * and tests verification to identify where the breakdown occurs.
 *
 * SECURITY: This file should be DELETED after diagnosis!
 */

require_once __DIR__ . '/../src/Utils/Database.php';
require_once __DIR__ . '/../src/Utils/Config.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Utils/Logger.php';

use VeriBits\Utils\Database;
use VeriBits\Utils\Auth;

header('Content-Type: text/plain');

echo "========================================\n";
echo "Authentication Diagnosis Script\n";
echo "========================================\n\n";

// Test password
$testPassword = "DiagnosticTest123!";
$testEmail = "diagnostic-" . time() . "@example.com";

try {
    echo "STEP 1: Generate password hash\n";
    echo "-------------------------------\n";

    $hash = Auth::hashPassword($testPassword);
    echo "Password: $testPassword\n";
    echo "Hash generated: $hash\n";
    echo "Hash length: " . strlen($hash) . "\n";
    echo "Hash hex: " . bin2hex($hash) . "\n\n";

    // Verify immediately (in-memory test)
    $inMemoryVerify = password_verify($testPassword, $hash);
    echo "In-memory password_verify(): " . ($inMemoryVerify ? 'PASS' : 'FAIL') . "\n";

    $inMemoryCrypt = crypt($testPassword, $hash);
    echo "In-memory crypt() match: " . (hash_equals($hash, $inMemoryCrypt) ? 'PASS' : 'FAIL') . "\n\n";

    echo "STEP 2: Store hash in database\n";
    echo "-------------------------------\n";

    // Create test user
    $userId = Database::insert('users', [
        'email' => $testEmail,
        'password_hash' => $hash,
        'status' => 'active'
    ]);

    echo "Test user created: ID=$userId, Email=$testEmail\n\n";

    echo "STEP 3: Retrieve hash from database\n";
    echo "------------------------------------\n";

    $user = Database::fetch(
        "SELECT id, email, password_hash FROM users WHERE id = :id",
        ['id' => $userId]
    );

    $retrievedHash = $user['password_hash'];
    echo "Retrieved hash: $retrievedHash\n";
    echo "Retrieved hash length: " . strlen($retrievedHash) . "\n";
    echo "Retrieved hash hex: " . bin2hex($retrievedHash) . "\n\n";

    // Compare original vs retrieved
    echo "STEP 4: Compare hashes\n";
    echo "----------------------\n";

    $hashesMatch = ($hash === $retrievedHash);
    echo "Original hash == Retrieved hash: " . ($hashesMatch ? 'YES' : 'NO') . "\n";

    if (!$hashesMatch) {
        echo "\nCRITICAL: Hash corruption detected!\n";
        echo "Original length: " . strlen($hash) . "\n";
        echo "Retrieved length: " . strlen($retrievedHash) . "\n";

        // Character-by-character comparison
        $minLen = min(strlen($hash), strlen($retrievedHash));
        for ($i = 0; $i < $minLen; $i++) {
            if ($hash[$i] !== $retrievedHash[$i]) {
                echo "First difference at position $i:\n";
                echo "  Original: " . ord($hash[$i]) . " ('" . $hash[$i] . "')\n";
                echo "  Retrieved: " . ord($retrievedHash[$i]) . " ('" . $retrievedHash[$i] . "')\n";
                break;
            }
        }
    }

    echo "\nSTEP 5: Test verification with retrieved hash\n";
    echo "----------------------------------------------\n";

    $verifyRetrieved = password_verify($testPassword, $retrievedHash);
    echo "password_verify() with retrieved hash: " . ($verifyRetrieved ? 'PASS' : 'FAIL') . "\n";

    $cryptRetrieved = crypt($testPassword, $retrievedHash);
    echo "crypt() with retrieved hash: " . (hash_equals($retrievedHash, $cryptRetrieved) ? 'PASS' : 'FAIL') . "\n\n";

    // Test with wrong password
    $wrongVerify = password_verify("WrongPassword", $retrievedHash);
    echo "password_verify() with wrong password: " . ($wrongVerify ? 'FAIL (security issue!)' : 'PASS (correctly rejected)') . "\n\n";

    echo "STEP 6: Test Auth::verifyPassword() method\n";
    echo "-------------------------------------------\n";

    $authVerify = Auth::verifyPassword($testPassword, $retrievedHash);
    echo "Auth::verifyPassword() result: " . ($authVerify ? 'PASS' : 'FAIL') . "\n\n";

    echo "STEP 7: Check database encoding\n";
    echo "--------------------------------\n";

    $encodingCheck = Database::fetch(
        "SELECT
            pg_encoding_to_char(encoding) as db_encoding,
            datcollate as collation,
            datctype as ctype
        FROM pg_database
        WHERE datname = current_database()"
    );

    echo "Database encoding: " . $encodingCheck['db_encoding'] . "\n";
    echo "Collation: " . $encodingCheck['collation'] . "\n";
    echo "Ctype: " . $encodingCheck['ctype'] . "\n\n";

    // Check column type
    $columnCheck = Database::fetch(
        "SELECT data_type, character_maximum_length, character_octet_length
        FROM information_schema.columns
        WHERE table_name = 'users' AND column_name = 'password_hash'"
    );

    echo "password_hash column type: " . $columnCheck['data_type'] . "\n";
    if ($columnCheck['character_maximum_length']) {
        echo "Max length: " . $columnCheck['character_maximum_length'] . "\n";
    }
    echo "\n";

    echo "STEP 8: Cleanup\n";
    echo "---------------\n";

    // Delete test user
    Database::query("DELETE FROM users WHERE id = :id", ['id' => $userId]);
    echo "Test user deleted\n\n";

    echo "========================================\n";
    echo "DIAGNOSIS COMPLETE\n";
    echo "========================================\n\n";

    if (!$hashesMatch) {
        echo "RESULT: DATABASE CORRUPTION DETECTED\n";
        echo "The password hash is being corrupted when stored/retrieved from PostgreSQL.\n";
        echo "This is the root cause of authentication failures.\n\n";
        echo "RECOMMENDED FIX:\n";
        echo "1. Run database migration 011 to clean existing hashes\n";
        echo "2. Ensure database encoding is UTF-8\n";
        echo "3. Consider using BYTEA column type instead of TEXT\n";
    } elseif (!$verifyRetrieved) {
        echo "RESULT: VERIFICATION FUNCTION FAILURE\n";
        echo "Hashes are stored correctly, but password_verify() is failing.\n";
        echo "This indicates a PHP environment issue (OPcache corruption, libcrypt problem).\n\n";
        echo "RECOMMENDED FIX:\n";
        echo "1. Clear OPcache and restart container\n";
        echo "2. Use crypt() fallback in Auth::verifyPassword()\n";
        echo "3. Check PHP extensions: php -m | grep crypt\n";
    } elseif (!$authVerify) {
        echo "RESULT: Auth::verifyPassword() LOGIC ERROR\n";
        echo "password_verify() works, but Auth::verifyPassword() fails.\n";
        echo "This indicates a bug in the Auth class implementation.\n\n";
        echo "RECOMMENDED FIX:\n";
        echo "1. Review Auth::verifyPassword() sanitization logic\n";
        echo "2. Check error logs for specific failure reasons\n";
    } else {
        echo "RESULT: AUTHENTICATION IS WORKING!\n";
        echo "All tests passed. The authentication system is functioning correctly.\n";
        echo "If users are experiencing login failures, the issue is likely:\n";
        echo "- Wrong password being used\n";
        echo "- Account status != 'active'\n";
        echo "- Rate limiting\n";
    }

} catch (\Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
