<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'];

// Handle AJAX actions
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $event_id = intval($_POST['event_id']);
    
    if($action == 'like') {
        // Toggle like
        $check = "SELECT * FROM event_likes WHERE event_id = $event_id AND user_id = $user_id";
        if(mysqli_num_rows(mysqli_query($conn, $check)) > 0) {
            mysqli_query($conn, "DELETE FROM event_likes WHERE event_id = $event_id AND user_id = $user_id");
            echo json_encode(['status' => 'unliked']);
        } else {
            mysqli_query($conn, "INSERT INTO event_likes (event_id, user_id) VALUES ($event_id, $user_id)");
            echo json_encode(['status' => 'liked']);
        }
    } elseif($action == 'save') {
        // Toggle save
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

// Fetch UPCOMING events (all future events)
$upcoming_sql = "SELECT e.*, u.full_name as organizer_name, 
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id AND user_id = $user_id) as is_liked,
               (SELECT COUNT(*) FROM event_saves WHERE event_id = e.id AND user_id = $user_id) as is_saved,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id) as total_likes
               FROM events e 
               JOIN users u ON e.organizer_id = u.id 
               WHERE e.status = 'upcoming' AND e.event_date > NOW()
               ORDER BY e.event_date ASC LIMIT 4";
$upcoming_result = mysqli_query($conn, $upcoming_sql);

// Fetch THIS WEEK events (events in the next 7 days)
$this_week_sql = "SELECT e.*, u.full_name as organizer_name, 
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id AND user_id = $user_id) as is_liked,
               (SELECT COUNT(*) FROM event_saves WHERE event_id = e.id AND user_id = $user_id) as is_saved,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id) as total_likes
               FROM events e 
               JOIN users u ON e.organizer_id = u.id 
               WHERE e.status = 'upcoming' 
               AND e.event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
               ORDER BY e.event_date ASC LIMIT 4";
$this_week_result = mysqli_query($conn, $this_week_sql);

// Fetch MERCHANDISE - FIXED QUERY
$merch_sql = "SELECT m.*, 
              (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image,
              u.full_name as organizer_name
              FROM merchandise m 
              JOIN users u ON m.organizer_id = u.id
              WHERE m.status = 'available' 
              ORDER BY m.created_at DESC 
              LIMIT 6";
$merch_result = mysqli_query($conn, $merch_sql);

// Determine the correct path based on user role
function getUserPath($role) {
    switch($role) {
        case 'student':
            return '../student';
        case 'organizer':
            return '../organizer';
        case 'admin':
            return '../admin';
        default:
            return '../student';
    }
}

$user_path = getUserPath($user_role);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Welcome Hero Section */
        .welcome-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 80px 40px;
            border-radius: 16px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }
        
        .welcome-hero h1 {
            color: white;
            font-size: 48px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .welcome-hero p {
            color: rgba(255, 255, 255, 0.95);
            font-size: 18px;
            margin-bottom: 30px;
        }
        
        .hero-search {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
        }
        
        .hero-search input {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .hero-search button {
            padding: 16px 32px;
            background: white;
            color: #667eea;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }
        
        .hero-search button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .section-header h2 {
            color: #2d3748;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .section-header a:hover {
            color: #5568d3;
            transform: translateX(5px);
        }
        
        /* Section Spacing */
        .events-section {
            margin-bottom: 50px;
        }
        
        /* Social Actions Styling */
        .social-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .social-btn {
            flex: 1;
            padding: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .social-btn:hover {
            background: #f7fafc;
            transform: translateY(-2px);
        }
        
        .social-btn.liked {
            color: #f56565;
            border-color: #f56565;
            background: #fed7d7;
        }
        
        .social-btn.saved {
            color: #667eea;
            border-color: #667eea;
            background: #e6e9fc;
        }
        
        /* Merchandise Card Styling */
        .merch-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .merch-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .merch-image {
            height: 200px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .merch-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .merch-placeholder {
            font-size: 60px;
            color: white;
        }
        
        .merch-content {
            padding: 20px;
        }
        
        .merch-content h3 {
            margin-bottom: 8px;
            color: #2d3748;
            font-size: 18px;
        }
        
        .merch-price {
            font-size: 24px;
            color: #667eea;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .merch-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #718096;
        }
        
        /* Responsive Grid */
        @media (max-width: 768px) {
            .welcome-hero h1 {
                font-size: 32px;
            }
            
            .hero-search {
                flex-direction: column;
            }
            
            .events-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Welcome Hero Section -->
            <div class="welcome-hero">
                <h1>
                    <span>🎉</span>
                    Welcome to Campus Events
                </h1>
                <p>Explore exciting events happening on campus</p>
                
                <div class="hero-search">
                    <input type="text" id="heroSearchInput" placeholder="Search for events...">
                    <button type="button" onclick="searchEvents()">
                        <span>🔍</span> Search
                    </button>
                </div>
            </div>
            
            <!-- UPCOMING EVENTS Section -->
            <div class="events-section">
                <div class="section-header">
                    <h2>📅 Upcoming Events</h2>
                    <a href="<?php echo $user_path; ?>/browse_events.php">View All →</a>
                </div>
                
                <?php if($upcoming_result && mysqli_num_rows($upcoming_result) > 0): ?>
                    <div class="events-grid">
                        <?php while($event = mysqli_fetch_assoc($upcoming_result)): 
                            $seats_left = $event['max_participants'] - $event['registered_count'];
                            $is_full = $seats_left <= 0;
                            
                            // Check if already registered
                            $check_reg = "SELECT * FROM registrations WHERE event_id={$event['id']} AND user_id=$user_id";
                            $is_registered = mysqli_num_rows(mysqli_query($conn, $check_reg)) > 0;
                        ?>
                            <div class="event-card">
                                <div class="event-image">
                                    <?php if($event['image']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                    <?php else: ?>
                                        <div class="event-placeholder">📅</div>
                                    <?php endif; ?>
                                    
                                    <?php if($is_full): ?>
                                        <span class="event-badge" style="background: #f56565; color: white;">FULL</span>
                                    <?php elseif($seats_left <= 10): ?>
                                        <span class="event-badge" style="background: #ed8936; color: white;">
                                            <?php echo $seats_left; ?> seats left
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-content">
                                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <p class="event-description">
                                        <?php echo htmlspecialchars(substr($event['description'], 0, 120)); ?>...
                                    </p>
                                    
                                    <div class="event-meta">
                                        <span>📅 <?php echo date('D, M d, Y - h:i A', strtotime($event['event_date'])); ?></span>
                                        <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                        <span>👤 By <?php echo htmlspecialchars($event['organizer_name']); ?></span>
                                        <?php if($event['category']): ?>
                                            <span>🏷️ <?php echo htmlspecialchars($event['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="event-footer">
                                        <span class="participants">
                                            <strong><?php echo $event['registered_count']; ?></strong> / <?php echo $event['max_participants']; ?>
                                        </span>
                                        
                                        <div style="display: flex; gap: 5px;">
                                            <?php if($user_role == 'student'): ?>
                                                <?php if($is_registered): ?>
                                                    <button class="btn btn-success btn-sm" disabled>✓ Registered</button>
                                                <?php elseif($is_full): ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Full</button>
                                                <?php else: ?>
                                                    <a href="../student/register_event.php?event_id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-primary btn-sm">Register</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <a href="<?php echo $user_path; ?>/view_event.php?id=<?php echo $event['id']; ?>" 
                                               class="btn btn-success btn-sm">👁️ View</a>
                                        </div>
                                    </div>
                                    
                                    <div class="social-actions">
                                        <button class="social-btn <?php echo $event['is_liked'] ? 'liked' : ''; ?>" 
                                                onclick="toggleLike(<?php echo $event['id']; ?>, this)">
                                            <span>❤️</span>
                                            <span id="like-count-<?php echo $event['id']; ?>"><?php echo $event['total_likes']; ?></span>
                                        </button>
                                     <?php if($user_role == 'student'): ?>   
                                        <button class="social-btn <?php echo $event['is_saved'] ? 'saved' : ''; ?>" 
                                                onclick="toggleSave(<?php echo $event['id']; ?>, this)">
                                            <span>🔖</span>
                                            <span><?php echo $event['is_saved'] ? 'Saved' : 'Save'; ?></span>
                                        </button>
                                    <?php endif; ?>    
                                        <button class="social-btn" onclick="shareEvent(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">
                                            <span>📤</span>
                                            <span>Share</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                        <p style="color: #718096;">No upcoming events at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- THIS WEEK Events Section -->
            <div class="events-section">
                <div class="section-header">
                    <h2>⚡ This Week</h2>
                    <a href="<?php echo $user_path; ?>/browse_events.php?filter=this_week">View All →</a>
                </div>
                
                <?php if($this_week_result && mysqli_num_rows($this_week_result) > 0): ?>
                    <div class="events-grid">
                        <?php while($event = mysqli_fetch_assoc($this_week_result)): 
                            $seats_left = $event['max_participants'] - $event['registered_count'];
                            $is_full = $seats_left <= 0;
                            $check_reg = "SELECT * FROM registrations WHERE event_id={$event['id']} AND user_id=$user_id";
                            $is_registered = mysqli_num_rows(mysqli_query($conn, $check_reg)) > 0;
                        ?>
                            <div class="event-card">
                                <div class="event-image">
                                    <?php if($event['image']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>" alt="Event">
                                    <?php else: ?>
                                        <div class="event-placeholder">📅</div>
                                    <?php endif; ?>
                                    <span class="event-badge" style="background: #48bb78; color: white;">This Week</span>
                                </div>
                                
                                <div class="event-content">
                                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                                    <p class="event-description">
                                        <?php echo htmlspecialchars(substr($event['description'], 0, 120)); ?>...
                                    </p>
                                    
                                    <div class="event-meta">
                                        <span>📅 <?php echo date('D, M d - h:i A', strtotime($event['event_date'])); ?></span>
                                        <span>📍 <?php echo htmlspecialchars($event['venue']); ?></span>
                                    </div>
                                    
                                    <div class="event-footer">
                                        <span class="participants">
                                            <strong><?php echo $event['registered_count']; ?></strong> / <?php echo $event['max_participants']; ?>
                                        </span>
                                        
                                        <div style="display: flex; gap: 5px;">
                                            <?php if($user_role == 'student'): ?>
                                                <?php if($is_registered): ?>
                                                    <button class="btn btn-success btn-sm" disabled>✓ Registered</button>
                                                <?php elseif($is_full): ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Full</button>
                                                <?php else: ?>
                                                    <a href="../student/register_event.php?event_id=<?php echo $event['id']; ?>" 
                                                       class="btn btn-primary btn-sm">Register</a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <a href="<?php echo $user_path; ?>/view_event.php?id=<?php echo $event['id']; ?>" 
                                               class="btn btn-success btn-sm">👁️ View</a>
                                        </div>
                                    </div>
                                    
                                    <div class="social-actions">
                                        <button class="social-btn <?php echo $event['is_liked'] ? 'liked' : ''; ?>" 
                                                onclick="toggleLike(<?php echo $event['id']; ?>, this)">
                                            <span>❤️</span>
                                            <span id="like-count-week-<?php echo $event['id']; ?>"><?php echo $event['total_likes']; ?></span>
                                        </button>
                                    <?php if($user_role == 'student'): ?>  
                                        <button class="social-btn <?php echo $event['is_saved'] ? 'saved' : ''; ?>" 
                                                onclick="toggleSave(<?php echo $event['id']; ?>, this)">
                                            <span>🔖</span>
                                            <span><?php echo $event['is_saved'] ? 'Saved' : 'Save'; ?></span>
                                        </button>
                                    <?php endif; ?>   
                                        <button class="social-btn" onclick="shareEvent(<?php echo $event['id']; ?>, '<?php echo addslashes($event['title']); ?>')">
                                            <span>📤</span>
                                            <span>Share</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; background: white; border-radius: 10px;">
                        <p style="color: #718096;">No events scheduled for this week.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- MERCHANDISE Section -->
            <?php if($merch_result && mysqli_num_rows($merch_result) > 0): ?>
            <div class="events-section">
                <div class="section-header">
                    <h2>🛍️ Campus Merchandise</h2>
                    <a href="<?php echo $user_path; ?>/browse_merchandise.php">View All →</a>
                    
                </div>
                
                <div class="events-grid">
                    <?php while($merch = mysqli_fetch_assoc($merch_result)): ?>
                        <div class="merch-card" onclick="window.location.href='<?php echo $user_path; ?>/view_merchandise.php?id=<?php echo $merch['id']; ?>'">
                            <div class="merch-image">
                                <?php if($merch['primary_image']): ?>
                                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['primary_image']); ?>" alt="Merchandise">
                                <?php else: ?>
                                    <div class="merch-placeholder">🛍️</div>
                                <?php endif; ?>
                                
                                <?php if($merch['quantity_available'] <= 5 && $merch['quantity_available'] > 0): ?>
                                    <span class="event-badge" style="background: #ed8936; color: white;">
                                        Only <?php echo $merch['quantity_available']; ?> left!
                                    </span>
                                <?php elseif($merch['quantity_available'] == 0): ?>
                                    <span class="event-badge" style="background: #f56565; color: white;">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="merch-content">
                                <h3><?php echo htmlspecialchars($merch['name']); ?></h3>
                                <div class="merch-price">₹<?php echo number_format($merch['price'], 2); ?></div>
                                
                                <div class="merch-meta">
                                    <span>🏷️ <?php echo ucwords(str_replace('-', ' ', $merch['category'])); ?></span>
                                    <?php if($merch['sizes_available']): ?>
                                        <span>📏 Sizes: <?php echo htmlspecialchars($merch['sizes_available']); ?></span>
                                    <?php endif; ?>
                                    <span>📦 <?php echo $merch['quantity_available']; ?> available</span>
                                    <?php if($merch['organizer_name']): ?>
                                        <span>👤 By <?php echo htmlspecialchars($merch['organizer_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <button class="btn btn-primary" style="width: 100%; text-align: center;">
                                    View Details →
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Search functionality
        function searchEvents() {
            const searchTerm = document.getElementById('heroSearchInput').value.toLowerCase();
            const eventCards = document.querySelectorAll('.event-card, .merch-card');
            
            eventCards.forEach(function(card) {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('.event-description, .merch-meta');
                const descText = description ? description.textContent.toLowerCase() : '';
                
                if(title.includes(searchTerm) || descText.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Allow Enter key to search
        document.getElementById('heroSearchInput').addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                searchEvents();
            }
        });
        
        // Like/Unlike event
        function toggleLike(eventId, button) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=like&event_id=' + eventId
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'liked') {
                    button.classList.add('liked');
                    const count1 = document.getElementById('like-count-' + eventId);
                    const count2 = document.getElementById('like-count-week-' + eventId);
                    if(count1) count1.textContent = parseInt(count1.textContent) + 1;
                    if(count2) count2.textContent = parseInt(count2.textContent) + 1;
                } else {
                    button.classList.remove('liked');
                    const count1 = document.getElementById('like-count-' + eventId);
                    const count2 = document.getElementById('like-count-week-' + eventId);
                    if(count1) count1.textContent = parseInt(count1.textContent) - 1;
                    if(count2) count2.textContent = parseInt(count2.textContent) - 1;
                }
            });
        }
        
        // Save/Unsave event
        function toggleSave(eventId, button) {
            fetch('', {
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
                    button.querySelector('span:last-child').textContent = 'Save';
                }
            });
        }
        
        // Share event
        function shareEvent(eventId, title) {
            const url = window.location.origin + window.location.pathname + '?event=' + eventId;
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