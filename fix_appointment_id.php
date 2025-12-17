<?php
/**
 * Fix appointment_id AUTO_INCREMENT issue
 * Resets the AUTO_INCREMENT counter to the correct value
 */

require_once 'config.php';
require_once 'db.php';

Database::requireAuth();

echo "<h2>Fix Appointment ID AUTO_INCREMENT</h2>";
echo "<hr>";

try {
    // Get the current maximum appointment_id
    $maxId = Database::fetchOne("SELECT MAX(appointment_id) as max_id FROM appointments", [], 'root');
    $currentMaxId = $maxId['max_id'] ?? 0;
    
    echo "<p>Current maximum appointment_id: <strong>" . $currentMaxId . "</strong></p>";
    
    // Get current AUTO_INCREMENT value
    $tableStatus = Database::fetchOne("SHOW TABLE STATUS LIKE 'appointments'", [], 'root');
    $currentAutoIncrement = $tableStatus['Auto_increment'] ?? 'Unknown';
    
    echo "<p>Current AUTO_INCREMENT value: <strong>" . $currentAutoIncrement . "</strong></p>";
    
    // Calculate the correct AUTO_INCREMENT value (max_id + 1)
    $newAutoIncrement = $currentMaxId + 1;
    
    echo "<hr>";
    echo "<h3>Fixing AUTO_INCREMENT...</h3>";
    
    // Reset AUTO_INCREMENT to the correct value
    $fixSQL = "ALTER TABLE appointments AUTO_INCREMENT = " . $newAutoIncrement;
    Database::executeQuery($fixSQL, [], 'root');
    
    echo "<p style='color: green; font-weight: bold;'>✓ SUCCESS! AUTO_INCREMENT reset to " . $newAutoIncrement . "</p>";
    
    // Verify the fix
    $tableStatusAfter = Database::fetchOne("SHOW TABLE STATUS LIKE 'appointments'", [], 'root');
    $newAutoIncrementValue = $tableStatusAfter['Auto_increment'];
    
    echo "<p>New AUTO_INCREMENT value: <strong>" . $newAutoIncrementValue . "</strong></p>";
    
    echo "<hr>";
    echo "<p style='color: green; font-size: 18px;'>✓ The appointment_id issue has been fixed!</p>";
    echo "<p>You can now add appointments successfully.</p>";
    echo "<p>The next appointment will have ID: <strong>" . $newAutoIncrementValue . "</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please ensure you have the necessary database permissions.</p>";
}

echo "<hr>";
echo "<a href='add_appointment.php'>Add Appointment</a> | ";
echo "<a href='check_db_structure.php'>Check Database Structure</a> | ";
echo "<a href='dashboard.php'>Dashboard</a>";
?>
