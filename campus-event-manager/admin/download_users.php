<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters (same as manage_users.php)
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';
$year_filter = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$sort_by = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Build WHERE clause
$where_conditions = ["role != 'admin'"];

if(!empty($role_filter)) {
    $where_conditions[] = "role = '$role_filter'";
}

if(!empty($department_filter)) {
    $where_conditions[] = "department = '$department_filter'";
}

if(!empty($year_filter)) {
    $where_conditions[] = "year = '$year_filter'";
}

if(!empty($search_query)) {
    $where_conditions[] = "(full_name LIKE '%$search_query%' OR username LIKE '%$search_query%' OR email LIKE '%$search_query%')";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Validate sort column
$allowed_sort = ['full_name', 'username', 'email', 'role', 'department', 'year', 'created_at'];
if(!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'created_at';
}

// Fetch users
$users_query = "SELECT id, full_name, username, email, role, department, year, phone, created_at 
                FROM users $where_clause ORDER BY $sort_by $sort_order";
$users_result = mysqli_query($conn, $users_query);

// Set headers for CSV download
$filename = "users_data_" . date('Y-m-d_His') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'User ID',
    'Full Name',
    'Username',
    'Email',
    'Role',
    'Department',
    'Year',
    'Phone',
    'Registration Date'
]);

// Add data rows
while($user = mysqli_fetch_assoc($users_result)) {
    fputcsv($output, [
        $user['id'],
        $user['full_name'],
        $user['username'],
        $user['email'],
        ucfirst($user['role']),
        $user['department'] ?? 'N/A',
        $user['year'] ?? 'N/A',
        $user['phone'] ?? 'N/A',
        date('Y-m-d H:i:s', strtotime($user['created_at']))
    ]);
}

fclose($output);
exit();
?>