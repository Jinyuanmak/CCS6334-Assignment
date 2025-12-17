<?php
/**
 * Debug script for appointment submission issues
 * This will help identify what data is being received from the form
 */

require_once 'config.php';
require_once 'db.php';

// Check authentication
Database::requireAuth();

echo "<h2>Appointment Form Debug Information</h2>";
echo "<hr>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data Received:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>Sanitized Values:</h3>";
    $patientId = Database::sanitizeInput($_POST['patient_id'] ?? '');
    $appointmentDate = Database::sanitizeInput($_POST['appointment_date'] ?? '');
    $appointmentTime = Database::sanitizeInput($_POST['appointment_time'] ?? '');
    $doctorName = Database::sanitizeInput($_POST['doctor_name'] ?? '');
    $reason = Database::sanitizeInput($_POST['reason'] ?? '');
    $duration = $_POST['duration'] ?? 60;
    
    echo "Patient ID: " . var_export($patientId, true) . "<br>";
    echo "Appointment Date: " . var_export($appointmentDate, true) . "<br>";
    echo "Appointment Time: " . var_export($appointmentTime, true) . "<br>";
    echo "Doctor Name: " . var_export($doctorName, true) . "<br>";
    echo "Reason (length): " . strlen($reason) . " characters<br>";
    echo "Reason (first 100 chars): " . htmlspecialchars(substr($reason, 0, 100)) . "<br>";
    echo "Duration: " . var_export($duration, true) . "<br>";
    
    echo "<h3>Validation Results:</h3>";
    $errors = [];
    
    if (empty($patientId)) {
        $errors[] = "Patient ID is empty";
    }
    if (empty($appointmentDate)) {
        $errors[] = "Appointment date is empty";
    }
    if (empty($appointmentTime)) {
        $errors[] = "Appointment time is empty";
    }
    if (empty($doctorName)) {
        $errors[] = "Doctor name is empty";
    }
    if (empty($reason)) {
        $errors[] = "Reason is empty";
    }
    
    if (empty($errors)) {
        echo "<p style='color: green;'>✓ All required fields are present</p>";
        
        // Test database connection
        try {
            $testQuery = Database::fetchOne("SELECT 1 as test");
            echo "<p style='color: green;'>✓ Database connection successful</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
        }
        
        // Test encryption
        try {
            $testEncrypt = Database::fetchOne("SELECT AES_ENCRYPT('test', ?) as encrypted", [ENCRYPTION_KEY]);
            echo "<p style='color: green;'>✓ Encryption test successful</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Encryption test failed: " . $e->getMessage() . "</p>";
        }
        
        // Test patient exists
        try {
            $patient = Database::fetchOne("SELECT id, name FROM patients WHERE id = ?", [$patientId]);
            if ($patient) {
                echo "<p style='color: green;'>✓ Patient found: " . htmlspecialchars($patient['name']) . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Patient not found with ID: " . $patientId . "</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Error checking patient: " . $e->getMessage() . "</p>";
        }
        
        // Test the actual INSERT query
        echo "<h3>Testing Appointment Insert Query:</h3>";
        try {
            $startTime = "{$appointmentDate} {$appointmentTime}";
            $endTime = date('Y-m-d H:i:s', strtotime($startTime) + ($duration * 60));
            
            echo "<p>Start Time: " . $startTime . "</p>";
            echo "<p>End Time: " . $endTime . "</p>";
            
            // Try to find doctor ID
            $doctorId = null;
            $cleanDoctorName = $doctorName;
            if (strpos($doctorName, '(') !== false) {
                $cleanDoctorName = trim(substr($doctorName, 0, strpos($doctorName, '(')));
            }
            
            $doctorQuery = Database::fetchOne("SELECT id FROM doctors WHERE name = ? LIMIT 1", [$cleanDoctorName]);
            if ($doctorQuery) {
                $doctorId = $doctorQuery['id'];
                echo "<p style='color: green;'>✓ Doctor found with ID: " . $doctorId . "</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Doctor not found in database, will insert with NULL doctor_id</p>";
            }
            
            // Test the INSERT query (without actually inserting)
            $sql = "INSERT INTO appointments (patient_id, appointment_date, start_time, end_time, doctor_name, doctor_id, reason) 
                    VALUES (?, ?, ?, ?, ?, ?, AES_ENCRYPT(?, ?))";
            
            echo "<p>SQL Query: " . htmlspecialchars($sql) . "</p>";
            echo "<p>Parameters:</p>";
            echo "<ol>";
            echo "<li>patient_id: " . var_export($patientId, true) . "</li>";
            echo "<li>appointment_date: " . var_export($startTime, true) . "</li>";
            echo "<li>start_time: " . var_export($startTime, true) . "</li>";
            echo "<li>end_time: " . var_export($endTime, true) . "</li>";
            echo "<li>doctor_name: " . var_export($doctorName, true) . "</li>";
            echo "<li>doctor_id: " . var_export($doctorId, true) . "</li>";
            echo "<li>reason: [" . strlen($reason) . " characters]</li>";
            echo "<li>ENCRYPTION_KEY: [" . strlen(ENCRYPTION_KEY) . " characters]</li>";
            echo "</ol>";
            
            // Try to execute the query
            echo "<p><strong>Attempting to insert appointment...</strong></p>";
            $result = Database::executeUpdate($sql, [
                $patientId,
                $startTime,
                $startTime,
                $endTime,
                $doctorName,
                $doctorId,
                $reason,
                ENCRYPTION_KEY
            ]);
            
            if ($result > 0) {
                echo "<p style='color: green; font-weight: bold;'>✓ SUCCESS! Appointment inserted successfully!</p>";
                echo "<p>Inserted appointment ID: " . Database::getLastInsertId() . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Insert returned 0 rows affected</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red; font-weight: bold;'>✗ INSERT FAILED: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p>This is the actual error preventing appointments from being created.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>Validation Errors:</p>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
    
} else {
    echo "<p>No POST data received. Please submit the appointment form.</p>";
}

echo "<hr>";
echo "<a href='add_appointment.php'>Back to Add Appointment</a> | ";
echo "<a href='dashboard.php'>Back to Dashboard</a>";
?>
