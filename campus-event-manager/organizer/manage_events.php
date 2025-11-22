<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle delete event
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = intval($_GET['delete']);
    
    // Check if event belongs to this organizer
    $check_query = "SELECT * FROM events WHERE id = $event_id AND organizer_id = $organizer_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if(mysqli_num_rows($check_result) > 0) {
        $event = mysqli_fetch_assoc($check_result);
        
        // Delete image file if exists
        if(!empty($event['image']) && file_exists('../uploads/' . $event['image'])) {
            unlink('../uploads/' . $event['image']);
        }
        
        // Delete event (registrations will be deleted automatically due to CASCADE)
        $delete_query = "DELETE FROM events WHERE id = $event_id";
        if(mysqli_query($conn, $delete_query)) {
            $success = "Event deleted successfully!";
        } else {
            $error = "Failed to delete event!";
        }
    } else {
        $error = "You don't have permission to delete this event!";
    }
}

// Handle status change
if(isset($_GET['status_change']) && is_numeric($_GET['status_change']) && isset($_GET['new_status'])) {
    $event_id = intval($_GET['status_change']);
    $new_status = mysqli_real_escape_string($conn, $_GET['new_status']);
    
    $allowed_statuses = ['upcoming', 'ongoing', 'completed', 'cancelled'];
    if(in_array($new_status, $allowed_statuses)) {
        $update_query = "UPDATE events SET status = '$new_status' WHERE id = $event_id AND organizer_id = $organizer_id";
        if(mysqli_query($conn, $update_query)) {
            $success = "Event status updated successfully!";
        } else {
            $error = "Failed to update status!";
        }
    }
}

// Fetch all events by this organizer
$events_query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                 FROM events e 
                 WHERE e.organizer_id = $organizer_id 
                 ORDER BY e.created_at DESC";
$events_result = mysqli_query($conn, $events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📋 Manage My Events</h1>
                <a href="create_event.php" class="btn btn-primary">➕ Create New Event</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(mysqli_num_rows($events_result) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Event Details</th>
                                <th>Date & Time</th>
                                <th>Venue</th>
                                <th>Registrations</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($event = mysqli_fetch_assoc($events_result)): ?>
                                <tr>
                                    <td>
                                        <?php if($event['image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" 
                                                 alt="Event" style="width: 80px; height: 60px; object-fit: cover; border-radius: 5px;">
                                        <?php else: ?>
                                            <div style="width: 80px; height: 60px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; border-radius: 5px; font-size: 24px;">
                                                📅
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #2d3748;"><?php echo htmlspecialchars($event['title']); ?></strong><br>
                                        <small style="color: #718096;">
                                            <?php echo htmlspecialchars(substr($event['description'], 0, 80)); ?>...
                                        </small><br>
                                        <?php if($event['category']): ?>
                                            <span style="display: inline-block; margin-top: 5px; padding: 3px 8px; background: #bee3f8; color: #2c5282; border-radius: 12px; font-size: 11px;">
                                                <?php echo htmlspecialchars($event['category']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo date('M d, Y', strtotime($event['event_date'])); ?></strong><br>
                                        <small style="color: #718096;"><?php echo date('h:i A', strtotime($event['event_date'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                    <td>
                                        <strong style="color: <?php echo $event['registered_count'] >= $event['max_participants'] ? '#f56565' : '#48bb78'; ?>">
                                            <?php echo $event['registered_count']; ?>
                                        </strong> / <?php echo $event['max_participants']; ?>
                                        <br>
                                        <a href="view_registrations.php?event_id=<?php echo $event['id']; ?>" 
                                           style="font-size: 12px; color: #667eea;">View List</a>
                                    </td>
                                    <td>
                                        <select onchange="changeStatus(<?php echo $event['id']; ?>, this.value)" 
                                                style="padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px;">
                                            <option value="upcoming" <?php echo $event['status'] == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                            <option value="ongoing" <?php echo $event['status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                            <option value="completed" <?php echo $event['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $event['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                           <a href="../student/view_event.php?id=<?php echo $event['id']; ?>" 
                                               class="btn btn-success btn-sm" 
                                               onclick="event.stopPropagation()"> View</a>
                                            <a href="edit_event.php?id=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">✏️ Edit</a>
                                            <a href="?delete=<?php echo $event['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this event? All registrations will also be deleted!')" 
                                               title="Delete">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px;">
                    <h2 style="color: #718096; margin-bottom: 10px;">📭 No Events Created Yet</h2>
                    <p style="color: #a0aec0; margin-bottom: 20px;">Start creating events to manage your campus activities!</p>
                    <a href="create_event.php" class="btn btn-primary">➕ Create Your First Event</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function changeStatus(eventId, newStatus) {
            if(confirm('Are you sure you want to change the status to "' + newStatus + '"?')) {
                window.location.href = '?status_change=' + eventId + '&new_status=' + newStatus;
            }
        }
    </script>
</body>
</html>