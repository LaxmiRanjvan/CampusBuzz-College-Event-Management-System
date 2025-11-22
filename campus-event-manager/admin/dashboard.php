<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get statistics
$total_users_query = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
$total_users = mysqli_fetch_assoc(mysqli_query($conn, $total_users_query))['count'];

$total_organizers_query = "SELECT COUNT(*) as count FROM users WHERE role = 'organizer'";
$total_organizers = mysqli_fetch_assoc(mysqli_query($conn, $total_organizers_query))['count'];

$total_students_query = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
$total_students = mysqli_fetch_assoc(mysqli_query($conn, $total_students_query))['count'];

$total_events_query = "SELECT COUNT(*) as count FROM events";
$total_events = mysqli_fetch_assoc(mysqli_query($conn, $total_events_query))['count'];

$total_registrations_query = "SELECT COUNT(*) as count FROM registrations WHERE status='registered'";
$total_registrations = mysqli_fetch_assoc(mysqli_query($conn, $total_registrations_query))['count'];

$upcoming_events_query = "SELECT COUNT(*) as count FROM events WHERE status='upcoming' AND event_date > NOW()";
$upcoming_events = mysqli_fetch_assoc(mysqli_query($conn, $upcoming_events_query))['count'];

// Fetch upcoming events for calendar
$calendar_events_query = "SELECT e.*, u.full_name as organizer_name,
                          (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as reg_count
                          FROM events e
                          JOIN users u ON e.organizer_id = u.id
                          WHERE e.status = 'upcoming' AND e.event_date > NOW()
                          ORDER BY e.event_date ASC
                          LIMIT 10";
$calendar_events = mysqli_query($conn, $calendar_events_query);

// Recent users
$recent_users_query = "SELECT * FROM users WHERE role != 'admin' ORDER BY created_at DESC LIMIT 5";
$recent_users_result = mysqli_query($conn, $recent_users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .calendar-event {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .calendar-event:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .calendar-event-date {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .calendar-event-title {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }
        .calendar-event-meta {
            font-size: 13px;
            color: #718096;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>👨‍💼 Admin Dashboard</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon blue">
                        <span>👥</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_users; ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon green">
                        <span>🎯</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_organizers; ?></h3>
                        <p>Organizers</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon purple">
                        <span>🎓</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_students; ?></h3>
                        <p>Students</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon red">
                        <span>📅</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_events; ?></h3>
                        <p>Total Events</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon blue">
                        <span>🎫</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_registrations; ?></h3>
                        <p>Total Registrations</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon green">
                        <span>🚀</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $upcoming_events; ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div style="margin-top: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 15px; color: #2d3748;">⚡ Quick Actions</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="create_user.php" class="btn btn-primary">➕ Create New User</a>
                    <a href="manage_users.php" class="btn btn-secondary">👥 Manage All Users</a>
                    <a href="browse_events.php" class="btn btn-success">📅 View All Events</a>
                    <a href="reports.php" class="btn btn-danger">📄 Generate Reports</a>
                </div>
            </div>
            
            <!-- Two Column Layout: Calendar & Recent Users -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
                
                <!-- Upcoming Events Calendar -->
                <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h2 style="margin-bottom: 20px; color: #2d3748; display: flex; align-items: center; gap: 10px;">
                        📅 Upcoming Events Calendar
                    </h2>
                    
                    <?php if(mysqli_num_rows($calendar_events) > 0): ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php while($event = mysqli_fetch_assoc($calendar_events)): ?>
                                <div class="calendar-event">
                                    <div class="calendar-event-date">
                                        📆 <?php echo date('M d, Y - h:i A', strtotime($event['event_date'])); ?>
                                    </div>
                                    <div class="calendar-event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                    <div class="calendar-event-meta">
                                        📍 <?php echo htmlspecialchars($event['venue']); ?> • 
                                        👤 <?php echo htmlspecialchars($event['organizer_name']); ?> • 
                                        🎫 <?php echo $event['reg_count']; ?> registered
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #a0aec0; padding: 40px;">No upcoming events scheduled</p>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Users -->
                <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h2 style="margin-bottom: 20px; color: #2d3748; display: flex; align-items: center; gap: 10px;">
                        🕐 Recently Registered Users
                    </h2>
                    
                    <?php if(mysqli_num_rows($recent_users_result) > 0): ?>
                        <div style="max-height: 500px; overflow-y: auto;">
                            <?php while($user = mysqli_fetch_assoc($recent_users_result)): ?>
                                <div style="padding: 15px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 600; color: #2d3748; margin-bottom: 5px;">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </div>
                                        <div style="font-size: 13px; color: #718096;">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #a0aec0; margin-top: 3px;">
                                            Registered: <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #a0aec0; padding: 40px;">No users found</p>
                    <?php endif; ?>
                </div>
                
            </div>
        </main>
    </div>
</body>
</html>