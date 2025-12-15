# 🏥 Private Clinic Patient Record System

A comprehensive, secure web-based patient management system designed for private medical clinics. Built with PHP, MySQL, and modern web technologies, featuring advanced security measures, role-based access control, and property-based testing.

## 📋 Table of Contents

- [Features](#-features)
- [Security Features](#-security-features)
- [System Architecture](#-system-architecture)
- [Installation](#-installation)
- [Database Setup](#-database-setup)
- [User Roles](#-user-roles)
- [File Structure](#-file-structure)
- [Testing](#-testing)
- [API Endpoints](#-api-endpoints)
- [Security Considerations](#-security-considerations)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Features

### 👨‍⚕️ Patient Management
- **Secure Patient Records**: Encrypted storage of sensitive medical data
- **Patient Registration**: Add new patients with comprehensive information
- **Patient Search**: Advanced search functionality with encrypted data support
- **Medical History**: Rich text diagnosis with TinyMCE editor
- **IC Number Encryption**: Malaysian IC numbers encrypted with AES-256

### 📅 Appointment Management
- **Appointment Scheduling**: Create, edit, and manage appointments
- **Doctor Assignment**: Assign appointments to specific doctors
- **Time Slot Management**: Prevent scheduling conflicts
- **Appointment History**: Track completed and upcoming appointments
- **Duration Control**: Standardized appointment durations (30, 60, 90, 120 minutes)

### 👩‍⚕️ Doctor Portal
- **Doctor Dashboard**: Personalized view for medical professionals
- **Patient List**: View patients assigned to specific doctors
- **Appointment Schedule**: Doctor-specific appointment management
- **Message Notifications**: Real-time notifications for patient updates
- **Unread Message Badge**: Visual indicators for new notifications

### 🔐 Security & Authentication
- **Role-Based Access Control**: Admin and Doctor roles with different permissions
- **Progressive Lockout System**: Automatic account lockout after failed attempts
- **Session Management**: Secure session handling with timeout
- **Audit Logging**: Comprehensive activity tracking
- **Input Sanitization**: XSS and SQL injection prevention

### 📊 Reporting & Analytics
- **Dashboard Statistics**: Real-time counts and metrics
- **Appointment History**: Detailed appointment tracking
- **Patient Analytics**: Patient registration trends
- **Audit Reports**: Security and activity logs

## 🛡️ Security Features

### Encryption
- **AES-256 Encryption**: Medical diagnoses and IC numbers
- **Dual Key System**: Separate keys for different data types
- **Database-Level Encryption**: MySQL AES_ENCRYPT/AES_DECRYPT functions

### Access Control
- **Multi-Tier Database Users**: 
  - `app_public`: Read-only access for authentication
  - `app_admin`: CRUD operations for admin functions
  - `root`: System maintenance and backups
- **Session Security**: HTTP-only cookies, strict mode
- **IP-Based Lockout**: Progressive lockout system

### Data Protection
- **Input Validation**: Comprehensive server-side validation
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: HTML entity encoding and sanitization
- **CSRF Protection**: Form token validation

## 🏗️ System Architecture

### Backend
- **PHP 8.0+**: Modern PHP with type declarations
- **MySQL 8.0+**: Relational database with encryption support
- **PDO**: Database abstraction layer with prepared statements

### Frontend
- **Bootstrap 5**: Responsive UI framework
- **TinyMCE**: Rich text editor for medical notes
- **FontAwesome**: Icon library
- **SweetAlert2**: Enhanced user notifications

### Database Design
- **Normalized Schema**: Proper relational design
- **Foreign Key Constraints**: Data integrity enforcement
- **Indexed Columns**: Optimized query performance
- **Encrypted Fields**: Sensitive data protection

## 🚀 Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Composer (optional, for dependencies)

### Quick Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd CCS6334-Assignment
   ```

2. **Configure database connection**
   ```php
   // Edit config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'clinic_db');
   define('DB_ADMIN_USER', 'your_admin_user');
   define('DB_ADMIN_PASS', 'your_admin_password');
   ```

3. **Set up encryption keys**
   ```php
   // Generate secure keys for production
   define('ENCRYPTION_KEY', 'your-secure-32-char-key-here');
   define('SECURE_KEY', 'your-secure-key-for-ic-numbers');
   ```

4. **Initialize database**
   ```bash
   mysql -u root -p < sql/schema.sql
   php seed_data.php  # Optional: Add sample data
   ```

5. **Configure web server**
   - Point document root to the CCS6334-Assignment directory
   - Ensure PHP extensions: PDO, MySQL, OpenSSL

## 🗄️ Database Setup

### Automated Setup
```bash
# Run the database setup script
mysql -u root -p < sql/schema.sql

# Create security users (recommended)
mysql -u root -p < sql/create_security_users.sql
```

### Manual Setup
See [SETUP_DATABASE_SECURITY.md](SETUP_DATABASE_SECURITY.md) for detailed security configuration.

### Database Schema
- **users**: System users (admin/doctor accounts)
- **patients**: Patient records with encrypted fields
- **doctors**: Doctor profiles and specializations
- **appointments**: Appointment scheduling and management
- **message_logs**: Notification system for doctors
- **audit_logs**: Security and activity tracking
- **login_attempts**: Progressive lockout system

## 👥 User Roles

### Administrator
- **Full System Access**: All patients, appointments, and system functions
- **User Management**: Create and manage doctor accounts
- **System Configuration**: Backup, audit logs, system settings
- **Patient Management**: Add, edit, delete patient records
- **Appointment Scheduling**: Create and manage all appointments

### Doctor
- **Patient Access**: View patients assigned to them
- **Appointment Management**: View and manage their appointments
- **Medical Records**: Update patient diagnoses and notes
- **Notifications**: Receive updates about their patients
- **Limited Admin**: Cannot access system-wide settings

## 📁 File Structure

```
CCS6334-Assignment/
├── 📄 index.php                 # Login page
├── 📄 config.php               # System configuration
├── 📄 db.php                   # Database connection class
├── 📄 dashboard.php            # Admin dashboard
├── 📄 doctor_dashboard.php     # Doctor portal
├── 📄 style.css                # Custom styling
├── 📄 javascript.js            # Frontend functionality
├── 📄 README.md               # This file
├── 📄 SETUP_DATABASE_SECURITY.md # Security setup guide
│
├── 👥 Patient Management/
│   ├── 📄 add_patient.php      # Add new patients
│   ├── 📄 edit_patient.php     # Edit patient records
│   ├── 📄 view_patient.php     # View patient details
│   ├── 📄 delete_patient.php   # Delete patients
│   └── 📄 all_patients.php     # Patient listing
│
├── 📅 Appointment System/
│   ├── 📄 add_appointment.php      # Schedule appointments
│   ├── 📄 edit_appointment.php     # Modify appointments
│   ├── 📄 appointment_detail.php   # Appointment details
│   ├── 📄 delete_appointment.php   # Cancel appointments
│   ├── 📄 all_upcoming.php        # Upcoming appointments
│   └── 📄 all_history.php         # Appointment history
│
├── 🔐 Security & Audit/
│   ├── 📄 audit_log.php        # Activity logs
│   ├── 📄 message_log.php      # Doctor notifications
│   └── 📄 logout.php           # Secure logout
│
├── 🗄️ Database/
│   └── sql/
│       ├── 📄 schema.sql       # Database structure
│       └── 📄 create_security_users.sql # Security setup
│
├── 🧪 Testing/
│   ├── 📄 run_all_property_tests.php # Test runner
│   └── properties/
│       ├── 📄 AuthenticationPropertiesTest.php
│       ├── 📄 PatientManagementPropertiesTest.php
│       ├── 📄 SecurityPropertiesTest.php
│       ├── 📄 UIPropertiesTest.php
│       └── 📄 ICEncryptionPropertiesTest.php
│
└── 🛠️ Utilities/
    ├── 📄 seed_data.php        # Sample data generator
    └── 📄 backup_system.php    # Database backup utility
```

## 🧪 Testing

### Property-Based Testing
The system includes comprehensive property-based tests to ensure correctness:

```bash
# Run all property tests
php tests/run_all_property_tests.php

# Run specific test suites
php tests/properties/SecurityPropertiesTest.php
php tests/properties/PatientManagementPropertiesTest.php
```

### Test Categories
- **Authentication Properties**: Login security and session management
- **Patient Management Properties**: CRUD operations and data integrity
- **Security Properties**: Encryption, input validation, and access control
- **UI Properties**: Interface consistency and responsive design
- **IC Encryption Properties**: Malaysian IC number encryption/decryption

### Database Verification
```bash
# Verify database setup and security
php tests/database_verification.php
```

## 🔌 API Endpoints

### AJAX Endpoints
- `GET message_log.php?ajax=count` - Get unread message count
- `POST add_patient.php` - Create new patient
- `POST edit_patient.php` - Update patient record
- `POST add_appointment.php` - Schedule appointment

### Authentication
- `POST index.php` - User login
- `GET logout.php` - User logout

## 🔒 Security Considerations

### Production Deployment
1. **Change Default Keys**: Generate new encryption keys
2. **Database Security**: Use dedicated database users with minimal privileges
3. **HTTPS Only**: Enable SSL/TLS encryption
4. **Error Reporting**: Disable error display in production
5. **File Permissions**: Restrict access to configuration files

### Security Headers
```php
// Add to .htaccess or server configuration
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=63072000"
```

### Regular Maintenance
- Monitor audit logs for suspicious activity
- Regular database backups
- Update dependencies and security patches
- Review user access permissions

## 🎨 Customization

### Styling
- Edit `style.css` for custom themes
- Bootstrap 5 variables can be overridden
- TinyMCE themes configurable in `javascript.js`

### Configuration
- Database settings in `config.php`
- Session timeout and security settings
- Encryption key management

## 📈 Performance Optimization

### Database
- Indexed columns for fast queries
- Pagination for large datasets
- Connection pooling with different privilege levels

### Frontend
- Minified CSS and JavaScript
- Lazy loading for large forms
- Responsive design for mobile devices

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Add property-based tests for new features
4. Ensure all tests pass
5. Submit a pull request

### Development Guidelines
- Follow PSR-12 coding standards
- Add comprehensive error handling
- Include security considerations
- Write property-based tests
- Document new features

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Check the documentation in this README
- Review the security setup guide
- Run the property-based tests to verify functionality
- Check audit logs for system issues

## 🔄 Version History

- **v1.0.0**: Initial release with core functionality
- **v1.1.0**: Added doctor portal and notification system
- **v1.2.0**: Enhanced security with property-based testing
- **v1.3.0**: Improved UI/UX and responsive design

---

**Built with ❤️ for healthcare professionals**

*This system prioritizes security, reliability, and user experience to support quality patient care.*