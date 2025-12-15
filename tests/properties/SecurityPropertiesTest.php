<?php
/**
 * Property-based tests for security features
 * Tests input sanitization and database security
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

class SecurityPropertiesTest {
    
    /**
     * Property 13: Input sanitization
     * Feature: CCS6334-Assignment, Property 13: Input sanitization
     * Validates: Requirements 5.2
     * 
     * For any user input containing potentially malicious content, 
     * the system should sanitize it before database operations
     */
    public function testInputSanitizationProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate potentially malicious input
            $maliciousInput = $this->generateMaliciousInput();
            
            // Test sanitization
            $sanitized = Database::sanitizeInput($maliciousInput);
            
            // Property: Sanitized input should not contain dangerous characters
            $result = $this->verifySanitization($maliciousInput, $sanitized);
            
            if (!$result['passed']) {
                echo "FAILED on iteration $i:\n";
                echo "Input: " . $result['input'] . "\n";
                echo "Sanitized: " . $result['sanitized'] . "\n";
                echo "Reason: " . $result['reason'] . "\n";
                return false;
            }
        }
        
        echo "Property 13 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate potentially malicious input for testing
     */
    private function generateMaliciousInput() {
        $maliciousPatterns = [
            // XSS attempts
            '<script>alert("xss")</script>',
            '<img src="x" onerror="alert(1)">',
            'javascript:alert(1)',
            '<svg onload=alert(1)>',
            
            // SQL injection attempts (though we use prepared statements)
            "'; DROP TABLE patients; --",
            "' OR '1'='1",
            "1' UNION SELECT * FROM patients --",
            
            // HTML injection
            '<h1>Injected HTML</h1>',
            '<iframe src="malicious.com"></iframe>',
            
            // Special characters
            '"\'<>&',
            '&lt;script&gt;',
            
            // Unicode and encoding attempts
            '%3Cscript%3E',
            '&#60;script&#62;',
            
            // Normal data with special chars
            "O'Connor",
            'Smith & Jones',
            'Test "quoted" text',
            
            // Empty and whitespace
            '',
            '   ',
            "\t\n\r",
            
            // Very long strings
            str_repeat('A', 1000),
            str_repeat('<script>', 100),
        ];
        
        // Randomly select a pattern or combine patterns
        if (rand(0, 1)) {
            return $maliciousPatterns[array_rand($maliciousPatterns)];
        } else {
            // Combine multiple patterns
            $pattern1 = $maliciousPatterns[array_rand($maliciousPatterns)];
            $pattern2 = $maliciousPatterns[array_rand($maliciousPatterns)];
            return $pattern1 . ' ' . $pattern2;
        }
    }
    
    /**
     * Verify that sanitization properly handles malicious input
     */
    private function verifySanitization($original, $sanitized) {
        $result = [
            'input' => $original,
            'sanitized' => $sanitized,
            'passed' => true,
            'reason' => ''
        ];
        
        // Check 1: Script tags should be escaped or removed
        if (strpos($sanitized, '<script') !== false) {
            $result['passed'] = false;
            $result['reason'] = 'Script tags not properly sanitized';
            return $result;
        }
        
        // Check 2: HTML tags should be escaped
        if (preg_match('/<[^>]*>/', $sanitized) && !preg_match('/&lt;|&gt;/', $sanitized)) {
            $result['passed'] = false;
            $result['reason'] = 'HTML tags not properly escaped';
            return $result;
        }
        
        // Check 3: JavaScript protocols should be neutralized
        if (stripos($sanitized, 'javascript:') !== false && stripos($sanitized, 'blocked:') === false) {
            $result['passed'] = false;
            $result['reason'] = 'JavaScript protocol not sanitized';
            return $result;
        }
        
        // Check 4: Event handlers should be escaped
        $eventHandlers = ['onload', 'onerror', 'onclick', 'onmouseover'];
        foreach ($eventHandlers as $handler) {
            if (stripos($sanitized, $handler . '=') !== false) {
                $result['passed'] = false;
                $result['reason'] = "Event handler '$handler' not sanitized";
                return $result;
            }
        }
        
        // Check 5: Quotes should be properly escaped
        if (strpos($original, '"') !== false) {
            if (strpos($sanitized, '"') !== false && strpos($sanitized, '&quot;') === false) {
                $result['passed'] = false;
                $result['reason'] = 'Double quotes not properly escaped';
                return $result;
            }
        }
        if (strpos($original, "'") !== false) {
            if (strpos($sanitized, "'") !== false && strpos($sanitized, '&#039;') === false) {
                $result['passed'] = false;
                $result['reason'] = 'Single quotes not properly escaped';
                return $result;
            }
        }
        
        // Check 6: Sanitized output should not be longer than reasonable limit (HTML encoding can expand)
        if (strlen($sanitized) > strlen($original) * 6) {
            $result['passed'] = false;
            $result['reason'] = 'Sanitized output unreasonably long';
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Property 14: Secure error handling
     * Feature: CCS6334-Assignment, Property 14: Secure error handling
     * Validates: Requirements 5.5
     * 
     * For any database error condition, the system should log errors appropriately 
     * without exposing sensitive information to users
     */
    public function testSecureErrorHandlingProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate various error conditions
            $errorCondition = $this->generateErrorCondition();
            
            // Test error handling
            $result = $this->testErrorHandling($errorCondition);
            
            if (!$result['passed']) {
                echo "FAILED on iteration $i:\n";
                echo "Error condition: " . $result['condition'] . "\n";
                echo "User message: " . $result['user_message'] . "\n";
                echo "Reason: " . $result['reason'] . "\n";
                return false;
            }
        }
        
        echo "Property 14 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate different error conditions for testing
     */
    private function generateErrorCondition() {
        $errorTypes = [
            'invalid_table' => "SELECT * FROM non_existent_table",
            'syntax_error' => "SELECT * FROM patients WHERE invalid syntax",
            'invalid_column' => "SELECT non_existent_column FROM patients",
            'connection_error' => "connection_failure",
        ];
        
        $errorType = array_rand($errorTypes);
        return [
            'type' => $errorType,
            'query' => $errorTypes[$errorType]
        ];
    }
    
    /**
     * Test error handling for various database error conditions
     */
    private function testErrorHandling($errorCondition) {
        $result = [
            'condition' => $errorCondition['type'],
            'user_message' => '',
            'passed' => true,
            'reason' => ''
        ];
        
        try {
            // Simulate error handling based on the condition type
            if ($errorCondition['type'] === 'connection_error') {
                // Simulate connection failure
                $userMessage = "Database connection failed. Please try again later.";
            } else {
                // Try to execute the problematic query to trigger error handling
                try {
                    // Temporarily disable error logging to suppress expected error messages during testing
                    $originalLogErrors = ini_get('log_errors');
                    ini_set('log_errors', '0');
                    
                    Database::executeQuery($errorCondition['query']);
                    
                    // Restore error logging
                    ini_set('log_errors', $originalLogErrors);
                    
                    // If no error occurred, that's unexpected for our test cases
                    $result['passed'] = false;
                    $result['reason'] = 'Expected database error did not occur';
                    return $result;
                } catch (Exception $e) {
                    // Restore error logging
                    ini_set('log_errors', $originalLogErrors);
                    
                    // This is expected - now check the error handling
                    $userMessage = $e->getMessage();
                }
            }
            
            $result['user_message'] = $userMessage;
            
            // Property checks for secure error handling
            
            // Check 1: User message should not contain sensitive database details
            $sensitivePatterns = [
                'mysql',
                'pdo',
                'connection string',
                'password',
                'root@',
                'localhost:3306',
                'clinic_db',
                'table \'',
                'column \'',
                'syntax error',
                'duplicate entry',
                'foreign key constraint'
            ];
            
            foreach ($sensitivePatterns as $pattern) {
                if (stripos($userMessage, $pattern) !== false) {
                    $result['passed'] = false;
                    $result['reason'] = "User message contains sensitive information: '$pattern'";
                    return $result;
                }
            }
            
            // Check 2: User message should be generic and user-friendly
            $validUserMessages = [
                'Database connection failed. Please try again later.',
                'Database operation failed. Please try again later.',
                'Database error occurred. Please try again later.',
                'An error occurred. Please try again later.',
                'System temporarily unavailable. Please try again later.'
            ];
            
            $isValidMessage = false;
            foreach ($validUserMessages as $validMessage) {
                if (stripos($userMessage, $validMessage) !== false || 
                    $this->isGenericErrorMessage($userMessage)) {
                    $isValidMessage = true;
                    break;
                }
            }
            
            if (!$isValidMessage) {
                $result['passed'] = false;
                $result['reason'] = 'User message is not generic enough or too technical';
                return $result;
            }
            
            // Check 3: User message should not be empty
            if (empty(trim($userMessage))) {
                $result['passed'] = false;
                $result['reason'] = 'User message is empty';
                return $result;
            }
            
            // Check 4: User message should not contain stack traces
            if (strpos($userMessage, 'Stack trace:') !== false || 
                strpos($userMessage, 'thrown in') !== false ||
                strpos($userMessage, '.php on line') !== false) {
                $result['passed'] = false;
                $result['reason'] = 'User message contains stack trace information';
                return $result;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $result['passed'] = false;
            $result['reason'] = 'Unexpected exception during error handling test: ' . $e->getMessage();
            return $result;
        }
    }
    
    /**
     * Check if a message is a generic error message
     */
    private function isGenericErrorMessage($message) {
        $genericPatterns = [
            'please try again',
            'temporarily unavailable',
            'system error',
            'operation failed',
            'unable to process',
            'service unavailable'
        ];
        
        foreach ($genericPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Property 15: Encryption effectiveness
     * Feature: CCS6334-Assignment, Property 15: Encryption effectiveness
     * Validates: Requirements 6.4
     * 
     * For any diagnosis text, the encrypted version stored in the database 
     * should be different from the original plaintext
     */
    public function testEncryptionEffectivenessProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random diagnosis text
            $diagnosisText = $this->generateDiagnosisText();
            
            // Test encryption effectiveness
            $result = $this->testEncryptionEffectiveness($diagnosisText);
            
            if (!$result['passed']) {
                echo "FAILED on iteration $i:\n";
                echo "Original: " . $result['original'] . "\n";
                echo "Encrypted: " . $result['encrypted'] . "\n";
                echo "Reason: " . $result['reason'] . "\n";
                return false;
            }
        }
        
        echo "Property 15 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate various diagnosis texts for testing
     */
    private function generateDiagnosisText() {
        $diagnosisOptions = [
            // Common medical conditions
            'Hypertension',
            'Type 2 Diabetes Mellitus',
            'Acute Upper Respiratory Tract Infection',
            'Gastroenteritis',
            'Migraine Headache',
            'Lower Back Pain',
            'Allergic Rhinitis',
            'Anxiety Disorder',
            'Chronic Obstructive Pulmonary Disease',
            'Osteoarthritis',
            
            // Complex diagnoses
            'Acute myocardial infarction with ST elevation',
            'Chronic kidney disease stage 3 with hypertension',
            'Major depressive disorder, recurrent episode, moderate severity',
            'Pneumonia, organism unspecified',
            'Fracture of distal radius, closed',
            
            // Short diagnoses
            'Flu',
            'Cold',
            'Fever',
            'Cough',
            'Rash',
            
            // Long diagnoses
            str_repeat('Complex medical condition with multiple comorbidities including ', 3) . 'various symptoms',
            
            // Special characters in diagnoses
            "Patient has Type-2 Diabetes & Hypertension (controlled)",
            "Diagnosis: COVID-19 positive, mild symptoms",
            "Post-operative care following appendectomy - healing well",
            
            // Empty and edge cases
            '',
            ' ',
            '   ',
            
            // Numbers and mixed content
            '1st degree burn on left hand',
            'Patient #12345 - routine checkup',
            'Blood pressure: 140/90 mmHg - needs monitoring',
            
            // Unicode characters
            'Café au lait spots observed',
            'Patient reports naïve symptoms',
            
            // Very long text
            str_repeat('This is a very long diagnosis that contains multiple sentences and detailed medical information about the patient condition. ', 10),
        ];
        
        // Randomly select or generate diagnosis
        if (rand(0, 1)) {
            return $diagnosisOptions[array_rand($diagnosisOptions)];
        } else {
            // Generate random text
            $length = rand(1, 200);
            $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 .,!?-()[]{}';
            $randomText = '';
            for ($i = 0; $i < $length; $i++) {
                $randomText .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $randomText;
        }
    }
    
    /**
     * Test encryption effectiveness for diagnosis text
     */
    private function testEncryptionEffectiveness($diagnosisText) {
        $result = [
            'original' => $diagnosisText,
            'encrypted' => '',
            'passed' => true,
            'reason' => ''
        ];
        
        try {
            // Encrypt the diagnosis
            $encryptSql = "SELECT AES_ENCRYPT(?, ?) as encrypted_diagnosis";
            $encryptResult = Database::fetchOne($encryptSql, [$diagnosisText, ENCRYPTION_KEY]);
            
            if (!$encryptResult || $encryptResult['encrypted_diagnosis'] === null) {
                $result['passed'] = false;
                $result['reason'] = 'Encryption returned null or failed';
                return $result;
            }
            
            $encryptedData = $encryptResult['encrypted_diagnosis'];
            $result['encrypted'] = bin2hex($encryptedData); // Convert binary to hex for display
            
            // Property checks for encryption effectiveness
            
            // Check 1: Encrypted data should be different from original (unless original is empty)
            if (!empty($diagnosisText) && $encryptedData === $diagnosisText) {
                $result['passed'] = false;
                $result['reason'] = 'Encrypted data is identical to original plaintext';
                return $result;
            }
            
            // Check 2: Encrypted data should not be empty for non-empty input
            if (!empty($diagnosisText) && empty($encryptedData)) {
                $result['passed'] = false;
                $result['reason'] = 'Encrypted data is empty for non-empty input';
                return $result;
            }
            
            // Check 3: Encrypted data should be binary (not contain original plaintext)
            if (!empty($diagnosisText) && strlen($diagnosisText) > 3) {
                // Check if encrypted data contains the original plaintext
                if (strpos($encryptedData, $diagnosisText) !== false) {
                    $result['passed'] = false;
                    $result['reason'] = 'Encrypted data contains original plaintext';
                    return $result;
                }
                
                // Check if encrypted data contains significant portions of original text
                $words = explode(' ', $diagnosisText);
                foreach ($words as $word) {
                    if (strlen($word) > 4 && strpos($encryptedData, $word) !== false) {
                        $result['passed'] = false;
                        $result['reason'] = 'Encrypted data contains original word: ' . $word;
                        return $result;
                    }
                }
            }
            
            // Check 4: Verify decryption works and returns original
            $decryptSql = "SELECT AES_DECRYPT(?, ?) as decrypted_diagnosis";
            $decryptResult = Database::fetchOne($decryptSql, [$encryptedData, ENCRYPTION_KEY]);
            
            if (!$decryptResult) {
                $result['passed'] = false;
                $result['reason'] = 'Decryption query failed';
                return $result;
            }
            
            $decryptedText = $decryptResult['decrypted_diagnosis'];
            
            if ($decryptedText !== $diagnosisText) {
                $result['passed'] = false;
                $result['reason'] = 'Decrypted text does not match original';
                return $result;
            }
            
            // Check 5: Different plaintexts should produce different ciphertexts
            if (!empty($diagnosisText)) {
                $modifiedText = $diagnosisText . 'X'; // Add a character
                $encryptModifiedSql = "SELECT AES_ENCRYPT(?, ?) as encrypted_diagnosis";
                $encryptModifiedResult = Database::fetchOne($encryptModifiedSql, [$modifiedText, ENCRYPTION_KEY]);
                
                if ($encryptModifiedResult && $encryptModifiedResult['encrypted_diagnosis'] !== null) {
                    $modifiedEncrypted = $encryptModifiedResult['encrypted_diagnosis'];
                    
                    if ($modifiedEncrypted === $encryptedData) {
                        $result['passed'] = false;
                        $result['reason'] = 'Different plaintexts produced identical ciphertexts';
                        return $result;
                    }
                }
            }
            
            // Check 6: Encryption should be deterministic for same input
            $encryptAgainResult = Database::fetchOne($encryptSql, [$diagnosisText, ENCRYPTION_KEY]);
            if ($encryptAgainResult && $encryptAgainResult['encrypted_diagnosis'] !== null) {
                $encryptedAgain = $encryptAgainResult['encrypted_diagnosis'];
                
                if ($encryptedAgain !== $encryptedData) {
                    $result['passed'] = false;
                    $result['reason'] = 'Encryption is not deterministic - same input produced different outputs';
                    return $result;
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $result['passed'] = false;
            $result['reason'] = 'Exception during encryption test: ' . $e->getMessage();
            return $result;
        }
    }
    
    /**
     * Run all security property tests
     */
    public function runAllTests() {
        echo "Running Security Property Tests...\n";
        echo "================================\n";
        
        $testsPassed = 0;
        $totalTests = 3;
        
        // Test Property 13: Input sanitization
        echo "Testing Property 13: Input sanitization...\n";
        if ($this->testInputSanitizationProperty()) {
            $testsPassed++;
            echo "✓ Property 13 PASSED\n";
        } else {
            echo "✗ Property 13 FAILED\n";
        }
        
        echo "\nTesting Property 14: Secure error handling...\n";
        echo "(Note: The following 'Query execution failed' messages are expected - we're testing error handling)\n";
        if ($this->testSecureErrorHandlingProperty()) {
            $testsPassed++;
            echo "✓ Property 14 PASSED\n";
        } else {
            echo "✗ Property 14 FAILED\n";
        }
        
        echo "\nTesting Property 15: Encryption effectiveness...\n";
        if ($this->testEncryptionEffectivenessProperty()) {
            $testsPassed++;
            echo "✓ Property 15 PASSED\n";
        } else {
            echo "✗ Property 15 FAILED\n";
        }
        
        echo "\n================================\n";
        echo "Results: $testsPassed/$totalTests tests passed\n";
        
        return $testsPassed === $totalTests;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new SecurityPropertiesTest();
    $success = $tester->runAllTests();
    exit($success ? 0 : 1);
}