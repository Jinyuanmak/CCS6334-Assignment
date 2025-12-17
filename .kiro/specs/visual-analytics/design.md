# Visual Analytics Feature Design Document

## Overview

The Visual Analytics feature enhances the Private Clinic Patient Record System's Admin Dashboard by adding interactive data visualizations. The primary component is a line chart displaying appointment counts with the ability to toggle between weekly (7 days) and monthly (30 days) views, providing administrators with immediate visual insights into workload distribution, scheduling patterns, and trends over time.

This feature integrates seamlessly with the existing PHP-based dashboard, leveraging Chart.js for client-side rendering and maintaining the current Bootstrap-based styling framework. The implementation follows the existing architecture patterns while adding minimal overhead to page load times. The line chart visualization with view toggles provides a more sophisticated and flexible analytics experience.

## Architecture

### High-Level Architecture

The Visual Analytics feature follows a three-tier architecture:

1. **Data Layer**: SQL queries against the existing appointments table with date-based filtering and aggregation
2. **Processing Layer**: PHP backend logic that transforms raw appointment data into JSON format suitable for visualization
3. **Presentation Layer**: Chart.js-powered interactive bar chart rendered in the browser

### Integration Points

- **Database Integration**: Utilizes existing `appointments` table with `start_time` column for date-based queries
- **Authentication**: Leverages existing admin authentication system via `Database::requireAuth()`
- **Styling**: Integrates with current Bootstrap 5 framework and existing card-based layout
- **Error Handling**: Follows established error handling patterns with try-catch blocks and graceful degradation

## Components and Interfaces

### Backend Components

#### AppointmentAnalyticsService
**Purpose**: Handles data retrieval and processing for visual analytics

**Key Methods**:
- `getAppointmentCounts($days)`: Returns array of appointment counts for specified number of days (7 or 30)
- `generateDateLabels($days)`: Creates date labels appropriate for the time period
- `formatDataForChart($days)`: Converts raw data to Chart.js compatible format

**Database Query (Parameterized)**:
```sql
SELECT DATE(start_time) as app_date, COUNT(*) as count 
FROM appointments 
WHERE start_time BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY) 
GROUP BY DATE(start_time)
ORDER BY app_date ASC
```

#### Data Processing Logic
- Generates complete date range (7 or 30 days) regardless of appointment availability
- Fills missing dates with zero counts to ensure consistent chart display
- Converts dates to appropriate labels based on view:
  - Weekly view: Abbreviated day names (Mon, Tue, Wed, etc.)
  - Monthly view: Date format (Dec 18, Dec 19, etc.)
- Outputs JSON-encoded arrays for JavaScript consumption
- Supports dynamic switching between time periods

### Frontend Components

#### WorkloadChart
**Purpose**: Renders interactive line chart using Chart.js with view toggle functionality

**Configuration**:
- Chart Type: Line chart with smooth curves
- Color Scheme: Soft blue (#3b82f6) for line, circular markers at data points
- Responsive: Maintains aspect ratio across device sizes
- Interactions: Hover tooltips showing exact counts, clickable view toggles
- Animation: Smooth transitions when switching between views

**HTML Structure**:
```html
<div class="card shadow">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-chart-line me-2"></i>
            Appointment Trends
        </h5>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary active" id="weeklyViewBtn">
                Weekly
            </button>
            <button type="button" class="btn btn-outline-primary" id="monthlyViewBtn">
                Monthly
            </button>
        </div>
    </div>
    <div class="card-body">
        <canvas id="workloadChart"></canvas>
    </div>
</div>
```

#### ViewToggleController
**Purpose**: Manages switching between weekly and monthly views

**Key Functions**:
- `switchView(viewType)`: Fetches data for specified view and updates chart
- `updateChart(labels, data)`: Updates Chart.js instance with new data
- `highlightActiveButton(viewType)`: Updates button styling to show active view
- `fetchAppointmentData(days)`: Makes AJAX request for appointment data

## Data Models

### AppointmentAnalytics Data Structure

```php
// Raw appointment data from database
$appointmentData = [
    ['app_date' => '2025-12-17', 'count' => 5],
    ['app_date' => '2025-12-18', 'count' => 3],
    // ... additional dates
];

// Processed data for Chart.js
$chartData = [
    'labels' => ['Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon'],
    'counts' => [5, 3, 0, 8, 2, 1, 4]
];
```

### Chart.js Configuration Object

```javascript
const chartConfig = {
    type: 'line',
    data: {
        labels: jsonDates,
        datasets: [{
            label: 'Appointments',
            data: jsonCounts,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#3b82f6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `${context.parsed.y} appointment${context.parsed.y !== 1 ? 's' : ''}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        animation: {
            duration: 750,
            easing: 'easeInOutQuart'
        }
    }
};
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Complete Date Coverage
*For any* 7-day period starting from today, the Visual Analytics System should return exactly 7 data points, one for each consecutive day, regardless of whether appointments exist for those dates
**Validates: Requirements 3.5**

### Property 2: Data Consistency
*For any* date within the next 7 days, the appointment count returned by the Visual Analytics System should equal the actual number of appointments scheduled for that date in the database
**Validates: Requirements 1.2**

### Property 3: Date Label Accuracy
*For any* generated date label, it should correspond to the correct abbreviated day name (Mon, Tue, Wed, Thu, Fri, Sat, Sun) for the actual calendar date
**Validates: Requirements 1.3**

### Property 4: Chart Rendering Integrity
*For any* valid dataset provided to Chart.js, the rendered bar chart should display exactly the same number of bars as there are data points, with each bar height proportional to its corresponding count value
**Validates: Requirements 2.5**

### Property 5: Error Handling Graceful Degradation
*For any* database error or connection failure, the Visual Analytics System should display appropriate fallback content without breaking the dashboard layout or functionality
**Validates: Requirements 3.4**

### Property 6: JSON Data Format Consistency
*For any* processed appointment data, the JSON output should contain two arrays of equal length: one for date labels and one for corresponding counts
**Validates: Requirements 3.2**

### Property 7: Weekly View Date Range
*For any* current date, when the weekly view is selected, the Visual Analytics System should return exactly 7 consecutive days of appointment data starting from today
**Validates: Requirements 6.2**

### Property 8: Monthly View Date Range
*For any* current date, when the monthly view is selected, the Visual Analytics System should return exactly 30 consecutive days of appointment data starting from today
**Validates: Requirements 6.3**

### Property 9: View Toggle State Consistency
*For any* view selection (weekly or monthly), exactly one toggle button should have the active state while the other should not
**Validates: Requirements 6.5**

### Property 10: Chart Update Without Reload
*For any* view switch operation, the chart should update its data without triggering a full page reload, maintaining the Chart.js instance
**Validates: Requirements 6.4**

### Property 11: Monthly Date Format Appropriateness
*For any* date in the monthly view, the label format should include both month and day information (e.g., "Dec 18") to distinguish dates across the 30-day span
**Validates: Requirements 6.6**

## Error Handling

### Database Error Scenarios
- **Connection Failure**: Display "Unable to load analytics data" message in chart area
- **Query Timeout**: Implement 5-second timeout with fallback to cached data if available
- **Permission Errors**: Log security event and show generic error message

### Data Processing Errors
- **Invalid Date Ranges**: Default to current date + 7 days if date calculation fails
- **Missing Appointment Data**: Display zero counts for all days with informational message
- **JSON Encoding Errors**: Fallback to empty arrays to prevent JavaScript errors

### Frontend Error Handling
- **Chart.js Load Failure**: Display static message indicating charts are unavailable
- **Canvas Rendering Issues**: Provide text-based fallback showing appointment counts
- **Responsive Layout Breaks**: Ensure chart container maintains minimum dimensions

## Testing Strategy

### Unit Testing Approach
The testing strategy employs both unit tests for specific functionality and property-based tests for comprehensive validation across input ranges.

**Unit Test Coverage**:
- Date range generation logic
- SQL query parameter binding
- JSON encoding/decoding operations
- Chart.js configuration object creation
- Error handling for specific failure scenarios

**Key Unit Test Cases**:
- Test appointment count aggregation for known date ranges
- Verify date label generation for different starting days of week
- Validate JSON output format matches Chart.js requirements
- Test graceful degradation when database is unavailable

### Property-Based Testing Framework
**Framework**: PHPUnit with Faker library for data generation
**Minimum Iterations**: 100 test runs per property to ensure statistical confidence
**Generator Strategy**: Create random appointment datasets with varying dates, times, and counts

**Property Test Implementation Requirements**:
- Each property-based test must run minimum 100 iterations
- Tests must be tagged with comments referencing design document properties
- Tag format: `**Feature: visual-analytics, Property {number}: {property_text}**`
- Generators should create realistic appointment data within valid date ranges

**Test Data Generation Strategy**:
- Generate random appointment dates within realistic ranges (next 30 days)
- Create varying appointment counts (0-20 per day) to test edge cases
- Include boundary conditions like weekends, holidays, and empty days
- Test with different starting days of the week to verify label accuracy

### Integration Testing
- End-to-end testing of dashboard page load with analytics enabled
- Cross-browser compatibility testing for Chart.js rendering
- Mobile responsiveness testing for chart interactions
- Performance testing with large appointment datasets (1000+ appointments)

### Accessibility Testing
- Screen reader compatibility with ARIA labels
- Keyboard navigation support for chart interactions and view toggle buttons
- Color contrast validation for chart elements and toggle buttons
- Focus management when chart updates dynamically
- Ensure toggle buttons are keyboard accessible with proper focus indicators

### View Toggle Testing
- Test switching from weekly to monthly view updates chart correctly
- Test switching from monthly to weekly view updates chart correctly
- Verify active button state changes appropriately
- Test that chart data matches selected view period
- Verify smooth transitions without page reload
- Test button click handlers and event listeners