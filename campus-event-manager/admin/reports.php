<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Date filters with sanitization and validation
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) 
    ? mysqli_real_escape_string($conn, $_GET['start_date']) 
    : date('Y-m-01');

$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) 
    ? mysqli_real_escape_string($conn, $_GET['end_date']) 
    : date('Y-m-d');

// Overall Statistics - with error checking
$total_users_query = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
$total_users_result = mysqli_query($conn, $total_users_query);
$total_users = 0;
if($total_users_result) {
    $total_users = mysqli_fetch_assoc($total_users_result)['count'];
} else {
    error_log("Query failed: " . mysqli_error($conn));
}

$total_students_query = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
$total_students_result = mysqli_query($conn, $total_students_query);
$total_students = 0;
if($total_students_result) {
    $total_students = mysqli_fetch_assoc($total_students_result)['count'];
}

$total_organizers_query = "SELECT COUNT(*) as count FROM users WHERE role = 'organizer'";
$total_organizers_result = mysqli_query($conn, $total_organizers_query);
$total_organizers = 0;
if($total_organizers_result) {
    $total_organizers = mysqli_fetch_assoc($total_organizers_result)['count'];
}

$total_events_query = "SELECT COUNT(*) as count FROM events";
$total_events_result = mysqli_query($conn, $total_events_query);
$total_events = 0;
if($total_events_result) {
    $total_events = mysqli_fetch_assoc($total_events_result)['count'];
}

$total_registrations_query = "SELECT COUNT(*) as count FROM registrations";
$total_registrations_result = mysqli_query($conn, $total_registrations_query);
$total_registrations = 0;
if($total_registrations_result) {
    $total_registrations = mysqli_fetch_assoc($total_registrations_result)['count'];
}

// Events in date range - using prepared statement for safety
$events_in_range = 0;
$events_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM events WHERE event_date BETWEEN ? AND ?");
if($events_stmt) {
    mysqli_stmt_bind_param($events_stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($events_stmt);
    $events_in_range_result = mysqli_stmt_get_result($events_stmt);
    if($events_in_range_result) {
        $events_in_range = mysqli_fetch_assoc($events_in_range_result)['count'];
    }
    mysqli_stmt_close($events_stmt);
}

// Registrations in date range - using prepared statement
$reg_in_range = 0;
$reg_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count FROM registrations WHERE DATE(registration_at) BETWEEN ? AND ?");
if($reg_stmt) {
    mysqli_stmt_bind_param($reg_stmt, "ss", $start_date, $end_date);
    mysqli_stmt_execute($reg_stmt);
    $reg_in_range_result = mysqli_stmt_get_result($reg_stmt);
    if($reg_in_range_result) {
        $reg_in_range = mysqli_fetch_assoc($reg_in_range_result)['count'];
    }
    mysqli_stmt_close($reg_stmt);
}

// Most popular events
$popular_events_query = "
    SELECT e.id, e.title, e.category, COUNT(r.id) as registration_count, e.capacity
    FROM events e
    LEFT JOIN registrations r ON e.id = r.event_id
    GROUP BY e.id, e.title, e.category, e.capacity
    ORDER BY registration_count DESC
    LIMIT 10
";
$popular_events = mysqli_query($conn, $popular_events_query);
if(!$popular_events) {
    error_log("Popular events query failed: " . mysqli_error($conn));
    $popular_events = mysqli_query($conn, "SELECT NULL LIMIT 0"); // Empty result set
}

// Events by category
$category_stats_query = "
    SELECT e.category, COUNT(DISTINCT e.id) as event_count, COUNT(r.id) as registration_count
    FROM events e
    LEFT JOIN registrations r ON e.id = r.event_id
    GROUP BY e.category
    ORDER BY event_count DESC
";
$category_stats = mysqli_query($conn, $category_stats_query);
if(!$category_stats) {
    error_log("Category stats query failed: " . mysqli_error($conn));
    $category_stats = mysqli_query($conn, "SELECT NULL LIMIT 0");
}

// Department participation
$dept_participation_query = "
    SELECT u.department, COUNT(DISTINCT u.id) as student_count, COUNT(r.id) as registration_count
    FROM users u
    LEFT JOIN registrations r ON u.id = r.user_id
    WHERE u.role = 'student'
    GROUP BY u.department
    ORDER BY registration_count DESC
";
$dept_participation = mysqli_query($conn, $dept_participation_query);
if(!$dept_participation) {
    error_log("Department participation query failed: " . mysqli_error($conn));
    $dept_participation = mysqli_query($conn, "SELECT NULL LIMIT 0");
}

// Most active organizers
$active_organizers_query = "
    SELECT u.id, u.full_name, u.department, COUNT(e.id) as event_count
    FROM users u
    LEFT JOIN events e ON u.id = e.organizer_id
    WHERE u.role = 'organizer'
    GROUP BY u.id, u.full_name, u.department
    ORDER BY event_count DESC
    LIMIT 10
";
$active_organizers = mysqli_query($conn, $active_organizers_query);
if(!$active_organizers) {
    error_log("Active organizers query failed: " . mysqli_error($conn));
    $active_organizers = mysqli_query($conn, "SELECT NULL LIMIT 0");
}

// Recent activity (last 30 days)
$recent_registrations_query = "
    SELECT DATE(registration_date) as date, COUNT(*) as count
    FROM registrations
    WHERE registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(registration_date)
    ORDER BY date DESC
    LIMIT 30
";
$recent_registrations = mysqli_query($conn, $recent_registrations_query);
if(!$recent_registrations) {
    error_log("Recent registrations query failed: " . mysqli_error($conn));
    $recent_registrations = mysqli_query($conn, "SELECT NULL LIMIT 0");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .filter-section {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .report-table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .report-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .report-table tr:hover {
            background: #f7fafc;
        }
        
        .progress-bar {
            background: #e2e8f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: white;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📊 Reports & Analytics</h1>
                <div class="export-buttons">
                    <a href="export_report.php?type=all_events" class="export-btn" style="background: #10b981;">
                        📥 Export All Events
                    </a>
                    <a href="export_report.php?type=all_registrations" class="export-btn" style="background: #3b82f6;">
                        📥 Export All Registrations
                    </a>
                    <a href="export_report.php?type=summary" class="export-btn" style="background: #8b5cf6;">
                        📥 Export Summary Report
                    </a>
                </div>
            </div>
            
            <!-- Date Filter -->
            <div class="filter-section">
                <form method="GET" action="" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap; width: 100%;">
                    <div class="form-group" style="margin: 0;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">End Date</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin: 0;">Apply Filter</button>
                    <a href="reports.php" class="btn btn-secondary" style="margin: 0;">Reset</a>
                </form>
            </div>
            
            <!-- Overall Statistics -->
            <div class="stat-grid">
                <div class="stat-box">
                    <div class="stat-number"><?php echo $total_users; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                
                <div class="stat-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students</div>
                </div>
                
                <div class="stat-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-number"><?php echo $total_organizers; ?></div>
                    <div class="stat-label">Organizers</div>
                </div>
                
                <div class="stat-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-number"><?php echo $total_events; ?></div>
                    <div class="stat-label">Total Events</div>
                </div>
                
                <div class="stat-box" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <div class="stat-number"><?php echo $total_registrations; ?></div>
                    <div class="stat-label">Total Registrations</div>
                </div>
            </div>
            
            <!-- Date Range Statistics -->
            <div class="report-card">
                <h3 style="margin-top: 0;">📅 Date Range Statistics (<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>)</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: #f7fafc; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: #667eea;"><?php echo $events_in_range; ?></div>
                        <div style="color: #718096;">Events Created</div>
                    </div>
                    
                    <div style="background: #f7fafc; padding: 20px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 2rem; font-weight: 700; color: #667eea;"><?php echo $reg_in_range; ?></div>
                        <div style="color: #718096;">New Registrations</div>
                    </div>
                </div>
            </div>
            
            <!-- Rest of the report sections... -->
            <!-- (Continue with the remaining HTML from your original file) -->
            
        </main>
    </div>
</body>
</html>