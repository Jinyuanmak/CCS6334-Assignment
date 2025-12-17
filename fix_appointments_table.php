<?php
/**
 * Fix appointments table structure if needed
 * This script will check and fix common issues with the appointments table
 */

require_once 'config.php';
require_once 'db.php';

Database::requireAuth();

echo "<h2>Appointments Table Fix Script</h2>";
echo "<hr>";

try {
    // Check if table exists
    $tables = Database::fetchAll("SHOW TABLES LIKE 'appointments'", [], 'root');
    
    if (empty($tables)) {
        echo "<p style='color: red;'>✗ Appointments table does not exist!</p>";
        echo "<p>Creating appointments table...</p>";
        
        $createTableSQL = "CREATE TABLE IF NOT EXISTS appointments (
            appointment_id INT AUTO_INCREMENT PRIMARY KEY,
            patient_id INT NOT NULL,
            appointment_date DATETIME NOT NULL,
            start_time DATETIME NOT NULL,
            end_time DATETIME NOT NULL,
            doctor_name VARCHAR(100) NOT NULL,
            doctor_id INT,
            reason BLOB NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
        )";
        
        Database::executeQuery($createTableSQL, [], 'root');
        echo "<p style='color: green;'>✓ Appointments table created successfully!</p>";
        
    } else {
        echo "<p style='color: green;'>✓ Appointments table exists</p>";
        
        // Get current structure
        $columns = Database::fetchAll("DESCRIBE appointments", [], 'root');
        $columnNames = array_column($columns, 'Field');
        
        // Check for missing columns and add them
        $fixes = [];
        
        if (!in_array('start_time', $columnNames)) {
            $fixes[] = "ALTER TABLE appointments ADD COLUMN start_time DATETIME NOT NULL AFTER appointment_date";
            echo "<p style='color: orange;'>⚠ Missing column: start_time</p>";
        }
        
        if (!in_array('end_time', $columnNames)) {
            $fixes[] = "ALTER TABLE appointments ADD COLUMN end_time DATETIME NOT NULL AFTER start_time";
            echo "<p style='color: orange;'>⚠ Missing column: end_time</p>";
        }
        
        if (!in_array('doctor_id', $columnNames)) {
            $fixes[] = "ALTER TABLE appointments ADD COLUMN doctor_id INT AFTER doctor_name";
            echo "<p style='color: orange;'>⚠ Missing column: doctor_id</p>";
        }
        
        // Apply fixes
        if (!empty($fixes)) {
            echo "<h3>Applying Fixes:</h3>";
            foreach ($fixes as $fix) {
                try {
                    Database::executeQuery($fix, [], 'root');
                    echo "<p style='color: green;'>✓ Applied: " . htmlspecialchars($fix) . "</p>";
                } catch (Exception $e) {
                    echo "<p style='color: red;'>✗ Failed: " . htmlspecialchars($fix) . "</p>";
                    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        } else {
            echo "<p style='color: green;'>✓ All required columns exist</p>";
        }
    }
    
    // Create indexes if they don't exist
    echo "<h3>Checking Indexes:</h3>";
    try {
        Database::executeQuery("CREATE INDEX IF NOT EXISTS idx_appointments_patient ON appointments(patient_id)", [], 'root');
        echo "<p style='color: green;'>✓ Patient index created/verified</p>";
        
        Database::executeQuery("CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date)", [], 'root');
        echo "<p style='color: green;'>✓ Date index created/verified</p>";
        
        Database::executeQuery("CREATE INDEX IF NOT EXISTS idx_appointments_doctor_time ON appointments(doctor_id, start_time)", [], 'root');
        echo "<p style='color: green;'>✓ Doctor time index created/verified</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Index creation warning: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
    echo "<p style='color: green; font-weight: bold;'>✓ Database structure check complete!</p>";
    echo "<p>You can now try adding an appointment again.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<a href='check_db_structure.php'>Check Database Structure</a> | ";
echo "<a href='add_appointment.php'>Add Appointment</a> | ";
echo "<a href='dashboard.php'>Dashboard</a>";
?>
