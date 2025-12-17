<?php
/**
 * Simple test runner for Visual Analytics Property Tests
 */

require_once 'tests/properties/VisualAnalyticsPropertiesTest.php';

echo "🏥 VISUAL ANALYTICS PROPERTY TESTS\n";
echo "=" . str_repeat("=", 40) . "\n\n";

$testInstance = new VisualAnalyticsPropertiesTest();

echo "🔬 Running Complete Date Coverage Property Test...\n";
echo "-" . str_repeat("-", 40) . "\n";

try {
    $result = $testInstance->testCompleteDateCoverageProperty();
    
    if ($result) {
        echo "✅ Complete Date Coverage Property Test PASSED\n";
    } else {
        echo "❌ Complete Date Coverage Property Test FAILED\n";
    }
} catch (Exception $e) {
    echo "💥 Complete Date Coverage Property Test - EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n🔬 Running Date Label Accuracy Property Test...\n";
echo "-" . str_repeat("-", 40) . "\n";

try {
    $result = $testInstance->testDateLabelAccuracyProperty();
    
    if ($result) {
        echo "✅ Date Label Accuracy Property Test PASSED\n";
    } else {
        echo "❌ Date Label Accuracy Property Test FAILED\n";
    }
} catch (Exception $e) {
    echo "💥 Date Label Accuracy Property Test - EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n🔬 Running JSON Data Format Consistency Property Test...\n";
echo "-" . str_repeat("-", 40) . "\n";

try {
    $result = $testInstance->testJSONDataFormatConsistencyProperty();
    
    if ($result) {
        echo "✅ JSON Data Format Consistency Property Test PASSED\n";
    } else {
        echo "❌ JSON Data Format Consistency Property Test FAILED\n";
    }
} catch (Exception $e) {
    echo "💥 JSON Data Format Consistency Property Test - EXCEPTION\n";
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 40) . "\n";
echo "Test completed.\n";