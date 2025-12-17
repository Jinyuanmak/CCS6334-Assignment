<?php
/**
 * Appointment Analytics Service for Visual Analytics Feature
 * Handles data retrieval and processing for appointment visualizations
 */

require_once 'config.php';
require_once 'db.php';

class AppointmentAnalyticsService {
    
    /**
     * Get appointment counts for the specified view type
     * Returns array with complete date coverage, filling missing dates with zero counts
     * 
     * @param string $viewType View type: 'weekly' for 7 days or 'monthly' for 12 months
     * @return array Array with 'labels' and 'counts' keys for Chart.js consumption
     * @throws Exception If database query fails
     */
    public static function getAppointmentCounts($viewType = 'weekly') {
        try {
            // Handle backward compatibility - convert numeric days to view type
            if (is_numeric($viewType)) {
                $viewType = ($viewType == 7) ? 'weekly' : 'monthly';
            }
            
            // Validate view type parameter
            if (!in_array($viewType, ['weekly', 'monthly'])) {
                throw new Exception("Invalid view type parameter. Must be 'weekly' or 'monthly'.");
            }
            
            if ($viewType === 'weekly') {
                return self::getWeeklyAppointmentCounts();
            } else {
                return self::getMonthlyAppointmentCounts();
            }
            
        } catch (Exception $e) {
            // Log general errors
            error_log("Appointment analytics query failed: " . $e->getMessage());
            throw new Exception("Unable to retrieve appointment analytics data");
        }
    }

    /**
     * Get appointment counts for weekly view (current week Mon-Sun)
     * 
     * @return array Array with 'labels' and 'counts' keys for Chart.js consumption
     * @throws Exception If database query fails
     */
    private static function getWeeklyAppointmentCounts() {
        try {
            // Generate complete week range (Monday to Sunday of current week)
            $weekRange = self::generateCurrentWeekRange();
            
            // Query appointment counts for the current week with date grouping
            $sql = "SELECT DATE(start_time) as app_date, COUNT(*) as count 
                    FROM appointments 
                    WHERE DATE(start_time) BETWEEN ? AND ?
                    GROUP BY DATE(start_time)
                    ORDER BY app_date ASC";
            
            $startDate = $weekRange[0]; // Monday
            $endDate = $weekRange[6];   // Sunday
            
            $appointmentData = Database::fetchAll($sql, [$startDate, $endDate], 'admin');
            
            // Create associative array for quick lookup
            $appointmentCounts = [];
            foreach ($appointmentData as $row) {
                $appointmentCounts[$row['app_date']] = (int)$row['count'];
            }
            
            // Fill missing dates with zero counts to ensure complete coverage
            $counts = [];
            foreach ($weekRange as $date) {
                $counts[] = isset($appointmentCounts[$date]) ? $appointmentCounts[$date] : 0;
            }
            
            // Generate fixed labels for weekly view (always Mon, Tue, Wed, Thu, Fri, Sat, Sun)
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            
            return [
                'labels' => $labels,
                'counts' => $counts,
                'date_range' => $weekRange
            ];
            
        } catch (PDOException $e) {
            // Log database-specific errors without exposing sensitive information
            error_log("Database error in weekly appointment analytics: " . $e->getMessage());
            throw new Exception("Database connection failed while retrieving weekly analytics data");
        }
    }

    /**
     * Get appointment counts for monthly view (current calendar year Jan-Dec)
     * 
     * @return array Array with 'labels' and 'counts' keys for Chart.js consumption
     * @throws Exception If database query fails
     */
    private static function getMonthlyAppointmentCounts() {
        try {
            // Generate current calendar year month range (Jan-Dec)
            $monthRange = self::generateCalendarYearRange();
            
            // Query appointment counts grouped by calendar months for current year
            $currentYear = date('Y');
            $sql = "SELECT MONTH(start_time) as app_month, COUNT(*) as count 
                    FROM appointments 
                    WHERE YEAR(start_time) = ?
                    GROUP BY MONTH(start_time)
                    ORDER BY app_month ASC";
            
            $appointmentData = Database::fetchAll($sql, [$currentYear], 'admin');
            
            // Create associative array for quick lookup using month number
            $appointmentCounts = [];
            foreach ($appointmentData as $row) {
                $appointmentCounts[(int)$row['app_month']] = (int)$row['count'];
            }
            
            // Fill missing months with zero counts to ensure complete coverage (Jan=1 to Dec=12)
            $counts = [];
            for ($month = 1; $month <= 12; $month++) {
                $counts[] = isset($appointmentCounts[$month]) ? $appointmentCounts[$month] : 0;
            }
            
            // Generate fixed labels for monthly view (always Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'July', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            
            return [
                'labels' => $labels,
                'counts' => $counts,
                'date_range' => $monthRange
            ];
            
        } catch (PDOException $e) {
            // Log database-specific errors without exposing sensitive information
            error_log("Database error in monthly appointment analytics: " . $e->getMessage());
            throw new Exception("Database connection failed while retrieving monthly analytics data");
        }
    }

    /**
     * Get appointment counts for the next 7 days (backward compatibility)
     * 
     * @return array Array with 'labels' and 'counts' keys for Chart.js consumption
     * @throws Exception If database query fails
     */
    public static function getNext7DaysAppointmentCounts() {
        return self::getAppointmentCounts(7);
    }
    
    /**
     * Generate array of next N days starting from today
     * 
     * @param int $days Number of days to generate
     * @return array Array of date strings in Y-m-d format
     */
    private static function generateDateRange($days) {
        $dates = [];
        $currentDate = new DateTime();
        
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }

    /**
     * Generate array of current week dates (Monday to Sunday)
     * 
     * @return array Array of date strings in Y-m-d format for current week
     */
    private static function generateCurrentWeekRange() {
        $dates = [];
        $today = new DateTime();
        
        // Get the current day of week (1 = Monday, 7 = Sunday)
        $dayOfWeek = $today->format('N');
        
        // Calculate Monday of current week
        $monday = clone $today;
        $monday->sub(new DateInterval('P' . ($dayOfWeek - 1) . 'D'));
        
        // Generate all 7 days of the week (Monday to Sunday)
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $monday->format('Y-m-d');
            $monday->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }

    /**
     * Generate array of current calendar year months (Jan-Dec)
     * 
     * @return array Array of month strings in YYYY-MM format for current year
     */
    private static function generateCalendarYearRange() {
        $months = [];
        $currentYear = date('Y');
        
        // Generate all 12 months of current year (Jan-Dec)
        for ($month = 1; $month <= 12; $month++) {
            $months[] = sprintf('%s-%02d', $currentYear, $month);
        }
        
        return $months;
    }

    /**
     * Generate array of next 12 calendar months starting from current month (legacy)
     * 
     * @return array Array of month strings in YYYY-MM format
     */
    private static function generateMonthRange() {
        $months = [];
        $currentDate = new DateTime();
        // Set to first day of current month
        $currentDate->setDate($currentDate->format('Y'), $currentDate->format('n'), 1);
        
        for ($i = 0; $i < 12; $i++) {
            $months[] = $currentDate->format('Y-m');
            $currentDate->add(new DateInterval('P1M'));
        }
        
        return $months;
    }

    /**
     * Generate array of next 7 days starting from today (backward compatibility)
     * 
     * @return array Array of date strings in Y-m-d format
     */
    private static function generateNext7DaysRange() {
        return self::generateDateRange(7);
    }
    
    /**
     * Convert dates to appropriate labels for weekly view
     * Weekly view: abbreviated day names (Mon, Tue, Wed, Thu, Fri, Sat, Sun)
     * 
     * @param array $dates Array of date strings in Y-m-d format
     * @param int $days Number of days (7 for weekly view)
     * @return array Array of formatted date labels
     */
    public static function generateDateLabels($dates, $days = 7) {
        $labels = [];
        
        foreach ($dates as $date) {
            try {
                $dateObj = new DateTime($date);
                // Weekly view: abbreviated day names (Mon, Tue, Wed, Thu, Fri, Sat, Sun)
                $labels[] = $dateObj->format('D');
            } catch (Exception $e) {
                // Fallback to original date if parsing fails
                $labels[] = $date;
            }
        }
        
        return $labels;
    }

    /**
     * Convert month keys to appropriate labels for monthly view
     * Monthly view: abbreviated month names (Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
     * 
     * @param array $monthKeys Array of month strings in YYYY-MM format
     * @return array Array of formatted month labels
     */
    public static function generateMonthLabels($monthKeys) {
        $labels = [];
        
        foreach ($monthKeys as $monthKey) {
            try {
                // Parse YYYY-MM format and create first day of month
                $dateObj = new DateTime($monthKey . '-01');
                // Generate abbreviated month name (Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
                $monthName = $dateObj->format('M');
                // Special case for July to match requirements
                if ($monthName === 'Jul') {
                    $monthName = 'July';
                }
                $labels[] = $monthName;
            } catch (Exception $e) {
                // Fallback to original month key if parsing fails
                $labels[] = $monthKey;
            }
        }
        
        return $labels;
    }
    
    /**
     * Format processed data into JSON arrays for Chart.js consumption
     * Ensures proper JSON encoding for JavaScript consumption
     * 
     * @param array $labels Array of date labels
     * @param array $counts Array of appointment counts
     * @return array Array with 'json_labels' and 'json_counts' keys
     */
    public static function formatDataForChart($labels, $counts) {
        // Validate that arrays have equal length
        if (count($labels) !== count($counts)) {
            throw new Exception("Labels and counts arrays must have equal length");
        }
        
        return [
            'json_labels' => json_encode($labels),
            'json_counts' => json_encode($counts)
        ];
    }
    
    /**
     * Get complete analytics data formatted for Chart.js
     * Convenience method that combines all processing steps
     * 
     * @param string $viewType View type: 'weekly' for 7 days or 'monthly' for 12 months
     * @return array Complete analytics data ready for Chart.js
     */
    public static function getAnalyticsData($viewType = 'weekly') {
        try {
            // Handle backward compatibility - convert numeric days to view type
            if (is_numeric($viewType)) {
                $viewType = ($viewType == 7) ? 'weekly' : 'monthly';
            }
            
            $data = self::getAppointmentCounts($viewType);
            $chartData = self::formatDataForChart($data['labels'], $data['counts']);
            
            return array_merge($data, $chartData);
        } catch (Exception $e) {
            // Log the error and return fallback data
            error_log("Analytics data processing failed: " . $e->getMessage());
            return self::getFallbackAnalyticsData($viewType);
        }
    }
    
    /**
     * Provide fallback analytics data when database queries fail
     * Returns empty data structure to prevent dashboard layout breaks
     * 
     * @param string $viewType View type: 'weekly' for 7 days or 'monthly' for 12 months
     * @return array Fallback analytics data with zero counts
     */
    public static function getFallbackAnalyticsData($viewType = 'weekly') {
        // Handle backward compatibility - convert numeric days to view type
        if (is_numeric($viewType)) {
            $viewType = ($viewType == 7) ? 'weekly' : 'monthly';
        }
        
        if ($viewType === 'weekly') {
            // Generate current week range for weekly fallback (Monday to Sunday)
            $dateRange = self::generateCurrentWeekRange();
            $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']; // Fixed labels
            $counts = array_fill(0, 7, 0); // All zeros for fallback
        } else {
            // Generate calendar year range for monthly fallback (Jan-Dec)
            $monthRange = self::generateCalendarYearRange();
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'July', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; // Fixed labels
            $counts = array_fill(0, 12, 0); // All zeros for fallback
            $dateRange = $monthRange; // Use month range as date range for monthly view
        }
        
        $chartData = self::formatDataForChart($labels, $counts);
        
        return [
            'labels' => $labels,
            'counts' => $counts,
            'date_range' => $dateRange,
            'json_labels' => $chartData['json_labels'],
            'json_counts' => $chartData['json_counts'],
            'is_fallback' => true // Flag to indicate this is fallback data
        ];
    }

    /**
     * Handle AJAX requests for dynamic data fetching
     * Returns JSON response with labels and counts for requested period
     */
    public static function handleAjaxRequest() {
        // Check if this is an AJAX request for chart data
        if (isset($_GET['mode']) && $_GET['mode'] === 'chart') {
            // Ensure user is authenticated (handle AJAX context)
            session_start();
            if (!Database::checkSessionTimeout()) {
                if (!headers_sent()) {
                    http_response_code(401);
                }
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
            
            // Get view type parameter - support both new viewType and legacy days parameter
            $viewType = 'weekly'; // default
            
            if (isset($_GET['view'])) {
                $viewType = $_GET['view'];
            } elseif (isset($_GET['viewType'])) {
                $viewType = $_GET['viewType'];
            } elseif (isset($_GET['days'])) {
                // Backward compatibility: convert days to view type
                $days = (int)$_GET['days'];
                $viewType = ($days == 7) ? 'weekly' : 'monthly';
            }
            
            // Validate view type parameter
            if (!in_array($viewType, ['weekly', 'monthly'])) {
                if (!headers_sent()) {
                    http_response_code(400);
                }
                echo json_encode(['error' => 'Invalid view type parameter. Must be "weekly" or "monthly".']);
                exit;
            }
            
            try {
                $data = self::getAnalyticsData($viewType);
                
                // Return JSON response (only set header if not already sent)
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode([
                    'success' => true,
                    'labels' => $data['labels'],
                    'counts' => $data['counts'],
                    'is_fallback' => isset($data['is_fallback']) ? $data['is_fallback'] : false
                ]);
                exit;
            } catch (Exception $e) {
                if (!headers_sent()) {
                    http_response_code(500);
                }
                echo json_encode(['error' => 'Unable to retrieve analytics data']);
                exit;
            }
        }
    }
}

// Handle AJAX requests if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === 'appointment_analytics.php') {
    AppointmentAnalyticsService::handleAjaxRequest();
}