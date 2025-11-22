<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get event ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: view_events.php");
    exit();
}

$event_id = intval($_GET['id']);

// Fetch event details with organizer info
$event_query = "
    SELECT e.*, u.full_name as organizer_name, u.email as organizer_email, u.department as organizer_dept
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    WHERE e.id = $event_id
";
$event_result = mysqli_query($conn, $event_query);

if(!$event_result) {
    die("Event query failed: " . mysqli_error($conn));
}

if(mysqli_num_rows($event_result) == 0) {
    header("Location: view_events.php");
    exit();
}

$event = mysqli_fetch_assoc($event_result);

// Get registration statistics
$stats_query = "
    SELECT COUNT(*) as total_registrations
    FROM registrations
    WHERE event_id = $event_id
";
$stats_result = mysqli_query($conn, $stats_query);

if(!$stats_result) {
    die("Stats query failed: " . mysqli_error($conn));
}

$stats = mysqli_fetch_assoc($stats_result);

// Get all registrations with user details - FIXED: using registered_at instead of registration_date
$registrations_query = "
    SELECT r.*, u.full_name, u.email, u.department, u.year, u.phone
    FROM registrations r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.event_id = $event_id
    ORDER BY r.registered_at DESC
";
$registrations_result = mysqli_query($conn, $registrations_query);

if(!$registrations_result) {
    die("Registrations query failed: " . mysqli_error($conn));
}

// Calculate capacity percentage - FIXED: using max_participants instead of capacity
$capacity_percentage = 0;
if($event['max_participants'] > 0) {
    $capacity_percentage = ($stats['total_registrations'] / $event['max_participants']) * 100;
}

// Determine event status based on event_date
$current_datetime = date('Y-m-d H:i:s');
$event_datetime = $event['event_date'];
$event_status = "Upcoming";
$status_color = "#3b82f6"; // Blue

// Since we only have event_date (no separate start/end dates), use the stored status
if($event['status'] == 'completed') {
    $event_status = "Completed";
    $status_color = "#6b7280";
} elseif($event['status'] == 'ongoing') {
    $event_status = "Ongoing";
    $status_color = "#10b981";
} elseif($event['status'] == 'cancelled') {
    $event_status = "Cancelled";
    $status_color = "#ef4444";
} else {
    $event_status = "Upcoming";
    $status_color = "#3b82f6";
}

// Check if event is full
$is_full = $stats['total_registrations'] >= $event['max_participants'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Details - <?php echo htmlspecialchars($event['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .event-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .event-image-large {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .capacity-bar {
            background: #e2e8f0;
            height: 30px;
            border-radius: 15px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .capacity-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            transition: width 0.3s ease;
        }
        
        .registrations-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .registrations-table th {
            background: #f7fafc;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .registrations-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .registrations-table tr:hover {
            background: #f7fafc;
        }
        
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .export-btn {
            background: #10b981;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .export-btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📋 Event Details</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="view_events.php" class="btn btn-secondary">← Back to Events</a>
                    <a href="download_registrations.php?event_id=<?php echo $event_id; ?>" class="export-btn">
                        📥 Export Registrations
                    </a>
                </div>
            </div>
            
            <!-- Event Header -->
            <div class="event-header">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="margin: 0 0 10px 0; font-size: 2rem;"><?php echo htmlspecialchars($event['title']); ?></h2>
                        <div style="display: flex; gap: 20px; align-items: center;">
                            <span class="status-badge" style="background: <?php echo $status_color; ?>; color: white;">
                                <?php echo $event_status; ?>
                            </span>
                            <?php if($is_full): ?>
                                <span class="status-badge" style="background: #ef4444; color: white;">
                                    🔴 FULL
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.9rem; opacity: 0.9;">Organized by</div>
                        <div style="font-size: 1.2rem; font-weight: 600;"><?php echo htmlspecialchars($event['organizer_name']); ?></div>
                        <div style="font-size: 0.85rem; opacity: 0.8;"><?php echo htmlspecialchars($event['organizer_dept'] ?: 'N/A'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Event Image -->
            <?php if(!empty($event['image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" 
                     alt="Event Image" 
                     class="event-image-large"
                     onerror="this.style.display='none'">
            <?php endif; ?>
            
            <!-- Event Information Grid -->
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">📅 Event Date & Time</div>
                    <div class="info-value">
                        <?php echo date('M d, Y', strtotime($event['event_date'])); ?><br>
                        <span style="font-size: 0.9rem; color: #718096;"><?php echo date('h:i A', strtotime($event['event_date'])); ?></span>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">📍 Venue</div>
                    <div class="info-value"><?php echo htmlspecialchars($event['venue'] ?: 'TBA'); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">🏷️ Category</div>
                    <div class="info-value"><?php echo htmlspecialchars($event['category'] ?: 'General'); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">⏰ Registration Deadline</div>
                    <div class="info-value">
                        <?php 
                        if($event['registration_deadline']) {
                            echo date('M d, Y h:i A', strtotime($event['registration_deadline']));
                        } else {
                            echo 'Not set';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Description -->
            <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top: 0; color: #2d3748;">📝 Description</h3>
                <p style="line-height: 1.8; color: #4a5568;"><?php echo nl2br(htmlspecialchars($event['description'] ?: 'No description provided.')); ?></p>
            </div>
            
            <!-- Registration Statistics -->
            <div style="background: white; padding: 25px; border-radius: 10px; margin-bottom: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top: 0; color: #2d3748;">📊 Registration Statistics</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: #667eea;">
                            <?php echo $stats['total_registrations']; ?>
                        </div>
                        <div style="color: #718096; font-size: 0.9rem;">Total Registrations</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: #667eea;">
                            <?php echo $event['max_participants']; ?>
                        </div>
                        <div style="color: #718096; font-size: 0.9rem;">Total Capacity</div>
                    </div>
                    
                    <div style="text-align: center; padding: 15px; background: #f7fafc; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: <?php echo $is_full ? '#ef4444' : '#10b981'; ?>;">
                            <?php echo $event['max_participants'] - $stats['total_registrations']; ?>
                        </div>
                        <div style="color: #718096; font-size: 0.9rem;">Seats Available</div>
                    </div>
                </div>
                
                <div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #718096; font-size: 0.9rem;">Capacity Filled</span>
                        <span style="color: #2d3748; font-weight: 600;"><?php echo number_format($capacity_percentage, 1); ?>%</span>
                    </div>
                    <div class="capacity-bar">
                        <div class="capacity-fill" style="width: <?php echo min($capacity_percentage, 100); ?>%;">
                            <?php echo $stats['total_registrations']; ?> / <?php echo $event['max_participants']; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Registered Students -->
            <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-top: 0; color: #2d3748;">👥 Registered Students (<?php echo $stats['total_registrations']; ?>)</h3>
                
                <?php if(mysqli_num_rows($registrations_result) > 0): ?>
                    <table class="registrations-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Phone</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($reg = mysqli_fetch_assoc($registrations_result)): ?>
                                <tr>
                                    <td><?php echo $reg['id']; ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($reg['full_name'] ?: 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['email'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['department'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['year'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($reg['phone'] ?: 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($reg['registered_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #718096;">
                        <div style="font-size: 3rem; margin-bottom: 15px;">🔭</div>
                        <p>No registrations yet for this event.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>