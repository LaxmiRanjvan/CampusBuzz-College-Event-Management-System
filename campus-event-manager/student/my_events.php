<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Handle messages from URL
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
    
    // Verify ownership
    $verify = "SELECT * FROM registrations WHERE id = $reg_id AND user_id = $user_id";
    if(mysqli_num_rows(mysqli_query($conn, $verify)) > 0) {
        $cancel_sql = "UPDATE registrations SET status = 'cancelled' WHERE id = $reg_id";
        if(mysqli_query($conn, $cancel_sql)) {
            header("Location: my_events.php?msg=cancelled");
            exit();
        }
    }
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'registered';

// Fetch registered events
$registered_sql = "SELECT e.*, r.*, u.full_name as organizer_name,
                   (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                   FROM registrations r
                   JOIN events e ON r.event_id = e.id
                   JOIN users u ON e.organizer_id = u.id
                   WHERE r.user_id = $user_id AND r.status = 'registered'
                   ORDER BY e.event_date ASC";
$registered_result = mysqli_query($conn, $registered_sql);

// Fetch past/attended events
$past_sql = "SELECT e.*, r.*, u.full_name as organizer_name
             FROM registrations r
             JOIN events e ON r.event_id = e.id
             JOIN users u ON e.organizer_id = u.id
             WHERE r.user_id = $user_id 
             AND r.status = 'registered'
             AND e.event_date < NOW()
             ORDER BY e.event_date DESC";
$past_result = mysqli_query($conn, $past_sql);

// Fetch cancelled registrations
$cancelled_sql = "SELECT e.*, r.*, u.full_name as organizer_name
                  FROM registrations r
                  JOIN events e ON r.event_id = e.id
                  JOIN users u ON e.organizer_id = u.id
                  WHERE r.user_id = $user_id AND r.status = 'cancelled'
                  ORDER BY r.registration_date DESC";
$cancelled_result = mysqli_query($conn, $cancelled_sql);

// Fetch saved events
$saved_sql = "SELECT e.*, u.full_name as organizer_name,
              (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
              (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND user_id = $user_id AND status='registered') as is_registered
              FROM event_saves es
              JOIN events e ON es.event_id = e.id
              JOIN users u ON e.organizer_id = u.id
              WHERE es.user_id = $user_id
              ORDER BY es.saved_at DESC";
$saved_result = mysqli_query($conn, $saved_sql);

// Calculate statistics
$total_registered = mysqli_num_rows($registered_result);
$total_attended = mysqli_num_rows($past_result);
$total_saved = mysqli_num_rows($saved_result);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 14px;
        }
        
        .tabs-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
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
            padding: 20px;
            border: none;
            background: white;
            color: #718096;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button:hover {
            background: #f7fafc;
        }
        
        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            padding: 30px;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.active {
            display: block;
        }
        
        .event-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .event-list-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: all 0.3s;
            background: white;
        }
        
        .event-list-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .event-thumbnail {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .event-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-info {
            flex: 1;
        }
        
        .event-info h3 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .event-meta-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            color: #718096;
            font-size: 14px;
        }
        
        .event-meta-list span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .registration-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .badge-registered {
            background: #c6f6d5;
            color: #276749;
        }
        
        .badge-cancelled {
            background: #fed7d7;
            color: #c53030;
        }
        
        .badge-past {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .event-list-item {
                flex-direction: column;
            }
            
            .event-thumbnail {
                width: 100%;
                height: 200px;
            }
            
            .tabs-header {
                flex-wrap: wrap;
            }
            
            .tab-button {
                min-width: 50%;
            }
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
                    <div class="stat-label">Registered Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_attended; ?></div>
                    <div class="stat-label">Events Attended</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_saved; ?></div>
                    <div class="stat-label">Saved Events</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-button <?php echo $active_tab == 'registered' ? 'active' : ''; ?>" 
                            onclick="switchTab('registered')">
                        📅 Upcoming Events
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'past' ? 'active' : ''; ?>" 
                            onclick="switchTab('past')">
                        🕐 Past Events
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'saved' ? 'active' : ''; ?>" 
                            onclick="switchTab('saved')">
                        🔖 Saved Events
                    </button>
                    <button class="tab-button <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>" 
                            onclick="switchTab('cancelled')">
                        ❌ Cancelled
                    </button>
                </div>
                
                <div class="tab-content">
                    <!-- Registered Events Tab -->
                    <div id="registered-tab" class="tab-pane <?php echo $active_tab == 'registered' ? 'active' : ''; ?>">
                        <?php if(mysqli_num_rows($registered_result) > 0): ?>
                            <div class="event-list">
                                <?php 
                                mysqli_data_seek($registered_result, 0);
                                while($event = mysqli_fetch_assoc($registered_result)): 
                                    $is_upcoming = strtotime($event['event_date']) > time();
                                    if($is_upcoming):
                                ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size: 60px; color: white;">📅</div>
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
                                            
                                            <p style="color: #718096; margin-bottom: 15px;">
                                                Registered on: <?php echo date('M d, Y', strtotime($event['registration_date'])); ?>
                                            </p>
                                            
                                            <div class="event-actions">
                                                <a href="event_detail.php?id=<?php echo $event['event_id']; ?>" 
                                                   class="btn btn-primary btn-sm">View Details</a>
                                                
                                                <form method="POST" style="display: inline;" 
                                                      onsubmit="return confirm('Are you sure you want to cancel this registration?');">
                                                    <input type="hidden" name="registration_id" value="<?php echo $event['id']; ?>">
                                                    <button type="submit" name="cancel_registration" 
                                                            class="btn btn-danger btn-sm">
                                                        Cancel Registration
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <h3>No Registered Events</h3>
                                <p>You haven't registered for any upcoming events yet.</p>
                                <a href="browse_events.php" class="btn btn-primary">Browse Events</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Past Events Tab -->
                    <div id="past-tab" class="tab-pane <?php echo $active_tab == 'past' ? 'active' : ''; ?>">
                        <?php if(mysqli_num_rows($past_result) > 0): ?>
                            <div class="event-list">
                                <?php 
                                mysqli_data_seek($past_result, 0);
                                while($event = mysqli_fetch_assoc($past_result)): 
                                ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size: 60px; color: white;">📅</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="event-info">
                                            <span class="registration-badge badge-past">✓ Attended</span>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            
                                            <div class="event-meta-list">
                                                <span>📅 <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?></span>
                                                <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                                <span>👤 <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                            </div>
                                            
                                            <div class="event-actions">
                                                <a href="event_detail.php?id=<?php echo $event['event_id']; ?>" 
                                                   class="btn btn-secondary btn-sm">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">🕐</div>
                                <h3>No Past Events</h3>
                                <p>You haven't attended any events yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Saved Events Tab -->
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
                                                <div style="font-size: 60px; color: white;">📅</div>
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
                                                <a href="event_detail.php?id=<?php echo $event['id']; ?>" 
                                                   class="btn btn-primary btn-sm">View Details</a>
                                                
                                                <?php if($event['is_registered']): ?>
                                                    <button class="btn btn-success btn-sm" disabled>Already Registered</button>
                                                <?php elseif($seats_left > 0): ?>
                                                    <a href="register_event.php?event_id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-success btn-sm">Register Now</a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Event Full</button>
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
                                <p>You haven't saved any events yet. Save events to view them later!</p>
                                <a href="browse_events.php" class="btn btn-primary">Browse Events</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Cancelled Tab -->
                    <div id="cancelled-tab" class="tab-pane <?php echo $active_tab == 'cancelled' ? 'active' : ''; ?>">
                        <?php if(mysqli_num_rows($cancelled_result) > 0): ?>
                            <div class="event-list">
                                <?php 
                                mysqli_data_seek($cancelled_result, 0);
                                while($event = mysqli_fetch_assoc($cancelled_result)): 
                                ?>
                                    <div class="event-list-item">
                                        <div class="event-thumbnail">
                                            <?php if($event['image']): ?>
                                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                            <?php else: ?>
                                                <div style="font-size: 60px; color: white;">📅</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="event-info">
                                            <span class="registration-badge badge-cancelled">❌ Cancelled</span>
                                            <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                            
                                            <div class="event-meta-list">
                                                <span>📅 <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?></span>
                                                <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                            </div>
                                            
                                            <p style="color: #718096;">
                                                Registration cancelled on: <?php echo date('M d, Y', strtotime($event['registration_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">✓</div>
                                <h3>No Cancelled Registrations</h3>
                                <p>You haven't cancelled any event registrations.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function switchTab(tabName) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
            
            // Hide all tabs
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Deactivate all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Activate button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>