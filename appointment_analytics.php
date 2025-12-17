<?php
/**
 * Appointment Analytics Service for Visual Analytics Feature
 * Handles data retrieval and processing for appointment visualizations
 */

require_once 'config.php';
require_once 'db.php';

class AppointmentAnalyticsService {
    
    /**
     * Get appointment counts for the next 7 days
     * Returns array with complete 7-day coverage, filling missing dates with zero counts
     * 
     * @return array Array with 'labels' and 'counts' keys for Chart.js consumption
     * @throws Exception If database query fails
     */
    public static function getNext7DaysAppointmentCounts() {
        try {
            // Generate complete 7-day date range starting from today
            $dateRange = self::generateNext7DaysRange();
            
            // Query appointment counts for the next 7 days with date grouping
            $sql = "SELECT DATE(start_time) as app_date, COUNT(*) as count 
                    FROM appointments 
                    WHERE start_time BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                    GROUP BY DATE(start_time)
                    ORDER BY app_date ASC";
            
            $appointmentData = Database::fetchAll($sql, [], 'admin');
            
            // Create associative array for quick lookup
            $appointmentCounts = [];
            foreach ($appointmentData as $row) {
                $appointmentCounts[$row['app_date']] = (int)$row['count'];
            }
            
            // Fill missing dates with zero counts to ensure complete 7-day coverage
            $counts = [];
            foreach ($dateRange as $date) {
                $counts[] = isset($appointmentCounts[$date]) ? $appointmentCounts[$date] : 0;
            }
            
            // Generate date labels for display
            $labels = self::generateDateLabels($dateRange);
            
            return [
                'labels' => $labels,
                'counts' => $counts,
                'date_range' => $dateRange
            ];
            
        } catch (PDOException $e) {
            // Log database-specific errors without exposing sensitive information
            error_log("Database error in appointment analytics: " . $e->getMessage());
            throw new Exception("Database connection failed while retrieving analytics data");
        } catch (Exception $e) {
            // Log general errors
            error_log("Appointment analytics query failed: " . $e->getMessage());
            throw new Exception("Unable to retrieve appointment analytics data");
        }
    }
    
    /**
     * Generate array of next 7 days starting from today
     * 
     * @return array Array of date strings in Y-m-d format
     */
    private static function generateNext7DaysRange() {
        $dates = [];
        $currentDate = new DateTime();
        
        for ($i = 0; $i < 7; $i++) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }
        
        return $dates;
    }
    
    /**
     * Convert dates to abbreviated day names for user-friendly labels
     * Handles proper timezone using existing system timezone
     * 
     * @param array $dates Array of date strings in Y-m-d format
     * @return array Array of abbreviated day names (Mon, Tue, Wed, etc.)
     */
    public static function generateDateLabels($dates) {
        $labels = [];
        
        foreach ($dates as $date) {
            try {
                $dateObj = new DateTime($date);
                // Get abbreviated day name (Mon, Tue, Wed, etc.)
                $labels[] = $dateObj->format('D');
            } catch (Exception $e) {
                // Fallback to original date if parsing fails
                $labels[] = $date;
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
     * @return array Complete analytics data ready for Chart.js
     */
    public static function getAnalyticsData() {
        try {
            $data = self::getNext7DaysAppointmentCounts();
            $chartData = self::formatDataForChart($data['labels'], $data['counts']);
            
            return array_merge($data, $chartData);
        } catch (Exception $e) {
            // Log the error and return fallback data
            error_log("Analytics data processing failed: " . $e->getMessage());
            return self::getFallbackAnalyticsData();
        }
    }
    
    /**
     * Provide fallback analytics data when database queries fail
     * Returns empty data structure to prevent dashboard layout breaks
     * 
     * @return array Fallback analytics data with zero counts
     */
    public static function getFallbackAnalyticsData() {
        // Generate 7-day date range for fallback
        $dateRange = self::generateNext7DaysRange();
        $labels = self::generateDateLabels($dateRange);
        $counts = array_fill(0, 7, 0); // All zeros for fallback
        
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
}