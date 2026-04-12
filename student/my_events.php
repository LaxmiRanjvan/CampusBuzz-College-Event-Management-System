<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

if(isset($_GET['msg'])) {
    switch($_GET['msg']) {
        case 'registered':
            $message = '<div class="alert alert-success">✓ Registration successful!</div>';
            break;
        case 'cancelled':
            $message = '<div class="alert alert-success">Registration cancelled successfully.</div>';
            break;
        case 'already_registered':
            $message = '<div class="alert alert-info">You are already registered for this event.</div>';
            break;
    }
}

// Handle cancellation
if(isset($_POST['cancel_registration'])) {
    $reg_id = intval($_POST['registration_id']);
    $verify = "SELECT * FROM registrations WHERE id = $reg_id AND user_id = $user_id";
    if(mysqli_num_rows(mysqli_query($conn, $verify)) > 0) {
        $cancel_sql = "UPDATE registrations SET status = 'cancelled' WHERE id = $reg_id";
        if(mysqli_query($conn, $cancel_sql)) {
            header("Location: my_events.php?msg=cancelled");
            exit();
        }
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'registered';

// Upcoming registered events (future date, status = registered)
$registered_sql = "SELECT e.*, r.id as reg_id, r.registration_date, r.status as reg_status,
                   u.full_name as organizer_name,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                   FROM registrations r
                   JOIN events e ON r.event_id = e.id
                   JOIN users u ON e.organizer_id = u.id
                   WHERE r.user_id = $user_id AND r.status = 'registered' AND e.event_date > NOW()
                   ORDER BY e.event_date ASC";
$registered_result = mysqli_query($conn, $registered_sql);

// Attended events — only those with a ticket_verification record
$attended_sql = "SELECT e.*, r.id as reg_id, r.registration_date, r.status as reg_status,
                 u.full_name as organizer_name,
                 tv.verified_at, tv.ticket_code,
                 tv.verified_by_name
                 FROM ticket_verifications tv
                 JOIN events e ON tv.event_id = e.id
                 JOIN registrations r ON r.event_id = e.id AND r.user_id = tv.user_id
                 JOIN users u ON e.organizer_id = u.id
                 WHERE tv.user_id = $user_id
                 ORDER BY tv.verified_at DESC";
$attended_result = mysqli_query($conn, $attended_sql);

// Past events where date passed but NOT verified (registered, date past, no verification)
$past_sql = "SELECT e.*, r.id as reg_id, r.registration_date, r.status as reg_status,
             u.full_name as organizer_name
             FROM registrations r
             JOIN events e ON r.event_id = e.id
             JOIN users u ON e.organizer_id = u.id
             WHERE r.user_id = $user_id 
             AND r.status = 'registered'
             AND e.event_date < NOW()
             AND NOT EXISTS (
                 SELECT 1 FROM ticket_verifications tv
                 WHERE tv.event_id = e.id AND tv.user_id = $user_id
             )
             ORDER BY e.event_date DESC";
$past_result = mysqli_query($conn, $past_sql);

// Saved events
$saved_sql = "SELECT e.*, u.full_name as organizer_name,
              (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
              (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND user_id = $user_id AND status='registered') as is_registered
              FROM event_saves es
              JOIN events e ON es.event_id = e.id
              JOIN users u ON e.organizer_id = u.id
              WHERE es.user_id = $user_id
              ORDER BY es.saved_at DESC";
$saved_result = mysqli_query($conn, $saved_sql);

// Cancelled
$cancelled_sql = "SELECT e.*, r.id as reg_id, r.registration_date, r.status as reg_status,
                  u.full_name as organizer_name
                  FROM registrations r
                  JOIN events e ON r.event_id = e.id
                  JOIN users u ON e.organizer_id = u.id
                  WHERE r.user_id = $user_id AND r.status = 'cancelled'
                  ORDER BY r.registration_date DESC";
$cancelled_result = mysqli_query($conn, $cancelled_sql);

$total_registered = mysqli_num_rows($registered_result);
$total_attended   = mysqli_num_rows($attended_result);
$total_saved      = mysqli_num_rows($saved_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Events - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-number { font-size: 36px; font-weight: 700; color: #667eea; margin-bottom: 5px; }
        .stat-label  { color: #718096; font-size: 14px; }

        .tabs-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .tabs-header {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            overflow-x: auto;
        }
        .tab-button {
            flex: 1;
            padding: 18px 12px;
            border: none;
            background: white;
            color: #718096;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
            font-size: 14px;
        }
        .tab-button:hover { background: #f7fafc; }
        .tab-button.active { color: #667eea; border-bottom-color: #667eea; }
        .tab-content { padding: 30px; }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        .event-list { display: flex; flex-direction: column; gap: 20px; }
        .event-list-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s;
            background: white;
        }
        .event-list-item:hover { border-color: #667eea; box-shadow: 0 4px 12px rgba(102,126,234,0.2); }
        .event-thumbnail {
            width: 150px; height: 150px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; overflow: hidden;
        }
        .event-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
        .event-info { flex: 1; }
        .event-info h3 { color: #2d3748; margin-bottom: 10px; font-size: 20px; }
        .event-meta-list {
            display: flex; flex-wrap: wrap; gap: 15px;
            margin-bottom: 12px; color: #718096; font-size: 14px;
        }
        .event-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }

        .registration-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .badge-registered { background: #c6f6d5; color: #276749; }
        .badge-attended   { background: #bee3f8; color: #2c5282; }
        .badge-past       { background: #fef3c7; color: #92400e; }
        .badge-cancelled  { background: #fed7d7; color: #c53030; }

        .certificate-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .certificate-btn:hover { opacity: 0.88; }

        .verified-info {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #276749;
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 8px;
        }

        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-icon  { font-size: 80px; margin-bottom: 20px; }
        .empty-state h3 { color: #2d3748; margin-bottom: 10px; font-size: 24px; }
        .empty-state p  { color: #718096; margin-bottom: 20px; }

        @media (max-width: 768px) {
            .event-list-item { flex-direction: column; }
            .event-thumbnail { width: 100%; height: 200px; }
            .tabs-header { flex-wrap: wrap; }
            .tab-button  { min-width: 50%; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🎫 My Events</h1>
            </div>
            
            <?php echo $message; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_registered; ?></div>
                    <div class="stat-label">Upcoming Registered</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #38a169;"><?php echo $total_attended; ?></div>
                    <div class="stat-label">✅ Officially Attended</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #d69e2e;"><?php echo mysqli_num_rows($past_result); ?></div>
                    <div class="stat-label">Past (Not Verified)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: #718096;"><?php echo $total_saved; ?></div>
                    <div class="stat-label">Saved Events</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-button <?php echo $active_tab == 'registered' ? 'active' : ''; ?>" 
                            onclick="switchTab('registered', this)">
                        📅 Upcoming (<?php echo $total_registered; ?>)
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'attended' ? 'active' : ''; ?>" 
                            onclick="switchTab('attended', this)">
                        ✅ Attended (<?php echo $total_attended; ?>)
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'past' ? 'active' : ''; ?>" 
                            onclick="switchTab('past', this)">
                        🕐 Past Events
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'saved' ? 'active' : ''; ?>" 
                            onclick="switchTab('saved', this)">
                        🔖 Saved
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>" 
                            onclick="switchTab('cancelled', this)">
                        ❌ Cancelled
                    </button>
                </div>
                
                <div class="tab-content">

                    <!-- ── UPCOMING REGISTERED ── -->
                    <div id="registered-tab" class="tab-pane <?php echo $active_tab == 'registered' ? 'active' : ''; ?>">
                        <?php 
                        mysqli_data_seek($registered_result, 0);
                        $has_upcoming = false;
                        while($event = mysqli_fetch_assoc($registered_result)):
                            $has_upcoming = true;
                        ?>
                            <div class="event-list-item" style="margin-bottom:0;">
                                <div class="event-thumbnail">
                                    <?php if($event['image']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                    <?php else: ?>
                                        <div style="font-size:60px;color:white;">📅</div>
                                    <?php endif; ?>
                                </div>
                                <div class="event-info">
                                    <span class="registration-badge badge-registered">✓ Registered</span>
                                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <div class="event-meta-list">
                                        <span>📅 <?php echo date('D, M d, Y - h:i A', strtotime($event['event_date'])); ?></span>
                                        <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                        <span>👤 <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                        <?php if($event['category']): ?>
                                            <span>🏷️ <?php echo htmlspecialchars($event['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <p style="color:#718096; font-size:13px;">
                                        Registered on: <?php echo date('M d, Y', strtotime($event['registration_date'])); ?>
                                    </p>
                                    <div class="event-actions">
                                        <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                        <form method="POST" style="display:inline;" 
                                              onsubmit="return confirm('Cancel this registration?');">
                                            <input type="hidden" name="registration_id" value="<?php echo $event['reg_id']; ?>">
                                            <button type="submit" name="cancel_registration" class="btn btn-danger btn-sm">Cancel Registration</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                        <?php if(!$has_upcoming): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <h3>No Upcoming Registered Events</h3>
                                <p>You haven't registered for any upcoming events yet.</p>
                                <a href="browse_events.php" class="btn btn-primary">Browse Events</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── ATTENDED (ticket verified) ── -->
                    <div id="attended-tab" class="tab-pane <?php echo $active_tab == 'attended' ? 'active' : ''; ?>">
                        <?php if($total_attended > 0): ?>
                            <div style="padding: 12px 16px; background: #f0fff4; border-left: 4px solid #48bb78; border-radius: 5px; margin-bottom: 20px; font-size: 14px; color: #276749;">
                                <strong>🎉 These are events you officially attended</strong> — your ticket was scanned or manually verified by the organizer.
                                You can download a participation certificate for each one.
                            </div>
                            <div class="event-list">
                                <?php 
                                mysqli_data_seek($attended_result, 0);
                                while($event = mysqli_fetch_assoc($attended_result)):
                                    $is_manual = strpos($event['ticket_code'], 'MANUAL-') === 0;
                                ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size:60px;color:white;">🎓</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-info">
                                            <span class="registration-badge badge-attended">✅ Attended</span>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            <div class="event-meta-list">
                                                <span>📅 <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?></span>
                                                <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                                <span>👤 <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                            </div>
                                            <div class="verified-info">
                                                🔏 Verified <?php echo date('M d, Y h:i A', strtotime($event['verified_at'])); ?>
                                                <?php if($is_manual): ?> · Manual entry<?php endif; ?>
                                            </div>
                                            <div class="event-actions">
                                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary btn-sm">View Event</a>
                                                <!-- <a href="generate_certificate.php?event_id=<?php echo $event['id']; ?>" 
                                                   class="certificate-btn" target="_blank">
                                                    🏆 Download Certificate
                                                </a> -->
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">🎫</div>
                                <h3>No Verified Attendance Yet</h3>
                                <p>Your attendance will appear here once your ticket is verified by the organizer at the event.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── PAST (date passed, NOT verified) ── -->
                    <div id="past-tab" class="tab-pane <?php echo $active_tab == 'past' ? 'active' : ''; ?>">
                        <?php 
                        mysqli_data_seek($past_result, 0);
                        if(mysqli_num_rows($past_result) > 0):
                        ?>
                            <div style="padding: 12px 16px; background: #fffbeb; border-left: 4px solid #f59e0b; border-radius: 5px; margin-bottom: 20px; font-size: 14px; color: #92400e;">
                                These events are past their date but your attendance was <strong>not verified</strong> by the organizer. 
                                Contact the organizer if you believe this is an error.
                            </div>
                            <div class="event-list">
                                <?php while($event = mysqli_fetch_assoc($past_result)): ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size:60px;color:white;">📅</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-info">
                                            <span class="registration-badge badge-past">⏰ Not Verified</span>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            <div class="event-meta-list">
                                                <span>📅 <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?></span>
                                                <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                                <span>👤 <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                            </div>
                                            <div class="event-actions">
                                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary btn-sm">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">🕐</div>
                                <h3>No Unverified Past Events</h3>
                                <p>All your past events have been verified, or you have no past registrations.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── SAVED ── -->
                    <div id="saved-tab" class="tab-pane <?php echo $active_tab == 'saved' ? 'active' : ''; ?>">
                        <?php if(mysqli_num_rows($saved_result) > 0): ?>
                            <div class="event-list">
                                <?php 
                                mysqli_data_seek($saved_result, 0);
                                while($event = mysqli_fetch_assoc($saved_result)):
                                    $seats_left = $event['max_participants'] - $event['registered_count'];
                                ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size:60px;color:white;">📅</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-info">
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            <div class="event-meta-list">
                                                <span>📅 <?php echo date('D, M d, Y - h:i A', strtotime($event['event_date'])); ?></span>
                                                <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                                <span>👤 <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                                <span>💺 <?php echo $seats_left; ?> seats left</span>
                                            </div>
                                            <div class="event-actions">
                                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                                                <?php if($event['is_registered']): ?>
                                                    <button class="btn btn-success btn-sm" disabled>Already Registered</button>
                                                <?php elseif($seats_left > 0 && $event['event_date'] > date('Y-m-d H:i:s')): ?>
                                                    <a href="register_event.php?event_id=<?php echo $event['id']; ?>" class="btn btn-success btn-sm">Register Now</a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Event Full / Passed</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">🔖</div>
                                <h3>No Saved Events</h3>
                                <p>Save events to view them later!</p>
                                <a href="browse_events.php" class="btn btn-primary">Browse Events</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ── CANCELLED ── -->
                    <div id="cancelled-tab" class="tab-pane <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>">
                        <?php if(mysqli_num_rows($cancelled_result) > 0): ?>
                            <div class="event-list">
                                <?php 
                                mysqli_data_seek($cancelled_result, 0);
                                while($event = mysqli_fetch_assoc($cancelled_result)):
                                    $seats_taken_q = "SELECT COUNT(*) as cnt FROM registrations WHERE event_id={$event['id']} AND status='registered'";
                                    $seats_data = mysqli_fetch_assoc(mysqli_query($conn, $seats_taken_q));
                                    $seats_left_c = $event['max_participants'] - $seats_data['cnt'];
                                ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size:60px;color:white;">📅</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="event-info">
                                            <span class="registration-badge badge-cancelled">❌ Cancelled</span>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            <div class="event-meta-list">
                                                <span>📅 <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?></span>
                                                <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                            </div>
                                            <div class="event-actions">
                                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary btn-sm">View Details</a>
                                                <?php if($event['event_date'] > date('Y-m-d H:i:s') && $event['status'] == 'upcoming' && $seats_left_c > 0): ?>
                                                    <a href="register_event.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary btn-sm">🔄 Register Again</a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">✓</div>
                                <h3>No Cancelled Registrations</h3>
                                <p>You haven't cancelled any registrations.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div><!-- tab-content -->
            </div><!-- tabs-container -->
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function switchTab(tabName, btn) {
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);

            document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));

            document.getElementById(tabName + '-tab').classList.add('active');
            if(btn) btn.classList.add('active');
        }
    </script>
</body>
</html>