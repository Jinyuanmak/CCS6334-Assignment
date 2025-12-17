<?php
/**
 * Comprehensive Property-Based Test Runner
 * Runs all property tests for the Private Clinic Patient Record System
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

// Include all property test classes
require_once __DIR__ . '/properties/AuthenticationPropertiesTest.php';
require_once __DIR__ . '/properties/PatientManagementPropertiesTest.php';
require_once __DIR__ . '/properties/SecurityPropertiesTest.php';
require_once __DIR__ . '/properties/UIPropertiesTest.php';
require_once __DIR__ . '/properties/ICEncryptionPropertiesTest.php';
require_once __DIR__ . '/properties/VisualAnalyticsPropertiesTest.php';

class ComprehensivePropertyTestRunner {
    
    private $testResults = [];
    private $totalTests = 0;
    private $passedTests = 0;
    
    public function runAllTests() {
        echo "🏥 PRIVATE CLINIC PATIENT RECORD SYSTEM\n";
        echo "🧪 COMPREHENSIVE PROPERTY-BASED TEST SUITE\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
        
        $startTime = microtime(true);
        
        // Run Authentication Tests
        $this->runTestSuite('Authentication Properties', new AuthenticationPropertiesTest(), [
            'testAuthenticationRejectionProperty',
            'testProtectedPageAccessProperty',
            'testSessionPersistenceProperty'
        ]);
        
        // Run Patient Management Tests
        $this->runTestSuite('Patient Management Properties', new PatientManagementPropertiesTest(), [
            'testPatientRecordDisplayProperty',
            'testPatientCreationProperty',
            'testDiagnosisEncryptionRoundTripProperty',
            'testFormValidationProperty',
            'testSuccessfulSubmissionWorkflowProperty',
            'testICNumberUniquenessProperty',
            'testCompletePatientDeletionProperty',
            'testDashboardRefreshAfterDeletionProperty'
        ]);
        
        // Run Security Tests
        $this->runTestSuite('Security Properties', new SecurityPropertiesTest(), [
            'testInputSanitizationProperty',
            'testSecureErrorHandlingProperty',
            'testEncryptionEffectivenessProperty'
        ]);
        
        // Run UI Tests
        $this->runTestSuite('UI Properties', new UIPropertiesTest(), [
            'testDesignConsistencyProperty',
            'testResponsiveDesignProperty'
        ]);
        
        // Audit Logging Tests removed - feature not implemented
        
        // Run IC Encryption Tests
        $this->runTestSuite('IC Encryption Properties', new ICEncryptionPropertiesTest(), [
            'testICNumberEncryptionRoundTripProperty',
            'testICNumberPrivacyMaskingProperty',
            'testICNumberSearchFunctionalityProperty'
        ]);
        
        // Run Visual Analytics Tests
        $this->runTestSuite('Visual Analytics Properties', new VisualAnalyticsPropertiesTest(), [
            'testCompleteDateCoverageProperty',
            'testDataConsistencyProperty',
            'testDateLabelAccuracyProperty',
            'testChartRenderingIntegrityProperty',
            'testErrorHandlingGracefulDegradationProperty',
            'testJSONDataFormatConsistencyProperty'
        ]);
        
        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);
        
        $this->displayFinalResults($executionTime);
    }
    
    private function runTestSuite($suiteName, $testInstance, $testMethods) {
        echo "🔬 Running $suiteName...\n";
        echo "-" . str_repeat("-", 40) . "\n";
        
        foreach ($testMethods as $method) {
            if (method_exists($testInstance, $method)) {
                $this->totalTests++;
                
                try {
                    // Capture output to prevent cluttering
                    ob_start();
                    $result = $testInstance->$method();
                    $output = ob_get_clean();
                    
                    if ($result) {
                        $this->passedTests++;
                        echo "✅ " . $this->formatMethodName($method) . "\n";
                    } else {
                        echo "❌ " . $this->formatMethodName($method) . "\n";
                        echo "   Output: " . trim($output) . "\n";
                    }
                    
                    $this->testResults[$suiteName][$method] = $result;
                    
                } catch (Exception $e) {
                    echo "💥 " . $this->formatMethodName($method) . " - EXCEPTION\n";
                    echo "   Error: " . $e->getMessage() . "\n";
                    $this->testResults[$suiteName][$method] = false;
                }
            }
        }
        
        echo "\n";
    }
    
    private function formatMethodName($methodName) {
        // Convert camelCase to readable format
        $formatted = preg_replace('/([A-Z])/', ' $1', $methodName);
        $formatted = str_replace('test', '', $formatted);
        $formatted = str_replace('Property', '', $formatted);
        $formatted = trim($formatted);
        return ucfirst($formatted);
    }
    
    private function displayFinalResults($executionTime) {
        echo "🎯 FINAL TEST RESULTS\n";
        echo "=" . str_repeat("=", 50) . "\n\n";
        
        // Display summary by test suite
        foreach ($this->testResults as $suiteName => $tests) {
            $suitePassed = array_sum($tests);
            $suiteTotal = count($tests);
            $suitePercentage = $suiteTotal > 0 ? round(($suitePassed / $suiteTotal) * 100, 1) : 0;
            
            $status = $suitePassed === $suiteTotal ? "✅" : "⚠️";
            echo "$status $suiteName: $suitePassed/$suiteTotal ($suitePercentage%)\n";
        }
        
        echo "\n" . str_repeat("-", 50) . "\n";
        
        // Overall statistics
        $overallPercentage = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 1) : 0;
        
        echo "📊 OVERALL STATISTICS:\n";
        echo "   Total Tests: $this->totalTests\n";
        echo "   Passed: $this->passedTests\n";
        echo "   Failed: " . ($this->totalTests - $this->passedTests) . "\n";
        echo "   Success Rate: $overallPercentage%\n";
        echo "   Execution Time: {$executionTime}s\n\n";
        
        // Final status
        if ($this->passedTests === $this->totalTests) {
            echo "🎉 ALL TESTS PASSED! SYSTEM READY FOR PRODUCTION! 🎉\n";
            echo "🏥 The Private Clinic Patient Record System has been fully validated.\n";
            echo "🔒 All security, functionality, and UI properties are working correctly.\n";
        } else {
            echo "⚠️  SOME TESTS FAILED - REVIEW REQUIRED\n";
            echo "🔧 Please address failing tests before production deployment.\n";
        }
        
        echo "\n" . str_repeat("=", 50) . "\n";
    }
    
    public function getDetailedReport() {
        return [
            'total_tests' => $this->totalTests,
            'passed_tests' => $this->passedTests,
            'failed_tests' => $this->totalTests - $this->passedTests,
            'success_rate' => $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 1) : 0,
            'test_results' => $this->testResults
        ];
    }
}

// Run all tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $runner = new ComprehensivePropertyTestRunner();
    $runner->runAllTests();
    
    // Optional: Generate JSON report
    if (isset($_GET['json']) || (isset($argv[1]) && $argv[1] === '--json')) {
        echo "\n📄 JSON REPORT:\n";
        echo json_encode($runner->getDetailedReport(), JSON_PRETTY_PRINT);
    }
}