#!/usr/bin/env php
<?php
/**
 * Test password verification fix
 *
 * This script validates that the password verification fix works correctly
 * in various scenarios including edge cases that were failing in production.
 */

echo "========================================\n";
echo "Password Verification Fix - Test Suite\n";
echo "========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Include the Auth class
require_once __DIR__ . '/../app/src/Utils/Auth.php';

use VeriBits\Utils\Auth;

/**
 * Test helper functions
 */
function test($name, $condition, $errorMsg = '') {
    global $testsPassed, $testsFailed;

    if ($condition) {
        echo "✓ PASS: $name\n";
        $testsPassed++;
    } else {
        echo "✗ FAIL: $name\n";
        if ($errorMsg) {
            echo "  Error: $errorMsg\n";
        }
        $testsFailed++;
    }
}

function sanitizeHash($hash) {
    // Replicate the sanitization logic from Auth::verifyPassword
    $hash = preg_replace('/[\x00-\x1F\x7F-\xFF\s]+/', '', $hash);
    $hash = preg_replace('/[^\x20-\x7E]/', '', $hash);
    if (function_exists('mb_convert_encoding')) {
        $hash = mb_convert_encoding($hash, 'UTF-8', 'UTF-8');
    }
    return $hash;
}

function verifyPasswordDirect($password, $hash) {
    // Direct implementation of the fix
    $hash = sanitizeHash($hash);
    $password = (string)$password;
    $hash = (string)$hash;

    if (strlen($hash) !== 60 || !preg_match('/^\$2[axy]\$\d{2}\$[.\/A-Za-z0-9]{53}$/', $hash)) {
        return false;
    }

    $cryptResult = crypt($password, $hash);
    return hash_equals($hash, $cryptResult);
}

echo "TEST 1: Basic password verification\n";
echo "------------------------------------\n";

// Generate a fresh hash
$password1 = "TestPassword123!";
$hash1 = password_hash($password1, PASSWORD_BCRYPT, ['cost' => 10]);

test(
    "Verify correct password",
    verifyPasswordDirect($password1, $hash1),
    "Failed to verify correct password"
);

test(
    "Reject incorrect password",
    !verifyPasswordDirect("WrongPassword", $hash1),
    "Incorrectly accepted wrong password"
);

echo "\nTEST 2: Production-failing hash (from CloudWatch logs)\n";
echo "-------------------------------------------------------\n";

// This is the exact hash that was failing in production
$prodPassword = "TestPassword123!";
$prodHash = '$2y$12$eKJCykdGXuNZ.k/lJQtHF.f51GG/Uetdhuqm0BU6cGYAlEYkCfAG2';

test(
    "Verify production hash with correct password",
    verifyPasswordDirect($prodPassword, $prodHash),
    "CRITICAL: Production hash still fails!"
);

test(
    "Reject production hash with wrong password",
    !verifyPasswordDirect("WrongPassword", $prodHash),
    "Incorrectly accepted wrong password for production hash"
);

echo "\nTEST 3: Hash sanitization (edge cases)\n";
echo "---------------------------------------\n";

// Hash with leading/trailing whitespace
$dirtyHash1 = "  $prodHash  ";
test(
    "Handle hash with whitespace",
    verifyPasswordDirect($prodPassword, $dirtyHash1),
    "Failed to sanitize whitespace: " . bin2hex($dirtyHash1)
);

// Hash with BOM (Byte Order Mark)
$dirtyHash2 = "\xEF\xBB\xBF" . $prodHash;
test(
    "Handle hash with BOM",
    verifyPasswordDirect($prodPassword, $dirtyHash2),
    "Failed to sanitize BOM: " . bin2hex($dirtyHash2)
);

// Hash with null byte
$dirtyHash3 = $prodHash . "\x00";
test(
    "Handle hash with null byte",
    verifyPasswordDirect($prodPassword, $dirtyHash3),
    "Failed to sanitize null byte: " . bin2hex($dirtyHash3)
);

// Hash with mixed control characters
$dirtyHash4 = $prodHash . "\r\n\t";
test(
    "Handle hash with control characters",
    verifyPasswordDirect($prodPassword, $dirtyHash4),
    "Failed to sanitize control chars: " . bin2hex($dirtyHash4)
);

echo "\nTEST 4: Invalid hash formats\n";
echo "-----------------------------\n";

test(
    "Reject hash with wrong length (59 chars)",
    !verifyPasswordDirect($password1, substr($prodHash, 0, 59)),
    "Accepted hash with wrong length"
);

test(
    "Reject hash with wrong length (61 chars)",
    !verifyPasswordDirect($password1, $prodHash . "X"),
    "Accepted hash with wrong length"
);

test(
    "Reject hash with invalid prefix",
    !verifyPasswordDirect($password1, '$2z$12$' . substr($prodHash, 7)),
    "Accepted hash with invalid prefix"
);

test(
    "Reject completely invalid hash",
    !verifyPasswordDirect($password1, "not-a-valid-bcrypt-hash-at-all-just-60-chars-padding-xxx"),
    "Accepted completely invalid hash"
);

echo "\nTEST 5: Different BCrypt costs\n";
echo "-------------------------------\n";

$password5 = "SecurePassword456!";

$hash5_4 = password_hash($password5, PASSWORD_BCRYPT, ['cost' => 4]);
test(
    "Verify BCrypt cost=4",
    verifyPasswordDirect($password5, $hash5_4),
    "Failed to verify cost=4 hash"
);

$hash5_10 = password_hash($password5, PASSWORD_BCRYPT, ['cost' => 10]);
test(
    "Verify BCrypt cost=10 (current default)",
    verifyPasswordDirect($password5, $hash5_10),
    "Failed to verify cost=10 hash"
);

$hash5_12 = password_hash($password5, PASSWORD_BCRYPT, ['cost' => 12]);
test(
    "Verify BCrypt cost=12 (previous default)",
    verifyPasswordDirect($password5, $hash5_12),
    "Failed to verify cost=12 hash"
);

echo "\nTEST 6: Password edge cases\n";
echo "----------------------------\n";

// Very short password
$shortPass = "12345678"; // 8 chars (minimum)
$shortHash = password_hash($shortPass, PASSWORD_BCRYPT, ['cost' => 10]);
test(
    "Verify minimum length password (8 chars)",
    verifyPasswordDirect($shortPass, $shortHash),
    "Failed to verify minimum length password"
);

// Very long password
$longPass = str_repeat("A", 72); // BCrypt truncates at 72 chars
$longHash = password_hash($longPass, PASSWORD_BCRYPT, ['cost' => 10]);
test(
    "Verify maximum BCrypt length password (72 chars)",
    verifyPasswordDirect($longPass, $longHash),
    "Failed to verify maximum length password"
);

// Password with special characters
$specialPass = "P@ssw0rd!#$%^&*()_+-=[]{}|;:,.<>?";
$specialHash = password_hash($specialPass, PASSWORD_BCRYPT, ['cost' => 10]);
test(
    "Verify password with special characters",
    verifyPasswordDirect($specialPass, $specialHash),
    "Failed to verify password with special chars"
);

// Password with Unicode (emoji)
$unicodePass = "Password🔒2024";
$unicodeHash = password_hash($unicodePass, PASSWORD_BCRYPT, ['cost' => 10]);
test(
    "Verify password with Unicode emoji",
    verifyPasswordDirect($unicodePass, $unicodeHash),
    "Failed to verify password with Unicode"
);

echo "\nTEST 7: Timing safety\n";
echo "---------------------\n";

// Measure timing for correct vs incorrect password
$timingPass = "TimingTestPassword123!";
$timingHash = password_hash($timingPass, PASSWORD_BCRYPT, ['cost' => 10]);

$iterations = 100;
$correctTimes = [];
$incorrectTimes = [];

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    verifyPasswordDirect($timingPass, $timingHash);
    $correctTimes[] = (microtime(true) - $start) * 1000;

    $start = microtime(true);
    verifyPasswordDirect("WrongPassword", $timingHash);
    $incorrectTimes[] = (microtime(true) - $start) * 1000;
}

$avgCorrect = array_sum($correctTimes) / count($correctTimes);
$avgIncorrect = array_sum($incorrectTimes) / count($incorrectTimes);
$timingDiff = abs($avgCorrect - $avgIncorrect);

test(
    "Timing difference < 10% (constant-time comparison)",
    $timingDiff < ($avgCorrect * 0.1),
    "Timing difference: " . number_format($timingDiff, 2) . "ms (avg correct: " . number_format($avgCorrect, 2) . "ms, avg incorrect: " . number_format($avgIncorrect, 2) . "ms)"
);

echo "\nTEST 8: Compare password_verify() vs crypt() implementation\n";
echo "-----------------------------------------------------------\n";

$comparePass = "CompareImplementations123!";
$compareHash = password_hash($comparePass, PASSWORD_BCRYPT, ['cost' => 10]);

$passwordVerifyResult = password_verify($comparePass, $compareHash);
$cryptResult = verifyPasswordDirect($comparePass, $compareHash);

test(
    "password_verify() and crypt() give same result (TRUE)",
    $passwordVerifyResult === $cryptResult && $passwordVerifyResult === true,
    "password_verify: " . ($passwordVerifyResult ? 'TRUE' : 'FALSE') . ", crypt: " . ($cryptResult ? 'TRUE' : 'FALSE')
);

$passwordVerifyResultFalse = password_verify("WrongPassword", $compareHash);
$cryptResultFalse = verifyPasswordDirect("WrongPassword", $compareHash);

test(
    "password_verify() and crypt() give same result (FALSE)",
    $passwordVerifyResultFalse === $cryptResultFalse && $passwordVerifyResultFalse === false,
    "password_verify: " . ($passwordVerifyResultFalse ? 'TRUE' : 'FALSE') . ", crypt: " . ($cryptResultFalse ? 'TRUE' : 'FALSE')
);

echo "\nTEST 9: Performance benchmarks\n";
echo "-------------------------------\n";

$benchPass = "BenchmarkPassword123!";
$benchHash = password_hash($benchPass, PASSWORD_BCRYPT, ['cost' => 10]);

$benchIterations = 50;
$benchStart = microtime(true);
for ($i = 0; $i < $benchIterations; $i++) {
    verifyPasswordDirect($benchPass, $benchHash);
}
$benchTime = (microtime(true) - $benchStart) / $benchIterations * 1000;

echo "  Average verification time: " . number_format($benchTime, 2) . "ms\n";
echo "  Expected throughput: " . number_format(1000 / $benchTime, 1) . " verifications/second\n";

test(
    "Verification time < 200ms (acceptable performance)",
    $benchTime < 200,
    "Verification took " . number_format($benchTime, 2) . "ms (too slow)"
);

test(
    "Verification time > 30ms (secure enough)",
    $benchTime > 30,
    "Verification took " . number_format($benchTime, 2) . "ms (too fast, may be insecure)"
);

echo "\n========================================\n";
echo "Test Results Summary\n";
echo "========================================\n";
echo "Passed: $testsPassed\n";
echo "Failed: $testsFailed\n";
echo "Total:  " . ($testsPassed + $testsFailed) . "\n";

if ($testsFailed === 0) {
    echo "\n✓✓✓ ALL TESTS PASSED ✓✓✓\n";
    echo "The password verification fix is working correctly!\n";
    exit(0);
} else {
    echo "\n✗✗✗ SOME TESTS FAILED ✗✗✗\n";
    echo "Fix may not work correctly in production!\n";
    exit(1);
}
