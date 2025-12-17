<?php
/**
 * Data Service for Dashboard Charts
 * Handles data retrieval and processing for dashboard visualizations
 */

require_once 'config.php';
require_once 'db.php';
require_once 'appointment_analytics.php';

// Handle AJAX requests if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === 'data.php') {
    AppointmentAnalyticsService::handleAjaxRequest();
}