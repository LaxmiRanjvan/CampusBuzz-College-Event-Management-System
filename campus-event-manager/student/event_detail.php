<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if(!$event_id) {
    header("Location: browse_events.php");
    exit();
}

// Handle AJAX actions
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    if($action == 'like') {
        $check = "SELECT * FROM event_likes WHERE event_id = $event_id AND user_id = $user_id";
        if(mysqli_num_rows(mysqli_query($conn, $check)) > 0) {
            mysqli_query($conn, "DELETE FROM event_likes WHERE event_id = $event_id AND user_id = $user_id");
            echo json_encode(['status' => 'unliked']);
        } else {
            mysqli_query($conn, "INSERT INTO event_likes (event_id, user_id) VALUES ($event_id, $user_id)");
            echo json_encode(['status' => 'liked']);
        }
    } elseif($action == 'save') {
        $check = "SELECT * FROM event_saves WHERE event_id = $event_id AND user_id = $user_id";
        if(mysqli_num_rows(mysqli_query($conn, $check)) > 0) {
            mysqli_query($conn, "DELETE FROM event_saves WHERE event_id = $event_id AND user_id = $user_id");
            echo json_encode(['status' => 'unsaved']);
        } else {
            mysqli_query($conn, "INSERT INTO event_saves (event_id, user_id) VALUES ($event_id, $user_id)");
            echo json_encode(['status' => 'saved']);
        }
    }
    exit();
}

// Fetch event details
$event_sql = "SELECT e.*, u.full_name as organizer_name, u.email as organizer_email,
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id AND user_id = $user_id) as is_liked,
               (SELECT COUNT(*) FROM event_saves WHERE event_id = e.id AND user_id = $user_id) as is_saved,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id) as total_likes
               FROM events e 
               JOIN users u ON e.organizer_id = u.id 
               WHERE e.id = $event_id";
$event_result = mysqli_query($conn, $event_sql);

if(mysqli_num_rows($event_result) == 0) {
    header("Location: browse_events.php");
    exit();
}

$event = mysqli_fetch_assoc($event_result);
$seats_left = $event['max_participants'] - $event['registered_count'];
$is_full = $seats_left <= 0;

// Check if already registered
$check_reg = "SELECT * FROM registrations WHERE event_id=$event_id AND user_id=$user_id";
$is_registered = mysqli_num_rows(mysqli_query($conn, $check_reg)) > 0;

// Get similar events
$similar_sql = "SELECT e.*, u.full_name as organizer_name,
                (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count
                FROM events e 
                JOIN users u ON e.organizer_id = u.id 
                WHERE e.id != $event_id 
                AND e.status = 'upcoming'
                AND (e.category = '{$event['category']}' OR e.organizer_id = {$event['organizer_id']})
                ORDER BY e.event_date ASC
                LIMIT 3";
$similar_result = mysqli_query($conn, $similar_sql);
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 10px 16px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .back-button:hover {
            background: #f7fafc;
        }
        
        .event-hero {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .event-hero-image {
            height: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .event-hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .event-hero-badges {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
        }
        
        .hero-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            backdrop-filter: blur(10px);
        }
        
        .event-hero-content {
            padding: 40px;
        }
        
        .event-title {
            font-size: 36px;
            color: #2d3748;
            margin-bottom: 20px;
        }
        
        .event-meta-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            padding: 20px 0;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 30px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4a5568;
        }
        
        .meta-item-icon {
            font-size: 24px;
        }
        
        .meta-item-text {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .meta-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 600;
        }
        
        .event-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .event-main {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        
        .event-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .section-title {
            font-size: 22px;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .event-description {
            color: #4a5568;
            line-height: 1.8;
            font-size: 16px;
        }
        
        .event-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .sidebar-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .registration-status {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .status-available {
            background: #c6f6d5;
            color: #276749;
        }
        
        .status-limited {
            background: #feebc8;
            color: #7c2d12;
        }
        
        .status-full {
            background: #fed7d7;
            color: #c53030;
        }
        
        .seats-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .seats-number {
            font-size: 48px;
            font-weight: 700;
            color: #667eea;
            line-height: 1;
        }
        
        .seats-label {
            color: #718096;
            font-size: 14px;
        }
        
        .organizer-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 8px;
        }
        
        .organizer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
        }
        
        .organizer-details h4 {
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .organizer-details p {
            color: #718096;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .social-actions-large {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .social-btn-large {
            padding: 12px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .social-btn-large:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .social-btn-large.liked {
            border-color: #f56565;
            background: #fed7d7;
            color: #f56565;
        }
        
        .social-btn-large.saved {
            border-color: #667eea;
            background: #e6e9fc;
            color: #667eea;
        }
        
        .similar-events {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .similar-event-card {
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .similar-event-card:hover {
            border-color: #667eea;
            transform: translateX(5px);
        }
        
        .similar-event-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .similar-event-meta {
            font-size: 13px;
            color: #718096;
        }
        
        @media (max-width: 968px) {
            .event-grid {
                grid-template-columns: 1fr;
            }
            
            .event-hero-image {
                height: 300px;
            }
            
            .event-title {
                font-size: 28px;
            }
            
            .event-meta-bar {
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="event-detail-container">
                <a href="browse_events.php" class="back-button">
                    ← Back to Events
                </a>
                
                <div class="event-hero">
                    <div class="event-hero-image">
                        <?php if($event['image']): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                        <?php else: ?>
                            <div style="font-size: 120px; color: white;">📅</div>
                        <?php endif; ?>
                        
                        <div class="event-hero-badges">
                            <?php if($event['category']): ?>
                                <span class="hero-badge" style="background: rgba(102, 126, 234, 0.9); color: white;">
                                    🏷️ <?php echo htmlspecialchars($event['category']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if($event['event_type']): ?>
                                <span class="hero-badge" style="background: rgba(255, 255, 255, 0.9); color: #2d3748;">
                                    <?php echo $event['event_type'] == 'online' ? '🌐 Online' : '🏛️ Offline'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="event-hero-content">
                        <h1 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h1>
                        
                        <div class="event-meta-bar">
                            <div class="meta-item">
                                <div class="meta-item-icon">📅</div>
                                <div class="meta-item-text">
                                    <span class="meta-label">Date & Time</span>
                                    <span class="meta-value">
                                        <?php echo date('D, M d, Y', strtotime($event['event_date'])); ?><br>
                                        <?php echo date('h:i A', strtotime($event['event_date'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-item-icon">📍</div>
                                <div class="meta-item-text">
                                    <span class="meta-label">Venue</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($event['venue']); ?></span>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-item-icon">👥</div>
                                <div class="meta-item-text">
                                    <span class="meta-label">Registered</span>
                                    <span class="meta-value">
                                        <?php echo $event['registered_count']; ?> / <?php echo $event['max_participants']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="meta-item">
                                <div class="meta-item-icon">❤️</div>
                                <div class="meta-item-text">
                                    <span class="meta-label">Likes</span>
                                    <span class="meta-value" id="total-likes"><?php echo $event['total_likes']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="event-grid">
                    <div class="event-main">
                        <!-- Description -->
                        <div class="event-section">
                            <h2 class="section-title">📝 About This Event</h2>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                            </div>
                        </div>
                        
                        <!-- Contact Info -->
                        <?php if($event['contact_info']): ?>
                        <div class="event-section">
                            <h2 class="section-title">📞 Contact Information</h2>
                            <div class="event-description">
                                <?php echo nl2br(htmlspecialchars($event['contact_info'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Registration Link -->
                        <?php if($event['registration_link']): ?>
                        <div class="event-section">
                            <h2 class="section-title">🔗 External Registration</h2>
                            <p style="color: #718096; margin-bottom: 15px;">
                                This event uses an external registration form.
                            </p>
                            <a href="<?php echo htmlspecialchars($event['registration_link']); ?>" 
                               target="_blank" 
                               class="btn btn-primary">
                                Open Registration Form →
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="event-sidebar">
                        <!-- Registration Card -->
                        <div class="sidebar-card">
                            <?php if($is_registered): ?>
                                <div class="registration-status status-available">
                                    ✓ You're Registered!
                                </div>
                            <?php elseif($is_full): ?>
                                <div class="registration-status status-full">
                                    ⚠️ Event Full
                                </div>
                            <?php elseif($seats_left <= 10): ?>
                                <div class="registration-status status-limited">
                                    ⏰ Hurry! Limited Seats
                                </div>
                            <?php else: ?>
                                <div class="registration-status status-available">
                                    ✓ Seats Available
                                </div>
                            <?php endif; ?>
                            
                            <div class="seats-info">
                                <div class="seats-number"><?php echo $seats_left; ?></div>
                                <div class="seats-label">Seats Remaining</div>
                            </div>
                            
                            <div class="action-buttons">
                                <?php if($_SESSION['role'] == 'student'): ?>
                                    <?php if($is_registered): ?>
                                        <button class="btn btn-success" style="width: 100%;" disabled>
                                            ✓ Already Registered
                                        </button>
                                        <a href="my_events.php" class="btn btn-secondary" style="width: 100%; text-align: center;">
                                            View My Events
                                        </a>
                                    <?php elseif($is_full): ?>
                                        <button class="btn btn-secondary" style="width: 100%;" disabled>
                                            Event Full
                                        </button>
                                    <?php else: ?>
                                        <a href="register_event.php?event_id=<?php echo $event_id; ?>" 
                                           class="btn btn-primary" style="width: 100%; text-align: center;">
                                            Register Now
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="social-actions-large">
                                <button class="social-btn-large <?php echo $event['is_liked'] ? 'liked' : ''; ?>" 
                                        onclick="toggleLike(this)">
                                    <span style="font-size: 24px;">❤️</span>
                                    <span id="like-text"><?php echo $event['is_liked'] ? 'Liked' : 'Like'; ?></span>
                                </button>
                                
                                <button class="social-btn-large <?php echo $event['is_saved'] ? 'saved' : ''; ?>" 
                                        onclick="toggleSave(this)">
                                    <span style="font-size: 24px;">🔖</span>
                                    <span id="save-text"><?php echo $event['is_saved'] ? 'Saved' : 'Save'; ?></span>
                                </button>
                                
                                <button class="social-btn-large" onclick="shareEvent()">
                                    <span style="font-size: 24px;">📤</span>
                                    <span>Share</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Organizer Card -->
                        <div class="sidebar-card">
                            <h3 class="section-title">👤 Organized By</h3>
                            <div class="organizer-info">
                                <div class="organizer-avatar">
                                    <?php echo strtoupper(substr($event['organizer_name'], 0, 1)); ?>
                                </div>
                                <div class="organizer-details">
                                    <h4><?php echo htmlspecialchars($event['organizer_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($event['organizer_email']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Similar Events -->
                        <?php if(mysqli_num_rows($similar_result) > 0): ?>
                        <div class="sidebar-card">
                            <h3 class="section-title">🎯 Similar Events</h3>
                            <div class="similar-events">
                                <?php while($similar = mysqli_fetch_assoc($similar_result)): ?>
                                    <a href="event_detail.php?id=<?php echo $similar['id']; ?>" class="similar-event-card">
                                        <div class="similar-event-title">
                                            <?php echo htmlspecialchars($similar['title']); ?>
                                        </div>
                                        <div class="similar-event-meta">
                                            📅 <?php echo date('M d, Y', strtotime($similar['event_date'])); ?> • 
                                            👤 <?php echo htmlspecialchars($similar['organizer_name']); ?>
                                        </div>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        function toggleLike(button) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=like&event_id=<?php echo $event_id; ?>'
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'liked') {
                    button.classList.add('liked');
                    document.getElementById('like-text').textContent = 'Liked';
                    const totalLikes = document.getElementById('total-likes');
                    totalLikes.textContent = parseInt(totalLikes.textContent) + 1;
                } else {
                    button.classList.remove('liked');
                    document.getElementById('like-text').textContent = 'Like';
                    const totalLikes = document.getElementById('total-likes');
                    totalLikes.textContent = parseInt(totalLikes.textContent) - 1;
                }
            });
        }
        
        function toggleSave(button) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=save&event_id=<?php echo $event_id; ?>'
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'saved') {
                    button.classList.add('saved');
                    document.getElementById('save-text').textContent = 'Saved';
                } else {
                    button.classList.remove('saved');
                    document.getElementById('save-text').textContent = 'Save';
                }
            });
        }
        
        function shareEvent() {
            const url = window.location.href;
            const title = '<?php echo addslashes($event['title']); ?>';
            
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