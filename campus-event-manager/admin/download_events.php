<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Build WHERE clause
$where_conditions = [];

if(!empty($status_filter)) {
    $where_conditions[] = "e.status = '$status_filter'";
}

if(!empty($category_filter)) {
    $where_conditions[] = "e.category = '$category_filter'";
}

if(!empty($search_query)) {
    $where_conditions[] = "(e.title LIKE '%$search_query%' OR e.description LIKE '%$search_query%' OR u.full_name LIKE '%$search_query%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch events
$events_query = "SELECT e.id, e.title, e.description, u.full_name as organizer_name, u.email as organizer_email,
                 e.event_date, e.venue, e.category, e.max_participants, e.status,
                 (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
                 e.created_at
                 FROM events e
                 JOIN users u ON e.organizer_id = u.id
                 $where_clause
                 ORDER BY e.event_date DESC";
$events_result = mysqli_query($conn, $events_query);

// Set headers for CSV download
$filename = "events_data_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'Event ID',
    'Title',
    'Description',
    'Organizer Name',
    'Organizer Email',
    'Event Date',
    'Venue',
    'Category',
    'Max Participants',
    'Registered',
    'Status',
    'Created Date'
]);

// Add data rows
while($event = mysqli_fetch_assoc($events_result)) {
    fputcsv($output, [
        $event['id'],
        $event['title'],
        $event['description'],
        $event['organizer_name'],
        $event['organizer_email'],
        date('Y-m-d H:i:s', strtotime($event['event_date'])),
        $event['venue'],
        $event['category'] ?? 'N/A',
        $event['max_participants'],
        $event['registered_count'],
        ucfirst($event['status']),
        date('Y-m-d H:i:s', strtotime($event['created_at']))
    ]);
}

fclose($output);
exit();
?>
