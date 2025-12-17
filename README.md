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
- **Auto-formatting**: Automatic name capitalization and IC number formatting
- **Form Validation**: Client-side validation with SweetAlert notifications

### 📅 Appointment Management
- **Appointment Scheduling**: Create, edit, and manage appointments
- **Doctor Assignment**: Assign appointments to specific doctors
- **Time Slot Management**: Prevent scheduling conflicts
- **Appointment History**: Track completed and upcoming appointments
- **Duration Control**: Standardized appointment durations (30, 60, 90, 120 minutes)
- **Rich Text Reasons**: TinyMCE editor for appointment reasons
- **Status Tracking**: Real-time appointment status (upcoming, in-progress, completed)

### 👩‍⚕️ Doctor Portal
- **Doctor Dashboard**: Personalized view for medical professionals
- **Patient List**: View patients assigned to specific doctors
- **Appointment Schedule**: Doctor-specific appointment management
- **Message Notifications**: Real-time notifications for patient updates
- **Unread Message Badge**: Visual indicators for new notifications
- **Message Log System**: Comprehensive notification management with read/unread status
- **Change Tracking**: Detailed change logs for patient updates
- **Pagination**: Efficient browsing of large datasets

### 🔐 Security & Authentication
- **Role-Based Access Control**: Admin and Doctor roles with different permissions
- **Progressive Lockout System**: Automatic account lockout after failed attempts
- **Session Management**: Secure session handling with timeout
- **Audit Logging**: Comprehensive activity tracking with IP addresses
- **Input Sanitization**: XSS and SQL injection prevention
- **Database Security**: Multi-tier database users with minimal privileges
- **Encryption Keys**: Dual encryption system for different data types

### 📊 Visual Analytics & Reporting
- **Interactive Charts**: Chart.js powered line charts with smooth animations
- **Weekly View**: 7-day appointment trends (Mon-Sun)
- **Monthly View**: 12-month appointment patterns (Jan-Dec)
- **View Toggle**: Dynamic switching between time periods without page reload
- **Real-time Data**: Live appointment counts and statistics
- **Responsive Charts**: Mobile-friendly visualizations
- **Accessibility**: Screen reader support and keyboard navigation
- **Error Handling**: Graceful fallback when data unavailable

### 🛠️ System Administration
- **Database Backup**: One-click encrypted backup system
- **Seed Data**: Automated test data generation
- **Audit Trail**: Complete security logging for all admin actions
- **System Maintenance**: Automated cleanup and optimization tools
- **Digital Clock**: Real-time dashboard clock display

### 🧪 Testing Framework
- **Property-Based Testing**: Comprehensive test suite with 100+ iterations per property
- **Multiple Test Categories**: Authentication, Patient Management, Security, UI, Encryption, Analytics
- **Automated Test Runner**: Single command execution of all test suites
- **Test Coverage**: 9 different property test classes covering all major features
- **Continuous Validation**: Ensures system correctness across all components

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
- **Bootstrap 5**: Responsive UI framework with custom styling
- **Chart.js**: Interactive data visualizations with animations
- **TinyMCE**: Rich text editor for medical notes and appointment reasons
- **FontAwesome & Bootstrap Icons**: Comprehensive icon libraries
- **SweetAlert2**: Enhanced user notifications and confirmations
- **Custom JavaScript**: Form validation, auto-formatting, and AJAX functionality

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
├── 📄 dashboard.php            # Admin dashboard with visual analytics
├── 📄 doctor_dashboard.php     # Doctor portal with message system
├── 📄 style.css                # Custom styling
├── 📄 javascript.js            # Frontend functionality with TinyMCE
├── 📄 data.php                 # AJAX data service for charts
├── 📄 README.md               # This file
├── 📄 SETUP_DATABASE_SECURITY.md # Security setup guide
│
├── 👥 Patient Management/
│   ├── 📄 add_patient.php      # Add new patients with validation
│   ├── 📄 edit_patient.php     # Edit patient records
│   ├── 📄 view_patient.php     # View patient details
│   ├── 📄 delete_patient.php   # Delete patients with confirmation
│   └── 📄 all_patients.php     # Patient listing with search
│
├── 📅 Appointment System/
│   ├── 📄 add_appointment.php      # Schedule appointments with rich text
│   ├── 📄 edit_appointment.php     # Modify appointments
│   ├── 📄 appointment_detail.php   # Appointment details
│   ├── 📄 appointment_analytics.php # Analytics service for charts
│   ├── 📄 delete_appointment.php   # Cancel appointments
│   ├── 📄 all_upcoming.php        # Upcoming appointments
│   └── 📄 all_history.php         # Appointment history
│
├── 🔐 Security & Audit/
│   ├── 📄 audit_log.php        # Security activity logs
│   ├── 📄 message_log.php      # Doctor notification system
│   └── 📄 logout.php           # Secure logout
│
├── 🗄️ Database/
│   └── sql/
│       ├── 📄 schema.sql       # Database structure
│       └── 📄 create_security_users.sql # Security setup
│
├── 🧪 Testing Framework/
│   ├── 📄 run_all_property_tests.php # Comprehensive test runner
│   ├── 📄 database_verification.php  # Database setup verification
│   └── properties/
│       ├── 📄 AuthenticationPropertiesTest.php
│       ├── 📄 PatientManagementPropertiesTest.php
│       ├── 📄 SecurityPropertiesTest.php
│       ├── 📄 UIPropertiesTest.php
│       ├── 📄 ICEncryptionPropertiesTest.php
│       └── 📄 VisualAnalyticsPropertiesTest.php
│
├── 📊 Visual Analytics/
│   └── .kiro/specs/visual-analytics/
│       ├── 📄 requirements.md  # Feature requirements
│       ├── 📄 design.md        # Technical design
│       └── 📄 tasks.md         # Implementation tasks
│
└── 🛠️ System Utilities/
    ├── 📄 seed_data.php        # Test data generator with secure hashing
    └── 📄 backup_system.php    # Encrypted database backup system
```

## 🧪 Testing Framework

### Property-Based Testing
The system includes comprehensive property-based tests with 100+ iterations per property to ensure statistical confidence:

```bash
# Run all property tests (comprehensive suite)
php tests/run_all_property_tests.php

# Generate JSON report
php tests/run_all_property_tests.php --json

# Run specific test suites
php tests/properties/SecurityPropertiesTest.php
php tests/properties/PatientManagementPropertiesTest.php
php tests/properties/VisualAnalyticsPropertiesTest.php
```

### Test Categories
- **Authentication Properties**: Login security, session management, and access control
- **Patient Management Properties**: CRUD operations, data integrity, and form validation
- **Security Properties**: Encryption effectiveness, input sanitization, and error handling
- **UI Properties**: Interface consistency, responsive design, and accessibility
- **IC Encryption Properties**: Malaysian IC number encryption/decryption round-trip testing
- **Visual Analytics Properties**: Chart data consistency, date coverage, and error handling

### Advanced Testing Features
- **Statistical Validation**: Each property runs 100 iterations with random data generation
- **Comprehensive Coverage**: 9 test classes covering all major system components
- **Automated Test Runner**: Single command execution with detailed reporting
- **Error Isolation**: Individual test failure reporting with specific error details
- **Performance Metrics**: Execution time tracking and success rate calculation

### Database Verification
```bash
# Verify database setup and security configuration
php tests/database_verification.php

# Check encryption functionality
php tests/properties/ICEncryptionPropertiesTest.php
```

### Test Results Dashboard
The test runner provides:
- ✅ **Overall Statistics**: Total tests, pass/fail counts, success percentage
- 📊 **Suite Breakdown**: Individual test suite performance metrics  
- 🎯 **Detailed Reporting**: Specific failure information and debugging data
- ⏱️ **Performance Tracking**: Execution time and efficiency metrics

## 🔌 API Endpoints

### AJAX Endpoints
- `GET message_log.php?ajax=count` - Get unread message count for doctors
- `GET data.php?mode=chart&view=weekly` - Get weekly appointment analytics data
- `GET data.php?mode=chart&view=monthly` - Get monthly appointment analytics data
- `POST add_patient.php` - Create new patient with validation
- `POST edit_patient.php` - Update patient record with change tracking
- `POST add_appointment.php` - Schedule appointment with rich text support

### Authentication & Security
- `POST index.php` - User login with progressive lockout
- `GET logout.php` - Secure logout with session cleanup
- `GET backup_system.php` - Admin-only database backup download
- `GET audit_log.php` - Security audit trail (admin only)

### Visual Analytics API
- **Weekly View**: Returns 7-day appointment data (Mon-Sun)
- **Monthly View**: Returns 12-month appointment data (Jan-Dec)
- **Real-time Updates**: Dynamic chart updates without page reload
- **Error Handling**: Graceful fallback data when database unavailable

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

## 📊 Visual Analytics Feature

### Interactive Dashboard Charts
The Visual Analytics feature provides administrators with powerful data visualization capabilities:

#### Weekly View
- **7-Day Trends**: Displays appointment counts for the current week (Monday through Sunday)
- **Real-time Data**: Updates automatically based on current appointment schedule
- **Interactive Tooltips**: Hover over data points to see exact appointment counts

#### Monthly View  
- **12-Month Overview**: Shows appointment patterns across calendar months (Jan-Dec)
- **Yearly Trends**: Helps identify seasonal patterns and planning opportunities
- **Smooth Transitions**: Animated switching between weekly and monthly views

#### Technical Implementation
- **Chart.js Integration**: Professional line charts with smooth curves and animations
- **AJAX Data Loading**: Dynamic data fetching without page reloads
- **Error Handling**: Graceful fallback when database is unavailable
- **Accessibility**: Screen reader support and keyboard navigation
- **Responsive Design**: Works seamlessly on desktop and mobile devices

#### Data Processing
- **AppointmentAnalyticsService**: Dedicated PHP service for data processing
- **Date Range Generation**: Ensures complete coverage even for days without appointments
- **Zero-Fill Logic**: Missing dates automatically filled with zero counts
- **JSON API**: RESTful endpoints for chart data retrieval

## 🎨 Customization

### Styling
- Edit `style.css` for custom themes and chart colors
- Bootstrap 5 variables can be overridden for consistent branding
- TinyMCE themes configurable in `javascript.js`
- Chart.js styling customizable through configuration objects

### Configuration
- Database settings in `config.php`
- Session timeout and security settings
- Encryption key management for sensitive data
- Chart display preferences and animation settings

## � Messarge & Notification System

### Doctor Notification System
Advanced messaging system keeps doctors informed about patient updates:

#### Message Types
- **Patient Updates**: Notifications when patient records are modified
- **Patient Creation**: Alerts when new patients are assigned to a doctor
- **Appointment Changes**: Updates about appointment modifications
- **System Notifications**: Important system-wide announcements

#### Features
- **Real-time Badge**: Unread message count displayed in navigation
- **Change Tracking**: Detailed before/after comparisons for patient updates
- **Read/Unread Status**: Visual indicators for message status
- **Bulk Actions**: Mark all messages as read functionality
- **Pagination**: Efficient browsing of message history
- **Rich Display**: Card-based layout with color-coded message types

#### Technical Implementation
- **AJAX Count Updates**: Real-time unread message count via API
- **JSON Change Details**: Structured storage of field-level changes
- **Doctor-Specific Filtering**: Messages targeted to specific doctors
- **Responsive Design**: Mobile-friendly message cards and navigation

## 📈 Performance Optimization

### Database
- Indexed columns for fast queries and analytics
- Pagination for large datasets (patients, appointments, messages, audit logs)
- Connection pooling with different privilege levels (public, admin, root)
- Optimized SQL queries with date range filtering for analytics

### Frontend
- Minified CSS and JavaScript libraries
- Lazy loading for TinyMCE editors and large forms
- Responsive design optimized for mobile devices
- Chart.js animations with performance-optimized rendering
- AJAX data loading to prevent full page reloads

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Add property-based tests for new features
4. Ensure all tests pass
5. Submit a pull request

### Development Guidelines
- Follow PSR-12 coding standards
- Add comprehensive error handling with graceful degradation
- Include security considerations and input validation
- Write property-based tests with 100+ iterations
- Document new features in README and create specs for complex features
- Use the Kiro specs system for feature development
- Maintain test coverage across all major components
- Follow the established encryption patterns for sensitive data

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support & Troubleshooting

### Getting Help
For support and questions:
- **Documentation**: Check this comprehensive README for feature details
- **Security Guide**: Review `SETUP_DATABASE_SECURITY.md` for security configuration
- **Test Suite**: Run `php tests/run_all_property_tests.php` to verify system functionality
- **Audit Logs**: Check `audit_log.php` for system activity and security events
- **Visual Analytics Spec**: Review `.kiro/specs/visual-analytics/` for detailed feature documentation

### Diagnostic Tools
- **Database Verification**: `php tests/database_verification.php`
- **Property Tests**: Individual test suites for specific components
- **Error Logs**: Check server error logs for detailed error information
- **Message System**: Use doctor message logs to track system notifications

### Common Issues
- **Chart Not Loading**: Check browser console for JavaScript errors, verify database connection
- **TinyMCE Problems**: Ensure CDN access, check for JavaScript conflicts
- **Authentication Issues**: Verify session configuration, check audit logs
- **Encryption Errors**: Validate encryption keys in `config.php`
- **Performance Issues**: Run database optimization, check query performance

## 🔄 Version History

- **v1.0.0**: Initial release with core functionality
- **v1.1.0**: Added doctor portal and notification system
- **v1.2.0**: Enhanced security with property-based testing
- **v1.3.0**: Improved UI/UX and responsive design
- **v1.4.0**: Visual Analytics feature with interactive charts
- **v1.5.0**: Comprehensive testing framework with 9 test suites
- **v1.6.0**: Advanced message system with change tracking
- **v1.7.0**: Database backup system and audit logging
- **v1.8.0**: TinyMCE integration and form validation enhancements

---

**Built with ❤️ for healthcare professionals**

*This system prioritizes security, reliability, and user experience to support quality patient care.*