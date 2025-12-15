<?php
/**
 * Database Seeder Script for Private Clinic Patient Record System
 * Securely populates initial users and doctors with proper password hashing
 * 
 * Usage: Run this script once after creating the database schema
 * Command: php seed_data.php
 */

require_once 'config.php';
require_once 'db.php';

echo "🏥 Private Clinic Database Seeder\n";
echo "================================\n\n";

try {
    // Check if admin user already exists
    $adminCheck = Database::fetchOne("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    
    if ($adminCheck['count'] > 0) {
        echo "⚠️  Admin user already exists. Skipping user creation.\n";
        echo "   If you want to reset the data, please truncate the users and doctors tables first.\n\n";
        exit(0);
    }
    
    echo "📝 Creating admin user...\n";
    
    // Create secure admin user
    $adminPasswordHash = password_hash('pass123', PASSWORD_DEFAULT);
    $adminSql = "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'admin')";
    Database::executeUpdate($adminSql, ['admin', $adminPasswordHash]);
    
    echo "✅ Admin user created successfully (username: admin, password: pass123)\n\n";
    
    echo "👨‍⚕️ Creating doctor users...\n";
    
    // Define doctor data
    $doctors = [
        [
            'username' => 'dr.ali',
            'password' => 'doctor123',
            'name' => 'Dr. Ali Rahman',
            'specialization' => 'General Practice'
        ],
        [
            'username' => 'dr.siti',
            'password' => 'doctor123',
            'name' => 'Dr. Siti Nurhaliza',
            'specialization' => 'Pediatrics'
        ],
        [
            'username' => 'dr.tan',
            'password' => 'doctor123',
            'name' => 'Dr. Tan Wei Ming',
            'specialization' => 'Cardiology'
        ],
        [
            'username' => 'dr.priya',
            'password' => 'doctor123',
            'name' => 'Dr. Priya Sharma',
            'specialization' => 'Dermatology'
        ],
        [
            'username' => 'dr.ahmad',
            'password' => 'doctor123',
            'name' => 'Dr. Ahmad Zaki',
            'specialization' => 'Orthopedics'
        ]
    ];
    
    // Create doctor users with secure password hashing
    foreach ($doctors as $doctor) {
        // Generate unique secure hash for each doctor
        $passwordHash = password_hash($doctor['password'], PASSWORD_DEFAULT);
        
        // Insert user account
        $userSql = "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'doctor')";
        Database::executeUpdate($userSql, [$doctor['username'], $passwordHash]);
        
        // Get the user ID for linking
        $userResult = Database::fetchOne("SELECT id FROM users WHERE username = ?", [$doctor['username']]);
        $userId = $userResult['id'];
        
        // Insert doctor profile
        $doctorSql = "INSERT INTO doctors (name, specialization, user_id) VALUES (?, ?, ?)";
        Database::executeUpdate($doctorSql, [$doctor['name'], $doctor['specialization'], $userId]);
        
        echo "✅ Created: {$doctor['name']} ({$doctor['specialization']}) - Username: {$doctor['username']}\n";
    }
    
    echo "\n🎉 Database seeding completed successfully!\n\n";
    
    echo "📋 Login Credentials:\n";
    echo "====================\n";
    echo "Admin Access:\n";
    echo "  Username: admin\n";
    echo "  Password: pass123\n";
    echo "  Dashboard: dashboard.php\n\n";
    
    echo "Doctor Access (all doctors use same password):\n";
    echo "  Password: doctor123\n";
    echo "  Dashboard: doctor_dashboard.php\n";
    echo "  Usernames:\n";
    foreach ($doctors as $doctor) {
        echo "    - {$doctor['username']} ({$doctor['name']})\n";
    }
    
    echo "\n🔒 Security Notes:\n";
    echo "==================\n";
    echo "• Each user has a unique, securely generated password hash\n";
    echo "• No hardcoded password hashes in the database\n";
    echo "• All passwords use PHP's PASSWORD_DEFAULT algorithm\n";
    echo "• Consider changing default passwords in production\n\n";
    
} catch (Exception $e) {
    echo "❌ Error during database seeding: " . $e->getMessage() . "\n";
    echo "   Please check your database connection and try again.\n";
    exit(1);
}