<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error = "";
$success = "";
$verification_result = null;

// Handle MANUAL attendance marking (AJAX)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['manual_attend'])) {
    header('Content-Type: application/json');
    $event_id  = intval($_POST['event_id']);
    $user_id   = intval($_POST['user_id']);

    require_once '../config/co_organizer_helper.php';
    if(!canViewEvent($conn, $event_id, $organizer_id)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied.']);
        exit();
    }

    // Already verified?
    $chk = mysqli_prepare($conn, "SELECT id FROM ticket_verifications WHERE event_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($chk, "ii", $event_id, $user_id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if(mysqli_stmt_num_rows($chk) > 0) {
        echo json_encode(['success' => false, 'message' => 'Already marked as attended.']);
        exit();
    }
    mysqli_stmt_close($chk);

    $verifier_name = mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? 'Organizer');
    $ticket_code   = 'MANUAL-' . strtoupper(uniqid());

    $ins = mysqli_prepare($conn,
        "INSERT INTO ticket_verifications (event_id, user_id, ticket_code, verified_by, verified_by_name, verified_at)
         VALUES (?, ?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($ins, "iisis", $event_id, $user_id, $ticket_code, $organizer_id, $verifier_name);

    if(mysqli_stmt_execute($ins)) {
        // Update registration status to 'attended'
        $upd = mysqli_prepare($conn,
            "UPDATE registrations SET status = 'attended' WHERE event_id = ? AND user_id = ?");
        mysqli_stmt_bind_param($upd, "ii", $event_id, $user_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        echo json_encode(['success' => true, 'message' => 'Attendance marked successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($ins);
    exit();
}

// Handle QR/manual ticket code verification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_ticket'])) {
    $ticket_code = trim($_POST['ticket_code']);
    
    if(empty($ticket_code)) {
        $error = "Please enter or scan a ticket code!";
    } else {
        $event_id = null;
        $user_id = null;
        $ticket_id = null;
        
        if(strpos($ticket_code, '|') !== false) {
            $parts = explode('|', $ticket_code);
            foreach($parts as $part) {
                if(strpos($part, ':') !== false) {
                    list($key, $value) = explode(':', $part, 2);
                    if($key == 'EVENT')  $event_id  = intval($value);
                    if($key == 'USER')   $user_id   = intval($value);
                    if($key == 'TICKET') $ticket_id = $value;
                }
            }
        }
        
        error_log("Parsed ticket - Event: $event_id, User: $user_id, Ticket: $ticket_id");
        
        if(!$event_id || !$user_id || !$ticket_id) {
            $error = "❌ Invalid ticket format! Code: " . htmlspecialchars($ticket_code);
        } else {
            require_once '../config/co_organizer_helper.php';
            
            if(!canViewEvent($conn, $event_id, $organizer_id)) {
                $error = "❌ This ticket is not for your event!";
            } else {
                $event_stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
                mysqli_stmt_bind_param($event_stmt, "i", $event_id);
                mysqli_stmt_execute($event_stmt);
                $event_result = mysqli_stmt_get_result($event_stmt);
                
                if(mysqli_num_rows($event_result) == 0) {
                    $error = "❌ Event not found!";
                } else {
                    $event = mysqli_fetch_assoc($event_result);
                    
                    $reg_check = mysqli_prepare($conn, 
                        "SELECT r.*, u.full_name, u.email, u.department 
                         FROM registrations r 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.event_id = ? AND r.user_id = ? AND r.status IN ('registered','attended')");
                    mysqli_stmt_bind_param($reg_check, "ii", $event_id, $user_id);
                    mysqli_stmt_execute($reg_check);
                    $reg_result = mysqli_stmt_get_result($reg_check);
                    
                    if(mysqli_num_rows($reg_result) == 0) {
                        $error = "❌ No valid registration found for this ticket!";
                    } else {
                        $registration = mysqli_fetch_assoc($reg_result);
                        
                        $verify_check = mysqli_prepare($conn,
                            "SELECT * FROM ticket_verifications WHERE event_id = ? AND user_id = ?");
                        mysqli_stmt_bind_param($verify_check, "ii", $event_id, $user_id);
                        mysqli_stmt_execute($verify_check);
                        $verify_result = mysqli_stmt_get_result($verify_check);
                        
                        if(mysqli_num_rows($verify_result) > 0) {
                            $existing = mysqli_fetch_assoc($verify_result);
                            $error = "⚠️ Ticket already scanned on " . 
                                    date('M d, Y h:i A', strtotime($existing['verified_at'])) . 
                                    " by " . htmlspecialchars($existing['verified_by_name']);
                            
                            $verification_result = [
                                'status'       => 'duplicate',
                                'event'        => $event,
                                'registration' => $registration,
                                'verified_at'  => $existing['verified_at']
                            ];
                        } else {
                            $verifier_name    = mysqli_real_escape_string($conn, $_SESSION['full_name'] ?? 'Organizer');
                            $ticket_id_escaped = mysqli_real_escape_string($conn, $ticket_id);
                            
                            $insert_verify = mysqli_prepare($conn,
                                "INSERT INTO ticket_verifications 
                                 (event_id, user_id, ticket_code, verified_by, verified_by_name, verified_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
                            mysqli_stmt_bind_param($insert_verify, "iisis", 
                                $event_id, $user_id, $ticket_id_escaped, $organizer_id, $verifier_name);
                            
                            if(mysqli_stmt_execute($insert_verify)) {
                                // Update registration status to attended
                                $upd = mysqli_prepare($conn,
                                    "UPDATE registrations SET status = 'attended' WHERE event_id = ? AND user_id = ?");
                                mysqli_stmt_bind_param($upd, "ii", $event_id, $user_id);
                                mysqli_stmt_execute($upd);
                                mysqli_stmt_close($upd);

                                $success = "✅ Ticket verified successfully!";
                                $verification_result = [
                                    'status'       => 'success',
                                    'event'        => $event,
                                    'registration' => $registration,
                                    'verified_at'  => date('Y-m-d H:i:s')
                                ];
                            } else {
                                $error = "Database error: " . mysqli_error($conn);
                                error_log("Verification insert failed: " . mysqli_error($conn));
                            }
                            mysqli_stmt_close($insert_verify);
                        }
                        mysqli_stmt_close($verify_check);
                    }
                    mysqli_stmt_close($reg_check);
                }
                mysqli_stmt_close($event_stmt);
            }
        }
    }
}

// Fetch organizer's events for stats + manual section
$events_query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status IN ('registered','attended')) as total_registered,
                 (SELECT COUNT(*) FROM ticket_verifications WHERE event_id = e.id) as total_verified
                 FROM events e 
                 WHERE e.organizer_id = $organizer_id AND e.status IN ('upcoming', 'ongoing')
                 ORDER BY e.event_date ASC";
$events_result = mysqli_query($conn, $events_query);

// Fetch all events (including completed) for the manual attendance dropdown
$all_events_query = "SELECT id, title FROM events 
                     WHERE organizer_id = $organizer_id AND status IN ('upcoming','ongoing','completed')
                     ORDER BY event_date DESC";
$all_events_result = mysqli_query($conn, $all_events_query);

// Manual attendance: fetch participants if event selected
$manual_event_id = isset($_GET['manual_event']) && is_numeric($_GET['manual_event']) ? intval($_GET['manual_event']) : null;
$manual_participants = [];
if($manual_event_id) {
    $mp_query = "SELECT r.user_id, u.full_name, u.email, u.department,
                 (SELECT id FROM ticket_verifications WHERE event_id = r.event_id AND user_id = r.user_id LIMIT 1) as verification_id
                 FROM registrations r
                 JOIN users u ON r.user_id = u.id
                 WHERE r.event_id = $manual_event_id AND r.status IN ('registered','attended')
                 ORDER BY u.full_name ASC";
    $mp_result = mysqli_query($conn, $mp_query);
    while($row = mysqli_fetch_assoc($mp_result)) {
        $manual_participants[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Tickets - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .scanner-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .camera-view {
            width: 100%;
            max-width: 500px;
            height: 400px;
            background: #f7fafc;
            border-radius: 10px;
            margin: 20px auto;
            position: relative;
            overflow: hidden;
        }
        #reader {
            width: 100%;
            height: 100%;
        }
        .placeholder-view {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            color: #a0aec0;
        }
        .verification-result {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .success-animation {
            text-align: center;
            font-size: 80px;
            margin: 20px 0;
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to   { transform: scale(1); opacity: 1; }
        }
        .event-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }
        .stat-number { font-size: 36px; font-weight: 700; margin-bottom: 5px; }
        .stat-label  { font-size: 14px; opacity: 0.9; }

        /* Manual attendance section */
        .manual-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-top: 30px;
        }
        .manual-section h2 {
            margin-bottom: 5px;
            color: #2d3748;
        }
        .manual-section .section-desc {
            color: #718096;
            font-size: 14px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
        }
        .participant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-radius: 8px;
            background: #f7fafc;
            margin-bottom: 10px;
            gap: 15px;
        }
        .participant-row:hover { background: #edf2f7; }
        .participant-info { flex: 1; min-width: 0; }
        .participant-name  { font-weight: 600; color: #2d3748; }
        .participant-meta  { font-size: 12px; color: #718096; margin-top: 2px; }
        .btn-mark-attend {
            padding: 8px 18px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .btn-mark-attend.pending {
            background: #667eea;
            color: white;
        }
        .btn-mark-attend.pending:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        .btn-mark-attend.done {
            background: #c6f6d5;
            color: #276749;
            cursor: not-allowed;
        }
        .attended-badge {
            display: inline-block;
            padding: 3px 10px;
            background: #c6f6d5;
            color: #276749;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🎫 Ticket Verification</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="attendance.php" class="btn btn-success">📋 View Attendance</a>
                    <a href="verification_report.php" class="btn btn-secondary">📊 Report</a>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Event Stats -->
            <div class="event-stats">
                <?php 
                mysqli_data_seek($events_result, 0);
                $total_reg = 0; $total_ver = 0;
                while($evt = mysqli_fetch_assoc($events_result)) {
                    $total_reg += $evt['total_registered'];
                    $total_ver += $evt['total_verified'];
                }
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo mysqli_num_rows($events_result); ?></div>
                    <div class="stat-label">Active Events</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);">
                    <div class="stat-number"><?php echo $total_reg; ?></div>
                    <div class="stat-label">Total Registered</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="stat-number"><?php echo $total_ver; ?></div>
                    <div class="stat-label">Tickets Verified</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                    <div class="stat-number"><?php echo max(0, $total_reg - $total_ver); ?></div>
                    <div class="stat-label">Pending Entry</div>
                </div>
            </div>
            
            <!-- QR Scanner Section -->
            <div class="scanner-container">
                <h2 style="margin-bottom: 20px;">📷 Scan Ticket QR Code</h2>
                
                <div class="camera-view">
                    <div id="reader"></div>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button id="startCamera" class="btn btn-primary" onclick="startScanner()">
                        📷 Start Camera
                    </button>
                    <button id="stopCamera" class="btn btn-danger" onclick="stopScanner()" style="display: none;">
                        ⏹️ Stop Camera
                    </button>
                </div>
                
                <form method="POST" action="" id="verificationForm">
                    <div class="form-group">
                        <label>Or Enter Ticket Code Manually:</label>
                        <input type="text" 
                               name="ticket_code" 
                               id="ticketCodeInput"
                               placeholder="EVENT:X|USER:Y|TICKET:TKT-XXXXX"
                               style="font-family: monospace; font-size: 14px;">
                        <small style="color: #718096;">Paste the ticket code from email or QR scan</small>
                    </div>
                    <button type="submit" name="verify_ticket" class="btn btn-success" style="width: 100%;">
                        ✅ Verify Ticket
                    </button>
                </form>
            </div>
            
            <!-- Verification Result -->
            <?php if($verification_result): ?>
                <div class="verification-result">
                    <?php if($verification_result['status'] == 'success'): ?>
                        <div class="success-animation">✅</div>
                        <h2 style="text-align: center; color: #48bb78; margin-bottom: 20px;">Entry Approved!</h2>
                    <?php else: ?>
                        <div class="success-animation" style="color: #f59e0b;">⚠️</div>
                        <h2 style="text-align: center; color: #f59e0b; margin-bottom: 20px;">Already Verified</h2>
                    <?php endif; ?>
                    
                    <div style="background: #f7fafc; padding: 20px; border-radius: 8px; margin-top: 20px;">
                        <h3 style="margin-top: 0;">👤 Attendee Details:</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px; font-weight: 600;">Name:</td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($verification_result['registration']['full_name']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px; font-weight: 600;">Email:</td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($verification_result['registration']['email']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px; font-weight: 600;">Department:</td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($verification_result['registration']['department']); ?></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 10px; font-weight: 600;">Event:</td>
                                <td style="padding: 10px;"><?php echo htmlspecialchars($verification_result['event']['title']); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; font-weight: 600;">Verified At:</td>
                                <td style="padding: 10px;"><?php echo date('M d, Y h:i A', strtotime($verification_result['verified_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <button onclick="location.reload()" class="btn btn-primary">Scan Next Ticket</button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ───────── MANUAL ATTENDANCE SECTION ───────── -->
            <div class="manual-section">
                <h2>🖐️ Manual Attendance Marking</h2>
                <p class="section-desc">
                    Use this if the QR scan isn't working. Select an event to see registered participants and mark their attendance manually.
                </p>

                <!-- Event selector -->
                <form method="GET" action="" id="manualEventForm">
                    <div style="display: flex; gap: 12px; align-items: flex-end; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1; margin-bottom: 0;">
                            <label>Select Event</label>
                            <select name="manual_event" onchange="this.form.submit()" style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px; width: 100%;">
                                <option value="">— Choose an event —</option>
                                <?php while($ae = mysqli_fetch_assoc($all_events_result)): ?>
                                    <option value="<?php echo $ae['id']; ?>"
                                            <?php echo ($manual_event_id == $ae['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ae['title']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <?php if($manual_event_id): ?>
                            <a href="verify_ticket.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if($manual_event_id): ?>
                    <?php if(count($manual_participants) > 0): ?>
                        <div style="margin-bottom: 12px; padding: 12px 16px; background: #ebf8ff; border-left: 4px solid #3182ce; border-radius: 5px; font-size: 14px;">
                            <strong><?php echo count($manual_participants); ?> registered participant(s)</strong> for this event.
                            Blue button = not yet marked. Green = already attended.
                        </div>

                        <div id="participantList">
                            <?php foreach($manual_participants as $p): ?>
                                <?php $attended = !empty($p['verification_id']); ?>
                                <div class="participant-row" id="row-<?php echo $p['user_id']; ?>">
                                    <div class="participant-info">
                                        <div class="participant-name">
                                            <?php echo htmlspecialchars($p['full_name']); ?>
                                            <?php if($attended): ?>
                                                <span class="attended-badge">✓ Attended</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="participant-meta">
                                            <?php echo htmlspecialchars($p['email']); ?>
                                            <?php if($p['department']): ?> · <?php echo htmlspecialchars($p['department']); ?><?php endif; ?>
                                        </div>
                                    </div>
                                    <button 
                                        class="btn-mark-attend <?php echo $attended ? 'done' : 'pending'; ?>"
                                        id="btn-<?php echo $p['user_id']; ?>"
                                        <?php echo $attended ? 'disabled' : ''; ?>
                                        <?php if(!$attended): ?>
                                            onclick="markAttendance(<?php echo $manual_event_id; ?>, <?php echo $p['user_id']; ?>, '<?php echo htmlspecialchars(addslashes($p['full_name'])); ?>')"
                                        <?php endif; ?>>
                                        <?php echo $attended ? '✓ Attended' : '✔ Mark Attended'; ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; background: #f7fafc; border-radius: 8px; color: #a0aec0;">
                            <div style="font-size: 48px; margin-bottom: 10px;">📭</div>
                            <p>No registered participants found for this event.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; background: #f7fafc; border-radius: 8px; color: #a0aec0;">
                        <div style="font-size: 48px; margin-bottom: 10px;">👆</div>
                        <p>Select an event above to see the participant list.</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
    
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <script>
        let html5QrCode = null;
        let isScanning = false;
        
        function startScanner() {
            const startBtn = document.getElementById('startCamera');
            const stopBtn  = document.getElementById('stopCamera');
            startBtn.style.display = 'none';
            stopBtn.style.display  = 'inline-block';
            
            html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    stopScanner();
                    document.getElementById('ticketCodeInput').value = decodedText;
                    document.getElementById('ticketCodeInput').focus();
                    alert('✅ QR Code scanned successfully! Click "Verify Ticket" to proceed.');
                },
                () => {}
            ).catch(err => {
                console.error('Unable to start camera:', err);
                alert('Unable to access camera. Please check permissions or enter code manually.');
                stopScanner();
            });
            isScanning = true;
        }
        
        function stopScanner() {
            if(html5QrCode && isScanning) {
                html5QrCode.stop().then(() => {
                    document.getElementById('startCamera').style.display = 'inline-block';
                    document.getElementById('stopCamera').style.display  = 'none';
                    isScanning = false;
                    html5QrCode = null;
                }).catch(err => console.error('Error stopping scanner:', err));
            }
        }
        
        document.getElementById('ticketCodeInput').addEventListener('focus', function() {
            if(isScanning) stopScanner();
        });
        
        window.addEventListener('beforeunload', function() {
            if(isScanning) stopScanner();
        });

        // Manual attendance via AJAX
        function markAttendance(eventId, userId, name) {
            if(!confirm('Mark ' + name + ' as attended?')) return;

            const btn = document.getElementById('btn-' + userId);
            btn.disabled = true;
            btn.textContent = 'Saving...';
            btn.style.background = '#e2e8f0';
            btn.style.color = '#4a5568';

            const formData = new FormData();
            formData.append('manual_attend', '1');
            formData.append('event_id', eventId);
            formData.append('user_id', userId);

            fetch('verify_ticket.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        btn.textContent = '✓ Attended';
                        btn.className = 'btn-mark-attend done';

                        // Add badge next to name
                        const row = document.getElementById('row-' + userId);
                        const nameEl = row.querySelector('.participant-name');
                        const badge = document.createElement('span');
                        badge.className = 'attended-badge';
                        badge.textContent = '✓ Attended';
                        nameEl.appendChild(badge);
                    } else {
                        btn.disabled = false;
                        btn.textContent = '✔ Mark Attended';
                        btn.className = 'btn-mark-attend pending';
                        alert('⚠️ ' + data.message);
                    }
                })
                .catch(() => {
                    btn.disabled = false;
                    btn.textContent = '✔ Mark Attended';
                    btn.className = 'btn-mark-attend pending';
                    alert('Network error. Please try again.');
                });
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>