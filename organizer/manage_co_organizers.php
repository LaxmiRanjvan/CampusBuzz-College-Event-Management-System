<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error = "";
$success = "";
$event_id = null;

// Get event ID
if(isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
} elseif(isset($_POST['event_id']) && is_numeric($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
}

// Fetch event details
$event = null;
if($event_id) {
    $event_stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? AND organizer_id = ?");
    mysqli_stmt_bind_param($event_stmt, "ii", $event_id, $organizer_id);
    mysqli_stmt_execute($event_stmt);
    $event_result = mysqli_stmt_get_result($event_stmt);
    
    if(mysqli_num_rows($event_result) > 0) {
        $event = mysqli_fetch_assoc($event_result);
    }
    mysqli_stmt_close($event_stmt);
}

// Handle adding co-organizer
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_co_organizer'])) {
    $selected_event_id = intval($_POST['event_id']);
    $co_organizer_id = intval($_POST['co_organizer_id']);
    $permissions = mysqli_real_escape_string($conn, $_POST['permissions']);
    $custom_message = trim($_POST['custom_message']);
    
    // Verify event ownership
    $verify_event = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? AND organizer_id = ?");
    mysqli_stmt_bind_param($verify_event, "ii", $selected_event_id, $organizer_id);
    mysqli_stmt_execute($verify_event);
    $verify_result = mysqli_stmt_get_result($verify_event);
    
    if(mysqli_num_rows($verify_result) == 0) {
        $error = "Event not found or you don't have permission!";
    } elseif($co_organizer_id == $organizer_id) {
        $error = "You cannot add yourself as co-organizer!";
    } else {
        $event_data = mysqli_fetch_assoc($verify_result);
        
        // Check if already a co-organizer
        $check_existing = mysqli_prepare($conn, "SELECT * FROM event_co_organizers WHERE event_id = ? AND organizer_id = ?");
        mysqli_stmt_bind_param($check_existing, "ii", $selected_event_id, $co_organizer_id);
        mysqli_stmt_execute($check_existing);
        $existing_result = mysqli_stmt_get_result($check_existing);
        
        if(mysqli_num_rows($existing_result) > 0) {
            $error = "This organizer is already added to this event!";
        } else {
            // Insert co-organizer invitation
            $insert_stmt = mysqli_prepare($conn, 
                "INSERT INTO event_co_organizers (event_id, organizer_id, invited_by, permissions, status, invited_at) 
                 VALUES (?, ?, ?, ?, 'pending', NOW())");
            mysqli_stmt_bind_param($insert_stmt, "iiis", $selected_event_id, $co_organizer_id, $organizer_id, $permissions);
            
            if(mysqli_stmt_execute($insert_stmt)) {
                // Fetch co-organizer details
                $co_org_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
                mysqli_stmt_bind_param($co_org_stmt, "i", $co_organizer_id);
                mysqli_stmt_execute($co_org_stmt);
                $co_org_result = mysqli_stmt_get_result($co_org_stmt);
                $co_organizer = mysqli_fetch_assoc($co_org_result);
                
                // Send email notification
                $subject = "You've been invited as Co-Organizer - " . $event_data['title'];
                
                $accept_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                             '://' . $_SERVER['HTTP_HOST'] . 
                             str_replace('/organizer/manage_co_organizers.php', '/organizer/co_organizer_invitations.php', $_SERVER['PHP_SELF']);
                
                $permissions_desc = [
                    'view' => 'View Only - You can view event details and registrations',
                    'edit' => 'Edit Access - You can modify event details and manage registrations',
                    'full' => 'Full Access - Complete control including adding more co-organizers'
                ];
                
                $content = "
                <p style='font-size: 16px; color: #2d3748; margin-bottom: 20px;'>
                    Hello <strong>" . htmlspecialchars($co_organizer['full_name']) . "</strong>,
                </p>
                <p style='color: #4a5568; line-height: 1.8;'>
                    <strong>" . htmlspecialchars($_SESSION['full_name']) . "</strong> has invited you to be a co-organizer for their event!
                </p>
                
                <div class='info-box'>
                    <h3 style='margin-top: 0; color: #2d3748;'>📅 Event Details:</h3>
                    <div class='info-item'>
                        <div class='info-label'>Event Name</div>
                        <div class='info-value'>" . htmlspecialchars($event_data['title']) . "</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Date & Time</div>
                        <div class='info-value'>" . date('D, M d, Y @ h:i A', strtotime($event_data['event_date'])) . "</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Venue</div>
                        <div class='info-value'>" . htmlspecialchars($event_data['venue']) . "</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Your Access Level</div>
                        <div class='info-value' style='background: #e6e9fc; padding: 8px; border-radius: 6px; color: #667eea; font-weight: 600;'>
                            " . ucfirst($permissions) . " Access
                        </div>
                    </div>
                    <div style='background: #f7fafc; padding: 12px; border-radius: 6px; margin-top: 10px;'>
                        <small style='color: #4a5568;'>" . $permissions_desc[$permissions] . "</small>
                    </div>
                </div>";
                
                if(!empty($custom_message)) {
                    $content .= "
                    <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 25px 0; border-radius: 6px;'>
                        <strong style='color: #856404;'>📝 Message from Organizer:</strong>
                        <p style='color: #856404; margin: 10px 0 0 0; line-height: 1.7;'>" . nl2br(htmlspecialchars($custom_message)) . "</p>
                    </div>";
                }
                
                $content .= "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $accept_url . "' class='button'>
                        ✅ View Invitation
                    </a>
                </div>
                
                <div style='background: #e6f7ff; border-left: 4px solid #1890ff; padding: 15px; border-radius: 6px;'>
                    <p style='margin: 0; color: #0050b3; font-size: 14px;'>
                        <strong>🤝 Benefits:</strong> Collaborate with other organizers, share responsibilities, and manage events together!
                    </p>
                </div>";
                
                $html_body = getEmailTemplate("Co-Organizer Invitation", $content);
                
                sendEmail($co_organizer['email'], $co_organizer['full_name'], $subject, $html_body);
                logEmail($organizer_id, $co_organizer['email'], $subject);
                
                $success = "✅ Co-organizer invitation sent successfully to " . htmlspecialchars($co_organizer['full_name']) . "!";
                
                mysqli_stmt_close($co_org_stmt);
            } else {
                $error = "Database error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($check_existing);
    }
    mysqli_stmt_close($verify_event);
}

// Handle removing co-organizer
if(isset($_GET['remove']) && is_numeric($_GET['remove']) && $event_id) {
    $co_org_id = intval($_GET['remove']);
    $delete_stmt = mysqli_prepare($conn, "DELETE FROM event_co_organizers WHERE id = ? AND event_id = ? AND invited_by = ?");
    mysqli_stmt_bind_param($delete_stmt, "iii", $co_org_id, $event_id, $organizer_id);
    
    if(mysqli_stmt_execute($delete_stmt)) {
        $success = "Co-organizer removed successfully!";
    } else {
        $error = "Failed to remove co-organizer!";
    }
    mysqli_stmt_close($delete_stmt);
}

// Fetch available organizers (exclude self and already added)
$available_orgs_query = "SELECT u.* FROM users u 
                         WHERE u.role = 'organizer' 
                         AND u.id != $organizer_id 
                         AND u.id NOT IN (
                             SELECT organizer_id FROM event_co_organizers WHERE event_id = " . ($event_id ?: 0) . "
                         )
                         ORDER BY u.full_name";
$available_orgs_result = mysqli_query($conn, $available_orgs_query);

// Fetch existing co-organizers for this event with accepted/pending status indicator
if($event_id) {
    $co_orgs_query = "SELECT eco.*, u.full_name, u.email, u.department 
                      FROM event_co_organizers eco 
                      JOIN users u ON eco.organizer_id = u.id 
                      WHERE eco.event_id = $event_id 
                      ORDER BY eco.status DESC, eco.invited_at DESC";
    $co_orgs_result = mysqli_query($conn, $co_orgs_query);
}

// Fetch all organizer's events
$events_query = "SELECT * FROM events WHERE organizer_id = $organizer_id ORDER BY event_date DESC";
$events_result = mysqli_query($conn, $events_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Co-Organizers - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .co-org-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .co-org-info {
            flex: 1;
        }
        .permission-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .permission-view { background: #bee3f8; color: #2c5282; }
        .permission-edit { background: #c6f6d5; color: #276749; }
        .permission-full { background: #fbd38d; color: #7c2d12; }
        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-declined { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🤝 Manage Co-Organizers</h1>
                <div style="display: flex; gap: 10px;">
                    <?php if($event_id): ?>
                        <a href="../student/view_event.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">← Back to Event</a>
                    <?php endif; ?>
                    <a href="manage_events.php" class="btn btn-secondary">My Events</a>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Info Banner -->
            <div style="background: #e6f7ff; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #1890ff;">
                <h3 style="margin-top: 0; color: #0050b3;">ℹ️ About Co-Organizers</h3>
                <p style="color: #0050b3; margin: 10px 0 0 0; line-height: 1.6;">
                    Share your event management responsibilities with other organizers. Set different permission levels to control what they can access.
                </p>
                <ul style="color: #0050b3; margin: 10px 0 0 20px;">
                    <li><strong>View:</strong> Can only see event details and registrations</li>
                    <li><strong>Edit:</strong> Can modify event and manage registrations</li>
                    <li><strong>Full:</strong> Complete access including adding more co-organizers</li>
                </ul>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 25px;">
                <h2 style="margin-top: 0;">➕ Add Co-Organizer</h2>
                
                <form method="POST" action="">
                    <?php if($event): ?>
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        
                        <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <strong>Event:</strong> <?php echo htmlspecialchars($event['title']); ?>
                            <span style="color: #718096; margin-left: 10px;">
                                <?php echo date('M d, Y', strtotime($event['event_date'])); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Select Event *</label>
                            <select name="event_id" required onchange="window.location.href='manage_co_organizers.php?event_id=' + this.value">
                                <option value="">-- Choose an event --</option>
                                <?php while($evt = mysqli_fetch_assoc($events_result)): ?>
                                    <option value="<?php echo $evt['id']; ?>">
                                        <?php echo htmlspecialchars($evt['title']); ?> 
                                        (<?php echo date('M d, Y', strtotime($evt['event_date'])); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($event): ?>
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Select Co-Organizer *</label>
                            <select name="co_organizer_id" required>
                                <option value="">-- Choose an organizer --</option>
                                <?php 
                                mysqli_data_seek($available_orgs_result, 0);
                                while($org = mysqli_fetch_assoc($available_orgs_result)): 
                                ?>
                                    <option value="<?php echo $org['id']; ?>">
                                        <?php echo htmlspecialchars($org['full_name']); ?> 
                                        (<?php echo htmlspecialchars($org['department']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Permission Level *</label>
                            <select name="permissions" required>
                                <option value="view">👁️ View Only</option>
                                <option value="edit">✏️ Edit Access</option>
                                <option value="full">⭐ Full Access</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Custom Message (Optional)</label>
                        <textarea name="custom_message" rows="3" placeholder="Add a personal message to the invitation..."></textarea>
                    </div>
                    
                    <button type="submit" name="add_co_organizer" class="btn btn-primary">
                        ✉️ Send Invitation
                    </button>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Existing Co-Organizers -->
            <?php if($event && isset($co_orgs_result) && mysqli_num_rows($co_orgs_result) > 0): ?>
                <h2 style="margin-bottom: 20px;">👥 Current Co-Organizers</h2>
                
                <?php while($co_org = mysqli_fetch_assoc($co_orgs_result)): ?>
                    <div class="co-org-card">
                        <div class="co-org-info">
                            <h3 style="margin: 0 0 8px 0; color: #2d3748;">
                                <?php echo htmlspecialchars($co_org['full_name']); ?>
                                <span class="permission-badge permission-<?php echo $co_org['permissions']; ?>">
                                    <?php echo ucfirst($co_org['permissions']); ?>
                                </span>
                                <span class="status-badge status-<?php echo $co_org['status']; ?>">
                                    <?php echo ucfirst($co_org['status']); ?>
                                </span>
                            </h3>
                            <div style="color: #718096; font-size: 14px;">
                                📧 <?php echo htmlspecialchars($co_org['email']); ?> • 
                                🏢 <?php echo htmlspecialchars($co_org['department']); ?> • 
                                📅 Added <?php echo date('M d, Y', strtotime($co_org['invited_at'])); ?>
                                <?php if($co_org['responded_at']): ?>
                                    • Responded <?php echo date('M d, Y', strtotime($co_org['responded_at'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <a href="?event_id=<?php echo $event_id; ?>&remove=<?php echo $co_org['id']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Remove this co-organizer?')">
                                🗑️ Remove
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php elseif($event): ?>
                <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                    <div style="font-size: 60px; margin-bottom: 15px;">👥</div>
                    <h3 style="color: #718096;">No Co-Organizers Yet</h3>
                    <p style="color: #a0aec0;">Add other organizers to share event management responsibilities.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
</body>
</html>