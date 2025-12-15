/**
 * Consolidated JavaScript for Private Clinic Patient Record System
 * Contains all JavaScript functionality for forms, TinyMCE, and validation
 */

// Global variables for TinyMCE configuration
let tinyMCEConfig = {};
let currentUserId = null;
let validationErrors = {};

/**
 * Initialize the application with user ID and configuration
 */
function initializeApp(userId, tmceConfig, errors = {}) {
    currentUserId = userId;
    tinyMCEConfig = tmceConfig || {};
    validationErrors = errors || {};
}

/**
 * TinyMCE Theme Application Function
 * Applies consistent white theme to TinyMCE editors
 */
function applyTinyMCETheme(editor) {
    try {
        if (!editor || !editor.getDoc) {
            return;
        }
        
        const doc = editor.getDoc();
        
        if (!doc || !doc.body) {
            return;
        }
        
        const body = doc.body;
        
        // Always use white background
        body.style.backgroundColor = '#ffffff';
        body.style.color = '#0f172a';
    } catch (error) {
        // TinyMCE theme application skipped - editor not ready
    }
}

/**
 * Initialize TinyMCE for diagnosis textarea (Patient forms)
 */
function initializeDiagnosisTinyMCE() {
    const config = {
        selector: '#diagnosis',
        height: 300,
        resize: false,
        menubar: false,
        branding: false,
        plugins: 'lists link',
        toolbar: 'bold italic underline | bullist numlist | removeformat',
        placeholder: 'Enter patient\'s diagnosis...',
        content_style: `
            body { 
                font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                font-size: 14px; 
                line-height: 1.6;
                background-color: white !important; 
                color: #0f172a !important;
                margin: 0;
                padding: 8px;
            }
            body[data-mce-placeholder]:not([data-mce-placeholder=""]):before {
                color: #64748b !important;
            }
        `,
        setup: function (editor) {
            // Force sync to textarea on change so PHP $_POST works
            editor.on('change', function () {
                editor.save();
            });
            
            // Apply theme on editor initialization
            editor.on('init', function () {
                // Delay theme application to ensure editor is fully ready
                setTimeout(function() {
                    applyTinyMCETheme(editor);
                }, 100);
            });
        }
    };
    
    tinymce.init(config);
}

/**
 * Initialize TinyMCE for reason textarea (Appointment forms)
 */
function initializeReasonTinyMCE() {
    const config = {
        selector: '#reason',
        height: 300,
        resize: false,
        menubar: false,
        branding: false,
        plugins: 'lists link',
        toolbar: 'bold italic underline | bullist numlist | removeformat',
        placeholder: 'Enter appointment reason...',
        content_style: `
            body { 
                font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                font-size: 14px; 
                line-height: 1.6;
                background-color: white !important; 
                color: #0f172a !important;
                margin: 0;
                padding: 8px;
            }
            body[data-mce-placeholder]:not([data-mce-placeholder=""]):before {
                color: #64748b !important;
            }
        `,
        setup: function (editor) {
            // Force sync to textarea on change so PHP $_POST works
            editor.on('change', function () {
                editor.save();
            });
            
            // Apply theme on editor initialization
            editor.on('init', function () {
                // Delay theme application to ensure editor is fully ready
                setTimeout(function() {
                    applyTinyMCETheme(editor);
                }, 100);
            });
        }
    };
    
    tinymce.init(config);
}

/**
 * Initialize TinyMCE for edit appointment page (smaller height)
 */
function initializeEditAppointmentTinyMCE() {
    const config = {
        selector: '#reason',
        height: 150,
        menubar: false,
        plugins: 'lists link',
        toolbar: 'bold italic underline | bullist numlist | removeformat',
        placeholder: 'Enter appointment reason...',
        content_style: `
            body { 
                font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                font-size: 14px; 
                line-height: 1.6;
                background-color: white !important; 
                color: #0f172a !important;
                margin: 0;
                padding: 8px;
            }
            body[data-mce-placeholder]:not([data-mce-placeholder=""]):before {
                color: #64748b !important;
            }
        `,
        branding: false,
        resize: false,
        statusbar: false,
        setup: function (editor) {
            // Apply theme on editor initialization
            editor.on('init', function () {
                // Delay theme application to ensure editor is fully ready
                setTimeout(function() {
                    applyTinyMCETheme(editor);
                }, 100);
            });
        }
    };
    
    tinymce.init(config);
}

/**
 * Auto-format patient name to Title Case
 */
function setupNameFormatting() {
    const nameField = document.getElementById('name');
    if (nameField) {
        nameField.addEventListener('blur', function() {
            const words = this.value.toLowerCase().split(' ');
            const titleCase = words.map(word => {
                if (word.length > 0) {
                    return word.charAt(0).toUpperCase() + word.slice(1);
                }
                return word;
            }).join(' ');
            this.value = titleCase.trim();
        });
    }
}

/**
 * Auto-format IC number input with Malaysian format (XXXXXX-XX-XXXX)
 */
function setupICNumberFormatting() {
    const icField = document.getElementById('ic_number');
    if (icField) {
        icField.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, ''); // Remove non-digits
            
            // Apply formatting: XXXXXX-XX-XXXX
            if (value.length > 6) {
                value = value.substring(0, 6) + '-' + value.substring(6);
            }
            if (value.length > 9) {
                value = value.substring(0, 9) + '-' + value.substring(9);
            }
            
            // Limit to 14 characters (12 digits + 2 dashes)
            if (value.length > 14) {
                value = value.substring(0, 14);
            }
            
            this.value = value;
        });
    }
}

/**
 * Auto-format phone number input (digits only)
 */
function setupPhoneNumberFormatting() {
    const phoneField = document.getElementById('phone_number');
    if (phoneField) {
        phoneField.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, ''); // Keep digits only
        });
    }
}

/**
 * Patient form validation with SweetAlert
 */
function setupPatientFormValidation() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const ic = document.getElementById('ic_number').value.trim();
            const diagnosis = document.getElementById('diagnosis').value.trim();
            const phoneNumber = document.getElementById('phone_number').value.trim();

            if (!name || !ic || !diagnosis || !phoneNumber) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields.',
                    confirmButtonColor: '#ffc107'
                });
                return false;
            }

            // Validate IC format (XXXXXX-XX-XXXX)
            if (ic.length !== 14 || !ic.match(/^\d{6}-\d{2}-\d{4}$/)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid IC Number',
                    text: 'IC number must be in format XXXXXX-XX-XXXX.',
                    confirmButtonColor: '#dc3545'
                });
                return false;
            }

            if (phoneNumber.length < 7 || phoneNumber.length > 15) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Phone Number',
                    text: 'Phone number must be between 7-15 digits.',
                    confirmButtonColor: '#dc3545'
                });
                return false;
            }
        });
    }
}

/**
 * Appointment form validation with SweetAlert
 */
function setupAppointmentFormValidation() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const patientId = document.getElementById('patient_id') ? document.getElementById('patient_id').value : 'locked';
            const appointmentDate = document.getElementById('appointment_date').value;
            const appointmentTime = document.getElementById('appointment_time').value;
            const doctorName = document.getElementById('doctor_name').value.trim();
            const reason = document.getElementById('reason').value.trim();

            if (!patientId || !appointmentDate || !appointmentTime || !doctorName || !reason) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields.',
                    confirmButtonColor: '#ffc107'
                });
                return false;
            }

            // Check if appointment is in the past
            const appointmentDateTime = new Date(appointmentDate + 'T' + appointmentTime);
            const now = new Date();
            
            if (appointmentDateTime <= now) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date/Time',
                    text: 'Appointment date and time cannot be in the past.',
                    confirmButtonColor: '#dc3545'
                });
                return false;
            }
        });
    }
}

/**
 * Edit appointment form validation with duration checking
 */
function setupEditAppointmentFormValidation() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const appointmentDate = document.getElementById('appointment_date').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const doctorName = document.getElementById('doctor_name').value.trim();
            const reason = document.getElementById('reason').value.trim();
            
            if (!appointmentDate || !startTime || !endTime || !doctorName || !reason) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all required fields.',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Validate duration
            const start = new Date('2000-01-01T' + startTime);
            const end = new Date('2000-01-01T' + endTime);
            const durationMinutes = (end - start) / (1000 * 60);
            const allowedDurations = [30, 60, 90, 120];
            
            if (!allowedDurations.includes(durationMinutes)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Duration',
                    text: `Appointment duration must be exactly 30, 60, 90, or 120 minutes. Current duration: ${durationMinutes} minutes.`,
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Check if appointment is in the past
            const appointmentDateTime = new Date(appointmentDate + 'T' + startTime);
            const now = new Date();
            
            if (appointmentDateTime <= now) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date/Time',
                    text: 'Cannot schedule appointments in the past.',
                    confirmButtonText: 'OK'
                });
                return;
            }
        });
    }
}

/**
 * Setup appointment duration calculation for edit appointment page
 */
function setupAppointmentDurationCalculation() {
    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const durationSelect = document.getElementById('duration');
    
    if (!startTimeInput || !endTimeInput || !durationSelect) {
        return;
    }
    
    // Calculate current duration on page load and set the correct duration option
    function setCurrentDuration() {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;
        
        if (startTime && endTime) {
            const start = new Date('2000-01-01T' + startTime);
            const end = new Date('2000-01-01T' + endTime);
            const durationMinutes = (end - start) / (1000 * 60);
            
            // Set the duration select to match current duration
            if ([30, 60, 90, 120].includes(durationMinutes)) {
                durationSelect.value = durationMinutes;
            }
        }
    }
    
    // Calculate end time based on start time and duration
    function calculateEndTime() {
        const startTime = startTimeInput.value;
        const duration = parseInt(durationSelect.value);
        
        if (startTime && duration) {
            const start = new Date('2000-01-01T' + startTime);
            start.setMinutes(start.getMinutes() + duration);
            
            const hours = start.getHours().toString().padStart(2, '0');
            const minutes = start.getMinutes().toString().padStart(2, '0');
            endTimeInput.value = hours + ':' + minutes;
        }
    }
    
    // Set current duration on page load
    setCurrentDuration();
    
    // Update end time when start time or duration changes
    startTimeInput.addEventListener('change', calculateEndTime);
    durationSelect.addEventListener('change', calculateEndTime);
}

/**
 * Show validation errors with SweetAlert
 */
function showValidationErrors() {
    if (validationErrors && Object.keys(validationErrors).length > 0 && !validationErrors.general) {
        Swal.fire({
            icon: 'error',
            title: 'Validation Errors',
            text: 'Please check the form for errors and try again.',
            confirmButtonColor: '#dc3545'
        });
    }
}

/**
 * PaginationManager Class
 * Handles pagination logic and UI rendering for doctor dashboard
 */
class PaginationManager {
    constructor(containerId, itemsPerPage = 10) {
        this.containerId = containerId;
        this.itemsPerPage = itemsPerPage;
        this.currentPage = 1;
        this.totalItems = 0;
        this.totalPages = 0;
    }

    /**
     * Calculate pagination metadata
     */
    calculatePagination(totalItems) {
        this.totalItems = totalItems;
        this.totalPages = Math.ceil(totalItems / this.itemsPerPage);
        return {
            totalItems: this.totalItems,
            totalPages: this.totalPages,
            currentPage: this.currentPage,
            itemsPerPage: this.itemsPerPage,
            startItem: (this.currentPage - 1) * this.itemsPerPage + 1,
            endItem: Math.min(this.currentPage * this.itemsPerPage, totalItems)
        };
    }

    /**
     * Render pagination controls
     */
    renderPaginationControls(containerId, baseUrl = '', additionalParams = {}) {
        const container = document.getElementById(containerId);
        if (!container || this.totalPages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        let html = '<nav aria-label="Pagination navigation" class="mt-4">';
        html += '<ul class="pagination justify-content-center">';

        // Previous button
        if (this.currentPage > 1) {
            const prevParams = { ...additionalParams, page: this.currentPage - 1 };
            const prevUrl = this.buildUrl(baseUrl, prevParams);
            html += `<li class="page-item">
                        <a class="page-link" href="${prevUrl}">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                     </li>`;
        }

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.totalPages, this.currentPage + 2);

        for (let i = startPage; i <= endPage; i++) {
            const pageParams = { ...additionalParams, page: i };
            const pageUrl = this.buildUrl(baseUrl, pageParams);
            const activeClass = i === this.currentPage ? 'active' : '';
            html += `<li class="page-item ${activeClass}">
                        <a class="page-link" href="${pageUrl}">${i}</a>
                     </li>`;
        }

        // Next button
        if (this.currentPage < this.totalPages) {
            const nextParams = { ...additionalParams, page: this.currentPage + 1 };
            const nextUrl = this.buildUrl(baseUrl, nextParams);
            html += `<li class="page-item">
                        <a class="page-link" href="${nextUrl}">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                     </li>`;
        }

        html += '</ul></nav>';
        container.innerHTML = html;
    }

    /**
     * Build URL with parameters
     */
    buildUrl(baseUrl, params) {
        const url = new URL(baseUrl || window.location.href);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.set(key, params[key]);
            }
        });
        return url.toString();
    }

    /**
     * Set current page
     */
    setCurrentPage(page) {
        this.currentPage = Math.max(1, Math.min(page, this.totalPages));
    }
}

/**
 * FilterPanelManager Class
 * Manages filter panel visibility and outside-click detection
 */
class FilterPanelManager {
    constructor(toggleButtonId, filterPanelId, inputId) {
        this.toggleButton = document.getElementById(toggleButtonId);
        this.filterPanel = document.getElementById(filterPanelId);
        this.input = document.getElementById(inputId);
        this.isOpen = false;
        
        this.init();
    }

    /**
     * Initialize event listeners
     */
    init() {
        if (!this.toggleButton || !this.filterPanel || !this.input) {
            return;
        }

        // Toggle button click
        this.toggleButton.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggle();
        });

        // Outside click detection
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.filterPanel.contains(e.target) && !this.toggleButton.contains(e.target)) {
                this.close();
            }
        });

        // Prevent panel from closing when clicking inside
        this.filterPanel.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    /**
     * Open filter panel
     */
    open() {
        this.filterPanel.style.display = 'block';
        this.input.focus();
        this.isOpen = true;
    }

    /**
     * Close filter panel
     */
    close() {
        this.filterPanel.style.display = 'none';
        this.isOpen = false;
    }

    /**
     * Toggle filter panel
     */
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
}

/**
 * DoctorNotificationManager Class
 * Handles doctor-specific appointment notifications with session tracking
 */
class DoctorNotificationManager {
    constructor(doctorId) {
        this.doctorId = doctorId;
        this.sessionKey = `doctor_${doctorId}_notifications`;
        this.shownNotifications = this.getShownNotifications();
    }

    /**
     * Get shown notifications from session storage
     */
    getShownNotifications() {
        const stored = sessionStorage.getItem(this.sessionKey);
        return stored ? JSON.parse(stored) : [];
    }

    /**
     * Save shown notifications to session storage
     */
    saveShownNotifications() {
        sessionStorage.setItem(this.sessionKey, JSON.stringify(this.shownNotifications));
    }

    /**
     * Check if notification was already shown
     */
    wasNotificationShown(appointmentId) {
        return this.shownNotifications.includes(appointmentId.toString());
    }

    /**
     * Mark notification as shown
     */
    markNotificationShown(appointmentId) {
        const id = appointmentId.toString();
        if (!this.shownNotifications.includes(id)) {
            this.shownNotifications.push(id);
            this.saveShownNotifications();
        }
    }

    /**
     * Show appointment notification if not already shown
     */
    showAppointmentNotification(appointment) {
        if (this.wasNotificationShown(appointment.id)) {
            return false;
        }

        // Check if appointment is within notification window (60 minutes)
        const appointmentTime = new Date(appointment.start_time).getTime();
        const currentTime = new Date().getTime();
        const oneHour = 60 * 60 * 1000;

        if (appointmentTime > currentTime && appointmentTime <= (currentTime + oneHour)) {
            const timeString = new Date(appointment.start_time).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });

            Swal.fire({
                icon: 'info',
                title: 'Upcoming Appointment Soon!',
                text: `Patient ${appointment.patient_name} is scheduled for ${timeString}. Please prepare.`,
                timer: 10000,
                timerProgressBar: true,
                showConfirmButton: true,
                confirmButtonText: 'Acknowledged'
            }).then(() => {
                this.markNotificationShown(appointment.id);
            });

            return true;
        }

        return false;
    }

    /**
     * Process multiple appointments for notifications
     */
    processAppointments(appointments) {
        let notificationsShown = 0;
        appointments.forEach(appointment => {
            if (this.showAppointmentNotification(appointment)) {
                notificationsShown++;
            }
        });
        return notificationsShown;
    }
}

// Export functions for global access
window.ClinicJS = {
    initializeApp,
    applyTinyMCETheme,
    initializeDiagnosisTinyMCE,
    initializeReasonTinyMCE,
    initializeEditAppointmentTinyMCE,
    setupNameFormatting,
    setupICNumberFormatting,
    setupPhoneNumberFormatting,
    setupPatientFormValidation,
    setupAppointmentFormValidation,
    setupEditAppointmentFormValidation,
    setupAppointmentDurationCalculation,
    showValidationErrors,
    PaginationManager,
    FilterPanelManager,
    DoctorNotificationManager
};