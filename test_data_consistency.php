<?php
/**
 * Test runner for Data Consistency Property
 */

require_once 'db.php';
require_once 'config.php';
require_once 'tests/properties/VisualAnalyticsPropertiesTest.php';

echo "Testing Data Consistency Property...\n";
echo "====================================\n\n";

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "Creating test instance...\n";
    $test = new VisualAnalyticsPropertiesTest();
    
    echo "Running data consistency property test...\n";
    $result = $test->testDataConsistencyProperty();
    
    if ($result) {
        echo "\n✅ Data Consistency Property Test PASSED!\n";
    } else {
        echo "\n❌ Data Consistency Property Test FAILED!\n";
    }
    
} catch (Exception $e) {
    echo "\n💥 Test Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "\n💥 Fatal Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}