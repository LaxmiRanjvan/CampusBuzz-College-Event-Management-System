<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get event ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../common/home.php");
    exit();
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Fetch event details
$event_query = "SELECT e.*, u.full_name as organizer_name, u.email as organizer_email, u.department as organizer_dept,
                (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
                (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id) as total_likes,
                (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id AND user_id = $user_id) as is_liked,
                (SELECT COUNT(*) FROM event_saves WHERE event_id = e.id AND user_id = $user_id) as is_saved
                FROM events e
                JOIN users u ON e.organizer_id = u.id
                WHERE e.id = $event_id";
$event_result = mysqli_query($conn, $event_query);

if(mysqli_num_rows($event_result) == 0) {
    header("Location: ../common/home.php");
    exit();
}

$event = mysqli_fetch_assoc($event_result);

// Check if already registered
$check_reg = "SELECT * FROM registrations WHERE event_id = $event_id AND user_id = $user_id";
$is_registered = mysqli_num_rows(mysqli_query($conn, $check_reg)) > 0;

$seats_left = $event['max_participants'] - $event['registered_count'];
$is_full = $seats_left <= 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title']); ?> - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .event-detail-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .event-main {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .event-sidebar {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            align-self: start;
            position: sticky;
            top: 20px;
        }
        
        .event-hero-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .event-badge-large {
            display: inline-block;
            padding: 8px 16px;
            background: #bee3f8;
            color: #2c5282;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .social-actions-large {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 20px 0;
        }
        
        .social-btn-large {
            padding: 12px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .social-btn-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .social-btn-large.liked {
            background: #fed7d7;
            border-color: #f56565;
            color: #c53030;
        }
        
        .social-btn-large.saved {
            background: #e6e9fc;
            border-color: #667eea;
            color: #667eea;
        }
        
        @media (max-width: 968px) {
            .event-detail-container {
                grid-template-columns: 1fr;
            }
            
            .event-sidebar {
                position: relative;
                top: 0;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>📅 Event Details</h1>
                <a href="../common/home.php" class="btn btn-secondary">← Back to Home</a>
            </div>
            
            <div class="event-detail-container">
                <!-- Main Content -->
                <div class="event-main">
                    <?php if($event['image']): ?>
                        <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" 
                             class="event-hero-image" alt="Event">
                    <?php endif; ?>
                    
                    <span class="event-badge-large">
                        <?php echo $event['category'] ? htmlspecialchars($event['category']) : 'General Event'; ?>
                    </span>
                    
                    <h2 style="margin-bottom: 20px; color: #2d3748; font-size: 32px;">
                        <?php echo htmlspecialchars($event['title']); ?>
                    </h2>
                    
                    <!-- Social Actions -->
                    <div class="social-actions-large">
                        <button class="social-btn-large <?php echo $event['is_liked'] ? 'liked' : ''; ?>" 
                                onclick="toggleLike(<?php echo $event_id; ?>, this)">
                            <span>❤️</span>
                            <span id="like-count"><?php echo $event['total_likes']; ?> Likes</span>
                        </button>
                        
                        <button class="social-btn-large <?php echo $event['is_saved'] ? 'saved' : ''; ?>" 
                                onclick="toggleSave(<?php echo $event_id; ?>, this)">
                            <span>🔖</span>
                            <span><?php echo $event['is_saved'] ? 'Saved' : 'Save Event'; ?></span>
                        </button>
                    </div>
                    
                    <!-- Description -->
                    <div style="margin: 30px 0; padding: 25px; background: #f7fafc; border-radius: 10px;">
                        <h3 style="margin-bottom: 15px; color: #2d3748;">📝 About This Event</h3>
                        <p style="line-height: 1.8; color: #4a5568; white-space: pre-wrap;">
                            <?php echo htmlspecialchars($event['description']); ?>
                        </p>
                    </div>
                    
                    <!-- Event Details -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 30px;">
                        <div style="padding: 20px; background: #f7fafc; border-radius: 8px;">
                            <div style="font-size: 12px; color: #718096; margin-bottom: 5px;">📅 DATE & TIME</div>
                            <div style="font-weight: 600; color: #2d3748;">
                                <?php echo date('l, F d, Y', strtotime($event['event_date'])); ?><br>
                                <?php echo date('h:i A', strtotime($event['event_date'])); ?>
                            </div>
                        </div>
                        
                        <div style="padding: 20px; background: #f7fafc; border-radius: 8px;">
                            <div style="font-size: 12px; color: #718096; margin-bottom: 5px;">📍 VENUE</div>
                            <div style="font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($event['venue']); ?></div>
                        </div>
                        
                        <?php if($event['registration_deadline']): ?>
                        <div style="padding: 20px; background: #fff3cd; border-radius: 8px;">
                            <div style="font-size: 12px; color: #856404; margin-bottom: 5px;">⏰ REGISTRATION DEADLINE</div>
                            <div style="font-weight: 600; color: #856404;">
                                <?php echo date('M d, Y h:i A', strtotime($event['registration_deadline'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Organizer Info -->
                    <div style="margin-top: 30px; padding: 25px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; color: white;">
                        <h4 style="margin-bottom: 15px;">👤 Organized By</h4>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="width: 60px; height: 60px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 700; color: #667eea;">
                                <?php echo strtoupper(substr($event['organizer_name'], 0, 2)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 18px; margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($event['organizer_name']); ?>
                                </div>
                                <div style="opacity: 0.9; font-size: 14px;">
                                    <?php echo htmlspecialchars($event['organizer_dept'] ?? 'Campus Event Organizer'); ?>
                                </div>
                                <div style="opacity: 0.9; font-size: 13px; margin-top: 3px;">
                                    📧 <?php echo htmlspecialchars($event['organizer_email']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="event-sidebar">
                    <!-- Registration Status -->
                    <div style="padding: 20px; background: <?php echo $is_full ? '#fed7d7' : '#c6f6d5'; ?>; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                        <?php if($is_full): ?>
                            <div style="font-size: 32px; margin-bottom: 10px;">😔</div>
                            <div style="font-weight: 700; color: #c53030; margin-bottom: 5px;">Event Full</div>
                            <div style="font-size: 14px; color: #c53030;">All seats are taken</div>
                        <?php else: ?>
                            <div style="font-size: 32px; margin-bottom: 10px;">🎉</div>
                            <div style="font-weight: 700; color: #276749; margin-bottom: 5px;">Seats Available</div>
                            <div style="font-size: 14px; color: #276749;"><?php echo $seats_left; ?> seats remaining</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Capacity -->
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-weight: 600; color: #2d3748;">Capacity</span>
                            <span style="color: #667eea; font-weight: 600;">
                                <?php echo $event['registered_count']; ?> / <?php echo $event['max_participants']; ?>
                            </span>
                        </div>
                        <div style="width: 100%; height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); width: <?php echo min(($event['registered_count'] / $event['max_participants']) * 100, 100); ?>%;"></div>
                        </div>
                    </div>
                    
                    <!-- Register Button -->
                    <?php if($_SESSION['role'] == 'student'): ?>
                        <?php if($is_registered): ?>
                            <button class="btn btn-success" style="width: 100%; font-size: 16px; padding: 15px;" disabled>
                                ✓ You're Registered
                            </button>
                            <a href="../student/my_events.php" class="btn btn-secondary" style="width: 100%; text-align: center; margin-top: 10px;">
                                View My Events
                            </a>
                        <?php elseif($is_full): ?>
                            <button class="btn btn-secondary" style="width: 100%; font-size: 16px; padding: 15px;" disabled>
                                Event Full
                            </button>
                        <?php else: ?>
                            <a href="register_event.php?event_id=<?php echo $event_id; ?>" 
                               class="btn btn-primary" 
                               style="width: 100%; text-align: center; font-size: 16px; padding: 15px;">
                                🎫 Register Now
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Share Button -->
                    <button class="btn btn-secondary" 
                            style="width: 100%; text-align: center; margin-top: 10px;"
                            onclick="shareEvent(<?php echo $event_id; ?>, '<?php echo addslashes($event['title']); ?>')">
                        📤 Share Event
                    </button>
                    
                    <!-- Event Stats -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <div style="font-size: 13px; color: #718096; margin-bottom: 10px;">Event Statistics</div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="color: #4a5568;">❤️ Likes</span>
                            <span style="font-weight: 600; color: #2d3748;"><?php echo $event['total_likes']; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="color: #4a5568;">👥 Registered</span>
                            <span style="font-weight: 600; color: #2d3748;"><?php echo $event['registered_count']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function toggleLike(eventId, button) {
            fetch('../common/home.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=like&event_id=' + eventId
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'liked') {
                    button.classList.add('liked');
                    const count = document.getElementById('like-count');
                    count.textContent = (parseInt(count.textContent) + 1) + ' Likes';
                } else {
                    button.classList.remove('liked');
                    const count = document.getElementById('like-count');
                    count.textContent = (parseInt(count.textContent) - 1) + ' Likes';
                }
            });
        }
        
        function toggleSave(eventId, button) {
            fetch('../common/home.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=save&event_id=' + eventId
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'saved') {
                    button.classList.add('saved');
                    button.querySelector('span:last-child').textContent = 'Saved';
                } else {
                    button.classList.remove('saved');
                    button.querySelector('span:last-child').textContent = 'Save Event';
                }
            });
        }
        
        function shareEvent(eventId, title) {
            const url = window.location.origin + '/campus-event-manager/student/view_event.php?id=' + eventId;
            if(navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Check out this event: ' + title,
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url);
                alert('Event link copied to clipboard!');
            }
        }
    </script>
</body>
</html>