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

// Handle accept/decline
if(isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $invitation_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if($action == 'accept' || $action == 'decline') {
        $new_status = $action == 'accept' ? 'accepted' : 'declined';
        $update_stmt = mysqli_prepare($conn, 
            "UPDATE event_co_organizers 
             SET status = ?, responded_at = NOW() 
             WHERE id = ? AND organizer_id = ?");
        mysqli_stmt_bind_param($update_stmt, "sii", $new_status, $invitation_id, $organizer_id);
        
        if(mysqli_stmt_execute($update_stmt)) {
            $success = $action == 'accept' ? "✅ Invitation accepted!" : "❌ Invitation declined.";
        } else {
            $error = "Failed to update invitation!";
        }
        mysqli_stmt_close($update_stmt);
    }
}

// Fetch pending invitations
$pending_query = "SELECT eco.*, e.title, e.event_date, e.venue, e.event_type, 
                  u.full_name as invited_by_name, u.email as invited_by_email
                  FROM event_co_organizers eco
                  JOIN events e ON eco.event_id = e.id
                  JOIN users u ON eco.invited_by = u.id
                  WHERE eco.organizer_id = $organizer_id AND eco.status = 'pending'
                  ORDER BY eco.invited_at DESC";
$pending_result = mysqli_query($conn, $pending_query);

// Fetch accepted invitations (my co-organized events)
$accepted_query = "SELECT eco.*, e.title, e.event_date, e.venue, e.event_type, e.organizer_id,
                   u.full_name as main_organizer_name,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                   FROM event_co_organizers eco
                   JOIN events e ON eco.event_id = e.id
                   JOIN users u ON e.organizer_id = u.id
                   WHERE eco.organizer_id = $organizer_id AND eco.status = 'accepted'
                   ORDER BY e.event_date ASC";
$accepted_result = mysqli_query($conn, $accepted_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Co-Organizer Invitations - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .invitation-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }
        .event-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .badge-online { background: #4299e1; color: white; }
        .badge-offline { background: #48bb78; color: white; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🤝 Co-Organizer Invitations</h1>
                <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Pending Invitations -->
            <h2 style="margin-bottom: 20px;">📬 Pending Invitations (<?php echo mysqli_num_rows($pending_result); ?>)</h2>
            
            <?php if(mysqli_num_rows($pending_result) > 0): ?>
                <?php while($inv = mysqli_fetch_assoc($pending_result)): ?>
                    <div class="invitation-card">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h3 style="margin: 0 0 10px 0; color: #2d3748; font-size: 22px;">
                                    <?php echo htmlspecialchars($inv['title']); ?>
                                    <span class="event-badge badge-<?php echo $inv['event_type']; ?>">
                                        <?php echo ucfirst($inv['event_type']); ?>
                                    </span>
                                </h3>
                                
                                <div style="color: #4a5568; margin-bottom: 15px; line-height: 1.8;">
                                    <div>📅 <strong><?php echo date('l, F d, Y @ h:i A', strtotime($inv['event_date'])); ?></strong></div>
                                    <div>📍 <?php echo htmlspecialchars($inv['venue']); ?></div>
                                    <div>👤 Invited by: <strong><?php echo htmlspecialchars($inv['invited_by_name']); ?></strong></div>
                                    <div>⏰ <?php echo date('M d, Y', strtotime($inv['invited_at'])); ?></div>
                                </div>
                                
                                <div style="background: #fff3cd; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                                    <strong style="color: #856404;">🔑 Your Access Level:</strong>
                                    <span style="color: #856404; font-weight: 600; text-transform: uppercase;">
                                        <?php echo $inv['permissions']; ?> ACCESS
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 10px; margin-left: 20px;">
                                <a href="?action=accept&id=<?php echo $inv['id']; ?>" 
                                   class="btn btn-success"
                                   onclick="return confirm('Accept this invitation?')">
                                    ✅ Accept
                                </a>
                                <a href="?action=decline&id=<?php echo $inv['id']; ?>" 
                                   class="btn btn-danger"
                                   onclick="return confirm('Decline this invitation?')">
                                    ❌ Decline
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 10px; margin-bottom: 30px;">
                    <div style="font-size: 60px; margin-bottom: 15px;">📭</div>
                    <h3 style="color: #718096;">No Pending Invitations</h3>
                    <p style="color: #a0aec0;">You don't have any co-organizer invitations at the moment.</p>
                </div>
            <?php endif; ?>
            
            <!-- My Co-Organized Events -->
            <h2 style="margin: 40px 0 20px 0;">🎯 Events I'm Co-Organizing (<?php echo mysqli_num_rows($accepted_result); ?>)</h2>
            
            <?php if(mysqli_num_rows($accepted_result) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Date & Time</th>
                                <th>Main Organizer</th>
                                <th>My Access</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($event = mysqli_fetch_assoc($accepted_result)): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                        <br>
                                        <small style="color: #718096;">📍 <?php echo htmlspecialchars($event['venue']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y<\b\r>h:i A', strtotime($event['event_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($event['main_organizer_name']); ?></td>
                                    <td>
                                        <span class="role-badge role-organizer" style="font-size: 12px;">
                                            <?php echo ucfirst($event['permissions']); ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $event['registered_count']; ?></strong> students</td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="../common/view_event.php?id=<?php echo $event['event_id']; ?>" 
                                               class="btn btn-sm btn-secondary">👁️ View</a>
                                            
                                            <?php if($event['permissions'] == 'edit' || $event['permissions'] == 'full'): ?>
                                                <a href="view_registrations.php?event_id=<?php echo $event['event_id']; ?>" 
                                                   class="btn btn-sm btn-primary">📋 Manage</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                    <div style="font-size: 60px; margin-bottom: 15px;">🎭</div>
                    <h3 style="color: #718096;">Not Co-Organizing Any Events</h3>
                    <p style="color: #a0aec0;">Accept invitations to start co-organizing events!</p>
                </div>
            <?php endif; ?>
        </main>
    </div>    <script src="../assets/js/script.js"></script></body>
</html>