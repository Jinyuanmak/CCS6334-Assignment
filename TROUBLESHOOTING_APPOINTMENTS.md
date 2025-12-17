# Troubleshooting Appointment Creation Issues

## Problem
Error message: "Error scheduling appointment: Database operation failed. Please try again later."

## Solution Steps

### Step 1: Check Database Structure
Visit: `check_db_structure.php`

This will show you:
- If the appointments table exists
- All columns in the table
- If any required columns are missing
- Sample data from the table

### Step 2: Fix Database Structure (if needed)
Visit: `fix_appointments_table.php`

This will automatically:
- Create the appointments table if it doesn't exist
- Add any missing columns (start_time, end_time, doctor_id)
- Create necessary indexes

### Step 3: Debug Form Submission
Visit: `debug_appointment.php`

To use this:
1. Go to `add_appointment.php`
2. Fill in the appointment form
3. Change the form action from `add_appointment.php` to `debug_appointment.php`
4. Submit the form
5. You'll see detailed information about what data was submitted and where the error occurs

### Step 4: Check Error Logs
Look at your PHP error log for detailed error messages. The location varies:
- XAMPP: `xampp/php/logs/php_error_log`
- WAMP: `wamp/logs/php_error.log`
- Linux: `/var/log/php/error.log`

### Step 5: Try Adding Appointment Again
After running the fix script, try adding an appointment again at `add_appointment.php`

## Common Issues and Solutions

### Issue 1: Missing Columns
**Symptom:** Error mentions unknown column 'start_time', 'end_time', or 'doctor_id'
**Solution:** Run `fix_appointments_table.php` to add missing columns

### Issue 2: TinyMCE Content Not Saving
**Symptom:** Error says "Please enter the reason for the appointment"
**Solution:** Already fixed in javascript.js - TinyMCE now syncs before form submission

### Issue 3: Database Connection Issues
**Symptom:** Error about database connection failed
**Solution:** Check config.php and ensure database credentials are correct

### Issue 4: Encryption Key Issues
**Symptom:** Error about AES_ENCRYPT function
**Solution:** Ensure ENCRYPTION_KEY is defined in config.php

### Issue 5: Foreign Key Constraint
**Symptom:** Error about foreign key constraint fails
**Solution:** Ensure the patient_id exists in the patients table

### Issue 6: AUTO_INCREMENT Out of Range
**Symptom:** Error "Out of range value for column 'appointment_id' at row 1"
**Solution:** Run `fix_appointment_id.php` to reset the AUTO_INCREMENT counter
**Cause:** The AUTO_INCREMENT counter became corrupted or reached an invalid value

## Files Modified

1. **javascript.js** - Fixed TinyMCE sync issue
2. **add_appointment.php** - Enhanced error reporting
3. **db.php** - Added detailed error logging
4. **config.php** - Added SHOW_DETAILED_ERRORS flag

## New Diagnostic Files

1. **check_db_structure.php** - Check database table structure
2. **fix_appointments_table.php** - Automatically fix table structure
3. **debug_appointment.php** - Debug form submission
4. **TROUBLESHOOTING_APPOINTMENTS.md** - This file

## Next Steps

1. Run `check_db_structure.php` first
2. If issues found, run `fix_appointments_table.php`
3. Try adding an appointment
4. If still failing, use `debug_appointment.php` to see the exact error
5. Share the detailed error message for further assistance
