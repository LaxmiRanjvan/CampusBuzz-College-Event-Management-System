<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get statistics
$registered_events_query = "SELECT COUNT(*) as count FROM registrations WHERE user_id = $student_id AND status='registered'";
$registered_events = mysqli_fetch_assoc(mysqli_query($conn, $registered_events_query))['count'];

$upcoming_registered_query = "SELECT COUNT(*) as count FROM registrations r 
                               JOIN events e ON r.event_id = e.id 
                               WHERE r.user_id = $student_id AND r.status='registered' 
                               AND e.status='upcoming' AND e.event_date > NOW()";
$upcoming_registered = mysqli_fetch_assoc(mysqli_query($conn, $upcoming_registered_query))['count'];

$attended_query = "SELECT COUNT(*) as count FROM registrations r 
                   JOIN events e ON r.event_id = e.id 
                   WHERE r.user_id = $student_id AND e.status='completed'";
$attended = mysqli_fetch_assoc(mysqli_query($conn, $attended_query))['count'];

$available_events_query = "SELECT COUNT(*) as count FROM events 
                           WHERE status='upcoming' AND event_date > NOW()";
$available_events = mysqli_fetch_assoc(mysqli_query($conn, $available_events_query))['count'];

// My upcoming registered events
$my_events_query = "SELECT e.*, u.full_name as organizer_name,
                    (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                    FROM registrations r
                    JOIN events e ON r.event_id = e.id
                    JOIN users u ON e.organizer_id = u.id
                    WHERE r.user_id = $student_id AND r.status='registered' 
                    AND e.status='upcoming' AND e.event_date > NOW()
                    ORDER BY e.event_date ASC LIMIT 5";
$my_events_result = mysqli_query($conn, $my_events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🎓 Student Dashboard</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon blue">
                        <span>🎫</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $registered_events; ?></h3>
                        <p>Total Registrations</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon green">
                        <span>🚀</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $upcoming_registered; ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon purple">
                        <span>✅</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $attended; ?></h3>
                        <p>Events Attended</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon red">
                        <span>📅</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $available_events; ?></h3>
                        <p>Available Events</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div style="margin-top: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 15px; color: #2d3748;">⚡ Quick Actions</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="../common/home.php" class="btn btn-primary">🔍 Browse All Events</a>
                    <a href="my_events.php" class="btn btn-secondary">🎫 My Registered Events</a>
                </div>
            </div>
            
            <!-- My Upcoming Events -->
            <div class="table-container">
                <h2 style="padding: 20px; border-bottom: 1px solid #e2e8f0; color: #2d3748;">
                    📅 My Upcoming Events
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Organizer</th>
                            <th>Participants</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($my_events_result) > 0): ?>
                            <?php while($event = mysqli_fetch_assoc($my_events_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td><?php echo htmlspecialchars($event['organizer_name']); ?></td>
                                    <td><?php echo $event['registered_count']; ?> / <?php echo $event['max_participants']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #a0aec0;">
                                    No upcoming events. <a href="../common/home.php">Browse and register for events!</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>