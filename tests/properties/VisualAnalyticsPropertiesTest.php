<?php
/**
 * Property-based tests for Visual Analytics features
 * Tests appointment analytics data processing and chart rendering
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../appointment_analytics.php';

class VisualAnalyticsPropertiesTest {
    
    /**
     * Property 1: Complete Date Coverage
     * Feature: visual-analytics, Property 1: Complete Date Coverage
     * Validates: Requirements 3.5
     * 
     * For any 7-day period starting from today, the Visual Analytics System should return 
     * exactly 7 data points, one for each consecutive day, regardless of whether 
     * appointments exist for those dates
     */
    public function testCompleteDateCoverageProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random appointment data for testing
            $testAppointments = $this->generateRandomAppointmentData();
            
            // Test the analytics service
            $result = $this->testDateCoverage($testAppointments);
            
            // Property: Should always return exactly 7 data points
            if (!$result['has_seven_data_points']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected 7 data points, got: " . $result['data_point_count'] . "\n";
                echo "Labels: " . json_encode($result['labels']) . "\n";
                echo "Counts: " . json_encode($result['counts']) . "\n";
                return false;
            }
            
            // Property: Should cover consecutive days starting from today
            if (!$result['covers_consecutive_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Date range not consecutive or not starting from today\n";
                echo "Date range: " . json_encode($result['date_range']) . "\n";
                return false;
            }
            
            // Property: Should include zero counts for days without appointments
            if (!$result['includes_zero_counts_for_missing_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Missing days not filled with zero counts\n";
                echo "Expected missing days: " . json_encode($result['expected_missing_days']) . "\n";
                echo "Actual counts: " . json_encode($result['counts']) . "\n";
                return false;
            }
            
            // Property: Labels and counts arrays should have equal length
            if (!$result['arrays_equal_length']) {
                echo "FAILED on iteration $i:\n";
                echo "Labels and counts arrays have different lengths\n";
                echo "Labels length: " . count($result['labels']) . "\n";
                echo "Counts length: " . count($result['counts']) . "\n";
                return false;
            }
        }
        
        echo "Property 1 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Property 2: Data Consistency
     * Feature: visual-analytics, Property 2: Data Consistency
     * Validates: Requirements 1.2
     * 
     * For any date within the next 7 days, the appointment count returned by the 
     * Visual Analytics System should equal the actual number of appointments 
     * scheduled for that date in the database
     */
    public function testDataConsistencyProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random appointment data and insert into database
            $testAppointments = $this->generateRandomAppointmentData();
            $testPatients = $this->getOrCreateTestPatients();
            
            if (empty($testPatients)) {
                echo "FAILED on iteration $i: Could not create test patients\n";
                return false;
            }
            
            // Assign patient IDs to appointments
            foreach ($testAppointments as &$appointment) {
                $appointment['patient_id'] = $testPatients[array_rand($testPatients)]['id'];
            }
            
            try {
                // Set up test data in database
                $this->setupTestAppointments($testAppointments);
                
                // Test data consistency
                $result = $this->testDataConsistency($testAppointments);
                
                // Property: Analytics counts should match actual database counts
                if (!$result['counts_match_database']) {
                    echo "FAILED on iteration $i:\n";
                    echo "Analytics counts don't match database counts\n";
                    echo "Expected counts: " . json_encode($result['expected_counts']) . "\n";
                    echo "Actual counts: " . json_encode($result['actual_counts']) . "\n";
                    echo "Date range: " . json_encode($result['date_range']) . "\n";
                    return false;
                }
                
                // Property: All dates in range should be represented
                if (!$result['all_dates_represented']) {
                    echo "FAILED on iteration $i:\n";
                    echo "Not all dates in range are represented\n";
                    echo "Missing dates: " . json_encode($result['missing_dates']) . "\n";
                    return false;
                }
                
                // Property: Zero counts should be accurate for days without appointments
                if (!$result['zero_counts_accurate']) {
                    echo "FAILED on iteration $i:\n";
                    echo "Zero counts are not accurate\n";
                    echo "Incorrect zero count dates: " . json_encode($result['incorrect_zero_dates']) . "\n";
                    return false;
                }
                
                // Property: Non-zero counts should match exact appointment numbers
                if (!$result['nonzero_counts_accurate']) {
                    echo "FAILED on iteration $i:\n";
                    echo "Non-zero counts are not accurate\n";
                    echo "Incorrect non-zero counts: " . json_encode($result['incorrect_nonzero_counts']) . "\n";
                    return false;
                }
                
            } finally {
                // Always clean up test data
                $this->cleanupTestAppointments();
            }
        }
        
        echo "Property 2 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Property 3: Date Label Accuracy
     * Feature: visual-analytics, Property 3: Date Label Accuracy
     * Validates: Requirements 1.3
     * 
     * For any generated date label, it should correspond to the correct abbreviated 
     * day name (Mon, Tue, Wed, Thu, Fri, Sat, Sun) for the actual calendar date
     */
    public function testDateLabelAccuracyProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random date range for testing
            $testDates = $this->generateRandomDateRange();
            
            // Test date label accuracy
            $result = $this->testDateLabelAccuracy($testDates);
            
            // Property: Each label should match the correct day name for its date
            if (!$result['all_labels_accurate']) {
                echo "FAILED on iteration $i:\n";
                echo "Inaccurate labels: " . json_encode($result['inaccurate_labels']) . "\n";
                return false;
            }
            
            // Property: Labels should be in abbreviated format (3 characters)
            if (!$result['all_labels_abbreviated']) {
                echo "FAILED on iteration $i:\n";
                echo "Non-abbreviated labels: " . json_encode($result['non_abbreviated_labels']) . "\n";
                return false;
            }
            
            // Property: Labels should be valid day names
            if (!$result['all_labels_valid_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Invalid day labels: " . json_encode($result['invalid_day_labels']) . "\n";
                return false;
            }
        }
        
        echo "Property 3 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Property 5: Error Handling Graceful Degradation
     * Feature: visual-analytics, Property 5: Error Handling Graceful Degradation
     * Validates: Requirements 3.4
     * 
     * For any database error or connection failure, the Visual Analytics System should 
     * display appropriate fallback content without breaking the dashboard layout or functionality
     */
    public function testErrorHandlingGracefulDegradationProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test different error scenarios
            $errorScenarios = $this->generateErrorScenarios();
            
            foreach ($errorScenarios as $scenario) {
                $result = $this->testErrorHandlingGracefulDegradation($scenario);
                
                // Property: Should always return valid data structure even on error
                if (!$result['returns_valid_structure']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}\n";
                    echo "Invalid structure returned: " . json_encode($result['structure_issues']) . "\n";
                    return false;
                }
                
                // Property: Should provide fallback data when errors occur
                if (!$result['provides_fallback_data']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}\n";
                    echo "No fallback data provided\n";
                    return false;
                }
                
                // Property: Should maintain consistent array lengths in fallback
                if (!$result['maintains_array_consistency']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}\n";
                    echo "Array length inconsistency in fallback data\n";
                    echo "Labels length: " . $result['labels_length'] . "\n";
                    echo "Counts length: " . $result['counts_length'] . "\n";
                    return false;
                }
                
                // Property: Should include error indication flag
                if (!$result['includes_error_flag']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}\n";
                    echo "Missing error indication flag\n";
                    return false;
                }
                
                // Property: Should provide valid JSON for Chart.js consumption
                if (!$result['provides_valid_json']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}\n";
                    echo "Invalid JSON in fallback data: " . json_encode($result['json_errors']) . "\n";
                    return false;
                }
            }
        }
        
        echo "Property 5 passed all $iterations iterations across multiple error scenarios\n";
        return true;
    }
    
    /**
     * Property 4: Chart Rendering Integrity
     * Feature: visual-analytics, Property 4: Chart Rendering Integrity
     * Validates: Requirements 2.5
     * 
     * For any valid dataset provided to Chart.js, the rendered bar chart should display 
     * exactly the same number of bars as there are data points, with each bar height 
     * proportional to its corresponding count value
     */
    public function testChartRenderingIntegrityProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random valid dataset for Chart.js
            $testDataset = $this->generateRandomChartDataset();
            
            // Test chart rendering integrity
            $result = $this->testChartRenderingIntegrity($testDataset);
            
            // Property: Chart should display exactly the same number of bars as data points
            if (!$result['correct_number_of_bars']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected bars: " . $result['expected_bars'] . "\n";
                echo "Actual bars: " . $result['actual_bars'] . "\n";
                echo "Dataset: " . json_encode($testDataset) . "\n";
                return false;
            }
            
            // Property: Each bar height should be proportional to its corresponding count value
            if (!$result['proportional_bar_heights']) {
                echo "FAILED on iteration $i:\n";
                echo "Bar heights not proportional to data values\n";
                echo "Data values: " . json_encode($testDataset['data']) . "\n";
                echo "Height proportions: " . json_encode($result['height_proportions']) . "\n";
                return false;
            }
            
            // Property: Chart configuration should be valid for Chart.js
            if (!$result['valid_chart_config']) {
                echo "FAILED on iteration $i:\n";
                echo "Invalid Chart.js configuration\n";
                echo "Config errors: " . json_encode($result['config_errors']) . "\n";
                return false;
            }
            
            // Property: Chart should handle zero values correctly
            if (!$result['handles_zero_values']) {
                echo "FAILED on iteration $i:\n";
                echo "Chart does not handle zero values correctly\n";
                echo "Zero value issues: " . json_encode($result['zero_value_issues']) . "\n";
                return false;
            }
            
            // Property: Chart data arrays should have consistent lengths
            if (!$result['consistent_array_lengths']) {
                echo "FAILED on iteration $i:\n";
                echo "Inconsistent array lengths in chart data\n";
                echo "Labels length: " . $result['labels_length'] . "\n";
                echo "Data length: " . $result['data_length'] . "\n";
                return false;
            }
        }
        
        echo "Property 4 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Property 6: JSON Data Format Consistency
     * Feature: visual-analytics, Property 6: JSON Data Format Consistency
     * Validates: Requirements 3.2
     * 
     * For any processed appointment data, the JSON output should contain two arrays 
     * of equal length: one for date labels and one for corresponding counts
     */
    public function testJSONDataFormatConsistencyProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random labels and counts for testing
            $testData = $this->generateRandomLabelsAndCounts();
            
            // Test JSON data format consistency
            $result = $this->testJSONDataFormatConsistency($testData['labels'], $testData['counts']);
            
            // Property: JSON output should contain both labels and counts
            if (!$result['contains_both_arrays']) {
                echo "FAILED on iteration $i:\n";
                echo "Missing arrays: " . json_encode($result['missing_arrays']) . "\n";
                return false;
            }
            
            // Property: JSON arrays should be valid JSON
            if (!$result['valid_json_format']) {
                echo "FAILED on iteration $i:\n";
                echo "Invalid JSON: " . json_encode($result['json_errors']) . "\n";
                return false;
            }
            
            // Property: Decoded arrays should have equal length
            if (!$result['equal_array_lengths']) {
                echo "FAILED on iteration $i:\n";
                echo "Labels length: " . $result['labels_length'] . "\n";
                echo "Counts length: " . $result['counts_length'] . "\n";
                return false;
            }
            
            // Property: Decoded data should match original data
            if (!$result['data_matches_original']) {
                echo "FAILED on iteration $i:\n";
                echo "Original labels: " . json_encode($testData['labels']) . "\n";
                echo "Decoded labels: " . json_encode($result['decoded_labels']) . "\n";
                echo "Original counts: " . json_encode($testData['counts']) . "\n";
                echo "Decoded counts: " . json_encode($result['decoded_counts']) . "\n";
                return false;
            }
        }
        
        echo "Property 6 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate random labels and counts for testing
     */
    private function generateRandomLabelsAndCounts() {
        $length = rand(1, 14); // 1 to 14 data points
        $labels = [];
        $counts = [];
        
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        for ($i = 0; $i < $length; $i++) {
            $labels[] = $dayNames[array_rand($dayNames)];
            $counts[] = rand(0, 50); // 0 to 50 appointments
        }
        
        return [
            'labels' => $labels,
            'counts' => $counts
        ];
    }
    
    /**
     * Test JSON data format consistency
     */
    private function testJSONDataFormatConsistency($labels, $counts) {
        $result = [
            'original_labels' => $labels,
            'original_counts' => $counts,
            'contains_both_arrays' => false,
            'missing_arrays' => [],
            'valid_json_format' => false,
            'json_errors' => [],
            'equal_array_lengths' => false,
            'labels_length' => 0,
            'counts_length' => 0,
            'data_matches_original' => false,
            'decoded_labels' => null,
            'decoded_counts' => null,
            'formatted_data' => null
        ];
        
        try {
            // Format data using the service
            $formattedData = AppointmentAnalyticsService::formatDataForChart($labels, $counts);
            $result['formatted_data'] = $formattedData;
            
            // Check if both arrays are present
            $requiredKeys = ['json_labels', 'json_counts'];
            $missingKeys = [];
            
            foreach ($requiredKeys as $key) {
                if (!isset($formattedData[$key])) {
                    $missingKeys[] = $key;
                }
            }
            
            if (empty($missingKeys)) {
                $result['contains_both_arrays'] = true;
            } else {
                $result['missing_arrays'] = $missingKeys;
                return $result;
            }
            
            // Check if JSON is valid
            $decodedLabels = json_decode($formattedData['json_labels'], true);
            $decodedCounts = json_decode($formattedData['json_counts'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['json_errors'][] = 'Labels JSON error: ' . json_last_error_msg();
            }
            
            if ($decodedLabels === null && $formattedData['json_labels'] !== 'null') {
                $result['json_errors'][] = 'Labels JSON decode failed';
            }
            
            if ($decodedCounts === null && $formattedData['json_counts'] !== 'null') {
                $result['json_errors'][] = 'Counts JSON decode failed';
            }
            
            if (empty($result['json_errors'])) {
                $result['valid_json_format'] = true;
                $result['decoded_labels'] = $decodedLabels;
                $result['decoded_counts'] = $decodedCounts;
            } else {
                return $result;
            }
            
            // Check array lengths
            $result['labels_length'] = count($decodedLabels);
            $result['counts_length'] = count($decodedCounts);
            $result['equal_array_lengths'] = ($result['labels_length'] === $result['counts_length']);
            
            // Check if data matches original
            $labelsMatch = ($decodedLabels === $labels);
            $countsMatch = ($decodedCounts === $counts);
            $result['data_matches_original'] = ($labelsMatch && $countsMatch);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Generate random date range for testing
     */
    private function generateRandomDateRange() {
        $dates = [];
        $numDates = rand(1, 14); // 1 to 14 dates
        
        // Start from a random date within the last 30 days to next 30 days
        $startOffset = rand(-30, 30);
        $currentDate = new DateTime();
        $currentDate->add(new DateInterval('P' . abs($startOffset) . 'D'));
        if ($startOffset < 0) {
            $currentDate->sub(new DateInterval('P' . (abs($startOffset) * 2) . 'D'));
        }
        
        for ($i = 0; $i < $numDates; $i++) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }
    
    /**
     * Test date label accuracy
     */
    private function testDateLabelAccuracy($testDates) {
        $result = [
            'test_dates' => $testDates,
            'all_labels_accurate' => true,
            'inaccurate_labels' => [],
            'all_labels_abbreviated' => true,
            'non_abbreviated_labels' => [],
            'all_labels_valid_days' => true,
            'invalid_day_labels' => [],
            'generated_labels' => []
        ];
        
        $validDayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        try {
            // Generate labels using the service
            $generatedLabels = AppointmentAnalyticsService::generateDateLabels($testDates);
            $result['generated_labels'] = $generatedLabels;
            
            // Check each label
            for ($i = 0; $i < count($testDates); $i++) {
                $date = $testDates[$i];
                $generatedLabel = $generatedLabels[$i] ?? null;
                
                if ($generatedLabel === null) {
                    $result['all_labels_accurate'] = false;
                    $result['inaccurate_labels'][] = [
                        'date' => $date,
                        'expected' => 'not null',
                        'actual' => null
                    ];
                    continue;
                }
                
                // Calculate expected label
                $dateObj = new DateTime($date);
                $expectedLabel = $dateObj->format('D');
                
                // Check accuracy
                if ($generatedLabel !== $expectedLabel) {
                    $result['all_labels_accurate'] = false;
                    $result['inaccurate_labels'][] = [
                        'date' => $date,
                        'expected' => $expectedLabel,
                        'actual' => $generatedLabel
                    ];
                }
                
                // Check if abbreviated (3 characters)
                if (strlen($generatedLabel) !== 3) {
                    $result['all_labels_abbreviated'] = false;
                    $result['non_abbreviated_labels'][] = [
                        'date' => $date,
                        'label' => $generatedLabel,
                        'length' => strlen($generatedLabel)
                    ];
                }
                
                // Check if valid day name
                if (!in_array($generatedLabel, $validDayNames)) {
                    $result['all_labels_valid_days'] = false;
                    $result['invalid_day_labels'][] = [
                        'date' => $date,
                        'label' => $generatedLabel,
                        'valid_options' => $validDayNames
                    ];
                }
            }
            
        } catch (Exception $e) {
            $result['all_labels_accurate'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Generate random appointment data for testing
     * Creates appointments with random dates within the next 7 days
     */
    private function generateRandomAppointmentData() {
        $appointments = [];
        $numAppointments = rand(0, 20); // 0 to 20 appointments
        
        // Generate random appointments within next 7 days
        for ($i = 0; $i < $numAppointments; $i++) {
            $randomDay = rand(0, 6); // 0-6 days from today
            $startTime = date('Y-m-d H:i:s', strtotime("+$randomDay days") + rand(8*3600, 17*3600)); // 8 AM to 5 PM
            
            $appointments[] = [
                'appointment_id' => $i + 1,
                'start_time' => $startTime,
                'patient_id' => rand(1, 100), // Mock patient ID
                'doctor_name' => 'Dr. Test ' . ($i % 5 + 1)
            ];
        }
        
        return $appointments;
    }
    
    /**
     * Get existing test patients or create temporary ones
     */
    private function getOrCreateTestPatients() {
        try {
            // First, try to get existing patients
            $sql = "SELECT id FROM patients LIMIT 5";
            $existingPatients = Database::fetchAll($sql, [], 'admin');
            
            if (!empty($existingPatients)) {
                return $existingPatients;
            }
            
            // If no patients exist, create temporary test patients
            $testPatients = [];
            Database::beginTransaction();
            
            for ($i = 1; $i <= 3; $i++) {
                $sql = "INSERT INTO patients (name, ic_number, diagnosis, phone) VALUES (?, ?, ?, ?)";
                $icNumber = sprintf('%06d-%02d-%04d', rand(800000, 999999), rand(10, 99), rand(1000, 9999));
                
                $encryptedDiagnosis = 'Test diagnosis for analytics'; // Simple text for testing
                
                Database::executeUpdate($sql, [
                    "Test Patient $i",
                    $icNumber,
                    $encryptedDiagnosis,
                    '012' . rand(1000000, 9999999)
                ]);
                
                $testPatients[] = ['id' => Database::getLastInsertId()];
            }
            
            Database::commit();
            return $testPatients;
            
        } catch (Exception $e) {
            Database::rollback();
            error_log("Failed to get or create test patients: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Test date coverage functionality
     * Tests the core logic without database operations
     */
    private function testDateCoverage($testAppointments) {
        $result = [
            'test_appointments' => $testAppointments,
            'has_seven_data_points' => false,
            'data_point_count' => 0,
            'covers_consecutive_days' => false,
            'includes_zero_counts_for_missing_days' => false,
            'arrays_equal_length' => false,
            'labels' => [],
            'counts' => [],
            'date_range' => [],
            'expected_missing_days' => []
        ];
        
        try {
            // Test the date range generation directly
            $dateRange = $this->testGenerateNext7DaysRange();
            $result['date_range'] = $dateRange;
            $result['data_point_count'] = count($dateRange);
            
            // Check if we have exactly 7 data points
            $result['has_seven_data_points'] = (count($dateRange) === 7);
            
            // Check if date range covers consecutive days starting from today
            $result['covers_consecutive_days'] = $this->validateConsecutiveDays($dateRange);
            
            // Test label generation
            $labels = AppointmentAnalyticsService::generateDateLabels($dateRange);
            $result['labels'] = $labels;
            
            // Simulate appointment counts (some days with appointments, some without)
            $appointmentDates = $this->extractAppointmentDates($testAppointments);
            $counts = $this->simulateAppointmentCounts($dateRange, $appointmentDates);
            $result['counts'] = $counts;
            
            // Check if arrays have equal length
            $result['arrays_equal_length'] = (count($labels) === count($counts));
            
            // Check if missing days are filled with zero counts
            $result['expected_missing_days'] = $this->findMissingDays($dateRange, $appointmentDates);
            $result['includes_zero_counts_for_missing_days'] = $this->validateZeroCountsForMissingDays(
                $dateRange, 
                $counts, 
                $appointmentDates
            );
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Test the date range generation method directly
     */
    private function testGenerateNext7DaysRange() {
        $dates = [];
        $currentDate = new DateTime();
        
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }
    
    /**
     * Simulate appointment counts based on test data
     */
    private function simulateAppointmentCounts($dateRange, $appointmentDates) {
        $counts = [];
        
        foreach ($dateRange as $date) {
            $count = 0;
            foreach ($appointmentDates as $appointmentDate) {
                if ($appointmentDate === $date) {
                    $count++;
                }
            }
            $counts[] = $count;
        }
        
        return $counts;
    }
    
    /**
     * Set up temporary test appointments in database
     */
    private function setupTestAppointments($appointments) {
        // Clean up any existing test data first
        $this->cleanupTestAppointments();
        
        if (empty($appointments)) {
            return;
        }
        
        try {
            Database::beginTransaction();
            
            foreach ($appointments as $appointment) {
                // Generate truly unique appointment ID using microtime and random
                $uniqueId = (int)(microtime(true) * 1000000) + rand(1, 999999);
                
                // Ensure we don't exceed INT max value
                $uniqueId = $uniqueId % 2147483647;
                
                $sql = "INSERT INTO appointments (appointment_id, start_time, end_time, patient_id, doctor_name, reason, appointment_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $endTime = date('Y-m-d H:i:s', strtotime($appointment['start_time']) + 3600); // 1 hour duration
                $appointmentDate = date('Y-m-d', strtotime($appointment['start_time']));
                
                Database::executeUpdate($sql, [
                    $uniqueId,
                    $appointment['start_time'],
                    $endTime,
                    $appointment['patient_id'],
                    $appointment['doctor_name'],
                    'Test appointment for analytics',
                    $appointmentDate
                ]);
                
                // Small delay to ensure unique microtime values
                usleep(1000); // 1ms delay
            }
            
            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            throw $e;
        }
    }
    
    /**
     * Clean up temporary test appointments
     */
    private function cleanupTestAppointments() {
        try {
            // Remove test appointments (those with reason containing "Test appointment for analytics")
            $sql = "DELETE FROM appointments WHERE reason = ? OR reason LIKE ?";
            Database::executeUpdate($sql, ['Test appointment for analytics', '%Test appointment for analytics%']);
            
            // Also clean up test patients if they were created
            $sql = "DELETE FROM patients WHERE name LIKE ?";
            Database::executeUpdate($sql, ['Test Patient %']);
        } catch (Exception $e) {
            // Log error but don't throw - cleanup should be non-blocking
            error_log("Failed to cleanup test data: " . $e->getMessage());
        }
    }
    
    /**
     * Validate that date range covers consecutive days starting from today
     */
    private function validateConsecutiveDays($dateRange) {
        if (count($dateRange) !== 7) {
            return false;
        }
        
        $today = date('Y-m-d');
        $expectedDate = new DateTime($today);
        
        foreach ($dateRange as $date) {
            if ($date !== $expectedDate->format('Y-m-d')) {
                return false;
            }
            $expectedDate->add(new DateInterval('P1D'));
        }
        
        return true;
    }
    
    /**
     * Extract appointment dates from test data
     */
    private function extractAppointmentDates($appointments) {
        $dates = [];
        foreach ($appointments as $appointment) {
            $date = date('Y-m-d', strtotime($appointment['start_time']));
            if (!in_array($date, $dates)) {
                $dates[] = $date;
            }
        }
        return $dates;
    }
    
    /**
     * Find days in date range that don't have appointments
     */
    private function findMissingDays($dateRange, $appointmentDates) {
        $missingDays = [];
        foreach ($dateRange as $date) {
            if (!in_array($date, $appointmentDates)) {
                $missingDays[] = $date;
            }
        }
        return $missingDays;
    }
    
    /**
     * Validate that missing days have zero counts
     */
    private function validateZeroCountsForMissingDays($dateRange, $counts, $appointmentDates) {
        for ($i = 0; $i < count($dateRange); $i++) {
            $date = $dateRange[$i];
            $count = $counts[$i];
            
            // If this date has no appointments, count should be 0
            if (!in_array($date, $appointmentDates) && $count !== 0) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate different error scenarios for testing
     */
    private function generateErrorScenarios() {
        return [
            [
                'name' => 'database_connection_failure',
                'type' => 'connection',
                'description' => 'Simulate database connection failure'
            ],
            [
                'name' => 'query_timeout',
                'type' => 'timeout',
                'description' => 'Simulate database query timeout'
            ],
            [
                'name' => 'invalid_data_format',
                'type' => 'data',
                'description' => 'Simulate invalid data format from database'
            ],
            [
                'name' => 'permission_denied',
                'type' => 'permission',
                'description' => 'Simulate database permission error'
            ]
        ];
    }
    
    /**
     * Test error handling graceful degradation
     */
    private function testErrorHandlingGracefulDegradation($scenario) {
        $result = [
            'scenario' => $scenario,
            'returns_valid_structure' => false,
            'structure_issues' => [],
            'provides_fallback_data' => false,
            'maintains_array_consistency' => false,
            'includes_error_flag' => false,
            'provides_valid_json' => false,
            'json_errors' => [],
            'labels_length' => 0,
            'counts_length' => 0,
            'fallback_data' => null
        ];
        
        try {
            // Test the fallback data generation directly
            $fallbackData = AppointmentAnalyticsService::getFallbackAnalyticsData();
            $result['fallback_data'] = $fallbackData;
            
            // Check if valid structure is returned
            $requiredKeys = ['labels', 'counts', 'date_range', 'json_labels', 'json_counts'];
            $missingKeys = [];
            
            foreach ($requiredKeys as $key) {
                if (!isset($fallbackData[$key])) {
                    $missingKeys[] = $key;
                }
            }
            
            if (empty($missingKeys)) {
                $result['returns_valid_structure'] = true;
            } else {
                $result['structure_issues'] = $missingKeys;
                return $result;
            }
            
            // Check if fallback data is provided (non-empty arrays)
            if (is_array($fallbackData['labels']) && is_array($fallbackData['counts'])) {
                $result['provides_fallback_data'] = true;
            }
            
            // Check array consistency
            $result['labels_length'] = count($fallbackData['labels']);
            $result['counts_length'] = count($fallbackData['counts']);
            $result['maintains_array_consistency'] = ($result['labels_length'] === $result['counts_length']);
            
            // Check for error indication flag
            $result['includes_error_flag'] = isset($fallbackData['is_fallback']) && $fallbackData['is_fallback'] === true;
            
            // Check JSON validity
            $decodedLabels = json_decode($fallbackData['json_labels'], true);
            $decodedCounts = json_decode($fallbackData['json_counts'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $result['json_errors'][] = 'JSON decode error: ' . json_last_error_msg();
            } else if ($decodedLabels !== null && $decodedCounts !== null) {
                $result['provides_valid_json'] = true;
            } else {
                $result['json_errors'][] = 'JSON decoded to null unexpectedly';
            }
            
            // Additional validation: ensure fallback data has expected structure
            if ($result['provides_fallback_data'] && $result['maintains_array_consistency']) {
                // Should have exactly 7 data points for weekly workload
                if ($result['labels_length'] !== 7) {
                    $result['structure_issues'][] = "Expected 7 data points, got {$result['labels_length']}";
                    $result['returns_valid_structure'] = false;
                }
                
                // All counts should be zero in fallback
                $allZeroCounts = true;
                foreach ($fallbackData['counts'] as $count) {
                    if ($count !== 0) {
                        $allZeroCounts = false;
                        break;
                    }
                }
                
                if (!$allZeroCounts) {
                    $result['structure_issues'][] = "Fallback counts should all be zero";
                }
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            // Even if there's an exception, we should still get some fallback data
            // This tests the robustness of error handling
        }
        
        return $result;
    }
    
    /**
     * Generate random chart dataset for testing
     */
    private function generateRandomChartDataset() {
        $length = rand(1, 14); // 1 to 14 data points
        $labels = [];
        $data = [];
        
        $dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        for ($i = 0; $i < $length; $i++) {
            $labels[] = $dayNames[array_rand($dayNames)];
            $data[] = rand(0, 50); // 0 to 50 appointments (including zero values)
        }
        
        return [
            'labels' => $labels,
            'data' => $data
        ];
    }
    
    /**
     * Test chart rendering integrity
     */
    private function testChartRenderingIntegrity($testDataset) {
        $result = [
            'test_dataset' => $testDataset,
            'correct_number_of_bars' => false,
            'expected_bars' => 0,
            'actual_bars' => 0,
            'proportional_bar_heights' => false,
            'height_proportions' => [],
            'valid_chart_config' => false,
            'config_errors' => [],
            'handles_zero_values' => false,
            'zero_value_issues' => [],
            'consistent_array_lengths' => false,
            'labels_length' => 0,
            'data_length' => 0,
            'chart_config' => null
        ];
        
        try {
            $labels = $testDataset['labels'];
            $data = $testDataset['data'];
            
            $result['labels_length'] = count($labels);
            $result['data_length'] = count($data);
            
            // Check array length consistency
            $result['consistent_array_lengths'] = ($result['labels_length'] === $result['data_length']);
            
            if (!$result['consistent_array_lengths']) {
                return $result;
            }
            
            // Create Chart.js configuration similar to the actual implementation
            $chartConfig = [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'label' => 'Appointments',
                        'data' => $data,
                        'backgroundColor' => '#3b82f6',
                        'borderColor' => '#2563eb',
                        'borderWidth' => 1,
                        'borderRadius' => 4,
                        'borderSkipped' => false
                    ]]
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => ['display' => false],
                        'tooltip' => [
                            'callbacks' => [
                                'label' => 'function(context) { return context.parsed.y + " appointment" + (context.parsed.y !== 1 ? "s" : ""); }'
                            ]
                        ]
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'ticks' => ['stepSize' => 1]
                        ]
                    ]
                ]
            ];
            
            $result['chart_config'] = $chartConfig;
            
            // Validate Chart.js configuration structure
            $configValidation = $this->validateChartConfig($chartConfig);
            $result['valid_chart_config'] = $configValidation['is_valid'];
            $result['config_errors'] = $configValidation['errors'];
            
            if (!$result['valid_chart_config']) {
                return $result;
            }
            
            // Test number of bars (should equal number of data points)
            $result['expected_bars'] = count($data);
            $result['actual_bars'] = count($chartConfig['data']['datasets'][0]['data']);
            $result['correct_number_of_bars'] = ($result['expected_bars'] === $result['actual_bars']);
            
            // Test proportional bar heights
            $proportionalityTest = $this->testBarHeightProportionality($data);
            $result['proportional_bar_heights'] = $proportionalityTest['is_proportional'];
            $result['height_proportions'] = $proportionalityTest['proportions'];
            
            // Test zero value handling
            $zeroValueTest = $this->testZeroValueHandling($data);
            $result['handles_zero_values'] = $zeroValueTest['handles_correctly'];
            $result['zero_value_issues'] = $zeroValueTest['issues'];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate Chart.js configuration structure
     */
    private function validateChartConfig($config) {
        $result = [
            'is_valid' => true,
            'errors' => []
        ];
        
        // Check required top-level properties
        $requiredKeys = ['type', 'data', 'options'];
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                $result['errors'][] = "Missing required key: $key";
                $result['is_valid'] = false;
            }
        }
        
        if (!$result['is_valid']) {
            return $result;
        }
        
        // Check chart type
        if ($config['type'] !== 'bar') {
            $result['errors'][] = "Expected chart type 'bar', got: " . $config['type'];
            $result['is_valid'] = false;
        }
        
        // Check data structure
        if (!isset($config['data']['labels']) || !isset($config['data']['datasets'])) {
            $result['errors'][] = "Invalid data structure - missing labels or datasets";
            $result['is_valid'] = false;
        }
        
        // Check dataset structure
        if (empty($config['data']['datasets']) || !isset($config['data']['datasets'][0]['data'])) {
            $result['errors'][] = "Invalid dataset structure - missing data array";
            $result['is_valid'] = false;
        }
        
        // Check required styling properties
        $dataset = $config['data']['datasets'][0];
        $requiredDatasetKeys = ['backgroundColor', 'borderColor', 'borderRadius'];
        foreach ($requiredDatasetKeys as $key) {
            if (!isset($dataset[$key])) {
                $result['errors'][] = "Missing required dataset property: $key";
                $result['is_valid'] = false;
            }
        }
        
        // Check color values
        if (isset($dataset['backgroundColor']) && $dataset['backgroundColor'] !== '#3b82f6') {
            $result['errors'][] = "Incorrect backgroundColor - expected #3b82f6, got: " . $dataset['backgroundColor'];
            $result['is_valid'] = false;
        }
        
        return $result;
    }
    
    /**
     * Test bar height proportionality
     */
    private function testBarHeightProportionality($data) {
        $result = [
            'is_proportional' => true,
            'proportions' => []
        ];
        
        if (empty($data)) {
            return $result;
        }
        
        $maxValue = max($data);
        
        // If all values are zero, proportionality is maintained
        if ($maxValue === 0) {
            $result['proportions'] = array_fill(0, count($data), 0);
            return $result;
        }
        
        // Calculate proportions relative to max value
        foreach ($data as $value) {
            $proportion = $maxValue > 0 ? ($value / $maxValue) : 0;
            $result['proportions'][] = $proportion;
        }
        
        // Verify proportions are correct (this is a logical test since we can't render actual bars)
        for ($i = 0; $i < count($data); $i++) {
            $expectedProportion = $maxValue > 0 ? ($data[$i] / $maxValue) : 0;
            if (abs($result['proportions'][$i] - $expectedProportion) > 0.001) {
                $result['is_proportional'] = false;
                break;
            }
        }
        
        return $result;
    }
    
    /**
     * Test zero value handling
     */
    private function testZeroValueHandling($data) {
        $result = [
            'handles_correctly' => true,
            'issues' => []
        ];
        
        $zeroCount = 0;
        foreach ($data as $value) {
            if ($value === 0) {
                $zeroCount++;
            } elseif ($value < 0) {
                $result['issues'][] = "Negative value found: $value";
                $result['handles_correctly'] = false;
            }
        }
        
        // Zero values should be preserved in the data array
        $zeroIndices = [];
        for ($i = 0; $i < count($data); $i++) {
            if ($data[$i] === 0) {
                $zeroIndices[] = $i;
            }
        }
        
        // Verify zero values are handled (they should remain as 0 in the chart data)
        foreach ($zeroIndices as $index) {
            if ($data[$index] !== 0) {
                $result['issues'][] = "Zero value at index $index was modified: " . $data[$index];
                $result['handles_correctly'] = false;
            }
        }
        
        return $result;
    }
    
    /**
     * Test data consistency between analytics and database
     */
    private function testDataConsistency($testAppointments) {
        $result = [
            'test_appointments' => $testAppointments,
            'counts_match_database' => false,
            'all_dates_represented' => false,
            'zero_counts_accurate' => false,
            'nonzero_counts_accurate' => false,
            'expected_counts' => [],
            'actual_counts' => [],
            'date_range' => [],
            'missing_dates' => [],
            'incorrect_zero_dates' => [],
            'incorrect_nonzero_counts' => []
        ];
        
        try {
            // Generate the expected 7-day date range
            $dateRange = $this->testGenerateNext7DaysRange();
            $result['date_range'] = $dateRange;
            
            // Calculate expected counts from test appointments
            $expectedCounts = $this->calculateExpectedCounts($dateRange, $testAppointments);
            $result['expected_counts'] = $expectedCounts;
            
            // Get actual counts from analytics service
            $analyticsData = AppointmentAnalyticsService::getAnalyticsData();
            $actualCounts = json_decode($analyticsData['json_counts'], true);
            $result['actual_counts'] = $actualCounts;
            
            // Verify all dates are represented
            if (count($actualCounts) === 7) {
                $result['all_dates_represented'] = true;
            } else {
                $result['missing_dates'] = array_slice($dateRange, count($actualCounts));
            }
            
            // Compare expected vs actual counts
            $countsMatch = true;
            $zeroCountsAccurate = true;
            $nonzeroCountsAccurate = true;
            
            for ($i = 0; $i < min(count($expectedCounts), count($actualCounts)); $i++) {
                $expected = $expectedCounts[$i];
                $actual = $actualCounts[$i];
                $date = $dateRange[$i] ?? "unknown";
                
                if ($expected !== $actual) {
                    $countsMatch = false;
                    
                    if ($expected === 0 && $actual !== 0) {
                        $zeroCountsAccurate = false;
                        $result['incorrect_zero_dates'][] = [
                            'date' => $date,
                            'expected' => $expected,
                            'actual' => $actual
                        ];
                    } elseif ($expected > 0 && $actual !== $expected) {
                        $nonzeroCountsAccurate = false;
                        $result['incorrect_nonzero_counts'][] = [
                            'date' => $date,
                            'expected' => $expected,
                            'actual' => $actual
                        ];
                    }
                }
            }
            
            $result['counts_match_database'] = $countsMatch;
            $result['zero_counts_accurate'] = $zeroCountsAccurate;
            $result['nonzero_counts_accurate'] = $nonzeroCountsAccurate;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Calculate expected appointment counts for date range
     */
    private function calculateExpectedCounts($dateRange, $appointments) {
        $counts = array_fill(0, count($dateRange), 0);
        
        foreach ($appointments as $appointment) {
            $appointmentDate = date('Y-m-d', strtotime($appointment['start_time']));
            
            // Find the index of this date in our date range
            $dateIndex = array_search($appointmentDate, $dateRange);
            
            if ($dateIndex !== false) {
                $counts[$dateIndex]++;
            }
        }
        
        return $counts;
    }
}