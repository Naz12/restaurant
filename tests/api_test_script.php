<?php

/**
 * Mobile API Test Script
 * 
 * This script tests all mobile API endpoints
 * Run with: php tests/api_test_script.php
 * 
 * Make sure to:
 * 1. Set up a test database
 * 2. Seed test data (restaurant, branch, user, menu items, tables, etc.)
 * 3. Update BASE_URL if needed
 * 4. Update test credentials
 */

define('BASE_URL', 'http://localhost:8000/api/mobile');
define('TEST_EMAIL', 'test@example.com');
define('TEST_PASSWORD', 'password');

$token = null;
$testResults = [];

function makeRequest($method, $endpoint, $data = null, $token = null) {
    $url = BASE_URL . $endpoint;
    
    $ch = curl_init($url);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response
    ];
}

function test($name, $method, $endpoint, $data = null, $expectedCode = 200, $token = null) {
    global $testResults;
    
    echo "Testing: $name... ";
    
    $result = makeRequest($method, $endpoint, $data, $token);
    
    $passed = $result['code'] == $expectedCode;
    
    if ($passed) {
        echo "✓ PASSED\n";
    } else {
        echo "✗ FAILED (Expected: $expectedCode, Got: {$result['code']})\n";
        if (isset($result['body']['message'])) {
            echo "  Error: {$result['body']['message']}\n";
        }
    }
    
    $testResults[] = [
        'name' => $name,
        'passed' => $passed,
        'code' => $result['code'],
        'expected' => $expectedCode
    ];
    
    return $result;
}

function getToken() {
    global $token;
    
    if ($token) {
        return $token;
    }
    
    echo "\n=== Authentication Tests ===\n";
    
    $result = test(
        'Login',
        'POST',
        '/auth/login',
        [
            'email' => TEST_EMAIL,
            'password' => TEST_PASSWORD
        ],
        200
    );
    
    if ($result['code'] == 200 && isset($result['body']['data']['token'])) {
        $token = $result['body']['data']['token'];
        echo "Token obtained: " . substr($token, 0, 20) . "...\n\n";
        return $token;
    }
    
    echo "ERROR: Could not obtain token. Please check credentials.\n";
    exit(1);
}

// Run tests
echo "========================================\n";
echo "Mobile API Test Suite\n";
echo "========================================\n\n";

// Get authentication token
$token = getToken();

echo "=== Menu Tests ===\n";
test('Get Menu Items', 'GET', '/menu/items', null, 200, $token);
test('Get Menu Categories', 'GET', '/menu/categories', null, 200, $token);
test('Get Modifier Groups', 'GET', '/menu/modifier-groups', null, 200, $token);

echo "\n=== Table Tests ===\n";
test('Get Tables', 'GET', '/tables', null, 200, $token);
test('Get Areas', 'GET', '/tables/areas', null, 200, $token);

echo "\n=== Order Tests ===\n";
test('Get Orders', 'GET', '/orders', null, 200, $token);

// Note: Create order test requires valid data - adjust based on your test database
// test('Create Order', 'POST', '/orders', [...], 201, $token);

echo "\n=== KOT Tests ===\n";
test('Get KOTs', 'GET', '/kots', null, 200, $token);
test('Get KOT Places', 'GET', '/kots/places', null, 200, $token);
test('Get Cancel Reasons', 'GET', '/kots/cancel-reasons', null, 200, $token);

echo "\n=== Payment Tests ===\n";
test('Get Payments', 'GET', '/payments', null, 200, $token);

echo "\n=== Sync Tests ===\n";
test('Sync Status', 'GET', '/sync/status', null, 200, $token);
test('Sync Pull', 'POST', '/sync/pull', ['sync_types' => ['menu', 'tables']], 200, $token);

echo "\n=== Auth Tests (with token) ===\n";
test('Get Authenticated User', 'GET', '/auth/user', null, 200, $token);
test('Refresh Token', 'POST', '/auth/refresh-token', null, 200, $token);

// Update token if refresh was successful
$refreshResult = makeRequest('POST', '/auth/refresh-token', null, $token);
if ($refreshResult['code'] == 200 && isset($refreshResult['body']['data']['token'])) {
    $token = $refreshResult['body']['data']['token'];
    echo "Token refreshed successfully\n";
}

test('Logout', 'POST', '/auth/logout', null, 200, $token);

// Test that token is invalid after logout
echo "\n=== Post-Logout Tests ===\n";
test('Get User (should fail after logout)', 'GET', '/auth/user', null, 401, $token);

// Summary
echo "\n========================================\n";
echo "Test Summary\n";
echo "========================================\n";

$passed = 0;
$failed = 0;

foreach ($testResults as $result) {
    if ($result['passed']) {
        $passed++;
    } else {
        $failed++;
        echo "FAILED: {$result['name']} (Expected: {$result['expected']}, Got: {$result['code']})\n";
    }
}

echo "\nTotal Tests: " . count($testResults) . "\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed == 0) {
    echo "\n✓ All tests passed!\n";
    exit(0);
} else {
    echo "\n✗ Some tests failed. Please review the errors above.\n";
    exit(1);
}

