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

// Handle ticket verification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_ticket'])) {
    $ticket_code = trim($_POST['ticket_code']);
    
    if(empty($ticket_code)) {
        $error = "Please enter or scan a ticket code!";
    } else {
        // More flexible parsing - handle different formats
        $event_id = null;
        $user_id = null;
        $ticket_id = null;
        
        // Try to parse the ticket code
        if(strpos($ticket_code, '|') !== false) {
            // Format: EVENT:X|USER:Y|TICKET:TKT-XXXXX
            $parts = explode('|', $ticket_code);
            foreach($parts as $part) {
                if(strpos($part, ':') !== false) {
                    list($key, $value) = explode(':', $part, 2);
                    if($key == 'EVENT') $event_id = intval($value);
                    if($key == 'USER') $user_id = intval($value);
                    if($key == 'TICKET') $ticket_id = $value;
                }
            }
        }
        
        // Debug info (remove after testing)
        error_log("Parsed ticket - Event: $event_id, User: $user_id, Ticket: $ticket_id");
        
        if(!$event_id || !$user_id || !$ticket_id) {
            $error = "❌ Invalid ticket format! Code: " . htmlspecialchars($ticket_code);
        } else {
            // Verify event belongs to this organizer OR co-organizer
            require_once '../config/co_organizer_helper.php';
            
            if(!canViewEvent($conn, $event_id, $organizer_id)) {
                $error = "❌ This ticket is not for your event!";
            } else {
                // Rest of verification logic...
                $event_stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ?");
                mysqli_stmt_bind_param($event_stmt, "i", $event_id);
                mysqli_stmt_execute($event_stmt);
                $event_result = mysqli_stmt_get_result($event_stmt);
                
                if(mysqli_num_rows($event_result) == 0) {
                    $error = "❌ Event not found!";
                } else {
                    $event = mysqli_fetch_assoc($event_result);
                    
                    // Check registration
                    $reg_check = mysqli_prepare($conn, 
                        "SELECT r.*, u.full_name, u.email, u.department 
                         FROM registrations r 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.event_id = ? AND r.user_id = ? AND r.status = 'registered'");
                    mysqli_stmt_bind_param($reg_check, "ii", $event_id, $user_id);
                    mysqli_stmt_execute($reg_check);
                    $reg_result = mysqli_stmt_get_result($reg_check);
                    
                    if(mysqli_num_rows($reg_result) == 0) {
                        $error = "❌ No valid registration found for this ticket!";
                    } else {
                        $registration = mysqli_fetch_assoc($reg_result);
                        
                        // Check if already verified
                        $verify_check = mysqli_prepare($conn,
                            "SELECT * FROM ticket_verifications 
                             WHERE event_id = ? AND user_id = ?");
                        mysqli_stmt_bind_param($verify_check, "ii", $event_id, $user_id);
                        mysqli_stmt_execute($verify_check);
                        $verify_result = mysqli_stmt_get_result($verify_check);
                        
                        if(mysqli_num_rows($verify_result) > 0) {
                            $existing = mysqli_fetch_assoc($verify_result);
                            $error = "⚠️ Ticket already scanned on " . 
                                    date('M d, Y h:i A', strtotime($existing['verified_at'])) . 
                                    " by " . htmlspecialchars($existing['verified_by_name']);
                            
                            $verification_result = [
                                'status' => 'duplicate',
                                'event' => $event,
                                'registration' => $registration,
                                'verified_at' => $existing['verified_at']
                            ];
                        } else {
                            // Mark as verified
                            $verifier_name = mysqli_real_escape_string($conn, $_SESSION['full_name']);
                            $ticket_id_escaped = mysqli_real_escape_string($conn, $ticket_id);
                            
                            $insert_verify = mysqli_prepare($conn,
                                "INSERT INTO ticket_verifications 
                                 (event_id, user_id, ticket_code, verified_by, verified_by_name, verified_at) 
                                 VALUES (?, ?, ?, ?, ?, NOW())");
                            mysqli_stmt_bind_param($insert_verify, "iisis", 
                                $event_id, $user_id, $ticket_id_escaped, $organizer_id, $verifier_name);
                            
                            if(mysqli_stmt_execute($insert_verify)) {
                                $success = "✅ Ticket verified successfully!";
                                
                                $verification_result = [
                                    'status' => 'success',
                                    'event' => $event,
                                    'registration' => $registration,
                                    'verified_at' => date('Y-m-d H:i:s')
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
// Fetch organizer's events for quick selection
$events_query = "SELECT e.*, 
                 (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as total_registered,
                 (SELECT COUNT(*) FROM ticket_verifications WHERE event_id = e.id) as total_verified
                 FROM events e 
                 WHERE e.organizer_id = $organizer_id AND e.status IN ('upcoming', 'ongoing')
                 ORDER BY e.event_date ASC";
$events_result = mysqli_query($conn, $events_query);
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
            to { transform: scale(1); opacity: 1; }
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
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
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
                    <a href="verification_report.php" class="btn btn-success">📊 View Report</a>
                    <a href="manage_events.php" class="btn btn-secondary">← Back to Events</a>
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
                $total_reg = 0;
                $total_ver = 0;
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
            
            <!-- Scanner Section -->
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
                        <button onclick="location.reload()" class="btn btn-primary">
                            Scan Next Ticket
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Include QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <script>
        let html5QrCode = null;
        let isScanning = false;
        
        function startScanner() {
            const startBtn = document.getElementById('startCamera');
            const stopBtn = document.getElementById('stopCamera');
            
            startBtn.style.display = 'none';
            stopBtn.style.display = 'inline-block';
            
            // Initialize scanner
            html5QrCode = new Html5Qrcode("reader");
            
            html5QrCode.start(
                { facingMode: "environment" }, // Use back camera
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 }
                },
                (decodedText, decodedResult) => {
                    // Successfully scanned
                    console.log('QR Code scanned:', decodedText);
                    
                    // Stop scanner
                    stopScanner();
                    
                    // Fill input field
                    document.getElementById('ticketCodeInput').value = decodedText;
                    
                    // Focus on the input to show the user
                    document.getElementById('ticketCodeInput').focus();
                    
                    // Visual feedback
                    alert('✅ QR Code scanned successfully! Click "Verify Ticket" to proceed.');
                },
                (errorMessage) => {
                    // Scanning error (can be ignored - happens frequently during scanning)
                    // console.log('Scan error:', errorMessage);
                }
            ).catch(err => {
                console.error('Unable to start camera:', err);
                alert('Unable to access camera. Please check permissions or enter code manually.');
                stopScanner();
            });
            
            isScanning = true;
        }
        
        function stopScanner() {
            if (html5QrCode && isScanning) {
                html5QrCode.stop().then(() => {
                    const startBtn = document.getElementById('startCamera');
                    const stopBtn = document.getElementById('stopCamera');
                    
                    startBtn.style.display = 'inline-block';
                    stopBtn.style.display = 'none';
                    
                    isScanning = false;
                    html5QrCode = null;
                }).catch(err => {
                    console.error('Error stopping scanner:', err);
                });
            }
        }
        
        // Stop camera when manually entering code
        document.getElementById('ticketCodeInput').addEventListener('focus', function() {
            if (isScanning) {
                stopScanner();
            }
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (isScanning) {
                stopScanner();
            }
        });
    </script>
</body>
</html>