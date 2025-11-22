<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get report type
if(!isset($_GET['type'])) {
    header("Location: reports.php");
    exit();
}

$type = $_GET['type'];
$filename = "";
$headers = array();
$data = array();

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

switch($type) {
    case 'all_events':
        $filename = "all_events_" . date('Y-m-d') . ".csv";
        
        // Add report header
        fputcsv($output, array('All Events Report'));
        fputcsv($output, array('Generated Date:', date('Y-m-d H:i:s')));
        fputcsv($output, array('')); // Empty row
        
        // Column headers
        fputcsv($output, array(
            'Event ID',
            'Title',
            'Category',
            'Start Date',
            'Start Time',
            'End Date',
            'End Time',
            'Location',
            'Capacity',
            'Registrations',
            'Available Seats',
            'Fill Rate (%)',
            'Organizer Name',
            'Organizer Department',
            'Created Date'
        ));
        
        // Fetch events with registration counts
        $query = "
            SELECT 
                e.id,
                e.title,
                e.category,
                e.start_date,
                e.start_time,
                e.end_date,
                e.end_time,
                e.location,
                e.capacity,
                e.created_at,
                u.full_name as organizer_name,
                u.department as organizer_dept,
                COUNT(r.id) as registration_count
            FROM events e
            LEFT JOIN users u ON e.organizer_id = u.id
            LEFT JOIN registrations r ON e.id = r.event_id
            GROUP BY e.id
            ORDER BY e.start_date DESC
        ";
        $result = mysqli_query($conn, $query);
        
        while($row = mysqli_fetch_assoc($result)) {
            $fill_rate = $row['capacity'] > 0 ? ($row['registration_count'] / $row['capacity']) * 100 : 0;
            $available_seats = $row['capacity'] - $row['registration_count'];
            
            fputcsv($output, array(
                $row['id'],
                $row['title'],
                $row['category'],
                $row['start_date'],
                $row['start_time'],
                $row['end_date'],
                $row['end_time'],
                $row['location'],
                $row['capacity'],
                $row['registration_count'],
                $available_seats,
                number_format($fill_rate, 2),
                $row['organizer_name'],
                $row['organizer_dept'],
                $row['created_at']
            ));
        }
        break;
        
    case 'all_registrations':
        $filename = "all_registrations_" . date('Y-m-d') . ".csv";
        
        // Add report header
        fputcsv($output, array('All Registrations Report'));
        fputcsv($output, array('Generated Date:', date('Y-m-d H:i:s')));
        fputcsv($output, array('')); // Empty row
        
        // Column headers
        fputcsv($output, array(
            'Registration ID',
            'Event Title',
            'Event Category',
            'Event Date',
            'Student Name',
            'Student Email',
            'Department',
            'Year',
            'Phone',
            'Registration Date'
        ));
        
        // Fetch all registrations
        $query = "
            SELECT 
                r.id,
                e.title as event_title,
                e.category as event_category,
                e.start_date,
                u.full_name,
                u.email,
                u.department,
                u.year,
                u.phone,
                r.registration_date
            FROM registrations r
            LEFT JOIN events e ON r.event_id = e.id
            LEFT JOIN users u ON r.user_id = u.id
            ORDER BY r.registration_date DESC
        ";
        $result = mysqli_query($conn, $query);
        
        while($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, array(
                $row['id'],
                $row['event_title'],
                $row['event_category'],
                $row['start_date'],
                $row['full_name'],
                $row['email'],
                $row['department'],
                $row['year'],
                $row['phone'] ?: 'N/A',
                $row['registration_date']
            ));
        }
        break;
        
    case 'summary':
        $filename = "summary_report_" . date('Y-m-d') . ".csv";
        
        // Add report header
        fputcsv($output, array('Campus Event Manager - Summary Report'));
        fputcsv($output, array('Generated Date:', date('Y-m-d H:i:s')));
        fputcsv($output, array('')); // Empty row
        
        // Overall Statistics
        fputcsv($output, array('=== OVERALL STATISTICS ==='));
        fputcsv($output, array(''));
        
        $total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role != 'admin'"))['count'];
        $total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'student'"))['count'];
        $total_organizers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'organizer'"))['count'];
        $total_events = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM events"))['count'];
        $total_registrations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations"))['count'];
        
        fputcsv($output, array('Total Users:', $total_users));
        fputcsv($output, array('Total Students:', $total_students));
        fputcsv($output, array('Total Organizers:', $total_organizers));
        fputcsv($output, array('Total Events:', $total_events));
        fputcsv($output, array('Total Registrations:', $total_registrations));
        fputcsv($output, array(''));
        
        // Events by Category
        fputcsv($output, array('=== EVENTS BY CATEGORY ==='));
        fputcsv($output, array('Category', 'Event Count', 'Total Registrations'));
        
        $category_query = "
            SELECT e.category, COUNT(DISTINCT e.id) as event_count, COUNT(r.id) as registration_count
            FROM events e
            LEFT JOIN registrations r ON e.id = r.event_id
            GROUP BY e.category
        ";
        $category_result = mysqli_query($conn, $category_query);
        
        while($row = mysqli_fetch_assoc($category_result)) {
            fputcsv($output, array($row['category'], $row['event_count'], $row['registration_count']));
        }
        fputcsv($output, array(''));
        
        // Department Participation
        fputcsv($output, array('=== DEPARTMENT PARTICIPATION ==='));
        fputcsv($output, array('Department', 'Student Count', 'Total Registrations', 'Avg Reg/Student'));
        
        $dept_query = "
            SELECT u.department, COUNT(DISTINCT u.id) as student_count, COUNT(r.id) as registration_count
            FROM users u
            LEFT JOIN registrations r ON u.id = r.user_id
            WHERE u.role = 'student'
            GROUP BY u.department
        ";
        $dept_result = mysqli_query($conn, $dept_query);
        
        while($row = mysqli_fetch_assoc($dept_result)) {
            $avg_reg = $row['student_count'] > 0 ? $row['registration_count'] / $row['student_count'] : 0;
            fputcsv($output, array(
                $row['department'], 
                $row['student_count'], 
                $row['registration_count'],
                number_format($avg_reg, 2)
            ));
        }
        fputcsv($output, array(''));
        
        // Top 10 Events
        fputcsv($output, array('=== TOP 10 MOST POPULAR EVENTS ==='));
        fputcsv($output, array('Event Title', 'Category', 'Registrations', 'Capacity', 'Fill Rate (%)'));
        
        $top_events_query = "
            SELECT e.title, e.category, COUNT(r.id) as registration_count, e.capacity
            FROM events e
            LEFT JOIN registrations r ON e.id = r.event_id
            GROUP BY e.id
            ORDER BY registration_count DESC
            LIMIT 10
        ";
        $top_events_result = mysqli_query($conn, $top_events_query);
        
        while($row = mysqli_fetch_assoc($top_events_result)) {
            $fill_rate = $row['capacity'] > 0 ? ($row['registration_count'] / $row['capacity']) * 100 : 0;
            fputcsv($output, array(
                $row['title'],
                $row['category'],
                $row['registration_count'],
                $row['capacity'],
                number_format($fill_rate, 2)
            ));
        }
        fputcsv($output, array(''));
        
        // Top Organizers
        fputcsv($output, array('=== TOP 10 MOST ACTIVE ORGANIZERS ==='));
        fputcsv($output, array('Organizer Name', 'Department', 'Events Created'));
        
        $top_org_query = "
            SELECT u.full_name, u.department, COUNT(e.id) as event_count
            FROM users u
            LEFT JOIN events e ON u.id = e.organizer_id
            WHERE u.role = 'organizer'
            GROUP BY u.id
            ORDER BY event_count DESC
            LIMIT 10
        ";
        $top_org_result = mysqli_query($conn, $top_org_query);
        
        while($row = mysqli_fetch_assoc($top_org_result)) {
            fputcsv($output, array(
                $row['full_name'],
                $row['department'],
                $row['event_count']
            ));
        }
        
        break;
        
    default:
        header("Location: reports.php");
        exit();
}

// Set filename for download
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Close output stream
fclose($output);
exit();
?>