# Implementation Plan

- [x] 1. Set up Chart.js integration and basic structure





  - Add Chart.js CDN link to dashboard.php head section
  - Create Weekly Workload card section in dashboard layout
  - Add canvas element with proper ID for chart rendering
  - _Requirements: 2.1, 2.2_

- [-] 2. Implement backend data processing logic



  - [x] 2.1 Create appointment analytics data retrieval function


    - Write SQL query to get appointment counts for next 7 days with date grouping
    - Implement date range generation for complete 7-day coverage
    - Handle missing dates by filling with zero counts
    - _Requirements: 1.2, 3.1, 3.3, 3.5_

  - [x] 2.2 Write property test for complete date coverage

    - **Property 1: Complete Date Coverage**
    - **Validates: Requirements 3.5**

  - [x] 2.3 Implement date label formatting


    - Create function to convert dates to abbreviated day names (Mon, Tue, Wed, etc.)
    - Ensure proper timezone handling using existing system timezone
    - _Requirements: 1.3_

  - [x] 2.4 Write property test for date label accuracy

    - **Property 3: Date Label Accuracy**
    - **Validates: Requirements 1.3**

  - [x] 2.5 Create JSON data formatting for Chart.js


    - Format processed data into separate arrays for labels and counts
    - Ensure proper JSON encoding for JavaScript consumption
    - _Requirements: 3.2_

  - [x] 2.6 Write property test for JSON data format consistency

    - **Property 6: JSON Data Format Consistency**
    - **Validates: Requirements 3.2**

- [x] 3. Implement error handling and graceful degradation




  - [x] 3.1 Add database error handling for analytics queries


    - Implement try-catch blocks around appointment data retrieval
    - Create fallback content for database connection failures
    - Log errors appropriately without exposing sensitive information
    - _Requirements: 3.4_

  - [x] 3.2 Write property test for error handling graceful degradation

    - **Property 5: Error Handling Graceful Degradation**
    - **Validates: Requirements 3.4**

- [x] 4. Create Chart.js configuration and rendering




  - [x] 4.1 Implement Chart.js initialization script


    - Create chart configuration object with bar chart type
    - Set soft blue color (#3b82f6) and rounded corners for bars
    - Configure responsive behavior and tooltip interactions
    - _Requirements: 1.4, 1.5, 2.5_

  - [x] 4.2 Add chart rendering logic to dashboard


    - Pass PHP JSON data to JavaScript variables
    - Initialize Chart.js with processed appointment data
    - Ensure chart renders after DOM content loads
    - _Requirements: 1.1, 2.5_

  - [x] 4.3 Write property test for chart rendering integrity

    - **Property 4: Chart Rendering Integrity**
    - **Validates: Requirements 2.5**

- [x] 5. Implement responsive design and accessibility






  - [x] 5.1 Add responsive chart container styling


    - Ensure chart maintains proper aspect ratio on different screen sizes
    - Integrate with existing Bootstrap grid system
    - Test mobile device compatibility
    - _Requirements: 4.1, 4.2_

  - [x] 5.2 Implement accessibility features


    - Add appropriate ARIA labels for screen readers
    - Ensure keyboard navigation support for chart interactions
    - Verify color contrast meets accessibility standards
    - _Requirements: 4.3, 4.4, 4.5_

  - [x] 5.3 Write property test for data consistency

    - **Property 2: Data Consistency**
    - **Validates: Requirements 1.2**

- [x] 6. Integration and positioning in dashboard layout





  - [x] 6.1 Position Weekly Workload card in dashboard


    - Place chart card prominently in existing dashboard layout
    - Ensure proper spacing and alignment with other dashboard elements
    - Maintain existing dashboard styling and Bootstrap framework
    - _Requirements: 2.3, 2.4_

  - [x] 6.2 Test chart integration with existing dashboard functionality


    - Verify no conflicts with existing JavaScript functionality
    - Ensure chart loads properly with dashboard authentication
    - Test chart behavior with different appointment data scenarios
    - _Requirements: 2.4_

- [x] 7. Final testing and validation




  - [x] 7.1 Perform end-to-end testing


    - Test complete dashboard load with analytics enabled
    - Verify chart displays correctly with real appointment data
    - Test error scenarios and fallback behavior
    - _Requirements: 1.1, 3.4_

  - [x] 7.2 Write unit tests for data processing functions

    - Create unit tests for date range generation
    - Test SQL query parameter binding and execution
    - Validate JSON encoding/decoding operations
    - _Requirements: 1.2, 3.1, 3.2_

- [x] 8. Checkpoint - Ensure all tests pass



  - Ensure all tests pass, ask the user if questions arise.

- [x] 9. Convert bar chart to line chart with smooth curves




  - [x] 9.1 Update Chart.js configuration to use line chart type


    - Change chart type from 'bar' to 'line' in configuration
    - Add tension property (0.4) for smooth curves between data points
    - Configure point styling with circular markers (radius: 4)
    - Update colors to use soft blue (#3b82f6) for line and points
    - Add fill property with semi-transparent background
    - _Requirements: 5.1, 5.2, 5.3, 5.4_

  - [x] 9.2 Write property test for line chart configuration

    - **Property 12: Line Chart Configuration**
    - **Validates: Requirements 5.1, 5.2, 5.3, 5.4**

  - [x] 9.3 Update tooltip configuration for line chart


    - Ensure tooltips display correctly on hover over data points
    - Verify tooltip formatting shows exact appointment counts
    - _Requirements: 5.5_

- [x] 10. Implement view toggle functionality







  - [x] 10.1 Add toggle buttons to chart card header

    - Create button group with Weekly and Monthly buttons
    - Position buttons beside chart title in card header
    - Add appropriate IDs (weeklyViewBtn, monthlyViewBtn)
    - Style buttons using Bootstrap btn-outline-primary
    - _Requirements: 6.1_

  - [x] 10.2 Implement backend endpoint for dynamic data fetching


    - Create PHP function to accept view type parameter ('weekly' or 'monthly')
    - Implement separate SQL queries for weekly (7 days) and monthly (12 months) views
    - Return JSON response with labels and counts for requested period
    - Handle weekly day sequence (Mon, Tue, Wed, Thu, Fri, Sat, Sun) and monthly labels (Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
    - _Requirements: 6.2, 6.3, 6.6, 6.7_


  - [x] 10.3 Write property test for weekly view date range

    - **Property 7: Weekly View Date Range**
    - **Validates: Requirements 6.2**

  - [x] 10.4 Write property test for monthly view date range

    - **Property 8: Monthly View Date Range**
    - **Validates: Requirements 6.3**

  - [ ] 10.5 Write property test for total count display

    - **Property 12: Total Count Display**
    - **Validates: Requirements 6.7**

  - [x] 10.6 Create ViewToggleController JavaScript component


    - Implement switchView(viewType) function to handle view changes
    - Create updateChart(labels, data) function to refresh chart data
    - Add highlightActiveButton(viewType) for button state management
    - Implement AJAX request to fetch data based on selected view
    - _Requirements: 6.2, 6.3, 6.4, 6.5_

  - [x] 10.7 Write property test for toggle button state consistency

    - **Property 9: View Toggle State Consistency**
    - **Validates: Requirements 6.5**

  - [x] 10.8 Write property test for chart update without reload

    - **Property 10: Chart Update Without Reload**
    - **Validates: Requirements 6.4**

  - [x] 10.9 Implement date label formatting for monthly view



    - Create function to format months as abbreviated month names (Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
    - Ensure weekly view uses correct day sequence (Mon, Tue, Wed, Thu, Fri, Sat, Sun)
    - Handle calendar month grouping and total count calculation
    - _Requirements: 6.6, 6.7_

  - [x] 10.10 Write property test for monthly date format

    - **Property 11: Monthly Date Format Appropriateness**
    - **Validates: Requirements 6.6**

- [x] 11. Update chart styling and animations




  - [x] 11.1 Configure smooth transitions for view switching


    - Add animation configuration to Chart.js options
    - Set appropriate duration (750ms) and easing function
    - Ensure chart updates smoothly when data changes
    - _Requirements: 6.4_

  - [x] 11.2 Update card header layout for toggle buttons


    - Use flexbox to position title and buttons
    - Ensure responsive layout on mobile devices
    - Maintain consistent spacing and alignment
    - _Requirements: 6.1_

- [x] 12. Integration testing and validation





  - [x] 12.1 Test view toggle functionality end-to-end


    - Verify weekly view displays 7 days correctly in sequence (Mon, Tue, Wed, Thu, Fri, Sat, Sun)
    - Verify monthly view displays 12 months correctly (Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
    - Test switching between views updates chart properly
    - Ensure active button state changes correctly
    - _Requirements: 6.2, 6.3, 6.4, 6.5_

  - [x] 12.2 Test line chart rendering with real data


    - Verify line chart displays correctly with various data patterns
    - Test with zero appointments, sparse data, and full data
    - Ensure smooth curves and data points render properly
    - _Requirements: 5.1, 5.3, 5.4_

  - [x] 12.3 Write unit tests for date formatting functions

    - Test weekly date label generation (Mon, Tue, Wed, etc.)
    - Test monthly date label generation (Jan, Feb, Mar, Apr, May, Jun, July, Aug, Sep, Oct, Nov, Dec)
    - Test edge cases like month transitions
    - _Requirements: 6.6_

- [x] 14. Update backend for new monthly view and day sequence








  - [x] 14.1 Modify monthly view to use calendar months instead of 30-day rolling window




    - Update SQL query to group by YEAR and MONTH instead of consecutive days
    - Implement logic to return 12 months of data (Jan through Dec)
    - Handle cases where some months have no appointments (fill with zero)
    - _Requirements: 6.3, 6.6, 6.7_

  - [x] 14.2 Fix weekly day sequence to match Mon-Sun order




    - Update day label generation to ensure correct sequence: Mon, Tue, Wed, Thu, Fri, Sat, Sun
    - Modify date processing logic to align with the specified day order
    - _Requirements: 1.3_

  - [x] 14.3 Implement total count display for both views




    - Ensure tooltips show total appointment counts for each time period
    - Update chart configuration to display counts clearly
    - _Requirements: 6.7_

- [x] 15. Final checkpoint - Ensure all tests pass





  - Ensure all tests pass, ask the user if questions arise.