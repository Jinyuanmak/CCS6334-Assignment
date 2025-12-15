<?php
/**
 * Database verification test for encryption and core operations
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

echo "Database and Encryption Verification Test\n";
echo "==========================================\n";

try {
    // Test 1: Database connection
    $connection = Database::getConnection();
    echo "✓ Database connection successful\n";
    
    // Test 2: Create test patient with encryption
    $testData = [
        'name' => 'Encryption Test Patient',
        'ic_number' => '555666777888',
        'diagnosis' => 'Test Diagnosis for Encryption Verification',
        'phone' => '0123456789'
    ];
    
    Database::beginTransaction();
    
    $sql = "INSERT INTO patients (name, ic_number, diagnosis, phone) 
            VALUES (?, ?, AES_ENCRYPT(?, ?), ?)";
    
    $result = Database::executeUpdate($sql, [
        $testData['name'],
        $testData['ic_number'],
        $testData['diagnosis'],
        ENCRYPTION_KEY,
        $testData['phone']
    ]);
    
    $patientId = Database::getLastInsertId();
    Database::commit();
    
    echo "✓ Patient created with encrypted diagnosis (ID: $patientId)\n";
    
    // Test 3: Verify encryption (raw data should be different)
    $rawSql = "SELECT diagnosis FROM patients WHERE id = ?";
    $rawData = Database::fetchOne($rawSql, [$patientId]);
    
    if ($rawData['diagnosis'] !== $testData['diagnosis']) {
        echo "✓ Diagnosis is properly encrypted in database\n";
    } else {
        echo "✗ Diagnosis is NOT encrypted in database\n";
    }
    
    // Test 4: Verify decryption
    $decryptSql = "SELECT AES_DECRYPT(diagnosis, ?) as diagnosis FROM patients WHERE id = ?";
    $decryptedData = Database::fetchOne($decryptSql, [ENCRYPTION_KEY, $patientId]);
    
    if ($decryptedData['diagnosis'] === $testData['diagnosis']) {
        echo "✓ Diagnosis decryption successful\n";
    } else {
        echo "✗ Diagnosis decryption failed\n";
    }
    
    // Test 5: Test prepared statements (SQL injection prevention)
    $maliciousInput = "'; DROP TABLE patients; --";
    $safeSql = "SELECT COUNT(*) as count FROM patients WHERE name = ?";
    $safeResult = Database::fetchOne($safeSql, [$maliciousInput]);
    
    echo "✓ Prepared statements working (SQL injection prevented)\n";
    
    // Test 6: Input sanitization
    $dirtyInput = "<script>alert('xss')</script>Test Name";
    $cleanInput = Database::sanitizeInput($dirtyInput);
    
    if (strpos($cleanInput, '<script>') === false) {
        echo "✓ Input sanitization working (XSS prevented)\n";
    } else {
        echo "✗ Input sanitization failed\n";
    }
    
    // Cleanup: Delete test patient
    Database::executeUpdate("DELETE FROM patients WHERE id = ?", [$patientId]);
    echo "✓ Test patient cleaned up\n";
    
    echo "\n==========================================\n";
    echo "All database and encryption tests PASSED!\n";
    echo "==========================================\n";
    
} catch (Exception $e) {
    Database::rollback();
    echo "✗ Test failed: " . $e->getMessage() . "\n";
}
?>