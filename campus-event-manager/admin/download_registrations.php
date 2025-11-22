<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get event ID
if(!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    header("Location: view_events.php");
    exit();
}

$event_id = intval($_GET['event_id']);

// Get event details
$event_query = "SELECT title FROM events WHERE id = $event_id";
$event_result = mysqli_query($conn, $event_query);

if(!$event_result || mysqli_num_rows($event_result) == 0) {
    header("Location: view_events.php");
    exit();
}

$event = mysqli_fetch_assoc($event_result);

// Get registrations with user details - FIXED: using registered_at
$reg_query = "
    SELECT 
        r.id as registration_id,
        u.id as user_id,
        u.full_name,
        u.username,
        u.email,
        u.department,
        u.year,
        u.phone,
        r.registered_at,
        r.status
    FROM registrations r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.event_id = $event_id
    ORDER BY r.registered_at DESC
";
$reg_result = mysqli_query($conn, $reg_query);

if(!$reg_result) {
    die("Query failed: " . mysqli_error($conn));
}

// Set headers for CSV download
$filename = "event_" . $event_id . "_registrations_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add event information header
fputcsv($output, array('Event Registrations Report'));
fputcsv($output, array('Event Name:', $event['title']));
fputcsv($output, array('Generated Date:', date('Y-m-d H:i:s')));
fputcsv($output, array('Total Registrations:', mysqli_num_rows($reg_result)));
fputcsv($output, array('')); // Empty row

// Add column headers
fputcsv($output, array(
    'Registration ID',
    'User ID',
    'Full Name',
    'Username',
    'Email',
    'Department',
    'Year',
    'Phone',
    'Status',
    'Registration Date'
));

// Add data rows
while($row = mysqli_fetch_assoc($reg_result)) {
    fputcsv($output, array(
        $row['registration_id'],
        $row['user_id'],
        $row['full_name'] ?: 'N/A',
        $row['username'] ?: 'N/A',
        $row['email'] ?: 'N/A',
        $row['department'] ?: 'N/A',
        $row['year'] ?: 'N/A',
        $row['phone'] ?: 'N/A',
        ucfirst($row['status']),
        date('Y-m-d H:i:s', strtotime($row['registered_at']))
    ));
}

// Close output stream
fclose($output);
exit();
?>