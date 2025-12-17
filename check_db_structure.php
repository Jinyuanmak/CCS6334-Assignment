<?php
/**
 * Check database structure for appointments table
 */

require_once 'config.php';
require_once 'db.php';

Database::requireAuth();

echo "<h2>Database Structure Check</h2>";
echo "<hr>";

try {
    // Check if appointments table exists
    $tables = Database::fetchAll("SHOW TABLES LIKE 'appointments'");
    
    if (empty($tables)) {
        echo "<p style='color: red;'>✗ Appointments table does not exist!</p>";
        echo "<p>Please run the schema.sql file to create the database structure.</p>";
    } else {
        echo "<p style='color: green;'>✓ Appointments table exists</p>";
        
        // Get table structure
        echo "<h3>Appointments Table Structure:</h3>";
        $columns = Database::fetchAll("DESCRIBE appointments");
        
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check expected columns
        echo "<h3>Column Validation:</h3>";
        $expectedColumns = [
            'appointment_id',
            'patient_id',
            'appointment_date',
            'start_time',
            'end_time',
            'doctor_name',
            'doctor_id',
            'reason',
            'created_at'
        ];
        
        $actualColumns = array_column($columns, 'Field');
        
        foreach ($expectedColumns as $expected) {
            if (in_array($expected, $actualColumns)) {
                echo "<p style='color: green;'>✓ Column '$expected' exists</p>";
            } else {
                echo "<p style='color: red;'>✗ Column '$expected' is MISSING!</p>";
            }
        }
        
        // Check for extra columns
        $extraColumns = array_diff($actualColumns, $expectedColumns);
        if (!empty($extraColumns)) {
            echo "<h3>Extra Columns (not expected):</h3>";
            foreach ($extraColumns as $extra) {
                echo "<p style='color: orange;'>⚠ Extra column: '$extra'</p>";
            }
        }
        
        // Test encryption
        echo "<h3>Encryption Test:</h3>";
        try {
            $testEncrypt = Database::fetchOne("SELECT AES_ENCRYPT('test data', ?) as encrypted, AES_DECRYPT(AES_ENCRYPT('test data', ?), ?) as decrypted", [ENCRYPTION_KEY, ENCRYPTION_KEY, ENCRYPTION_KEY]);
            
            if ($testEncrypt['encrypted'] !== null && $testEncrypt['decrypted'] === 'test data') {
                echo "<p style='color: green;'>✓ Encryption/Decryption working correctly</p>";
            } else {
                echo "<p style='color: red;'>✗ Encryption/Decryption test failed</p>";
                echo "<pre>";
                print_r($testEncrypt);
                echo "</pre>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Encryption test error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        // Check sample data
        echo "<h3>Sample Appointments:</h3>";
        $sampleAppointments = Database::fetchAll("SELECT appointment_id, patient_id, appointment_date, doctor_name, doctor_id FROM appointments LIMIT 5");
        
        if (empty($sampleAppointments)) {
            echo "<p>No appointments in database yet.</p>";
        } else {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>ID</th><th>Patient ID</th><th>Date</th><th>Doctor Name</th><th>Doctor ID</th></tr>";
            foreach ($sampleAppointments as $apt) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($apt['appointment_id']) . "</td>";
                echo "<td>" . htmlspecialchars($apt['patient_id']) . "</td>";
                echo "<td>" . htmlspecialchars($apt['appointment_date']) . "</td>";
                echo "<td>" . htmlspecialchars($apt['doctor_name']) . "</td>";
                echo "<td>" . htmlspecialchars($apt['doctor_id'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<a href='add_appointment.php'>Back to Add Appointment</a> | ";
echo "<a href='dashboard.php'>Back to Dashboard</a>";
?>
