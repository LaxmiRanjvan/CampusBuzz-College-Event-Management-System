<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];

// Get statistics
$my_events_query = "SELECT COUNT(*) as count FROM events WHERE organizer_id = $organizer_id";
$my_events = mysqli_fetch_assoc(mysqli_query($conn, $my_events_query))['count'];

$upcoming_events_query = "SELECT COUNT(*) as count FROM events WHERE organizer_id = $organizer_id AND status = 'upcoming'";
$upcoming_events = mysqli_fetch_assoc(mysqli_query($conn, $upcoming_events_query))['count'];

$completed_events_query = "SELECT COUNT(*) as count FROM events WHERE organizer_id = $organizer_id AND status = 'completed'";
$completed_events = mysqli_fetch_assoc(mysqli_query($conn, $completed_events_query))['count'];

$total_registrations_query = "SELECT COUNT(*) as count FROM registrations r 
                               JOIN events e ON r.event_id = e.id 
                               WHERE e.organizer_id = $organizer_id AND r.status='registered'";
$total_registrations = mysqli_fetch_assoc(mysqli_query($conn, $total_registrations_query))['count'];

// My recent events
$my_events_query = "SELECT e.*, 
                    (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                    FROM events e 
                    WHERE e.organizer_id = $organizer_id 
                    ORDER BY e.created_at DESC LIMIT 5";
$my_events_result = mysqli_query($conn, $my_events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🎯 Organizer Dashboard</h1>
            </div>
            
            <!-- Statistics Cards -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon blue">
                        <span>📅</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $my_events; ?></h3>
                        <p>Total Events Created</p>
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
                
                <div class="dashboard-card">
                    <div class="card-icon purple">
                        <span>✅</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $completed_events; ?></h3>
                        <p>Completed Events</p>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon red">
                        <span>🎫</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_registrations; ?></h3>
                        <p>Total Registrations</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div style="margin-top: 30px; margin-bottom: 30px;">
                <h2 style="margin-bottom: 15px; color: #2d3748;">⚡ Quick Actions</h2>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <a href="create_event.php" class="btn btn-primary">➕ Create New Event</a>
                    <a href="manage_events.php" class="btn btn-secondary">📋 Manage Events</a>
                    <a href="view_registrations.php" class="btn btn-success">👥 View Registrations</a>
                </div>
            </div>
            
            <!-- My Recent Events -->
            <div class="table-container">
                <h2 style="padding: 20px; border-bottom: 1px solid #e2e8f0; color: #2d3748;">
                    📊 My Recent Events
                </h2>
                <table>
                    <thead>
                        <tr>
                            <th>Event Title</th>
                            <th>Date & Time</th>
                            <th>Venue</th>
                            <th>Registrations</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($my_events_result) > 0): ?>
                            <?php while($event = mysqli_fetch_assoc($my_events_result)): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($event['title']); ?></strong></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td><?php echo $event['registered_count']; ?> / <?php echo $event['max_participants']; ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $event['status'] == 'upcoming' ? 'student' : 'organizer'; ?>">
                                            <?php echo ucfirst($event['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_registrations.php?event_id=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-secondary">View</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: #a0aec0;">
                                    No events created yet. <a href="create_event.php">Create your first event!</a>
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