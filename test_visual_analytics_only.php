<?php
/**
 * Test runner for Visual Analytics Properties only
 */

require_once 'db.php';
require_once 'config.php';
require_once 'tests/properties/VisualAnalyticsPropertiesTest.php';

echo "Testing Visual Analytics Properties...\n";
echo "=====================================\n\n";

try {
    $test = new VisualAnalyticsPropertiesTest();
    
    // Test Property 2: Data Consistency
    echo "Testing Property 2: Data Consistency...\n";
    $result2 = $test->testDataConsistencyProperty();
    echo $result2 ? "✅ PASSED\n" : "❌ FAILED\n";
    
    echo "\nAll Visual Analytics Property Tests Complete!\n";
    
} catch (Exception $e) {
    echo "\n💥 Test Exception: " . $e->getMessage() . "\n";
}