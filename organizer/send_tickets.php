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

// Get event ID from URL or POST
if(isset($_GET['event_id']) && is_numeric($_GET['event_id'])) {
    $event_id = intval($_GET['event_id']);
} elseif(isset($_POST['event_id']) && is_numeric($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
}

// Fetch event details if event_id is provided
$event = null;
$registrations = [];
if($event_id) {
    $event_stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? AND organizer_id = ?");
    mysqli_stmt_bind_param($event_stmt, "ii", $event_id, $organizer_id);
    mysqli_stmt_execute($event_stmt);
    $event_result = mysqli_stmt_get_result($event_stmt);
    
    if(mysqli_num_rows($event_result) > 0) {
        $event = mysqli_fetch_assoc($event_result);
        
        // Fetch registered users
        $reg_query = "SELECT r.*, u.full_name, u.email 
                      FROM registrations r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.event_id = ? AND r.status = 'registered'
                      ORDER BY u.full_name";
        $reg_stmt = mysqli_prepare($conn, $reg_query);
        mysqli_stmt_bind_param($reg_stmt, "i", $event_id);
        mysqli_stmt_execute($reg_stmt);
        $reg_result = mysqli_stmt_get_result($reg_stmt);
        
        while($reg = mysqli_fetch_assoc($reg_result)) {
            $registrations[] = $reg;
        }
        mysqli_stmt_close($reg_stmt);
    }
    mysqli_stmt_close($event_stmt);
}

// Handle ticket sending
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_tickets'])) {
    $selected_event_id = intval($_POST['event_id']);
    $custom_message = trim($_POST['custom_message']);
    $selected_recipients = isset($_POST['recipients']) ? $_POST['recipients'] : [];
    
    if(empty($selected_recipients)) {
        $error = "Please select at least one recipient!";
    } else {
        $event_stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? AND organizer_id = ?");
        mysqli_stmt_bind_param($event_stmt, "ii", $selected_event_id, $organizer_id);
        mysqli_stmt_execute($event_stmt);
        $event_result = mysqli_stmt_get_result($event_stmt);
        
        if(mysqli_num_rows($event_result) == 0) {
            $error = "Event not found or you don't have permission!";
        } else {
            $event_data = mysqli_fetch_assoc($event_result);
            
            // Fetch recipients
            $placeholders = implode(',', array_fill(0, count($selected_recipients), '?'));
            $recipients_query = "SELECT u.*, r.id as registration_id FROM users u 
                                JOIN registrations r ON u.id = r.user_id 
                                WHERE r.event_id = ? AND r.status = 'registered' AND u.id IN ($placeholders)";
            $rec_stmt = mysqli_prepare($conn, $recipients_query);
            
            $types = 'i' . str_repeat('i', count($selected_recipients));
            $params = array_merge([$selected_event_id], $selected_recipients);
            mysqli_stmt_bind_param($rec_stmt, $types, ...$params);
            
            mysqli_stmt_execute($rec_stmt);
            $recipients_result = mysqli_stmt_get_result($rec_stmt);
            
            $sent_count = 0;
            $failed_count = 0;
            
            while($recipient = mysqli_fetch_assoc($recipients_result)) {
                // Generate unique ticket ID
                $ticket_id = strtoupper(uniqid('TKT-'));
                $qr_data = "EVENT:" . $event_data['id'] . "|USER:" . $recipient['id'] . "|TICKET:" . $ticket_id;
                
                $subject = "Your Ticket for " . $event_data['title'];
                
                // Build ticket HTML
                $ticket_html = "
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 15px; margin: 20px 0;'>
                    <div style='background: white; padding: 30px; border-radius: 10px;'>
                        <div style='text-align: center; margin-bottom: 25px;'>
                            <h2 style='color: #667eea; margin: 0 0 10px 0;'>🎫 EVENT TICKET</h2>
                            <div style='font-size: 24px; font-weight: 700; color: #2d3748;'>" . htmlspecialchars($event_data['title']) . "</div>
                        </div>
                        
                        <div style='border-top: 2px dashed #e2e8f0; border-bottom: 2px dashed #e2e8f0; padding: 20px 0; margin: 20px 0;'>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr>
                                    <td style='padding: 10px 0; color: #718096; font-size: 13px;'>TICKET HOLDER</td>
                                    <td style='padding: 10px 0; color: #2d3748; font-weight: 600; text-align: right;'>" . htmlspecialchars($recipient['full_name']) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #718096; font-size: 13px;'>DATE & TIME</td>
                                    <td style='padding: 10px 0; color: #2d3748; font-weight: 600; text-align: right;'>" . date('D, M d, Y @ h:i A', strtotime($event_data['event_date'])) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #718096; font-size: 13px;'>VENUE</td>
                                    <td style='padding: 10px 0; color: #2d3748; font-weight: 600; text-align: right;'>" . htmlspecialchars($event_data['venue']) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #718096; font-size: 13px;'>EVENT TYPE</td>
                                    <td style='padding: 10px 0; color: #2d3748; font-weight: 600; text-align: right;'>" . ucfirst($event_data['event_type']) . "</td>
                                </tr>
                                <tr>
                                    <td style='padding: 10px 0; color: #718096; font-size: 13px;'>TICKET ID</td>
                                    <td style='padding: 10px 0; color: #667eea; font-weight: 700; text-align: right; font-family: monospace;'>" . $ticket_id . "</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div style='text-align: center; margin: 25px 0;'>
                            <img src='https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_data) . "' 
                                 alt='QR Code' 
                                 style='border: 3px solid #667eea; border-radius: 10px; padding: 10px; background: white;'>
                            <p style='margin: 10px 0 0 0; color: #718096; font-size: 12px;'>Scan this QR code at the venue</p>
                        </div>
                        
                        <div style='background: #f7fafc; padding: 15px; border-radius: 8px; margin-top: 20px;'>
                            <p style='margin: 0; color: #4a5568; font-size: 13px; line-height: 1.6;'>
                                <strong>⚠️ Important:</strong> Please present this ticket (digital or printed) at the event venue. 
                                Keep your ticket ID safe and do not share it with others.
                            </p>
                        </div>
                    </div>
                </div>";
                
                $content = "
                <p style='font-size: 16px; color: #2d3748; margin-bottom: 20px;'>
                    Hello <strong>" . htmlspecialchars($recipient['full_name']) . "</strong>,
                </p>
                <p style='color: #4a5568; line-height: 1.8;'>
                    Your event ticket is ready! Please find your digital ticket below for the event you registered for.
                </p>
                
                " . $ticket_html;
                
                if(!empty($custom_message)) {
                    $content .= "
                    <div style='background: #f7fafc; padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #667eea;'>
                        <h4 style='margin-top: 0; color: #2d3748;'>📝 Message from Organizer:</h4>
                        <p style='color: #4a5568; line-height: 1.8; margin: 10px 0 0 0;'>" . nl2br(htmlspecialchars($custom_message)) . "</p>
                    </div>";
                }
                
                $event_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                            '://' . $_SERVER['HTTP_HOST'] . 
                            str_replace('/organizer/send_tickets.php', '/student/view_event.php?id=' . $event_data['id'], $_SERVER['PHP_SELF']);
                
                $content .= "
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . $event_url . "' class='button'>
                        👁️ View Event Details
                    </a>
                </div>
                
                <div style='background: #e6fffa; border-left: 4px solid #38b2ac; padding: 20px; border-radius: 8px; margin-top: 20px;'>
                    <p style='margin: 0; color: #234e52; font-size: 14px;'>
                        <strong>💡 Tip:</strong> Save this email or take a screenshot of your ticket for easy access at the event.
                    </p>
                </div>";
                
                $html_body = getEmailTemplate("Event Ticket - " . $event_data['title'], $content, "This ticket was sent because you registered for this event.");
                
                if(sendEmail($recipient['email'], $recipient['full_name'], $subject, $html_body)) {
                    $sent_count++;
                    logEmail($organizer_id, $recipient['email'], $subject);
                } else {
                    $failed_count++;
                }
            }
            
            mysqli_stmt_close($rec_stmt);
            
            if($sent_count > 0) {
                $success = "✅ Successfully sent tickets to $sent_count recipient(s)!";
                if($failed_count > 0) {
                    $success .= " ($failed_count failed)";
                }
            } else {
                $error = "❌ Failed to send tickets. Please check your email configuration.";
            }
        }
        mysqli_stmt_close($event_stmt);
    }
}

// Fetch all events by this organizer
$events_query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                 FROM events e 
                 WHERE e.organizer_id = ? AND e.status IN ('upcoming', 'ongoing')
                 ORDER BY e.event_date DESC";
$events_stmt = mysqli_prepare($conn, $events_query);
mysqli_stmt_bind_param($events_stmt, "i", $organizer_id);
mysqli_stmt_execute($events_stmt);
$events_result = mysqli_stmt_get_result($events_stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Event Tickets - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .ticket-preview {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .ticket-inner {
            background: white;
            padding: 25px;
            border-radius: 8px;
        }
        .recipient-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            background: #f7fafc;
        }
        .recipient-item {
            padding: 10px;
            margin: 5px 0;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .recipient-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🎫 Send Event Tickets</h1>
                <div style="display: flex; gap: 10px;">
                    <?php if($event_id): ?>
                        <a href="view_registrations.php?event_id=<?php echo $event_id; ?>" class="btn btn-secondary">← Back to Registrations</a>
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
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                
                <!-- Info Banner -->
                <div style="background: #e6fffa; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #38b2ac;">
                    <h3 style="margin-top: 0; color: #234e52;">ℹ️ About Event Tickets</h3>
                    <p style="color: #234e52; margin: 10px 0 0 0; line-height: 1.6;">
                        Send digital tickets to registered participants. Each ticket includes:
                    </p>
                    <ul style="color: #234e52; margin: 10px 0 0 20px;">
                        <li>Unique ticket ID for verification</li>
                        <li>QR code for quick scanning at venue</li>
                        <li>Event details and participant information</li>
                        <li>Professional ticket design</li>
                    </ul>
                </div>
                
                <form method="POST" action="">
                    
                    <!-- Event Selection -->
                    <?php if($event): ?>
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 10px; margin-bottom: 25px; color: white;">
                            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                                <span>🎫</span> Sending tickets for:
                            </h3>
                            <div style="font-size: 24px; font-weight: 700; margin: 10px 0;">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px; opacity: 0.95;">
                                <div>
                                    <div style="font-size: 12px; opacity: 0.8;">Date</div>
                                    <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; opacity: 0.8;">Venue</div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($event['venue']); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 12px; opacity: 0.8;">Registered</div>
                                    <div style="font-weight: 600;"><?php echo count($registrations); ?> students</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Select Event *</label>
                            <select name="event_id" required onchange="window.location.href='send_tickets.php?event_id=' + this.value" style="padding: 12px; font-size: 15px;">
                                <option value="">-- Choose an event --</option>
                                <?php while($evt = mysqli_fetch_assoc($events_result)): ?>
                                    <option value="<?php echo $evt['id']; ?>">
                                        <?php echo htmlspecialchars($evt['title']); ?> 
                                        (<?php echo date('M d, Y', strtotime($evt['event_date'])); ?>) - 
                                        <?php echo $evt['registered_count']; ?> registered
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($event): ?>
                    
                    <!-- Custom Message -->
                    <div class="form-group">
                        <label>Additional Message (Optional)</label>
                        <textarea name="custom_message" 
                                  rows="5" 
                                  placeholder="Add any special instructions or message for ticket holders..."
                                  style="width: 100%; padding: 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 15px; line-height: 1.6;"></textarea>
                        <small style="color: #718096;">This message will be included with the ticket email.</small>
                    </div>
                    
                    <!-- Ticket Preview -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="margin-bottom: 15px; color: #2d3748;">📋 Ticket Preview</h3>
                        <div class="ticket-preview">
                            <div class="ticket-inner">
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <h3 style="color: #667eea; margin: 0 0 10px 0;">🎫 EVENT TICKET</h3>
                                    <div style="font-size: 20px; font-weight: 700; color: #2d3748;"><?php echo htmlspecialchars($event['title']); ?></div>
                                </div>
                                <div style="border-top: 2px dashed #e2e8f0; border-bottom: 2px dashed #e2e8f0; padding: 15px 0; margin: 15px 0;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                                        <div style="color: #718096;">TICKET HOLDER:</div>
                                        <div style="color: #2d3748; font-weight: 600; text-align: right;">[Student Name]</div>
                                        <div style="color: #718096;">DATE & TIME:</div>
                                        <div style="color: #2d3748; font-weight: 600; text-align: right;"><?php echo date('D, M d, Y @ h:i A', strtotime($event['event_date'])); ?></div>
                                        <div style="color: #718096;">TICKET ID:</div>
                                        <div style="color: #667eea; font-weight: 700; text-align: right; font-family: monospace;">TKT-XXXXXXX</div>
                                    </div>
                                </div>
                                <div style="text-align: center; margin-top: 15px;">
                                    <div style="width: 150px; height: 150px; background: #f7fafc; margin: 0 auto; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #a0aec0;">
                                        QR Code
                                    </div>
                                    <p style="margin: 8px 0 0 0; color: #718096; font-size: 12px;">Scan at venue</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recipient Selection -->
                    <?php if(count($registrations) > 0): ?>
                        <div style="margin-bottom: 25px;">
                            <label style="font-weight: 600; color: #2d3748; display: block; margin-bottom: 12px;">
                                Select Recipients (<?php echo count($registrations); ?> registered students)
                            </label>
                            
                            <label style="display: flex; align-items: center; gap: 10px; padding: 12px; background: #667eea; color: white; border-radius: 8px; cursor: pointer; margin-bottom: 10px;">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAllRecipients(this)" checked>
                                <strong>Select All Recipients</strong>
                            </label>
                            
                            <div class="recipient-list">
                                <?php foreach($registrations as $reg): ?>
                                    <div class="recipient-item">
                                        <label>
                                            <input type="checkbox" name="recipients[]" value="<?php echo $reg['user_id']; ?>" class="recipient-checkbox" checked>
                                            <div>
                                                <div style="font-weight: 600; color: #2d3748;">
                                                    <?php echo htmlspecialchars($reg['full_name']); ?>
                                                </div>
                                                <div style="font-size: 13px; color: #718096;">
                                                    <?php echo htmlspecialchars($reg['email']); ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 15px; margin-top: 30px;">
                            <button type="submit" name="send_tickets" class="btn btn-primary" style="font-size: 16px; padding: 14px 28px;">
                                🎫 Send Tickets
                            </button>
                            <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    <?php else: ?>
                        <div style="background: #fed7d7; padding: 20px; border-radius: 8px; text-align: center; color: #c53030; margin-top: 20px;">
                            <strong>⚠️ No registered participants yet!</strong>
                            <p style="margin: 10px 0 0 0;">You cannot send tickets until students register for this event.</p>
                        </div>
                        <div style="margin-top: 20px;">
                            <a href="manage_events.php" class="btn btn-secondary">← Back to My Events</a>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        function toggleAllRecipients(checkbox) {
            const checkboxes = document.querySelectorAll('.recipient-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const recipientCheckboxes = document.querySelectorAll('.recipient-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            
            if (recipientCheckboxes.length > 0 && selectAllCheckbox) {
                recipientCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(recipientCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;
                    });
                });
            }
        });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>