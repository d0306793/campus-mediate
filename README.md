# Campus Mediate - Hostel Management System

A comprehensive hostel management system with advanced booking management, real-time analytics, and interactive reporting capabilities.

## Manager Dashboard Features

### Bookings Management Section

**Features Added:**
- Multi-tab booking interface (Pending, Confirmed, Cancelled, Completed)
- Bulk actions for processing multiple bookings
- Auto-room assignment with customizable numbering templates
- 24-hour cancellation policy enforcement
- Smart cancellation restrictions (no cancellation after room assignment)
- Real-time payment status tracking
- Booking completion with 24-hour undo functionality
- Room assignment notifications to students
- Booking policy documentation for students

**Booking Workflow:**
- **Pending:** New booking requests awaiting manager approval
- **Confirmed:** Approved bookings ready for room assignment
- **Room Assignment:** Automatic room number generation based on templates
- **Completed:** Finished stays with undo option (24 hours)
- **Cancelled:** Bookings cancelled within policy guidelines

**Cancellation Policy:**
- Students can cancel within 24 hours of booking (full refund)
- No cancellation allowed after 24 hours
- No cancellation allowed after room assignment
- Visual indicators show when cancellation is blocked

**Room Assignment System:**
- Automatic room number generation using templates
- Customizable numbering patterns (prefix, padding, floor numbers)
- Real-time occupancy tracking
- Room status updates when assigned
- Student notifications with room details

**Bulk Operations:**
- Confirm all pending bookings
- Auto-assign rooms to all confirmed bookings
- Complete all past bookings
- Auto-processing toggle for hands-free management

**Test it by:**
1. Going to the Bookings tab
2. Switching between booking status tabs
3. Using bulk actions for multiple bookings
4. Assigning rooms and checking occupancy updates
5. Testing cancellation policy restrictions

### Reports & Downloads Section

**Features Added:**
- Interactive Analytics Dashboard with real-time statistical charts
- PDF Preview functionality - view reports before downloading
- Quick Statistics Dashboard - Shows total bookings, revenue, and occupancy rate
- Booking Reports - Detailed booking data with date filters
- Financial Reports - Payment and revenue tracking
- Occupancy Reports - Room utilization by month
- Student Reports - Active residents and check-out data
- Custom Reports - Monthly summaries, payment status, room utilization, booking trends
- Export Options - Both PDF and CSV formats for all reports
- Date Filtering - Customizable date ranges for most reports

**Statistical Charts:**
- Booking Status Distribution - Doughnut chart showing pending/confirmed/completed bookings
- Monthly Revenue Trend - Bar chart displaying revenue performance
- Room Occupancy - Pie chart showing occupied vs available rooms
- Real-time data visualization using Chart.js library
- Interactive hover effects and responsive design

**PDF Preview System:**
- Preview button on all report forms for instant viewing
- Full-screen modal popup with professional formatting
- Styled HTML preview with charts and tables
- Download directly from preview without regenerating
- No need to create PDF files to see content first

**How it works:**
- View interactive charts automatically at top of Reports section
- Managers can select report type and date range
- Click Preview to view formatted report in modal popup
- Click PDF or CSV button to download reports
- Reports open in new tab/window with professional styling
- All reports are filtered by the manager's hostel
- Responsive grid layout for different screen sizes

The reports section now provides comprehensive analytics with visual charts, convenient preview functionality, and professional report generation for hostel managers to track their business performance, occupancy rates, financial status, and student information.

**Test it by:**
1. Going to the Reports tab
2. Viewing the interactive charts at the top
3. Selecting a report type and date range
4. Clicking Preview to view the report in modal
5. Clicking PDF or CSV to download
6. Hovering over chart elements for interactive details
