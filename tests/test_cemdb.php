<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * CEMDB Integration Unit Tests
 * 
 * Basic unit tests for CEMDB functionality
 */

// Include CEMDB functions
require_once(__DIR__ . '/../thold_cemdb.php');

/**
 * Test CEMDB error message parsing
 */
function test_cemdb_parse_error_message() {
echo "Testing CEMDB error message parsing...\n";

$test_cases = array(
array(
'message' => 'Interface GigabitEthernet0/1 %LINK-3-UPDOWN: changed state to down',
'expected_count' => 1,
'expected_code' => '%LINK-3-UPDOWN'
),
array(
'message' => '%SYS-5-CONFIG_I: Configured from console by admin',
'expected_count' => 1,
'expected_code' => '%SYS-5-CONFIG_I'
),
array(
'message' => 'Multiple errors: %LINEPROTO-5-UPDOWN and %LINK-3-UPDOWN occurred',
'expected_count' => 2,
'expected_code' => '%LINEPROTO-5-UPDOWN'
),
array(
'message' => 'No Cisco error codes here',
'expected_count' => 0,
'expected_code' => null
)
);

$passed = 0;
$failed = 0;

foreach ($test_cases as $index => $test) {
$result = thold_cemdb_parse_error_message($test['message']);

if (count($result) == $test['expected_count']) {
if ($test['expected_count'] > 0) {
if ($result[0]['full_code'] == $test['expected_code']) {
echo "  ✓ Test case " . ($index + 1) . " passed\n";
$passed++;
} else {
echo "  ✗ Test case " . ($index + 1) . " failed: Expected code '{$test['expected_code']}', got '{$result[0]['full_code']}'\n";
$failed++;
}
} else {
echo "  ✓ Test case " . ($index + 1) . " passed\n";
$passed++;
}
} else {
echo "  ✗ Test case " . ($index + 1) . " failed: Expected {$test['expected_count']} error codes, got " . count($result) . "\n";
$failed++;
}
}

echo "\nParsing Tests: $passed passed, $failed failed\n\n";
return $failed == 0;
}

/**
 * Test CEMDB error code structure
 */
function test_cemdb_error_code_structure() {
echo "Testing CEMDB error code structure...\n";

$message = '%LINK-3-UPDOWN: Interface GigabitEthernet0/1, changed state to down';
$result = thold_cemdb_parse_error_message($message);

if (count($result) != 1) {
echo "  ✗ Failed to parse error code\n\n";
return false;
}

$error = $result[0];
$passed = 0;
$failed = 0;

// Check structure
$expected_keys = array('full_code', 'facility', 'severity', 'mnemonic');
foreach ($expected_keys as $key) {
if (isset($error[$key])) {
echo "  ✓ Has key '$key': {$error[$key]}\n";
$passed++;
} else {
echo "  ✗ Missing key '$key'\n";
$failed++;
}
}

// Verify values
if ($error['facility'] == 'LINK') {
echo "  ✓ Facility correct: LINK\n";
$passed++;
} else {
echo "  ✗ Facility incorrect: {$error['facility']}\n";
$failed++;
}

if ($error['severity'] == '3') {
echo "  ✓ Severity correct: 3\n";
$passed++;
} else {
echo "  ✗ Severity incorrect: {$error['severity']}\n";
$failed++;
}

if ($error['mnemonic'] == 'UPDOWN') {
echo "  ✓ Mnemonic correct: UPDOWN\n";
$passed++;
} else {
echo "  ✗ Mnemonic incorrect: {$error['mnemonic']}\n";
$failed++;
}

echo "\nStructure Tests: $passed passed, $failed failed\n\n";
return $failed == 0;
}

/**
 * Run all tests
 */
function run_all_tests() {
echo "=====================================\n";
echo "CEMDB Integration Unit Tests\n";
echo "=====================================\n\n";

$results = array();

$results[] = test_cemdb_parse_error_message();
$results[] = test_cemdb_error_code_structure();

echo "=====================================\n";
$passed = count(array_filter($results));
$total = count($results);
echo "Overall Results: $passed/$total test suites passed\n";
echo "=====================================\n";

return $passed == $total;
}

// Run tests if executed directly
if (php_sapi_name() == 'cli') {
$success = run_all_tests();
exit($success ? 0 : 1);
}
