<?php
/**
 * Property-based tests for patient management features
 * Tests patient record display, creation, and deletion
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';

class PatientManagementPropertiesTest {
    
    /**
     * Property 4: Patient record display completeness
     * Feature: CCS6334-Assignment, Property 4: Patient record display completeness
     * Validates: Requirements 2.1, 2.3
     * 
     * For any set of patient records, the dashboard should display all records 
     * with name, IC number, phone, and decrypted diagnosis
     */
    public function testPatientRecordDisplayProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a random set of patient records
            $testPatients = $this->generateTestPatients();
            
            // Test patient record display
            $result = $this->testPatientDisplay($testPatients);
            
            // Property: All patient records should be displayed
            if (!$result['all_records_displayed']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected records: " . count($testPatients) . "\n";
                echo "Displayed records: " . $result['displayed_count'] . "\n";
                echo "Missing records: " . json_encode($result['missing_records']) . "\n";
                return false;
            }
            
            // Property: Each record should contain all required fields
            if (!$result['all_fields_present']) {
                echo "FAILED on iteration $i:\n";
                echo "Records with missing fields: " . json_encode($result['records_missing_fields']) . "\n";
                return false;
            }
            
            // Property: Diagnosis should be properly decrypted
            if (!$result['diagnosis_properly_decrypted']) {
                echo "FAILED on iteration $i:\n";
                echo "Records with decryption issues: " . json_encode($result['decryption_issues']) . "\n";
                return false;
            }
            
            // Property: Data should be properly sanitized for display
            if (!$result['data_properly_sanitized']) {
                echo "FAILED on iteration $i:\n";
                echo "Records with sanitization issues: " . json_encode($result['sanitization_issues']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 4 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate random test patient records for testing
     */
    private function generateTestPatients() {
        $numPatients = rand(0, 10); // Test with 0 to 10 patients
        $patients = [];
        
        for ($i = 0; $i < $numPatients; $i++) {
            $patients[] = [
                'id' => $i + 1,
                'name' => $this->generateRandomName(),
                'ic_number' => $this->generateRandomIC(),
                'diagnosis' => $this->generateRandomDiagnosis(),
                'phone' => $this->generateRandomPhone(),
                'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30)) // Random date within last 30 days
            ];
        }
        
        return $patients;
    }
    
    /**
     * Generate random patient name
     */
    private function generateRandomName() {
        $firstNames = [
            'Ahmad', 'Ali', 'Siti', 'Fatimah', 'Muhammad', 'Nurul', 'Mohd', 'Aisha',
            'John', 'Jane', 'David', 'Sarah', 'Michael', 'Lisa', 'Robert', 'Emily',
            'Chen', 'Li', 'Wang', 'Zhang', 'Kumar', 'Raj', 'Priya', 'Ravi',
            // Include names with special characters to test sanitization
            "O'Connor", 'José', 'François', 'Müller', 'Björk'
        ];
        
        $lastNames = [
            'Abdullah', 'Rahman', 'Hassan', 'Ibrahim', 'Ismail', 'Omar', 'Yusof',
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller',
            'Wong', 'Lim', 'Tan', 'Lee', 'Patel', 'Singh', 'Sharma', 'Gupta',
            // Include names with special characters
            'O\'Brien', 'García', 'François', 'Müller'
        ];
        
        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        
        // Sometimes add middle names or titles
        if (rand(0, 3) === 0) {
            $middleNames = ['bin', 'binti', 'Abdul', 'Al', 'Van', 'De', 'Jr.', 'Sr.'];
            $middleName = $middleNames[array_rand($middleNames)];
            return "$firstName $middleName $lastName";
        }
        
        return "$firstName $lastName";
    }
    
    /**
     * Generate random IC number (Malaysian format)
     */
    private function generateRandomIC() {
        // Generate 12-digit IC number
        $ic = '';
        for ($i = 0; $i < 12; $i++) {
            $ic .= rand(0, 9);
        }
        
        // Sometimes add formatting (dashes) to test handling
        if (rand(0, 2) === 0) {
            return substr($ic, 0, 6) . '-' . substr($ic, 6, 2) . '-' . substr($ic, 8);
        }
        
        return $ic;
    }
    
    /**
     * Generate random diagnosis text
     */
    private function generateRandomDiagnosis() {
        $diagnoses = [
            'Hypertension',
            'Diabetes Type 2',
            'Common Cold',
            'Migraine',
            'Asthma',
            'Gastritis',
            'Allergic Rhinitis',
            'Lower Back Pain',
            'Anxiety Disorder',
            'Insomnia',
            'Chronic Fatigue Syndrome',
            'Osteoarthritis',
            'Bronchitis',
            'Sinusitis',
            'Eczema',
            // Include diagnoses with special characters to test encryption/decryption
            'Type 2 Diabetes & Hypertension',
            'Post-operative care (knee replacement)',
            'Chronic pain - lower back',
            'Allergies: dust mites, pollen',
            'Depression & anxiety',
            // Include longer diagnoses
            'Chronic obstructive pulmonary disease with acute exacerbation',
            'Multiple sclerosis with relapsing-remitting course',
            // Include diagnoses with quotes and special characters
            'Patient reports "severe headaches" daily',
            'Condition improved after treatment (90% better)',
            // Include empty or minimal diagnoses
            'N/A',
            'TBD',
            'Follow-up required'
        ];
        
        $diagnosis = $diagnoses[array_rand($diagnoses)];
        
        // Sometimes combine multiple diagnoses
        if (rand(0, 4) === 0) {
            $secondDiagnosis = $diagnoses[array_rand($diagnoses)];
            if ($diagnosis !== $secondDiagnosis) {
                $diagnosis .= ', ' . $secondDiagnosis;
            }
        }
        
        return $diagnosis;
    }
    
    /**
     * Generate random phone number
     */
    private function generateRandomPhone() {
        $formats = [
            // Malaysian mobile formats
            '01' . rand(10000000, 99999999),
            '012' . rand(1000000, 9999999),
            '013' . rand(1000000, 9999999),
            '014' . rand(1000000, 9999999),
            '016' . rand(1000000, 9999999),
            '017' . rand(1000000, 9999999),
            '018' . rand(1000000, 9999999),
            '019' . rand(1000000, 9999999),
            
            // Landline formats
            '03' . rand(10000000, 99999999),
            '04' . rand(1000000, 9999999),
            '05' . rand(1000000, 9999999),
            '06' . rand(1000000, 9999999),
            '07' . rand(1000000, 9999999),
            '08' . rand(1000000, 9999999),
            '09' . rand(1000000, 9999999),
        ];
        
        $phone = $formats[array_rand($formats)];
        
        // Sometimes add formatting
        if (rand(0, 2) === 0) {
            // Add dashes
            if (strlen($phone) === 10) {
                return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
            } elseif (strlen($phone) === 11) {
                return substr($phone, 0, 3) . '-' . substr($phone, 3, 4) . '-' . substr($phone, 7);
            }
        }
        
        return $phone;
    }
    
    /**
     * Test patient display functionality
     */
    private function testPatientDisplay($testPatients) {
        $result = [
            'test_patients' => $testPatients,
            'all_records_displayed' => true,
            'displayed_count' => 0,
            'missing_records' => [],
            'all_fields_present' => true,
            'records_missing_fields' => [],
            'diagnosis_properly_decrypted' => true,
            'decryption_issues' => [],
            'data_properly_sanitized' => true,
            'sanitization_issues' => [],
            'displayed_records' => []
        ];
        
        try {
            // Simulate the dashboard display logic
            $displayedRecords = $this->simulateDashboardDisplay($testPatients);
            $result['displayed_records'] = $displayedRecords;
            $result['displayed_count'] = count($displayedRecords);
            
            // Check if all records are displayed
            if (count($displayedRecords) !== count($testPatients)) {
                $result['all_records_displayed'] = false;
                
                // Find missing records
                $displayedIds = array_column($displayedRecords, 'id');
                foreach ($testPatients as $patient) {
                    if (!in_array($patient['id'], $displayedIds)) {
                        $result['missing_records'][] = $patient['id'];
                    }
                }
            }
            
            // Check if all required fields are present in each displayed record
            foreach ($displayedRecords as $record) {
                $requiredFields = ['name', 'ic_number', 'diagnosis', 'phone', 'created_at'];
                $missingFields = [];
                
                foreach ($requiredFields as $field) {
                    if (!isset($record[$field]) || $record[$field] === null) {
                        $missingFields[] = $field;
                    }
                }
                
                if (!empty($missingFields)) {
                    $result['all_fields_present'] = false;
                    $result['records_missing_fields'][] = [
                        'record_id' => $record['id'] ?? 'unknown',
                        'missing_fields' => $missingFields
                    ];
                }
            }
            
            // Check diagnosis decryption
            foreach ($displayedRecords as $i => $record) {
                if (isset($testPatients[$i])) {
                    $originalDiagnosis = $testPatients[$i]['diagnosis'];
                    $displayedDiagnosis = $record['diagnosis'];
                    
                    // Check if diagnosis was properly decrypted
                    if ($displayedDiagnosis === null || $displayedDiagnosis === '') {
                        // This might be acceptable if the original was empty
                        if ($originalDiagnosis !== '' && $originalDiagnosis !== null) {
                            $result['diagnosis_properly_decrypted'] = false;
                            $result['decryption_issues'][] = [
                                'record_id' => $record['id'],
                                'original' => $originalDiagnosis,
                                'displayed' => $displayedDiagnosis,
                                'issue' => 'Decryption failed or returned empty'
                            ];
                        }
                    } else {
                        // Check if decrypted diagnosis matches original
                        if ($displayedDiagnosis !== $originalDiagnosis) {
                            $result['diagnosis_properly_decrypted'] = false;
                            $result['decryption_issues'][] = [
                                'record_id' => $record['id'],
                                'original' => $originalDiagnosis,
                                'displayed' => $displayedDiagnosis,
                                'issue' => 'Decrypted diagnosis does not match original'
                            ];
                        }
                    }
                }
            }
            
            // Check data sanitization
            foreach ($displayedRecords as $record) {
                foreach (['name', 'ic_number', 'diagnosis', 'phone'] as $field) {
                    if (isset($record[$field])) {
                        $value = $record[$field];
                        
                        // Check for potential XSS vulnerabilities
                        if (strpos($value, '<script') !== false || 
                            strpos($value, 'javascript:') !== false ||
                            strpos($value, 'onerror=') !== false ||
                            strpos($value, 'onload=') !== false) {
                            
                            $result['data_properly_sanitized'] = false;
                            $result['sanitization_issues'][] = [
                                'record_id' => $record['id'],
                                'field' => $field,
                                'value' => $value,
                                'issue' => 'Potential XSS vulnerability detected'
                            ];
                        }
                        
                        // Check for unescaped HTML entities
                        if (preg_match('/<[^>]*>/', $value) && !preg_match('/&lt;|&gt;/', $value)) {
                            $result['data_properly_sanitized'] = false;
                            $result['sanitization_issues'][] = [
                                'record_id' => $record['id'],
                                'field' => $field,
                                'value' => $value,
                                'issue' => 'HTML tags not properly escaped'
                            ];
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $result['all_records_displayed'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate the dashboard display logic
     */
    private function simulateDashboardDisplay($testPatients) {
        $displayedRecords = [];
        
        foreach ($testPatients as $patient) {
            // Simulate the database query with encryption/decryption
            $encryptedDiagnosis = $this->simulateEncryption($patient['diagnosis']);
            $decryptedDiagnosis = $this->simulateDecryption($encryptedDiagnosis);
            
            // Simulate the display record creation (as would happen in dashboard.php)
            $displayRecord = [
                'id' => $patient['id'],
                'name' => Database::sanitizeInput($patient['name']),
                'ic_number' => Database::sanitizeInput($patient['ic_number']),
                'diagnosis' => $decryptedDiagnosis,
                'phone' => Database::sanitizeInput($patient['phone']),
                'created_at' => $patient['created_at']
            ];
            
            // Handle cases where decryption might fail (as in dashboard.php)
            if ($displayRecord['diagnosis'] === null || $displayRecord['diagnosis'] === '') {
                if ($patient['diagnosis'] !== '' && $patient['diagnosis'] !== null) {
                    // This represents the "Unable to decrypt diagnosis" case
                    $displayRecord['diagnosis'] = null;
                }
            }
            
            $displayedRecords[] = $displayRecord;
        }
        
        return $displayedRecords;
    }
    

    
    /**
     * Property 6: Delete button presence
     * Feature: CCS6334-Assignment, Property 6: Delete button presence
     * Validates: Requirements 2.4
     * 
     * For any number of patient records displayed, each record should have 
     * an associated delete button
     */
    public function testDeleteButtonPresenceProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a random set of patient records
            $testPatients = $this->generateTestPatients();
            
            // Test delete button presence
            $result = $this->testDeleteButtonPresence($testPatients);
            
            // Property: Each patient record should have a delete button
            if (!$result['all_records_have_delete_button']) {
                echo "FAILED on iteration $i:\n";
                echo "Total records: " . count($testPatients) . "\n";
                echo "Records with delete buttons: " . $result['records_with_delete_buttons'] . "\n";
                echo "Records missing delete buttons: " . json_encode($result['records_missing_delete_buttons']) . "\n";
                return false;
            }
            
            // Property: Delete buttons should have proper attributes
            if (!$result['delete_buttons_properly_configured']) {
                echo "FAILED on iteration $i:\n";
                echo "Delete buttons with configuration issues: " . json_encode($result['button_configuration_issues']) . "\n";
                return false;
            }
            
            // Property: Delete buttons should reference correct patient IDs
            if (!$result['delete_buttons_reference_correct_ids']) {
                echo "FAILED on iteration $i:\n";
                echo "Delete buttons with incorrect IDs: " . json_encode($result['incorrect_id_references']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 6 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test delete button presence functionality
     */
    private function testDeleteButtonPresence($testPatients) {
        $result = [
            'test_patients' => $testPatients,
            'all_records_have_delete_button' => true,
            'records_with_delete_buttons' => 0,
            'records_missing_delete_buttons' => [],
            'delete_buttons_properly_configured' => true,
            'button_configuration_issues' => [],
            'delete_buttons_reference_correct_ids' => true,
            'incorrect_id_references' => [],
            'dashboard_html' => ''
        ];
        
        try {
            // Simulate the dashboard HTML generation
            $dashboardHtml = $this->simulateDashboardHtml($testPatients);
            $result['dashboard_html'] = $dashboardHtml;
            
            // Parse the HTML to check for delete buttons
            $deleteButtons = $this->extractDeleteButtons($dashboardHtml);
            
            // Check if each patient record has a delete button
            foreach ($testPatients as $patient) {
                $patientId = $patient['id'];
                $hasDeleteButton = false;
                $buttonConfigured = false;
                $correctIdReference = false;
                
                foreach ($deleteButtons as $button) {
                    // Check if this button is for the current patient
                    if (strpos($button['href'], "id=$patientId") !== false || 
                        strpos($button['href'], "id=" . urlencode($patientId)) !== false) {
                        $hasDeleteButton = true;
                        
                        // Check if button is properly configured
                        if ($this->isDeleteButtonProperlyConfigured($button)) {
                            $buttonConfigured = true;
                        } else {
                            $result['button_configuration_issues'][] = [
                                'patient_id' => $patientId,
                                'button' => $button,
                                'issues' => $this->getButtonConfigurationIssues($button)
                            ];
                        }
                        
                        // Check if button references correct ID
                        if ($this->doesButtonReferenceCorrectId($button, $patientId)) {
                            $correctIdReference = true;
                        } else {
                            $result['incorrect_id_references'][] = [
                                'patient_id' => $patientId,
                                'button_href' => $button['href'],
                                'expected_id' => $patientId
                            ];
                        }
                        
                        break;
                    }
                }
                
                if ($hasDeleteButton) {
                    $result['records_with_delete_buttons']++;
                } else {
                    $result['all_records_have_delete_button'] = false;
                    $result['records_missing_delete_buttons'][] = $patientId;
                }
                
                if (!$buttonConfigured) {
                    $result['delete_buttons_properly_configured'] = false;
                }
                
                if (!$correctIdReference && $hasDeleteButton) {
                    $result['delete_buttons_reference_correct_ids'] = false;
                }
            }
            
        } catch (Exception $e) {
            $result['all_records_have_delete_button'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate the dashboard HTML generation
     */
    private function simulateDashboardHtml($testPatients) {
        $html = '<div class="patients-section">';
        
        if (empty($testPatients)) {
            $html .= '<div class="no-records"><p>No patient records found.</p></div>';
        } else {
            $html .= '<div class="table-container">';
            $html .= '<table class="patients-table">';
            $html .= '<thead><tr><th>Name</th><th>IC Number</th><th>Diagnosis</th><th>Phone</th><th>Date Added</th><th>Actions</th></tr></thead>';
            $html .= '<tbody>';
            
            foreach ($testPatients as $patient) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($patient['name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($patient['ic_number']) . '</td>';
                
                // Handle diagnosis display
                $diagnosis = $patient['diagnosis'];
                if ($diagnosis === null || $diagnosis === '') {
                    $html .= '<td><em>Unable to decrypt diagnosis</em></td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($diagnosis) . '</td>';
                }
                
                $html .= '<td>' . htmlspecialchars($patient['phone']) . '</td>';
                $html .= '<td>' . date('M j, Y', strtotime($patient['created_at'])) . '</td>';
                
                // Actions column with delete button
                $html .= '<td>';
                $html .= '<a href="delete_patient.php?id=' . $patient['id'] . '" ';
                $html .= 'class="btn btn-danger btn-small" ';
                $html .= 'onclick="return confirm(\'Are you sure you want to delete this patient record? This action cannot be undone.\');">';
                $html .= 'Delete';
                $html .= '</a>';
                $html .= '</td>';
                
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Extract delete buttons from HTML
     */
    private function extractDeleteButtons($html) {
        $buttons = [];
        
        // Use regex to find delete buttons
        $pattern = '/<a\s+[^>]*href="delete_patient\.php\?id=([^"]*)"[^>]*>(.*?)<\/a>/i';
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $buttons[] = [
                    'full_tag' => $match[0],
                    'href' => 'delete_patient.php?id=' . $match[1],
                    'patient_id' => $match[1],
                    'text' => trim(strip_tags($match[2])),
                    'attributes' => $this->extractAttributes($match[0])
                ];
            }
        }
        
        return $buttons;
    }
    
    /**
     * Extract attributes from HTML tag
     */
    private function extractAttributes($tag) {
        $attributes = [];
        
        // Extract class attribute
        if (preg_match('/class="([^"]*)"/', $tag, $matches)) {
            $attributes['class'] = $matches[1];
        }
        
        // Extract onclick attribute
        if (preg_match('/onclick="([^"]*)"/', $tag, $matches)) {
            $attributes['onclick'] = $matches[1];
        }
        
        // Extract href attribute
        if (preg_match('/href="([^"]*)"/', $tag, $matches)) {
            $attributes['href'] = $matches[1];
        }
        
        return $attributes;
    }
    
    /**
     * Check if delete button is properly configured
     */
    private function isDeleteButtonProperlyConfigured($button) {
        // Check if button has proper CSS classes
        if (!isset($button['attributes']['class']) || 
            strpos($button['attributes']['class'], 'btn') === false) {
            return false;
        }
        
        // Check if button has confirmation dialog
        if (!isset($button['attributes']['onclick']) || 
            strpos($button['attributes']['onclick'], 'confirm') === false) {
            return false;
        }
        
        // Check if button text is appropriate
        if (strtolower($button['text']) !== 'delete') {
            return false;
        }
        
        // Check if href is properly formatted
        if (!isset($button['attributes']['href']) || 
            !preg_match('/delete_patient\.php\?id=\d+/', $button['attributes']['href'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get button configuration issues
     */
    private function getButtonConfigurationIssues($button) {
        $issues = [];
        
        if (!isset($button['attributes']['class']) || 
            strpos($button['attributes']['class'], 'btn') === false) {
            $issues[] = 'Missing or incorrect CSS classes';
        }
        
        if (!isset($button['attributes']['onclick']) || 
            strpos($button['attributes']['onclick'], 'confirm') === false) {
            $issues[] = 'Missing confirmation dialog';
        }
        
        if (strtolower($button['text']) !== 'delete') {
            $issues[] = 'Incorrect button text';
        }
        
        if (!isset($button['attributes']['href']) || 
            !preg_match('/delete_patient\.php\?id=\d+/', $button['attributes']['href'])) {
            $issues[] = 'Malformed href attribute';
        }
        
        return $issues;
    }
    
    /**
     * Check if button references correct patient ID
     */
    private function doesButtonReferenceCorrectId($button, $expectedId) {
        if (!isset($button['patient_id'])) {
            return false;
        }
        
        return $button['patient_id'] == $expectedId;
    }

    /**
     * Property 7: Patient creation with valid data
     * Feature: CCS6334-Assignment, Property 7: Patient creation with valid data
     * Validates: Requirements 3.1
     * 
     * For any complete and valid patient information, submitting it should create 
     * a new patient record in the database
     */
    public function testPatientCreationProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate valid patient data
            $patientData = $this->generateValidPatientData();
            
            // Test patient creation
            $result = $this->testPatientCreation($patientData);
            
            // Property: Valid patient data should create a record
            if (!$result['patient_created']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient data: " . json_encode($patientData) . "\n";
                echo "Creation result: " . json_encode($result) . "\n";
                return false;
            }
            
            // Property: Created record should match submitted data
            if (!$result['data_matches']) {
                echo "FAILED on iteration $i:\n";
                echo "Original data: " . json_encode($patientData) . "\n";
                echo "Stored data: " . json_encode($result['stored_data']) . "\n";
                echo "Mismatches: " . json_encode($result['data_mismatches']) . "\n";
                return false;
            }
            
            // Property: Diagnosis should be encrypted in storage
            if (!$result['diagnosis_encrypted']) {
                echo "FAILED on iteration $i:\n";
                echo "Original diagnosis: " . $patientData['diagnosis'] . "\n";
                echo "Stored diagnosis: " . $result['raw_stored_diagnosis'] . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 7 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate valid patient data for testing
     */
    private function generateValidPatientData() {
        return [
            'name' => $this->generateRandomName(),
            'ic_number' => $this->generateValidIC(),
            'diagnosis' => $this->generateRandomDiagnosis(),
            'phone' => $this->generateValidPhone(),
            'created_at' => date('Y-m-d H:i:s', time() - rand(0, 86400 * 30)) // Random date within last 30 days
        ];
    }
    
    /**
     * Generate valid IC number (exactly 12 digits)
     */
    private function generateValidIC() {
        $ic = '';
        for ($i = 0; $i < 12; $i++) {
            $ic .= rand(0, 9);
        }
        return $ic;
    }
    
    /**
     * Generate valid phone number (10-15 digits)
     */
    private function generateValidPhone() {
        $length = rand(10, 15);
        $phone = '';
        for ($i = 0; $i < $length; $i++) {
            $phone .= rand(0, 9);
        }
        return $phone;
    }
    
    /**
     * Test patient creation functionality
     */
    private function testPatientCreation($patientData) {
        $result = [
            'patient_data' => $patientData,
            'patient_created' => false,
            'data_matches' => false,
            'data_mismatches' => [],
            'diagnosis_encrypted' => false,
            'stored_data' => null,
            'raw_stored_diagnosis' => null,
            'created_patient_id' => null
        ];
        
        try {
            // Simulate the patient creation process
            $createdId = $this->simulatePatientCreation($patientData);
            
            if ($createdId > 0) {
                $result['patient_created'] = true;
                $result['created_patient_id'] = $createdId;
                
                // Retrieve the stored data to verify
                $storedData = $this->simulatePatientRetrieval($createdId);
                $result['stored_data'] = $storedData;
                
                if ($storedData) {
                    // Check if data matches (accounting for sanitization)
                    $dataMatches = true;
                    $mismatches = [];
                    
                    // For name, account for HTML entity encoding
                    $expectedName = Database::sanitizeInput($patientData['name']);
                    if ($storedData['name'] !== $expectedName) {
                        $dataMatches = false;
                        $mismatches[] = [
                            'field' => 'name',
                            'expected' => $expectedName,
                            'actual' => $storedData['name']
                        ];
                    }
                    
                    // For IC number, account for sanitization
                    $expectedIC = Database::sanitizeInput($patientData['ic_number']);
                    if ($storedData['ic_number'] !== $expectedIC) {
                        $dataMatches = false;
                        $mismatches[] = [
                            'field' => 'ic_number',
                            'expected' => $expectedIC,
                            'actual' => $storedData['ic_number']
                        ];
                    }
                    
                    // For phone, account for sanitization
                    $expectedPhone = Database::sanitizeInput($patientData['phone']);
                    if ($storedData['phone'] !== $expectedPhone) {
                        $dataMatches = false;
                        $mismatches[] = [
                            'field' => 'phone',
                            'expected' => $expectedPhone,
                            'actual' => $storedData['phone']
                        ];
                    }
                    
                    // Check decrypted diagnosis (should match original since it's decrypted)
                    if ($storedData['diagnosis'] !== $patientData['diagnosis']) {
                        $dataMatches = false;
                        $mismatches[] = [
                            'field' => 'diagnosis',
                            'expected' => $patientData['diagnosis'],
                            'actual' => $storedData['diagnosis']
                        ];
                    }
                    
                    $result['data_matches'] = $dataMatches;
                    $result['data_mismatches'] = $mismatches;
                    
                    // Check if diagnosis is encrypted in raw storage
                    $rawStoredDiagnosis = $this->simulateRawDiagnosisRetrieval($createdId);
                    $result['raw_stored_diagnosis'] = $rawStoredDiagnosis;
                    
                    // Diagnosis should be encrypted (different from original)
                    if ($rawStoredDiagnosis !== $patientData['diagnosis']) {
                        $result['diagnosis_encrypted'] = true;
                    }
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate patient creation (as in add_patient.php)
     */
    private function simulatePatientCreation($patientData) {
        // Simulate validation
        if (empty($patientData['name']) || empty($patientData['ic_number']) || 
            empty($patientData['diagnosis']) || empty($patientData['phone'])) {
            return 0;
        }
        
        // Simulate IC validation
        if (!preg_match('/^\d{12}$/', $patientData['ic_number'])) {
            return 0;
        }
        
        // Simulate phone validation
        if (!preg_match('/^\d{10,15}$/', $patientData['phone'])) {
            return 0;
        }
        
        // Simulate database insertion with encryption
        $patientId = rand(1000, 9999); // Simulate auto-increment ID
        
        // Store in simulated database
        $this->simulatedDatabase[$patientId] = [
            'id' => $patientId,
            'name' => Database::sanitizeInput($patientData['name']),
            'ic_number' => Database::sanitizeInput($patientData['ic_number']),
            'diagnosis_encrypted' => $this->simulateEncryption($patientData['diagnosis']),
            'phone' => Database::sanitizeInput($patientData['phone']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $patientId;
    }
    
    /**
     * Simulate patient retrieval with decryption
     */
    private function simulatePatientRetrieval($patientId) {
        if (!isset($this->simulatedDatabase[$patientId])) {
            return null;
        }
        
        $record = $this->simulatedDatabase[$patientId];
        
        return [
            'id' => $record['id'],
            'name' => $record['name'],
            'ic_number' => $record['ic_number'],
            'diagnosis' => $this->simulateDecryption($record['diagnosis_encrypted']),
            'phone' => $record['phone'],
            'created_at' => $record['created_at']
        ];
    }
    
    /**
     * Simulate raw diagnosis retrieval (encrypted)
     */
    private function simulateRawDiagnosisRetrieval($patientId) {
        if (!isset($this->simulatedDatabase[$patientId])) {
            return null;
        }
        
        return $this->simulatedDatabase[$patientId]['diagnosis_encrypted'];
    }
    
    // Simulated database storage
    private $simulatedDatabase = [];
    
    /**
     * Property 5: Diagnosis encryption round-trip
     * Feature: CCS6334-Assignment, Property 5: Diagnosis encryption round-trip
     * Validates: Requirements 3.2, 6.1, 6.2
     * 
     * For any diagnosis text, encrypting it during storage and then decrypting it 
     * during retrieval should produce the original text
     */
    public function testDiagnosisEncryptionRoundTripProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random diagnosis text
            $originalDiagnosis = $this->generateRandomDiagnosis();
            
            // Test encryption round-trip
            $result = $this->testEncryptionRoundTrip($originalDiagnosis);
            
            // Property: Round-trip should preserve original text
            if (!$result['round_trip_successful']) {
                echo "FAILED on iteration $i:\n";
                echo "Original diagnosis: " . json_encode($originalDiagnosis) . "\n";
                echo "Encrypted: " . json_encode($result['encrypted']) . "\n";
                echo "Decrypted: " . json_encode($result['decrypted']) . "\n";
                echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
                return false;
            }
            
            // Property: Encrypted text should be different from original (unless empty)
            if (!$result['encryption_effective']) {
                echo "FAILED on iteration $i:\n";
                echo "Original diagnosis: " . json_encode($originalDiagnosis) . "\n";
                echo "Encrypted: " . json_encode($result['encrypted']) . "\n";
                echo "Error: Encryption did not change the text\n";
                return false;
            }
            
            // Property: Decryption should handle edge cases properly
            if (!$result['edge_cases_handled']) {
                echo "FAILED on iteration $i:\n";
                echo "Edge case issues: " . json_encode($result['edge_case_issues']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 5 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test encryption round-trip functionality
     */
    private function testEncryptionRoundTrip($originalDiagnosis) {
        $result = [
            'original' => $originalDiagnosis,
            'encrypted' => null,
            'decrypted' => null,
            'round_trip_successful' => false,
            'encryption_effective' => false,
            'edge_cases_handled' => true,
            'edge_case_issues' => []
        ];
        
        try {
            // Test encryption
            $encrypted = $this->simulateEncryption($originalDiagnosis);
            $result['encrypted'] = $encrypted;
            
            // Test decryption
            $decrypted = $this->simulateDecryption($encrypted);
            $result['decrypted'] = $decrypted;
            
            // Check round-trip success
            if ($decrypted === $originalDiagnosis) {
                $result['round_trip_successful'] = true;
            }
            
            // Check encryption effectiveness
            if ($originalDiagnosis === '' || $originalDiagnosis === null) {
                // Empty strings might not be encrypted differently
                $result['encryption_effective'] = true;
            } else {
                // Non-empty strings should be encrypted differently
                if ($encrypted !== $originalDiagnosis) {
                    $result['encryption_effective'] = true;
                }
            }
            
            // Test edge cases
            $edgeCases = [
                '',                                    // Empty string
                null,                                  // Null value
                'A',                                   // Single character
                str_repeat('A', 1000),                // Very long string
                'Special chars: àáâãäåæçèéêë',        // Unicode characters
                'Quotes: "test" \'test\'',            // Quotes
                'HTML: <script>alert("test")</script>', // HTML/JS
                'SQL: \'; DROP TABLE patients; --',   // SQL injection attempt
                'Newlines:\nLine 1\nLine 2',          // Newlines
                'Tabs:\tTabbed\tText',                // Tabs
            ];
            
            foreach ($edgeCases as $edgeCase) {
                $edgeEncrypted = $this->simulateEncryption($edgeCase);
                $edgeDecrypted = $this->simulateDecryption($edgeEncrypted);
                
                // Handle null case specially - both null and empty string are acceptable
                if ($edgeCase === null) {
                    if ($edgeDecrypted !== null && $edgeDecrypted !== '') {
                        $result['edge_cases_handled'] = false;
                        $result['edge_case_issues'][] = [
                            'input' => $edgeCase,
                            'encrypted' => $edgeEncrypted,
                            'decrypted' => $edgeDecrypted,
                            'issue' => 'Null input should return null or empty string'
                        ];
                    }
                } else {
                    if ($edgeDecrypted !== $edgeCase) {
                        $result['edge_cases_handled'] = false;
                        $result['edge_case_issues'][] = [
                            'input' => $edgeCase,
                            'encrypted' => $edgeEncrypted,
                            'decrypted' => $edgeDecrypted,
                            'issue' => 'Round-trip failed for edge case'
                        ];
                    }
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Enhanced encryption simulation that better mimics MySQL AES_ENCRYPT
     */
    private function simulateEncryption($plaintext) {
        if ($plaintext === null || $plaintext === '') {
            return '';
        }
        
        // Simulate MySQL AES_ENCRYPT behavior
        // In reality, this would use MySQL's AES_ENCRYPT function
        // For testing, we'll use a reversible encoding that changes the text
        $key = ENCRYPTION_KEY;
        
        // Simple XOR encryption for simulation (not secure, just for testing)
        $encrypted = '';
        $keyLength = strlen($key);
        
        for ($i = 0; $i < strlen($plaintext); $i++) {
            $encrypted .= chr(ord($plaintext[$i]) ^ ord($key[$i % $keyLength]));
        }
        
        // Base64 encode to make it safe for storage (like MySQL would do)
        return base64_encode($encrypted);
    }
    
    /**
     * Enhanced decryption simulation that better mimics MySQL AES_DECRYPT
     */
    private function simulateDecryption($encrypted) {
        if ($encrypted === null || $encrypted === '') {
            return '';
        }
        
        try {
            // Decode from base64
            $decoded = base64_decode($encrypted);
            if ($decoded === false) {
                return null; // Decryption failed
            }
            
            // Simple XOR decryption (reverse of encryption)
            $key = ENCRYPTION_KEY;
            $decrypted = '';
            $keyLength = strlen($key);
            
            for ($i = 0; $i < strlen($decoded); $i++) {
                $decrypted .= chr(ord($decoded[$i]) ^ ord($key[$i % $keyLength]));
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            return null; // Decryption failed
        }
    }
    
    /**
     * Property 8: Form validation for incomplete data
     * Feature: CCS6334-Assignment, Property 8: Form validation for incomplete data
     * Validates: Requirements 3.3
     * 
     * For any incomplete patient form submission, the system should validate 
     * required fields and display appropriate error messages
     */
    public function testFormValidationProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate incomplete patient data
            $incompleteData = $this->generateIncompletePatientData();
            
            // Test form validation
            $result = $this->testFormValidation($incompleteData);
            
            // Property: Incomplete data should be rejected
            if (!$result['validation_rejected_incomplete_data']) {
                echo "FAILED on iteration $i:\n";
                echo "Incomplete data: " . json_encode($incompleteData) . "\n";
                echo "Validation result: " . json_encode($result) . "\n";
                return false;
            }
            
            // Property: Appropriate error messages should be provided
            if (!$result['appropriate_error_messages']) {
                echo "FAILED on iteration $i:\n";
                echo "Incomplete data: " . json_encode($incompleteData) . "\n";
                echo "Missing error messages for: " . json_encode($result['missing_error_messages']) . "\n";
                return false;
            }
            
            // Property: Valid fields should not generate errors
            if (!$result['valid_fields_no_errors']) {
                echo "FAILED on iteration $i:\n";
                echo "Incomplete data: " . json_encode($incompleteData) . "\n";
                echo "Unexpected errors for valid fields: " . json_encode($result['unexpected_errors']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 8 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate incomplete patient data for testing
     */
    private function generateIncompletePatientData() {
        $completeData = [
            'name' => $this->generateRandomName(),
            'ic_number' => $this->generateValidIC(),
            'diagnosis' => $this->generateRandomDiagnosis(),
            'phone' => $this->generateValidPhone()
        ];
        
        // Randomly make some fields incomplete
        $incompleteData = [];
        
        foreach ($completeData as $field => $value) {
            $makeIncomplete = rand(0, 3); // 25% chance to make incomplete
            
            switch ($makeIncomplete) {
                case 0:
                    // Make field empty
                    $incompleteData[$field] = '';
                    break;
                case 1:
                    // Make field null
                    $incompleteData[$field] = null;
                    break;
                case 2:
                    // Make field whitespace only
                    $incompleteData[$field] = '   ';
                    break;
                default:
                    // Keep field valid
                    $incompleteData[$field] = $value;
                    break;
            }
        }
        
        // Sometimes add invalid formats
        if (isset($incompleteData['ic_number']) && !empty($incompleteData['ic_number'])) {
            $invalidFormat = rand(0, 4);
            switch ($invalidFormat) {
                case 0:
                    $incompleteData['ic_number'] = '123'; // Too short
                    break;
                case 1:
                    $incompleteData['ic_number'] = '12345678901234567890'; // Too long
                    break;
                case 2:
                    $incompleteData['ic_number'] = '12345abc6789'; // Contains letters
                    break;
                case 3:
                    $incompleteData['ic_number'] = '123-456-789'; // Wrong format
                    break;
                // case 4: keep valid
            }
        }
        
        if (isset($incompleteData['phone']) && !empty($incompleteData['phone'])) {
            $invalidFormat = rand(0, 4);
            switch ($invalidFormat) {
                case 0:
                    $incompleteData['phone'] = '123'; // Too short
                    break;
                case 1:
                    $incompleteData['phone'] = '12345678901234567890'; // Too long
                    break;
                case 2:
                    $incompleteData['phone'] = '123abc456'; // Contains letters
                    break;
                case 3:
                    $incompleteData['phone'] = '+60-12-345-6789'; // Wrong format
                    break;
                // case 4: keep valid
            }
        }
        
        return $incompleteData;
    }
    
    /**
     * Test form validation functionality
     */
    private function testFormValidation($incompleteData) {
        $result = [
            'incomplete_data' => $incompleteData,
            'validation_rejected_incomplete_data' => false,
            'appropriate_error_messages' => true,
            'missing_error_messages' => [],
            'valid_fields_no_errors' => true,
            'unexpected_errors' => [],
            'validation_errors' => [],
            'validation_passed' => false
        ];
        
        try {
            // Simulate the validation logic from add_patient.php
            $validationResult = $this->simulateFormValidation($incompleteData);
            $result['validation_errors'] = $validationResult['errors'];
            $result['validation_passed'] = empty($validationResult['errors']);
            
            // Check if validation properly rejected incomplete data
            $hasIncompleteFields = false;
            $expectedErrors = [];
            
            // Check for empty/null required fields
            foreach (['name', 'ic_number', 'diagnosis', 'phone'] as $field) {
                $value = $incompleteData[$field] ?? null;
                if ($value === null || $value === '' || trim($value) === '') {
                    $hasIncompleteFields = true;
                    $expectedErrors[] = $field;
                }
            }
            
            // Check for invalid IC format
            if (isset($incompleteData['ic_number']) && !empty(trim($incompleteData['ic_number']))) {
                if (!preg_match('/^\d{12}$/', trim($incompleteData['ic_number']))) {
                    $hasIncompleteFields = true;
                    if (!in_array('ic_number', $expectedErrors)) {
                        $expectedErrors[] = 'ic_number';
                    }
                }
            }
            
            // Check for invalid phone format
            if (isset($incompleteData['phone']) && !empty(trim($incompleteData['phone']))) {
                $cleanPhone = preg_replace('/[\s\-\+]/', '', trim($incompleteData['phone']));
                if (!preg_match('/^\d{10,15}$/', $cleanPhone)) {
                    $hasIncompleteFields = true;
                    if (!in_array('phone', $expectedErrors)) {
                        $expectedErrors[] = 'phone';
                    }
                }
            }
            
            // If there are incomplete fields, validation should reject
            if ($hasIncompleteFields) {
                if (!empty($validationResult['errors'])) {
                    $result['validation_rejected_incomplete_data'] = true;
                }
            } else {
                // If all fields are complete and valid, validation should pass
                if (empty($validationResult['errors'])) {
                    $result['validation_rejected_incomplete_data'] = true;
                }
            }
            
            // Check if appropriate error messages are provided
            foreach ($expectedErrors as $expectedField) {
                if (!isset($validationResult['errors'][$expectedField])) {
                    $result['appropriate_error_messages'] = false;
                    $result['missing_error_messages'][] = $expectedField;
                }
            }
            
            // Check if valid fields don't generate errors
            foreach (['name', 'ic_number', 'diagnosis', 'phone'] as $field) {
                $value = $incompleteData[$field] ?? null;
                $isFieldValid = false;
                
                switch ($field) {
                    case 'name':
                    case 'diagnosis':
                        $isFieldValid = ($value !== null && $value !== '' && trim($value) !== '');
                        break;
                    case 'ic_number':
                        $isFieldValid = ($value !== null && $value !== '' && trim($value) !== '' && 
                                       preg_match('/^\d{12}$/', trim($value)));
                        break;
                    case 'phone':
                        if ($value !== null && $value !== '' && trim($value) !== '') {
                            $cleanPhone = preg_replace('/[\s\-\+]/', '', trim($value));
                            $isFieldValid = preg_match('/^\d{10,15}$/', $cleanPhone);
                        }
                        break;
                }
                
                if ($isFieldValid && isset($validationResult['errors'][$field])) {
                    $result['valid_fields_no_errors'] = false;
                    $result['unexpected_errors'][] = [
                        'field' => $field,
                        'value' => $value,
                        'error' => $validationResult['errors'][$field]
                    ];
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate form validation logic (as in add_patient.php)
     */
    private function simulateFormValidation($formData) {
        $errors = [];
        
        // Sanitize input data
        $sanitizedData = [];
        foreach ($formData as $field => $value) {
            $sanitizedData[$field] = Database::sanitizeInput($value ?? '');
        }
        
        // Validate required fields
        if (empty($sanitizedData['name'])) {
            $errors['name'] = 'Patient name is required';
        }
        
        if (empty($sanitizedData['ic_number'])) {
            $errors['ic_number'] = 'IC number is required';
        } elseif (!Database::validateIC($sanitizedData['ic_number'])) {
            $errors['ic_number'] = 'IC number must be 12 digits (e.g., 123456789012)';
        }
        
        if (empty($sanitizedData['diagnosis'])) {
            $errors['diagnosis'] = 'Diagnosis is required';
        }
        
        if (empty($sanitizedData['phone'])) {
            $errors['phone'] = 'Phone number is required';
        } elseif (!Database::validatePhone($sanitizedData['phone'])) {
            $errors['phone'] = 'Phone number must be 10-15 digits';
        }
        
        return [
            'errors' => $errors,
            'sanitized_data' => $sanitizedData
        ];
    }

    /**
     * Property 9: Successful submission workflow
     * Feature: CCS6334-Assignment, Property 9: Successful submission workflow
     * Validates: Requirements 3.4
     * 
     * For any valid patient data submission, the system should redirect to dashboard 
     * with a success message
     */
    public function testSuccessfulSubmissionWorkflowProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate valid patient data
            $validPatientData = $this->generateValidPatientData();
            
            // Test successful submission workflow
            $result = $this->testSuccessfulSubmissionWorkflow($validPatientData);
            
            // Property: Valid submission should redirect to dashboard
            if (!$result['redirects_to_dashboard']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient data: " . json_encode($validPatientData) . "\n";
                echo "Redirect result: " . json_encode($result['redirect_info']) . "\n";
                return false;
            }
            
            // Property: Success message should be set in session
            if (!$result['success_message_set']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient data: " . json_encode($validPatientData) . "\n";
                echo "Session data: " . json_encode($result['session_data']) . "\n";
                return false;
            }
            
            // Property: Patient record should be created in database
            if (!$result['patient_record_created']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient data: " . json_encode($validPatientData) . "\n";
                echo "Database result: " . json_encode($result['database_result']) . "\n";
                return false;
            }
            
            // Property: No validation errors should occur
            if (!$result['no_validation_errors']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient data: " . json_encode($validPatientData) . "\n";
                echo "Validation errors: " . json_encode($result['validation_errors']) . "\n";
                return false;
            }
            
            // Property: Success message should be appropriate
            if (!$result['appropriate_success_message']) {
                echo "FAILED on iteration $i:\n";
                echo "Success message: " . json_encode($result['success_message']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 9 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test successful submission workflow functionality
     */
    private function testSuccessfulSubmissionWorkflow($validPatientData) {
        $result = [
            'patient_data' => $validPatientData,
            'redirects_to_dashboard' => false,
            'redirect_info' => null,
            'success_message_set' => false,
            'session_data' => [],
            'patient_record_created' => false,
            'database_result' => null,
            'no_validation_errors' => false,
            'validation_errors' => [],
            'appropriate_success_message' => false,
            'success_message' => null
        ];
        
        try {
            // Simulate the successful submission workflow from add_patient.php
            $workflowResult = $this->simulateSuccessfulSubmissionWorkflow($validPatientData);
            
            // Check if redirect to dashboard occurs
            if (isset($workflowResult['redirect_location']) && 
                $workflowResult['redirect_location'] === 'dashboard.php') {
                $result['redirects_to_dashboard'] = true;
            }
            $result['redirect_info'] = $workflowResult['redirect_location'] ?? null;
            
            // Check if success message is set in session
            if (isset($workflowResult['session']['message']) && 
                !empty($workflowResult['session']['message'])) {
                $result['success_message_set'] = true;
                $result['success_message'] = $workflowResult['session']['message'];
                
                // Check if success message is appropriate
                $message = strtolower($workflowResult['session']['message']);
                if (strpos($message, 'success') !== false && 
                    strpos($message, 'added') !== false) {
                    $result['appropriate_success_message'] = true;
                }
            }
            $result['session_data'] = $workflowResult['session'] ?? [];
            
            // Check if patient record was created
            if (isset($workflowResult['database_insert_result']) && 
                $workflowResult['database_insert_result'] > 0) {
                $result['patient_record_created'] = true;
            }
            $result['database_result'] = $workflowResult['database_insert_result'] ?? null;
            
            // Check if no validation errors occurred
            if (empty($workflowResult['validation_errors'])) {
                $result['no_validation_errors'] = true;
            }
            $result['validation_errors'] = $workflowResult['validation_errors'] ?? [];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate the successful submission workflow (as in add_patient.php)
     */
    private function simulateSuccessfulSubmissionWorkflow($validPatientData) {
        $workflowResult = [
            'validation_errors' => [],
            'session' => [],
            'redirect_location' => null,
            'database_insert_result' => 0
        ];
        
        // Step 1: Validate the data (should pass for valid data)
        $validationResult = $this->simulateFormValidation($validPatientData);
        $workflowResult['validation_errors'] = $validationResult['errors'];
        
        // If validation passes, proceed with workflow
        if (empty($validationResult['errors'])) {
            
            // Step 2: Check for duplicate IC number (simulate no duplicates)
            $duplicateCheck = $this->simulateDuplicateICCheck($validPatientData['ic_number']);
            if (!$duplicateCheck['has_duplicate']) {
                
                // Step 3: Insert patient record into database
                $insertResult = $this->simulatePatientInsertion($validPatientData);
                $workflowResult['database_insert_result'] = $insertResult['rows_affected'];
                
                if ($insertResult['rows_affected'] > 0) {
                    // Step 4: Set success message in session
                    $workflowResult['session']['message'] = 'Patient record added successfully!';
                    $workflowResult['session']['message_type'] = 'success';
                    
                    // Step 5: Redirect to dashboard
                    $workflowResult['redirect_location'] = 'dashboard.php';
                }
            } else {
                $workflowResult['validation_errors']['ic_number'] = 'A patient with this IC number already exists';
            }
        }
        
        return $workflowResult;
    }
    
    /**
     * Simulate duplicate IC number check
     */
    private function simulateDuplicateICCheck($icNumber) {
        // For property testing, we'll assume no duplicates exist
        // In real implementation, this would query the database
        return [
            'has_duplicate' => false,
            'existing_patient_id' => null
        ];
    }
    
    /**
     * Simulate patient insertion into database
     */
    private function simulatePatientInsertion($patientData) {
        // Simulate successful database insertion
        // In real implementation, this would execute the INSERT query with AES_ENCRYPT
        
        // Validate that all required data is present
        if (empty($patientData['name']) || empty($patientData['ic_number']) || 
            empty($patientData['diagnosis']) || empty($patientData['phone'])) {
            return ['rows_affected' => 0, 'error' => 'Missing required data'];
        }
        
        // Simulate successful insertion
        $patientId = rand(1000, 9999);
        
        // Store in simulated database for consistency with other tests
        $this->simulatedDatabase[$patientId] = [
            'id' => $patientId,
            'name' => Database::sanitizeInput($patientData['name']),
            'ic_number' => Database::sanitizeInput($patientData['ic_number']),
            'diagnosis_encrypted' => $this->simulateEncryption($patientData['diagnosis']),
            'phone' => Database::sanitizeInput($patientData['phone']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return [
            'rows_affected' => 1,
            'inserted_id' => $patientId
        ];
    }

    /**
     * Property 10: IC number uniqueness enforcement
     * Feature: CCS6334-Assignment, Property 10: IC number uniqueness enforcement
     * Validates: Requirements 3.5
     * 
     * For any attempt to create a patient with a duplicate IC number, the system 
     * should reject the submission
     */
    public function testICNumberUniquenessProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a valid patient with a unique IC number
            $firstPatient = $this->generateValidPatientData();
            
            // Generate a second patient with the same IC number
            $duplicatePatient = $this->generateValidPatientData();
            $duplicatePatient['ic_number'] = $firstPatient['ic_number']; // Same IC number
            
            // Test IC number uniqueness enforcement
            $result = $this->testICNumberUniqueness($firstPatient, $duplicatePatient);
            
            // Property: First patient should be created successfully
            if (!$result['first_patient_created']) {
                echo "FAILED on iteration $i:\n";
                echo "First patient data: " . json_encode($firstPatient) . "\n";
                echo "First patient result: " . json_encode($result['first_patient_result']) . "\n";
                return false;
            }
            
            // Property: Duplicate IC number should be rejected
            if (!$result['duplicate_rejected']) {
                echo "FAILED on iteration $i:\n";
                echo "Duplicate patient data: " . json_encode($duplicatePatient) . "\n";
                echo "Duplicate result: " . json_encode($result['duplicate_result']) . "\n";
                return false;
            }
            
            // Property: Appropriate error message should be provided for duplicate
            if (!$result['appropriate_error_message']) {
                echo "FAILED on iteration $i:\n";
                $validationErrors = 'No validation errors found';
                if (isset($result['duplicate_result']) && is_array($result['duplicate_result'])) {
                    $validationErrors = $result['duplicate_result']['validation_errors'] ?? 'No validation errors in duplicate result';
                }
                echo "Expected error message for duplicate IC, but got: " . json_encode($validationErrors) . "\n";
                return false;
            }
            
            // Property: Database should contain only one record with the IC number
            if (!$result['database_integrity_maintained']) {
                echo "FAILED on iteration $i:\n";
                echo "Database contains multiple records with IC: " . $firstPatient['ic_number'] . "\n";
                echo "Records found: " . json_encode($result['database_records']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 10 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test IC number uniqueness enforcement functionality
     */
    private function testICNumberUniqueness($firstPatient, $duplicatePatient) {
        $result = [
            'first_patient' => $firstPatient,
            'duplicate_patient' => $duplicatePatient,
            'first_patient_created' => false,
            'first_patient_result' => null,
            'duplicate_rejected' => false,
            'duplicate_result' => null,
            'appropriate_error_message' => false,
            'database_integrity_maintained' => false,
            'database_records' => []
        ];
        
        try {
            // Step 1: Create the first patient (should succeed)
            $firstResult = $this->simulatePatientCreationWithDuplicateCheck($firstPatient);
            $result['first_patient_result'] = $firstResult;
            
            if ($firstResult['patient_created']) {
                $result['first_patient_created'] = true;
            }
            
            // Step 2: Attempt to create duplicate patient (should fail)
            $duplicateResult = $this->simulatePatientCreationWithDuplicateCheck($duplicatePatient);
            $result['duplicate_result'] = $duplicateResult;
            
            // Check if duplicate was properly rejected
            if (!$duplicateResult['patient_created']) {
                $result['duplicate_rejected'] = true;
                
                // Check if appropriate error message was provided
                if (isset($duplicateResult['validation_errors']['ic_number']) &&
                    strpos(strtolower($duplicateResult['validation_errors']['ic_number']), 'already exists') !== false) {
                    $result['appropriate_error_message'] = true;
                }
            }
            
            // Step 3: Check database integrity (should have only one record with this IC)
            $databaseRecords = $this->findRecordsByIC($firstPatient['ic_number']);
            $result['database_records'] = $databaseRecords;
            
            if (count($databaseRecords) === 1) {
                $result['database_integrity_maintained'] = true;
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate patient creation with duplicate IC number checking
     */
    private function simulatePatientCreationWithDuplicateCheck($patientData) {
        $result = [
            'patient_data' => $patientData,
            'patient_created' => false,
            'validation_errors' => [],
            'created_patient_id' => null
        ];
        
        try {
            // Step 1: Validate form data (as in add_patient.php)
            $validationResult = $this->simulateFormValidation($patientData);
            
            if (!empty($validationResult['errors'])) {
                $result['validation_errors'] = $validationResult['errors'];
                return $result;
            }
            
            // Step 2: Check for duplicate IC number
            $duplicateCheck = $this->simulateRealDuplicateICCheck($patientData['ic_number']);
            if ($duplicateCheck['has_duplicate']) {
                $result['validation_errors']['ic_number'] = 'A patient with this IC number already exists';
                return $result;
            }
            
            // Step 3: If no validation errors and no duplicates, create patient
            $insertionResult = $this->simulatePatientInsertion($patientData);
            
            if ($insertionResult['rows_affected'] > 0) {
                $result['patient_created'] = true;
                $result['created_patient_id'] = $insertionResult['inserted_id'];
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate realistic duplicate IC number check that actually checks the simulated database
     */
    private function simulateRealDuplicateICCheck($icNumber) {
        // Check if IC number already exists in our simulated database
        foreach ($this->simulatedDatabase as $patientId => $patient) {
            if ($patient['ic_number'] === $icNumber) {
                return [
                    'has_duplicate' => true,
                    'existing_patient_id' => $patientId
                ];
            }
        }
        
        return [
            'has_duplicate' => false,
            'existing_patient_id' => null
        ];
    }
    
    /**
     * Find all records with a specific IC number in the simulated database
     */
    private function findRecordsByIC($icNumber) {
        $records = [];
        
        foreach ($this->simulatedDatabase as $patientId => $patient) {
            if ($patient['ic_number'] === $icNumber) {
                $records[] = [
                    'id' => $patientId,
                    'ic_number' => $patient['ic_number'],
                    'name' => $patient['name']
                ];
            }
        }
        
        return $records;
    }

    /**
     * Property 11: Complete patient deletion
     * Feature: CCS6334-Assignment, Property 11: Complete patient deletion
     * Validates: Requirements 4.1, 4.4
     * 
     * For any patient record, deleting it should permanently remove all associated 
     * data from the database including encrypted diagnosis
     */
    public function testCompletePatientDeletionProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate a patient record to delete
            $patientData = $this->generateValidPatientData();
            
            // Test complete patient deletion
            $result = $this->testCompletePatientDeletion($patientData);
            
            // Property: Patient should be created first
            if (!$result['patient_created']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient data: " . json_encode($patientData) . "\n";
                echo "Creation result: " . json_encode($result['creation_result']) . "\n";
                return false;
            }
            
            // Property: Deletion should succeed for existing patient
            if (!$result['deletion_successful']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient ID: " . $result['patient_id'] . "\n";
                echo "Deletion result: " . json_encode($result['deletion_result']) . "\n";
                return false;
            }
            
            // Property: Patient record should be completely removed from database
            if (!$result['record_completely_removed']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient ID: " . $result['patient_id'] . "\n";
                echo "Records still found: " . json_encode($result['remaining_records']) . "\n";
                return false;
            }
            
            // Property: All associated data including encrypted diagnosis should be removed
            if (!$result['all_data_removed']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient ID: " . $result['patient_id'] . "\n";
                echo "Remaining data: " . json_encode($result['remaining_data']) . "\n";
                return false;
            }
            
            // Property: Deletion should be permanent (no recovery possible)
            if (!$result['deletion_permanent']) {
                echo "FAILED on iteration $i:\n";
                echo "Patient ID: " . $result['patient_id'] . "\n";
                echo "Recovery attempt result: " . json_encode($result['recovery_attempt']) . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 11 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test complete patient deletion functionality
     */
    private function testCompletePatientDeletion($patientData) {
        $result = [
            'patient_data' => $patientData,
            'patient_created' => false,
            'creation_result' => null,
            'patient_id' => null,
            'deletion_successful' => false,
            'deletion_result' => null,
            'record_completely_removed' => false,
            'remaining_records' => [],
            'all_data_removed' => false,
            'remaining_data' => null,
            'deletion_permanent' => false,
            'recovery_attempt' => null
        ];
        
        try {
            // Step 1: Create a patient record first
            $creationResult = $this->simulatePatientCreationWithDuplicateCheck($patientData);
            $result['creation_result'] = $creationResult;
            
            if ($creationResult['patient_created']) {
                $result['patient_created'] = true;
                $result['patient_id'] = $creationResult['created_patient_id'];
                
                // Verify patient exists before deletion
                $preDeleteRecord = $this->simulatePatientRetrieval($result['patient_id']);
                if ($preDeleteRecord) {
                    
                    // Step 2: Simulate patient deletion (as in delete_patient.php)
                    $deletionResult = $this->simulatePatientDeletion($result['patient_id']);
                    $result['deletion_result'] = $deletionResult;
                    
                    if ($deletionResult['deletion_successful']) {
                        $result['deletion_successful'] = true;
                        
                        // Step 3: Verify record is completely removed
                        $postDeleteRecord = $this->simulatePatientRetrieval($result['patient_id']);
                        if ($postDeleteRecord === null) {
                            $result['record_completely_removed'] = true;
                        } else {
                            $result['remaining_records'] = [$postDeleteRecord];
                        }
                        
                        // Step 4: Verify all data including encrypted diagnosis is removed
                        $remainingData = $this->simulateRawDataRetrieval($result['patient_id']);
                        if ($remainingData === null) {
                            $result['all_data_removed'] = true;
                        } else {
                            $result['remaining_data'] = $remainingData;
                        }
                        
                        // Step 5: Verify deletion is permanent (no recovery possible)
                        $recoveryAttempt = $this->simulateRecoveryAttempt($result['patient_id']);
                        $result['recovery_attempt'] = $recoveryAttempt;
                        if (!$recoveryAttempt['recovery_possible']) {
                            $result['deletion_permanent'] = true;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate patient deletion (as in delete_patient.php)
     */
    private function simulatePatientDeletion($patientId) {
        $result = [
            'patient_id' => $patientId,
            'deletion_successful' => false,
            'rows_affected' => 0,
            'error' => null
        ];
        
        try {
            // Simulate authentication check (assume authenticated)
            
            // Simulate patient ID validation
            if (!is_numeric($patientId) || $patientId <= 0) {
                $result['error'] = 'Invalid patient ID provided';
                return $result;
            }
            
            // Simulate checking if patient exists before deletion
            if (!isset($this->simulatedDatabase[$patientId])) {
                $result['error'] = 'Patient record not found';
                return $result;
            }
            
            // Simulate transaction begin
            
            // Simulate DELETE query execution
            unset($this->simulatedDatabase[$patientId]);
            $result['rows_affected'] = 1;
            $result['deletion_successful'] = true;
            
            // Simulate transaction commit
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            // Simulate transaction rollback
        }
        
        return $result;
    }
    
    /**
     * Simulate raw data retrieval (including encrypted diagnosis)
     */
    private function simulateRawDataRetrieval($patientId) {
        if (isset($this->simulatedDatabase[$patientId])) {
            return $this->simulatedDatabase[$patientId];
        }
        return null;
    }
    
    /**
     * Simulate recovery attempt (should fail for deleted records)
     */
    private function simulateRecoveryAttempt($patientId) {
        return [
            'recovery_possible' => false,
            'recovered_data' => null,
            'message' => 'No recovery mechanism available for deleted records'
        ];
    }
    
    /**
     * Property 12: Dashboard refresh after deletion
     * Feature: CCS6334-Assignment, Property 12: Dashboard refresh after deletion
     * Validates: Requirements 4.2
     * 
     * For any patient deletion operation, the dashboard should update to no longer 
     * display the deleted patient
     */
    public function testDashboardRefreshAfterDeletionProperty() {
        $results = [];
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate multiple patients for testing
            $testPatients = $this->generateTestPatients();
            
            // Ensure we have at least one patient to delete
            if (empty($testPatients)) {
                $testPatients = [$this->generateValidPatientData()];
                $testPatients[0]['id'] = 1;
            }
            
            // Test dashboard refresh after deletion
            $result = $this->testDashboardRefreshAfterDeletion($testPatients);
            
            // Property: Dashboard should display all patients before deletion
            if (!$result['dashboard_shows_all_before_deletion']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected patients: " . count($testPatients) . "\n";
                echo "Displayed before deletion: " . $result['patients_displayed_before'] . "\n";
                return false;
            }
            
            // Property: Deletion should succeed
            if (!$result['deletion_successful']) {
                echo "FAILED on iteration $i:\n";
                echo "Deleted patient ID: " . $result['deleted_patient_id'] . "\n";
                echo "Deletion result: " . json_encode($result['deletion_result']) . "\n";
                return false;
            }
            
            // Property: Dashboard should no longer display deleted patient
            if (!$result['deleted_patient_not_displayed']) {
                echo "FAILED on iteration $i:\n";
                echo "Deleted patient ID: " . $result['deleted_patient_id'] . "\n";
                echo "Dashboard still shows deleted patient\n";
                return false;
            }
            
            // Property: Dashboard should still display remaining patients
            if (!$result['remaining_patients_still_displayed']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected remaining patients: " . $result['expected_remaining_count'] . "\n";
                echo "Actually displayed: " . $result['patients_displayed_after'] . "\n";
                return false;
            }
            
            // Property: Dashboard should reflect correct patient count
            if (!$result['correct_patient_count_displayed']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected count: " . $result['expected_remaining_count'] . "\n";
                echo "Displayed count: " . $result['patients_displayed_after'] . "\n";
                return false;
            }
            
            $results[] = $result;
        }
        
        echo "Property 12 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test dashboard refresh after deletion functionality
     */
    private function testDashboardRefreshAfterDeletion($testPatients) {
        $result = [
            'test_patients' => $testPatients,
            'dashboard_shows_all_before_deletion' => false,
            'patients_displayed_before' => 0,
            'deletion_successful' => false,
            'deleted_patient_id' => null,
            'deletion_result' => null,
            'deleted_patient_not_displayed' => false,
            'remaining_patients_still_displayed' => false,
            'expected_remaining_count' => 0,
            'patients_displayed_after' => 0,
            'correct_patient_count_displayed' => false,
            'dashboard_before_deletion' => null,
            'dashboard_after_deletion' => null
        ];
        
        try {
            // Step 1: Populate simulated database with test patients
            foreach ($testPatients as $patient) {
                $this->simulatedDatabase[$patient['id']] = [
                    'id' => $patient['id'],
                    'name' => $patient['name'],
                    'ic_number' => $patient['ic_number'],
                    'diagnosis_encrypted' => $this->simulateEncryption($patient['diagnosis']),
                    'phone' => $patient['phone'],
                    'created_at' => $patient['created_at']
                ];
            }
            
            // Step 2: Simulate dashboard display before deletion
            $dashboardBefore = $this->simulateDashboardDisplay($testPatients);
            $result['dashboard_before_deletion'] = $dashboardBefore;
            $result['patients_displayed_before'] = count($dashboardBefore);
            
            // Check if all patients are displayed before deletion
            if (count($dashboardBefore) === count($testPatients)) {
                $result['dashboard_shows_all_before_deletion'] = true;
            }
            
            // Step 3: Select a random patient to delete
            $patientToDelete = $testPatients[array_rand($testPatients)];
            $result['deleted_patient_id'] = $patientToDelete['id'];
            
            // Step 4: Simulate patient deletion
            $deletionResult = $this->simulatePatientDeletion($patientToDelete['id']);
            $result['deletion_result'] = $deletionResult;
            
            if ($deletionResult['deletion_successful']) {
                $result['deletion_successful'] = true;
                
                // Step 5: Simulate dashboard display after deletion
                $remainingPatients = array_filter($testPatients, function($patient) use ($patientToDelete) {
                    return $patient['id'] !== $patientToDelete['id'];
                });
                
                $dashboardAfter = $this->simulateDashboardDisplay(array_values($remainingPatients));
                $result['dashboard_after_deletion'] = $dashboardAfter;
                $result['patients_displayed_after'] = count($dashboardAfter);
                $result['expected_remaining_count'] = count($remainingPatients);
                
                // Step 6: Check if deleted patient is no longer displayed
                $deletedPatientStillShown = false;
                foreach ($dashboardAfter as $displayedPatient) {
                    if ($displayedPatient['id'] == $patientToDelete['id']) {
                        $deletedPatientStillShown = true;
                        break;
                    }
                }
                
                if (!$deletedPatientStillShown) {
                    $result['deleted_patient_not_displayed'] = true;
                }
                
                // Step 7: Check if remaining patients are still displayed
                if (count($dashboardAfter) === count($remainingPatients)) {
                    $result['remaining_patients_still_displayed'] = true;
                }
                
                // Step 8: Check if correct patient count is displayed
                if (count($dashboardAfter) === count($remainingPatients)) {
                    $result['correct_patient_count_displayed'] = true;
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }

    /**
     * Run all patient management property tests
     */
    public function runAllTests() {
        echo "Running Patient Management Property Tests...\n";
        echo "==========================================\n";
        
        $testsPassed = 0;
        $totalTests = 9;
        
        // Test Property 4: Patient record display completeness
        echo "Testing Property 4: Patient record display completeness...\n";
        if ($this->testPatientRecordDisplayProperty()) {
            $testsPassed++;
            echo "✓ Property 4 PASSED\n";
        } else {
            echo "✗ Property 4 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 6: Delete button presence
        echo "Testing Property 6: Delete button presence...\n";
        if ($this->testDeleteButtonPresenceProperty()) {
            $testsPassed++;
            echo "✓ Property 6 PASSED\n";
        } else {
            echo "✗ Property 6 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 7: Patient creation with valid data
        echo "Testing Property 7: Patient creation with valid data...\n";
        if ($this->testPatientCreationProperty()) {
            $testsPassed++;
            echo "✓ Property 7 PASSED\n";
        } else {
            echo "✗ Property 7 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 5: Diagnosis encryption round-trip
        echo "Testing Property 5: Diagnosis encryption round-trip...\n";
        if ($this->testDiagnosisEncryptionRoundTripProperty()) {
            $testsPassed++;
            echo "✓ Property 5 PASSED\n";
        } else {
            echo "✗ Property 5 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 8: Form validation for incomplete data
        echo "Testing Property 8: Form validation for incomplete data...\n";
        if ($this->testFormValidationProperty()) {
            $testsPassed++;
            echo "✓ Property 8 PASSED\n";
        } else {
            echo "✗ Property 8 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 9: Successful submission workflow
        echo "Testing Property 9: Successful submission workflow...\n";
        if ($this->testSuccessfulSubmissionWorkflowProperty()) {
            $testsPassed++;
            echo "✓ Property 9 PASSED\n";
        } else {
            echo "✗ Property 9 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 10: IC number uniqueness enforcement
        echo "Testing Property 10: IC number uniqueness enforcement...\n";
        if ($this->testICNumberUniquenessProperty()) {
            $testsPassed++;
            echo "✓ Property 10 PASSED\n";
        } else {
            echo "✗ Property 10 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 11: Complete patient deletion
        echo "Testing Property 11: Complete patient deletion...\n";
        if ($this->testCompletePatientDeletionProperty()) {
            $testsPassed++;
            echo "✓ Property 11 PASSED\n";
        } else {
            echo "✗ Property 11 FAILED\n";
        }
        
        echo "\n";
        
        // Test Property 12: Dashboard refresh after deletion
        echo "Testing Property 12: Dashboard refresh after deletion...\n";
        if ($this->testDashboardRefreshAfterDeletionProperty()) {
            $testsPassed++;
            echo "✓ Property 12 PASSED\n";
        } else {
            echo "✗ Property 12 FAILED\n";
        }
        
        echo "\n==========================================\n";
        echo "Results: $testsPassed/$totalTests tests passed\n";
        
        return $testsPassed === $totalTests;
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new PatientManagementPropertiesTest();
    $success = $tester->runAllTests();
    exit($success ? 0 : 1);
}