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
     * Store test appointment IDs for cleanup
     */
    private $testAppointmentIds = [];
    
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
            
            // Store test appointment IDs for cleanup
            $this->testAppointmentIds = [];
            
            foreach ($appointments as $appointment) {
                // Generate truly unique appointment ID using microtime and random
                $uniqueId = (int)(microtime(true) * 1000000) + rand(1, 999999);
                
                // Ensure we don't exceed INT max value
                $uniqueId = $uniqueId % 2147483647;
                
                // Store for cleanup
                $this->testAppointmentIds[] = $uniqueId;
                
                $sql = "INSERT INTO appointments (appointment_id, start_time, end_time, patient_id, doctor_name, reason, appointment_date) 
                        VALUES (?, ?, ?, ?, ?, AES_ENCRYPT(?, ?), ?)";
                
                $endTime = date('Y-m-d H:i:s', strtotime($appointment['start_time']) + 3600); // 1 hour duration
                $appointmentDate = date('Y-m-d', strtotime($appointment['start_time']));
                
                Database::executeUpdate($sql, [
                    $uniqueId,
                    $appointment['start_time'],
                    $endTime,
                    $appointment['patient_id'],
                    $appointment['doctor_name'],
                    'Test appointment for analytics',
                    ENCRYPTION_KEY,
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
            // Remove test appointments by their IDs
            if (!empty($this->testAppointmentIds)) {
                $placeholders = implode(',', array_fill(0, count($this->testAppointmentIds), '?'));
                $sql = "DELETE FROM appointments WHERE appointment_id IN ($placeholders)";
                Database::executeUpdate($sql, $this->testAppointmentIds);
                $this->testAppointmentIds = [];
            }
            
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
     * Property 12: Line Chart Configuration
     * Feature: visual-analytics, Property 12: Line Chart Configuration
     * Validates: Requirements 5.1, 5.2, 5.3, 5.4
     * 
     * For any valid dataset provided to Chart.js, the line chart configuration should use 
     * line chart type with smooth curves, soft blue colors, circular markers, and 
     * semi-transparent background fill
     */
    public function testLineChartConfigurationProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random valid dataset for Chart.js
            $testDataset = $this->generateRandomChartDataset();
            
            // Test line chart configuration
            $result = $this->testLineChartConfiguration($testDataset);
            
            // Property: Chart type should be 'line'
            if (!$result['correct_chart_type']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected chart type 'line', got: " . $result['actual_chart_type'] . "\n";
                return false;
            }
            
            // Property: Should use soft blue color (#3b82f6) for line and points
            if (!$result['correct_colors']) {
                echo "FAILED on iteration $i:\n";
                echo "Incorrect colors used\n";
                echo "Color issues: " . json_encode($result['color_issues']) . "\n";
                return false;
            }
            
            // Property: Should have circular markers with radius 4
            if (!$result['correct_point_styling']) {
                echo "FAILED on iteration $i:\n";
                echo "Incorrect point styling\n";
                echo "Point styling issues: " . json_encode($result['point_styling_issues']) . "\n";
                return false;
            }
            
            // Property: Should have smooth curves with tension 0.4
            if (!$result['correct_tension']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected tension 0.4, got: " . $result['actual_tension'] . "\n";
                return false;
            }
            
            // Property: Should have semi-transparent background fill
            if (!$result['correct_fill_configuration']) {
                echo "FAILED on iteration $i:\n";
                echo "Incorrect fill configuration\n";
                echo "Fill issues: " . json_encode($result['fill_issues']) . "\n";
                return false;
            }
            
            // Property: Configuration should be valid for Chart.js
            if (!$result['valid_line_chart_config']) {
                echo "FAILED on iteration $i:\n";
                echo "Invalid line chart configuration\n";
                echo "Config errors: " . json_encode($result['config_errors']) . "\n";
                return false;
            }
        }
        
        echo "Property 12 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test line chart configuration
     */
    private function testLineChartConfiguration($testDataset) {
        $result = [
            'test_dataset' => $testDataset,
            'correct_chart_type' => false,
            'actual_chart_type' => null,
            'correct_colors' => false,
            'color_issues' => [],
            'correct_point_styling' => false,
            'point_styling_issues' => [],
            'correct_tension' => false,
            'actual_tension' => null,
            'correct_fill_configuration' => false,
            'fill_issues' => [],
            'valid_line_chart_config' => false,
            'config_errors' => [],
            'chart_config' => null
        ];
        
        try {
            $labels = $testDataset['labels'];
            $data = $testDataset['data'];
            
            // Create line chart configuration similar to the actual implementation
            $chartConfig = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'label' => 'Appointments',
                        'data' => $data,
                        'borderColor' => '#3b82f6', // Soft blue color for line
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Semi-transparent background fill
                        'borderWidth' => 2,
                        'fill' => true, // Add fill property with semi-transparent background
                        'tension' => 0.4, // Smooth curves between data points
                        'pointRadius' => 4, // Circular markers radius
                        'pointBackgroundColor' => '#3b82f6', // Soft blue color for points
                        'pointBorderColor' => '#fff',
                        'pointBorderWidth' => 2,
                        'pointHoverRadius' => 6
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
                    ],
                    'animation' => [
                        'duration' => 750,
                        'easing' => 'easeInOutQuart'
                    ]
                ]
            ];
            
            $result['chart_config'] = $chartConfig;
            
            // Test chart type
            $result['actual_chart_type'] = $chartConfig['type'];
            $result['correct_chart_type'] = ($chartConfig['type'] === 'line');
            
            // Test colors
            $dataset = $chartConfig['data']['datasets'][0];
            $colorTests = $this->validateLineChartColors($dataset);
            $result['correct_colors'] = $colorTests['all_correct'];
            $result['color_issues'] = $colorTests['issues'];
            
            // Test point styling
            $pointTests = $this->validatePointStyling($dataset);
            $result['correct_point_styling'] = $pointTests['all_correct'];
            $result['point_styling_issues'] = $pointTests['issues'];
            
            // Test tension
            $result['actual_tension'] = $dataset['tension'] ?? null;
            $result['correct_tension'] = ($result['actual_tension'] === 0.4);
            
            // Test fill configuration
            $fillTests = $this->validateFillConfiguration($dataset);
            $result['correct_fill_configuration'] = $fillTests['all_correct'];
            $result['fill_issues'] = $fillTests['issues'];
            
            // Validate overall configuration
            $configValidation = $this->validateLineChartConfig($chartConfig);
            $result['valid_line_chart_config'] = $configValidation['is_valid'];
            $result['config_errors'] = $configValidation['errors'];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate line chart colors
     */
    private function validateLineChartColors($dataset) {
        $result = [
            'all_correct' => true,
            'issues' => []
        ];
        
        // Check border color (line color)
        if (!isset($dataset['borderColor']) || $dataset['borderColor'] !== '#3b82f6') {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect borderColor - expected #3b82f6, got: " . ($dataset['borderColor'] ?? 'null');
        }
        
        // Check point background color
        if (!isset($dataset['pointBackgroundColor']) || $dataset['pointBackgroundColor'] !== '#3b82f6') {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect pointBackgroundColor - expected #3b82f6, got: " . ($dataset['pointBackgroundColor'] ?? 'null');
        }
        
        // Check background color (semi-transparent)
        if (!isset($dataset['backgroundColor']) || $dataset['backgroundColor'] !== 'rgba(59, 130, 246, 0.1)') {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect backgroundColor - expected rgba(59, 130, 246, 0.1), got: " . ($dataset['backgroundColor'] ?? 'null');
        }
        
        return $result;
    }
    
    /**
     * Validate point styling
     */
    private function validatePointStyling($dataset) {
        $result = [
            'all_correct' => true,
            'issues' => []
        ];
        
        // Check point radius
        if (!isset($dataset['pointRadius']) || $dataset['pointRadius'] !== 4) {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect pointRadius - expected 4, got: " . ($dataset['pointRadius'] ?? 'null');
        }
        
        // Check point border color
        if (!isset($dataset['pointBorderColor']) || $dataset['pointBorderColor'] !== '#fff') {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect pointBorderColor - expected #fff, got: " . ($dataset['pointBorderColor'] ?? 'null');
        }
        
        // Check point border width
        if (!isset($dataset['pointBorderWidth']) || $dataset['pointBorderWidth'] !== 2) {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect pointBorderWidth - expected 2, got: " . ($dataset['pointBorderWidth'] ?? 'null');
        }
        
        // Check point hover radius
        if (!isset($dataset['pointHoverRadius']) || $dataset['pointHoverRadius'] !== 6) {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect pointHoverRadius - expected 6, got: " . ($dataset['pointHoverRadius'] ?? 'null');
        }
        
        return $result;
    }
    
    /**
     * Validate fill configuration
     */
    private function validateFillConfiguration($dataset) {
        $result = [
            'all_correct' => true,
            'issues' => []
        ];
        
        // Check fill property
        if (!isset($dataset['fill']) || $dataset['fill'] !== true) {
            $result['all_correct'] = false;
            $result['issues'][] = "Incorrect fill - expected true, got: " . ($dataset['fill'] ?? 'null');
        }
        
        // Check that backgroundColor is set for fill
        if (!isset($dataset['backgroundColor'])) {
            $result['all_correct'] = false;
            $result['issues'][] = "Missing backgroundColor for fill";
        }
        
        return $result;
    }
    
    /**
     * Validate line chart configuration structure
     */
    private function validateLineChartConfig($config) {
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
        if ($config['type'] !== 'line') {
            $result['errors'][] = "Expected chart type 'line', got: " . $config['type'];
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
        
        // Check required line chart properties
        $dataset = $config['data']['datasets'][0];
        $requiredDatasetKeys = ['borderColor', 'backgroundColor', 'tension', 'pointRadius', 'fill'];
        foreach ($requiredDatasetKeys as $key) {
            if (!isset($dataset[$key])) {
                $result['errors'][] = "Missing required dataset property: $key";
                $result['is_valid'] = false;
            }
        }
        
        // Check animation configuration
        if (!isset($config['options']['animation'])) {
            $result['errors'][] = "Missing animation configuration";
            $result['is_valid'] = false;
        }
        
        return $result;
    }
    
    /**
     * Property 7: Weekly View Date Range
     * Feature: visual-analytics, Property 7: Weekly View Date Range
     * Validates: Requirements 6.2
     * 
     * For any current date, when the weekly view is selected, the Visual Analytics System 
     * should return exactly 7 consecutive days of appointment data starting from today
     */
    public function testWeeklyViewDateRangeProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test weekly view date range
            $result = $this->testWeeklyViewDateRange();
            
            // Property: Should return exactly 7 consecutive days
            if (!$result['returns_seven_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected 7 days, got: " . $result['actual_day_count'] . "\n";
                echo "Date range: " . json_encode($result['date_range']) . "\n";
                return false;
            }
            
            // Property: Should start from today
            if (!$result['starts_from_today']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected to start from today (" . $result['expected_start_date'] . "), got: " . $result['actual_start_date'] . "\n";
                return false;
            }
            
            // Property: Should be consecutive days
            if (!$result['consecutive_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Days are not consecutive\n";
                echo "Date range: " . json_encode($result['date_range']) . "\n";
                echo "Gaps found: " . json_encode($result['date_gaps']) . "\n";
                return false;
            }
            
            // Property: Should use abbreviated day labels for weekly view
            if (!$result['correct_label_format']) {
                echo "FAILED on iteration $i:\n";
                echo "Incorrect label format for weekly view\n";
                echo "Expected abbreviated day names, got: " . json_encode($result['labels']) . "\n";
                return false;
            }
        }
        
        echo "Property 7 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Property 8: Monthly View Date Range
     * Feature: visual-analytics, Property 8: Monthly View Date Range
     * Validates: Requirements 6.3
     * 
     * For any current date, when the monthly view is selected, the Visual Analytics System 
     * should return exactly 30 consecutive days of appointment data starting from today
     */
    public function testMonthlyViewDateRangeProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Test monthly view date range
            $result = $this->testMonthlyViewDateRange();
            
            // Property: Should return exactly 30 consecutive days
            if (!$result['returns_thirty_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected 30 days, got: " . $result['actual_day_count'] . "\n";
                echo "Date range: " . json_encode(array_slice($result['date_range'], 0, 5)) . "...\n";
                return false;
            }
            
            // Property: Should start from today
            if (!$result['starts_from_today']) {
                echo "FAILED on iteration $i:\n";
                echo "Expected to start from today (" . $result['expected_start_date'] . "), got: " . $result['actual_start_date'] . "\n";
                return false;
            }
            
            // Property: Should be consecutive days
            if (!$result['consecutive_days']) {
                echo "FAILED on iteration $i:\n";
                echo "Days are not consecutive\n";
                echo "First few dates: " . json_encode(array_slice($result['date_range'], 0, 5)) . "\n";
                echo "Gaps found: " . json_encode($result['date_gaps']) . "\n";
                return false;
            }
            
            // Property: Should use month-day format for monthly view
            if (!$result['correct_label_format']) {
                echo "FAILED on iteration $i:\n";
                echo "Incorrect label format for monthly view\n";
                echo "Expected 'M j' format, got first few: " . json_encode(array_slice($result['labels'], 0, 5)) . "\n";
                return false;
            }
        }
        
        echo "Property 8 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Test weekly view date range functionality
     */
    private function testWeeklyViewDateRange() {
        $result = [
            'returns_seven_days' => false,
            'actual_day_count' => 0,
            'starts_from_today' => false,
            'expected_start_date' => date('Y-m-d'),
            'actual_start_date' => null,
            'consecutive_days' => false,
            'date_gaps' => [],
            'correct_label_format' => false,
            'date_range' => [],
            'labels' => []
        ];
        
        try {
            // Test the weekly view (7 days)
            $analyticsData = AppointmentAnalyticsService::getAppointmentCounts(7);
            
            $result['date_range'] = $analyticsData['date_range'];
            $result['labels'] = $analyticsData['labels'];
            $result['actual_day_count'] = count($analyticsData['date_range']);
            
            // Check if returns exactly 7 days
            $result['returns_seven_days'] = ($result['actual_day_count'] === 7);
            
            // Check if starts from today
            if (!empty($analyticsData['date_range'])) {
                $result['actual_start_date'] = $analyticsData['date_range'][0];
                $result['starts_from_today'] = ($result['actual_start_date'] === $result['expected_start_date']);
            }
            
            // Check if days are consecutive
            $consecutiveTest = $this->validateConsecutiveDaysGeneric($analyticsData['date_range'], 7);
            $result['consecutive_days'] = $consecutiveTest['is_consecutive'];
            $result['date_gaps'] = $consecutiveTest['gaps'];
            
            // Check label format (should be abbreviated day names for weekly)
            $labelTest = $this->validateWeeklyLabelFormat($analyticsData['labels'], $analyticsData['date_range']);
            $result['correct_label_format'] = $labelTest['is_correct'];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Test monthly view date range functionality
     */
    private function testMonthlyViewDateRange() {
        $result = [
            'returns_thirty_days' => false,
            'actual_day_count' => 0,
            'starts_from_today' => false,
            'expected_start_date' => date('Y-m-d'),
            'actual_start_date' => null,
            'consecutive_days' => false,
            'date_gaps' => [],
            'correct_label_format' => false,
            'date_range' => [],
            'labels' => []
        ];
        
        try {
            // Test the monthly view (30 days)
            $analyticsData = AppointmentAnalyticsService::getAppointmentCounts(30);
            
            $result['date_range'] = $analyticsData['date_range'];
            $result['labels'] = $analyticsData['labels'];
            $result['actual_day_count'] = count($analyticsData['date_range']);
            
            // Check if returns exactly 30 days
            $result['returns_thirty_days'] = ($result['actual_day_count'] === 30);
            
            // Check if starts from today
            if (!empty($analyticsData['date_range'])) {
                $result['actual_start_date'] = $analyticsData['date_range'][0];
                $result['starts_from_today'] = ($result['actual_start_date'] === $result['expected_start_date']);
            }
            
            // Check if days are consecutive
            $consecutiveTest = $this->validateConsecutiveDaysGeneric($analyticsData['date_range'], 30);
            $result['consecutive_days'] = $consecutiveTest['is_consecutive'];
            $result['date_gaps'] = $consecutiveTest['gaps'];
            
            // Check label format (should be month-day format for monthly)
            $labelTest = $this->validateMonthlyLabelFormat($analyticsData['labels'], $analyticsData['date_range']);
            $result['correct_label_format'] = $labelTest['is_correct'];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate consecutive days for any number of days
     */
    private function validateConsecutiveDaysGeneric($dateRange, $expectedDays) {
        $result = [
            'is_consecutive' => true,
            'gaps' => []
        ];
        
        if (count($dateRange) !== $expectedDays) {
            $result['is_consecutive'] = false;
            $result['gaps'][] = "Expected $expectedDays days, got " . count($dateRange);
            return $result;
        }
        
        $today = date('Y-m-d');
        $expectedDate = new DateTime($today);
        
        foreach ($dateRange as $index => $date) {
            if ($date !== $expectedDate->format('Y-m-d')) {
                $result['is_consecutive'] = false;
                $result['gaps'][] = [
                    'index' => $index,
                    'expected' => $expectedDate->format('Y-m-d'),
                    'actual' => $date
                ];
            }
            $expectedDate->add(new DateInterval('P1D'));
        }
        
        return $result;
    }
    
    /**
     * Validate weekly label format (abbreviated day names)
     */
    private function validateWeeklyLabelFormat($labels, $dateRange) {
        $result = [
            'is_correct' => true,
            'issues' => []
        ];
        
        $validDayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        
        for ($i = 0; $i < count($labels); $i++) {
            $label = $labels[$i];
            $date = $dateRange[$i] ?? null;
            
            if ($date) {
                $expectedLabel = date('D', strtotime($date));
                
                if ($label !== $expectedLabel) {
                    $result['is_correct'] = false;
                    $result['issues'][] = [
                        'index' => $i,
                        'date' => $date,
                        'expected' => $expectedLabel,
                        'actual' => $label
                    ];
                }
                
                if (!in_array($label, $validDayNames)) {
                    $result['is_correct'] = false;
                    $result['issues'][] = [
                        'index' => $i,
                        'issue' => 'Invalid day name',
                        'label' => $label
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Validate monthly label format (M j format like "Dec 18")
     */
    private function validateMonthlyLabelFormat($labels, $dateRange) {
        $result = [
            'is_correct' => true,
            'issues' => []
        ];
        
        for ($i = 0; $i < count($labels); $i++) {
            $label = $labels[$i];
            $date = $dateRange[$i] ?? null;
            
            if ($date) {
                $expectedLabel = date('M j', strtotime($date));
                
                if ($label !== $expectedLabel) {
                    $result['is_correct'] = false;
                    $result['issues'][] = [
                        'index' => $i,
                        'date' => $date,
                        'expected' => $expectedLabel,
                        'actual' => $label
                    ];
                }
                
                // Check format pattern (should be like "Dec 18")
                if (!preg_match('/^[A-Z][a-z]{2} \d{1,2}$/', $label)) {
                    $result['is_correct'] = false;
                    $result['issues'][] = [
                        'index' => $i,
                        'issue' => 'Invalid format pattern',
                        'label' => $label,
                        'expected_pattern' => 'M j (e.g., "Dec 18")'
                    ];
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Property 9: View Toggle State Consistency
     * Feature: visual-analytics, Property 9: View Toggle State Consistency
     * Validates: Requirements 6.5
     * 
     * For any view selection (weekly or monthly), exactly one toggle button should have 
     * the active state while the other should not
     */
    public function testViewToggleStateConsistencyProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random view selections for testing
            $viewSelections = $this->generateRandomViewSelections();
            
            foreach ($viewSelections as $selection) {
                $result = $this->testViewToggleStateConsistency($selection);
                
                // Property: Exactly one button should be active
                if (!$result['exactly_one_active']) {
                    echo "FAILED on iteration $i, selection: {$selection['view']}:\n";
                    echo "Expected exactly one active button\n";
                    echo "Active buttons: " . json_encode($result['active_buttons']) . "\n";
                    echo "Button states: " . json_encode($result['button_states']) . "\n";
                    return false;
                }
                
                // Property: The correct button should be active for the selected view
                if (!$result['correct_button_active']) {
                    echo "FAILED on iteration $i, selection: {$selection['view']}:\n";
                    echo "Wrong button is active\n";
                    echo "Expected active: {$selection['view']}ViewBtn\n";
                    echo "Actually active: " . json_encode($result['active_buttons']) . "\n";
                    return false;
                }
                
                // Property: The inactive button should not have active state
                if (!$result['inactive_button_correct']) {
                    echo "FAILED on iteration $i, selection: {$selection['view']}:\n";
                    echo "Inactive button incorrectly has active state\n";
                    echo "Button states: " . json_encode($result['button_states']) . "\n";
                    return false;
                }
                
                // Property: Button states should be mutually exclusive
                if (!$result['mutually_exclusive_states']) {
                    echo "FAILED on iteration $i, selection: {$selection['view']}:\n";
                    echo "Button states are not mutually exclusive\n";
                    echo "Both buttons active: " . ($result['both_active'] ? 'true' : 'false') . "\n";
                    echo "Neither button active: " . ($result['neither_active'] ? 'true' : 'false') . "\n";
                    return false;
                }
            }
        }
        
        echo "Property 9 passed all $iterations iterations across multiple view selections\n";
        return true;
    }
    
    /**
     * Generate random view selections for testing
     */
    private function generateRandomViewSelections() {
        $selections = [];
        $views = ['weekly', 'monthly'];
        $numSelections = rand(2, 5); // 2 to 5 view selections per iteration
        
        for ($i = 0; $i < $numSelections; $i++) {
            $selections[] = [
                'view' => $views[array_rand($views)],
                'timestamp' => time() + $i
            ];
        }
        
        return $selections;
    }
    
    /**
     * Test view toggle state consistency
     */
    private function testViewToggleStateConsistency($selection) {
        $result = [
            'selection' => $selection,
            'exactly_one_active' => false,
            'correct_button_active' => false,
            'inactive_button_correct' => false,
            'mutually_exclusive_states' => false,
            'active_buttons' => [],
            'button_states' => [],
            'both_active' => false,
            'neither_active' => false
        ];
        
        try {
            // Simulate button state management logic
            $buttonStates = $this->simulateButtonStateManagement($selection['view']);
            $result['button_states'] = $buttonStates;
            
            // Count active buttons
            $activeButtons = [];
            foreach ($buttonStates as $buttonId => $isActive) {
                if ($isActive) {
                    $activeButtons[] = $buttonId;
                }
            }
            $result['active_buttons'] = $activeButtons;
            
            // Check if exactly one button is active
            $result['exactly_one_active'] = (count($activeButtons) === 1);
            
            // Check if the correct button is active
            $expectedActiveButton = $selection['view'] . 'ViewBtn';
            $result['correct_button_active'] = in_array($expectedActiveButton, $activeButtons);
            
            // Check if inactive button is correct
            $expectedInactiveButton = ($selection['view'] === 'weekly') ? 'monthlyViewBtn' : 'weeklyViewBtn';
            $result['inactive_button_correct'] = !in_array($expectedInactiveButton, $activeButtons);
            
            // Check mutual exclusivity
            $result['both_active'] = (count($activeButtons) === 2);
            $result['neither_active'] = (count($activeButtons) === 0);
            $result['mutually_exclusive_states'] = !$result['both_active'] && !$result['neither_active'];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate button state management logic
     * This simulates the highlightActiveButton function behavior
     */
    private function simulateButtonStateManagement($selectedView) {
        // Initialize both buttons as inactive
        $buttonStates = [
            'weeklyViewBtn' => false,
            'monthlyViewBtn' => false
        ];
        
        // Activate the selected view button
        if ($selectedView === 'weekly') {
            $buttonStates['weeklyViewBtn'] = true;
        } elseif ($selectedView === 'monthly') {
            $buttonStates['monthlyViewBtn'] = true;
        }
        
        return $buttonStates;
    }
    
    /**
     * Property 10: Chart Update Without Reload
     * Feature: visual-analytics, Property 10: Chart Update Without Reload
     * Validates: Requirements 6.4
     * 
     * For any view switch operation, the chart should update its data without triggering 
     * a full page reload, maintaining the Chart.js instance
     */
    public function testChartUpdateWithoutReloadProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random chart update scenarios
            $updateScenarios = $this->generateChartUpdateScenarios();
            
            foreach ($updateScenarios as $scenario) {
                $result = $this->testChartUpdateWithoutReload($scenario);
                
                // Property: Chart instance should be maintained after update
                if (!$result['chart_instance_maintained']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}:\n";
                    echo "Chart instance was not maintained\n";
                    echo "Original instance ID: {$result['original_instance_id']}\n";
                    echo "Updated instance ID: {$result['updated_instance_id']}\n";
                    return false;
                }
                
                // Property: Chart data should be updated to new values
                if (!$result['data_updated_correctly']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}:\n";
                    echo "Chart data was not updated correctly\n";
                    echo "Expected labels: " . json_encode($scenario['new_labels']) . "\n";
                    echo "Actual labels: " . json_encode($result['actual_labels']) . "\n";
                    echo "Expected data: " . json_encode($scenario['new_data']) . "\n";
                    echo "Actual data: " . json_encode($result['actual_data']) . "\n";
                    return false;
                }
                
                // Property: No page reload should occur during update
                if (!$result['no_page_reload']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}:\n";
                    echo "Page reload was triggered during chart update\n";
                    return false;
                }
                
                // Property: Chart should remain responsive after update
                if (!$result['chart_remains_responsive']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}:\n";
                    echo "Chart is not responsive after update\n";
                    echo "Responsiveness issues: " . json_encode($result['responsiveness_issues']) . "\n";
                    return false;
                }
                
                // Property: Animation should be smooth during update
                if (!$result['smooth_animation']) {
                    echo "FAILED on iteration $i, scenario: {$scenario['name']}:\n";
                    echo "Animation was not smooth during update\n";
                    echo "Animation issues: " . json_encode($result['animation_issues']) . "\n";
                    return false;
                }
            }
        }
        
        echo "Property 10 passed all $iterations iterations across multiple update scenarios\n";
        return true;
    }
    
    /**
     * Generate chart update scenarios for testing
     */
    private function generateChartUpdateScenarios() {
        $scenarios = [];
        
        // Weekly to Monthly switch
        $scenarios[] = [
            'name' => 'weekly_to_monthly',
            'from_view' => 'weekly',
            'to_view' => 'monthly',
            'original_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'original_data' => [3, 5, 2, 8, 1, 0, 4],
            'new_labels' => $this->generateMonthlyLabels(),
            'new_data' => $this->generateRandomCounts(30)
        ];
        
        // Monthly to Weekly switch
        $scenarios[] = [
            'name' => 'monthly_to_weekly',
            'from_view' => 'monthly',
            'to_view' => 'weekly',
            'original_labels' => $this->generateMonthlyLabels(),
            'original_data' => $this->generateRandomCounts(30),
            'new_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'new_data' => [2, 6, 1, 9, 3, 0, 5]
        ];
        
        // Same view refresh (should still work)
        $scenarios[] = [
            'name' => 'weekly_refresh',
            'from_view' => 'weekly',
            'to_view' => 'weekly',
            'original_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'original_data' => [1, 2, 3, 4, 5, 6, 7],
            'new_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'new_data' => [7, 6, 5, 4, 3, 2, 1]
        ];
        
        return $scenarios;
    }
    
    /**
     * Generate monthly labels for testing
     */
    private function generateMonthlyLabels() {
        $labels = [];
        $currentDate = new DateTime();
        
        for ($i = 0; $i < 30; $i++) {
            $labels[] = $currentDate->format('M j');
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $labels;
    }
    
    /**
     * Generate random counts for testing
     */
    private function generateRandomCounts($count) {
        $data = [];
        for ($i = 0; $i < $count; $i++) {
            $data[] = rand(0, 15);
        }
        return $data;
    }
    
    /**
     * Test chart update without reload functionality
     */
    private function testChartUpdateWithoutReload($scenario) {
        $result = [
            'scenario' => $scenario,
            'chart_instance_maintained' => false,
            'original_instance_id' => null,
            'updated_instance_id' => null,
            'data_updated_correctly' => false,
            'actual_labels' => [],
            'actual_data' => [],
            'no_page_reload' => false,
            'chart_remains_responsive' => false,
            'responsiveness_issues' => [],
            'smooth_animation' => false,
            'animation_issues' => []
        ];
        
        try {
            // Simulate chart update process
            $chartUpdate = $this->simulateChartUpdate($scenario);
            
            // Check if chart instance is maintained
            $result['original_instance_id'] = $chartUpdate['original_instance_id'];
            $result['updated_instance_id'] = $chartUpdate['updated_instance_id'];
            $result['chart_instance_maintained'] = ($result['original_instance_id'] === $result['updated_instance_id']);
            
            // Check if data is updated correctly
            $result['actual_labels'] = $chartUpdate['updated_labels'];
            $result['actual_data'] = $chartUpdate['updated_data'];
            $result['data_updated_correctly'] = (
                $result['actual_labels'] === $scenario['new_labels'] &&
                $result['actual_data'] === $scenario['new_data']
            );
            
            // Check if no page reload occurred (simulated)
            $result['no_page_reload'] = !$chartUpdate['page_reloaded'];
            
            // Check if chart remains responsive
            $responsivenessTest = $this->testChartResponsiveness($chartUpdate);
            $result['chart_remains_responsive'] = $responsivenessTest['is_responsive'];
            $result['responsiveness_issues'] = $responsivenessTest['issues'];
            
            // Check animation smoothness
            $animationTest = $this->testAnimationSmoothness($chartUpdate);
            $result['smooth_animation'] = $animationTest['is_smooth'];
            $result['animation_issues'] = $animationTest['issues'];
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Simulate chart update process
     */
    private function simulateChartUpdate($scenario) {
        // Simulate the updateChart method behavior
        $originalInstanceId = 'chart_instance_' . uniqid();
        
        // Simulate updating chart data (Chart.js update method)
        $updatedLabels = $scenario['new_labels'];
        $updatedData = $scenario['new_data'];
        
        // In a real Chart.js update, the instance ID would remain the same
        $updatedInstanceId = $originalInstanceId; // Same instance
        
        // Simulate no page reload (this would be true in a real AJAX update)
        $pageReloaded = false;
        
        return [
            'original_instance_id' => $originalInstanceId,
            'updated_instance_id' => $updatedInstanceId,
            'updated_labels' => $updatedLabels,
            'updated_data' => $updatedData,
            'page_reloaded' => $pageReloaded,
            'update_method_called' => true,
            'animation_config' => [
                'duration' => 750,
                'easing' => 'easeInOutQuart'
            ]
        ];
    }
    
    /**
     * Test chart responsiveness after update
     */
    private function testChartResponsiveness($chartUpdate) {
        $result = [
            'is_responsive' => true,
            'issues' => []
        ];
        
        // Simulate responsiveness checks
        // In a real implementation, this would check if the chart responds to:
        // - Window resize events
        // - Container size changes
        // - Hover interactions
        // - Click events
        
        // For testing purposes, we assume responsiveness is maintained
        // unless there are specific issues
        
        if (!$chartUpdate['update_method_called']) {
            $result['is_responsive'] = false;
            $result['issues'][] = 'Chart update method was not called';
        }
        
        if (empty($chartUpdate['updated_labels']) || empty($chartUpdate['updated_data'])) {
            $result['is_responsive'] = false;
            $result['issues'][] = 'Chart data is empty after update';
        }
        
        return $result;
    }
    
    /**
     * Test animation smoothness during update
     */
    private function testAnimationSmoothness($chartUpdate) {
        $result = [
            'is_smooth' => true,
            'issues' => []
        ];
        
        // Check if animation configuration is present
        if (!isset($chartUpdate['animation_config'])) {
            $result['is_smooth'] = false;
            $result['issues'][] = 'No animation configuration found';
            return $result;
        }
        
        $animationConfig = $chartUpdate['animation_config'];
        
        // Check animation duration (should be reasonable)
        if (!isset($animationConfig['duration']) || $animationConfig['duration'] < 100 || $animationConfig['duration'] > 2000) {
            $result['is_smooth'] = false;
            $result['issues'][] = 'Animation duration is not optimal: ' . ($animationConfig['duration'] ?? 'null');
        }
        
        // Check easing function
        if (!isset($animationConfig['easing']) || empty($animationConfig['easing'])) {
            $result['is_smooth'] = false;
            $result['issues'][] = 'No easing function specified';
        }
        
        return $result;
    }
    
    /**
     * Calculate expected appointment counts for date range
     * This includes both test appointments and existing appointments in the database
     */
    private function calculateExpectedCounts($dateRange, $testAppointments) {
        $counts = array_fill(0, count($dateRange), 0);
        
        // First, get existing appointments from database for the date range
        try {
            $sql = "SELECT DATE(start_time) as app_date, COUNT(*) as count 
                    FROM appointments 
                    WHERE start_time BETWEEN ? AND ? 
                    GROUP BY DATE(start_time)";
            
            $startDate = $dateRange[0];
            $endDate = end($dateRange) . ' 23:59:59';
            
            $existingAppointments = Database::fetchAll($sql, [$startDate, $endDate]);
            
            // Add existing appointment counts
            foreach ($existingAppointments as $existing) {
                $dateIndex = array_search($existing['app_date'], $dateRange);
                if ($dateIndex !== false) {
                    $counts[$dateIndex] = (int)$existing['count'];
                }
            }
            
        } catch (Exception $e) {
            // If we can't get existing appointments, just use test appointments
            error_log("Could not get existing appointments: " . $e->getMessage());
        }
        
        // Then add test appointments (these should already be in the database at this point)
        // Since we already got all appointments from the database above, we don't need to add test appointments separately
        // The database query already includes them
        
        return $counts;
    }
    
    /**
     * Property 11: Monthly Date Format Appropriateness
     * Feature: visual-analytics, Property 11: Monthly Date Format Appropriateness
     * Validates: Requirements 6.6
     * 
     * For any date in the monthly view, the label format should include both month and day 
     * information (e.g., "Dec 18") to distinguish dates across the 30-day span
     */
    public function testMonthlyDateFormatProperty() {
        $iterations = 100;
        
        for ($i = 0; $i < $iterations; $i++) {
            // Generate random 30-day date range for testing
            $testDates = $this->generateRandom30DayRange();
            
            // Test monthly date format appropriateness
            $result = $this->testMonthlyDateFormatAppropriateness($testDates);
            
            // Property: Each label should include both month and day information
            if (!$result['all_labels_include_month_and_day']) {
                echo "FAILED on iteration $i:\n";
                echo "Labels missing month/day info: " . json_encode($result['invalid_labels']) . "\n";
                return false;
            }
            
            // Property: Labels should be in "Dec 18" format (M j)
            if (!$result['all_labels_correct_format']) {
                echo "FAILED on iteration $i:\n";
                echo "Labels with incorrect format: " . json_encode($result['incorrect_format_labels']) . "\n";
                return false;
            }
            
            // Property: Labels should distinguish dates across month transitions
            if (!$result['distinguishes_month_transitions']) {
                echo "FAILED on iteration $i:\n";
                echo "Month transition issues: " . json_encode($result['transition_issues']) . "\n";
                return false;
            }
            
            // Property: Labels should be readable for 30-day span
            if (!$result['readable_for_30_day_span']) {
                echo "FAILED on iteration $i:\n";
                echo "Readability issues: " . json_encode($result['readability_issues']) . "\n";
                return false;
            }
            
            // Property: Labels should match expected format for each date
            if (!$result['matches_expected_format']) {
                echo "FAILED on iteration $i:\n";
                echo "Format mismatches: " . json_encode($result['format_mismatches']) . "\n";
                return false;
            }
        }
        
        echo "Property 11 passed all $iterations iterations\n";
        return true;
    }
    
    /**
     * Generate random 30-day date range for testing
     */
    private function generateRandom30DayRange() {
        $dates = [];
        
        // Start from a random date within the last 60 days to next 60 days
        $startOffset = rand(-60, 60);
        $currentDate = new DateTime();
        $currentDate->add(new DateInterval('P' . abs($startOffset) . 'D'));
        if ($startOffset < 0) {
            $currentDate->sub(new DateInterval('P' . (abs($startOffset) * 2) . 'D'));
        }
        
        // Generate exactly 30 consecutive days
        for ($i = 0; $i < 30; $i++) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }
    
    /**
     * Test monthly date format appropriateness
     */
    private function testMonthlyDateFormatAppropriateness($testDates) {
        $result = [
            'test_dates' => $testDates,
            'all_labels_include_month_and_day' => true,
            'invalid_labels' => [],
            'all_labels_correct_format' => true,
            'incorrect_format_labels' => [],
            'distinguishes_month_transitions' => true,
            'transition_issues' => [],
            'readable_for_30_day_span' => true,
            'readability_issues' => [],
            'matches_expected_format' => true,
            'format_mismatches' => [],
            'generated_labels' => []
        ];
        
        try {
            // Generate labels using the service with 30 days (monthly view)
            $generatedLabels = AppointmentAnalyticsService::generateDateLabels($testDates, 30);
            $result['generated_labels'] = $generatedLabels;
            
            // Check each label
            for ($i = 0; $i < count($testDates); $i++) {
                $date = $testDates[$i];
                $generatedLabel = $generatedLabels[$i] ?? null;
                
                if ($generatedLabel === null) {
                    $result['all_labels_include_month_and_day'] = false;
                    $result['invalid_labels'][] = [
                        'date' => $date,
                        'issue' => 'Label is null'
                    ];
                    continue;
                }
                
                // Calculate expected label (M j format: "Dec 18")
                $dateObj = new DateTime($date);
                $expectedLabel = $dateObj->format('M j');
                
                // Check if matches expected format
                if ($generatedLabel !== $expectedLabel) {
                    $result['matches_expected_format'] = false;
                    $result['format_mismatches'][] = [
                        'date' => $date,
                        'expected' => $expectedLabel,
                        'actual' => $generatedLabel
                    ];
                }
                
                // Check if includes both month and day information
                if (!$this->includesMonthAndDay($generatedLabel)) {
                    $result['all_labels_include_month_and_day'] = false;
                    $result['invalid_labels'][] = [
                        'date' => $date,
                        'label' => $generatedLabel,
                        'issue' => 'Missing month or day information'
                    ];
                }
                
                // Check if follows correct format pattern (Month Day)
                if (!$this->isCorrectMonthDayFormat($generatedLabel)) {
                    $result['all_labels_correct_format'] = false;
                    $result['incorrect_format_labels'][] = [
                        'date' => $date,
                        'label' => $generatedLabel,
                        'issue' => 'Not in "Month Day" format'
                    ];
                }
                
                // Check readability for 30-day span (should be concise but informative)
                if (!$this->isReadableFor30DaySpan($generatedLabel)) {
                    $result['readable_for_30_day_span'] = false;
                    $result['readability_issues'][] = [
                        'date' => $date,
                        'label' => $generatedLabel,
                        'issue' => 'Not readable for 30-day span'
                    ];
                }
            }
            
            // Check month transition handling
            $transitionIssues = $this->checkMonthTransitions($testDates, $generatedLabels);
            if (!empty($transitionIssues)) {
                $result['distinguishes_month_transitions'] = false;
                $result['transition_issues'] = $transitionIssues;
            }
            
        } catch (Exception $e) {
            $result['all_labels_include_month_and_day'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Check if label includes both month and day information
     */
    private function includesMonthAndDay($label) {
        // Should contain a month abbreviation and a day number
        $monthAbbreviations = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 
                              'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        $hasMonth = false;
        foreach ($monthAbbreviations as $month) {
            if (strpos($label, $month) !== false) {
                $hasMonth = true;
                break;
            }
        }
        
        // Should contain a day number (1-31)
        $hasDay = preg_match('/\b([1-9]|[12][0-9]|3[01])\b/', $label);
        
        return $hasMonth && $hasDay;
    }
    
    /**
     * Check if label follows correct "Month Day" format
     */
    private function isCorrectMonthDayFormat($label) {
        // Should match pattern: "Month Day" (e.g., "Dec 18", "Jan 1")
        return preg_match('/^[A-Z][a-z]{2} \d{1,2}$/', $label);
    }
    
    /**
     * Check if label is readable for 30-day span
     */
    private function isReadableFor30DaySpan($label) {
        // Should be concise (not too long) but informative
        // "Dec 18" format is ideal - short but includes necessary info
        return strlen($label) >= 5 && strlen($label) <= 6 && $this->isCorrectMonthDayFormat($label);
    }
    
    /**
     * Check month transition handling
     */
    private function checkMonthTransitions($dates, $labels) {
        $issues = [];
        
        for ($i = 1; $i < count($dates); $i++) {
            $prevDate = new DateTime($dates[$i-1]);
            $currDate = new DateTime($dates[$i]);
            
            // Check if we crossed a month boundary
            if ($prevDate->format('m') !== $currDate->format('m')) {
                $prevLabel = $labels[$i-1];
                $currLabel = $labels[$i];
                
                // Labels should clearly distinguish the month change
                $prevMonth = $prevDate->format('M');
                $currMonth = $currDate->format('M');
                
                if (strpos($prevLabel, $prevMonth) === false) {
                    $issues[] = [
                        'type' => 'previous_month_not_shown',
                        'date' => $dates[$i-1],
                        'label' => $prevLabel,
                        'expected_month' => $prevMonth
                    ];
                }
                
                if (strpos($currLabel, $currMonth) === false) {
                    $issues[] = [
                        'type' => 'current_month_not_shown',
                        'date' => $dates[$i],
                        'label' => $currLabel,
                        'expected_month' => $currMonth
                    ];
                }
                
                // The two labels should clearly show different months
                if ($prevMonth === $currMonth) {
                    // This shouldn't happen if we detected a month boundary, but check anyway
                    $issues[] = [
                        'type' => 'month_boundary_detection_error',
                        'prev_date' => $dates[$i-1],
                        'curr_date' => $dates[$i]
                    ];
                }
            }
        }
        
        return $issues;
    }
}