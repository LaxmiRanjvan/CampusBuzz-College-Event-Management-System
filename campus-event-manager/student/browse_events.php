<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle AJAX actions (like, save)
if(isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $event_id = intval($_POST['event_id']);
    
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

// Get filter parameters
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$time_filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

// Build WHERE clause
$where_clauses = ["e.status = 'upcoming'", "e.event_date > NOW()"];

if($category_filter && $category_filter != 'all') {
    $where_clauses[] = "e.category = '$category_filter'";
}

if($type_filter == 'online') {
    $where_clauses[] = "e.event_type = 'online'";
} elseif($type_filter == 'offline') {
    $where_clauses[] = "e.event_type = 'offline'";
}

if($time_filter == 'this_week') {
    $where_clauses[] = "e.event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
} elseif($time_filter == 'this_month') {
    $where_clauses[] = "e.event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
}

if($search) {
    $where_clauses[] = "(e.title LIKE '%$search%' OR e.description LIKE '%$search%' OR u.full_name LIKE '%$search%')";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch events with filters
$events_sql = "SELECT e.*, u.full_name as organizer_name, 
               (SELECT COUNT(*) FROM registrations WHERE event_id = e.id AND status='registered') as registered_count,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id AND user_id = $user_id) as is_liked,
               (SELECT COUNT(*) FROM event_saves WHERE event_id = e.id AND user_id = $user_id) as is_saved,
               (SELECT COUNT(*) FROM event_likes WHERE event_id = e.id) as total_likes
               FROM events e 
               JOIN users u ON e.organizer_id = u.id 
               WHERE $where_sql
               ORDER BY e.event_date ASC";
$events_result = mysqli_query($conn, $events_sql);

// Get all unique categories for filter
$categories_sql = "SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categories_result = mysqli_query($conn, $categories_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .filter-header h2 {
            color: #2d3748;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .clear-filters {
            color: #f56565;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 16px;
            border: 1px solid #f56565;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .clear-filters:hover {
            background: #f56565;
            color: white;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #4a5568;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .filter-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-filter {
            background: #667eea;
            color: white;
        }
        
        .btn-filter:hover {
            background: #5568d3;
        }
        
        .btn-reset {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-reset:hover {
            background: #cbd5e0;
        }
        
        /* Active Filter Tags */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-tag {
            background: #e6e9fc;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tag button {
            background: none;
            border: none;
            color: #667eea;
            font-size: 16px;
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .filter-tag button:hover {
            background: #667eea;
            color: white;
        }
        
        /* Results Header */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .results-count {
            color: #718096;
            font-size: 16px;
        }
        
        .results-count strong {
            color: #2d3748;
            font-size: 20px;
        }
        
        .sort-options {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sort-options label {
            font-size: 14px;
            color: #4a5568;
            font-weight: 600;
        }
        
        .sort-options select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        /* Social Actions */
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
        }
        
        .empty-state-icon {
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
            font-size: 16px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
            }
            
            .results-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🔍 Browse Events</h1>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <h2>🎯 Filter Events</h2>
                    <?php if($category_filter || $type_filter || $time_filter || $search): ?>
                        <a href="browse_events.php" class="clear-filters">Clear All Filters</a>
                    <?php endif; ?>
                </div>
                
                <form method="GET" action="" id="filterForm">
                    <div class="filter-grid">
                        <!-- Category Filter -->
                        <div class="filter-group">
                            <label>🏷️ Category</label>
                            <select name="category" id="categoryFilter">
                                <option value="all" <?php echo $category_filter == 'all' || !$category_filter ? 'selected' : ''; ?>>
                                    All Categories
                                </option>
                                <?php while($cat = mysqli_fetch_assoc($categories_result)): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                            <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Event Type Filter -->
                        <div class="filter-group">
                            <label>📍 Event Type</label>
                            <select name="type" id="typeFilter">
                                <option value="" <?php echo !$type_filter ? 'selected' : ''; ?>>All Types</option>
                                <option value="online" <?php echo $type_filter == 'online' ? 'selected' : ''; ?>>🌐 Online</option>
                                <option value="offline" <?php echo $type_filter == 'offline' ? 'selected' : ''; ?>>🏛️ Offline</option>
                            </select>
                        </div>
                        
                        <!-- Time Filter -->
                        <div class="filter-group">
                            <label>📅 When</label>
                            <select name="filter" id="timeFilter">
                                <option value="" <?php echo !$time_filter ? 'selected' : ''; ?>>All Time</option>
                                <option value="this_week" <?php echo $time_filter == 'this_week' ? 'selected' : ''; ?>>This Week</option>
                                <option value="this_month" <?php echo $time_filter == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            </select>
                        </div>
                        
                        <!-- Search -->
                        <div class="filter-group">
                            <label>🔍 Search</label>
                            <input type="text" name="search" placeholder="Search events..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn-filter">Apply Filters</button>
                        <button type="button" class="btn-reset" onclick="resetFilters()">Reset</button>
                    </div>
                </form>
            </div>
            
            <!-- Active Filters Display -->
            <?php if($category_filter || $type_filter || $time_filter || $search): ?>
                <div class="active-filters">
                    <?php if($category_filter && $category_filter != 'all'): ?>
                        <div class="filter-tag">
                            Category: <?php echo htmlspecialchars($category_filter); ?>
                            <button onclick="removeFilter('category')">×</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($type_filter): ?>
                        <div class="filter-tag">
                            Type: <?php echo ucfirst($type_filter); ?>
                            <button onclick="removeFilter('type')">×</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($time_filter): ?>
                        <div class="filter-tag">
                            Time: <?php echo ucwords(str_replace('_', ' ', $time_filter)); ?>
                            <button onclick="removeFilter('filter')">×</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($search): ?>
                        <div class="filter-tag">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <button onclick="removeFilter('search')">×</button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Results Header -->
            <div class="results-header">
                <div class="results-count">
                    <strong><?php echo mysqli_num_rows($events_result); ?></strong> events found
                </div>
                <div class="sort-options">
                    <label>Sort by:</label>
                    <select id="sortEvents" onchange="sortEvents()">
                        <option value="date-asc">Date (Earliest First)</option>
                        <option value="date-desc">Date (Latest First)</option>
                        <option value="popular">Most Popular</option>
                        <option value="seats">Available Seats</option>
                    </select>
                </div>
            </div>
            
            <!-- Events Grid -->
            <?php if(mysqli_num_rows($events_result) > 0): ?>
                <div class="events-grid" id="eventsContainer">
                    <?php while($event = mysqli_fetch_assoc($events_result)): 
                        $seats_left = $event['max_participants'] - $event['registered_count'];
                        $is_full = $seats_left <= 0;
                        
                        // Check if already registered
                        $check_reg = "SELECT * FROM registrations WHERE event_id={$event['id']} AND user_id=$user_id";
                        $is_registered = mysqli_num_rows(mysqli_query($conn, $check_reg)) > 0;
                    ?>
                        <div class="event-card" 
                             data-date="<?php echo strtotime($event['event_date']); ?>"
                             data-likes="<?php echo $event['total_likes']; ?>"
                             data-seats="<?php echo $seats_left; ?>">
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
                                
                                <?php if($event['event_type']): ?>
                                    <span class="event-badge" style="position: absolute; top: 10px; left: 10px; background: <?php echo $event['event_type'] == 'online' ? '#4299e1' : '#48bb78'; ?>; color: white;">
                                        <?php echo $event['event_type'] == 'online' ? '🌐 Online' : '🏛️ Offline'; ?>
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
                                    
                                    <?php if($is_registered): ?>
                                        <button class="btn btn-success btn-sm" disabled>✓ Registered</button>
                                    <?php elseif($is_full): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Full</button>
                                    <?php else: ?>
                                        <a href="register_event.php?event_id=<?php echo $event['id']; ?>" 
                                           class="btn btn-primary btn-sm">Register Now</a>
                                    <?php endif; ?>
                                    <a href="view_event.php?id=<?php echo $event['id']; ?>" 
                                    class="btn btn-success btn-sm">👁️ View</a>
                                    
                                </div>
                                
                                <!-- Social Actions -->
                                <div class="social-actions">
                                    <button class="social-btn <?php echo $event['is_liked'] ? 'liked' : ''; ?>" 
                                            onclick="toggleLike(<?php echo $event['id']; ?>, this)">
                                        <span>❤️</span>
                                        <span id="like-count-<?php echo $event['id']; ?>"><?php echo $event['total_likes']; ?></span>
                                    </button>
                                    
                                    <button class="social-btn <?php echo $event['is_saved'] ? 'saved' : ''; ?>" 
                                            onclick="toggleSave(<?php echo $event['id']; ?>, this)">
                                        <span>🔖</span>
                                        <span><?php echo $event['is_saved'] ? 'Saved' : 'Save'; ?></span>
                                    </button>
                                    
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
                <div class="empty-state">
                    <div class="empty-state-icon">🔍</div>
                    <h3>No Events Found</h3>
                    <p>Try adjusting your filters or search criteria</p>
                    <a href="browse_events.php" class="btn btn-primary" style="margin-top: 20px;">View All Events</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="../assets/js/script.js"></script>
    <script>
        // Reset filters
        function resetFilters() {
            window.location.href = 'browse_events.php';
        }
        
        // Remove specific filter
        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            window.location.href = url.toString();
        }
        
        // Sort events
        function sortEvents() {
            const sortBy = document.getElementById('sortEvents').value;
            const container = document.getElementById('eventsContainer');
            const cards = Array.from(container.querySelectorAll('.event-card'));
            
            cards.sort((a, b) => {
                switch(sortBy) {
                    case 'date-asc':
                        return parseInt(a.dataset.date) - parseInt(b.dataset.date);
                    case 'date-desc':
                        return parseInt(b.dataset.date) - parseInt(a.dataset.date);
                    case 'popular':
                        return parseInt(b.dataset.likes) - parseInt(a.dataset.likes);
                    case 'seats':
                        return parseInt(b.dataset.seats) - parseInt(a.dataset.seats);
                    default:
                        return 0;
                }
            });
            
            // Re-append sorted cards
            cards.forEach(card => container.appendChild(card));
        }
        
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
                    const count = document.getElementById('like-count-' + eventId);
                    count.textContent = parseInt(count.textContent) + 1;
                    // Update data attribute for sorting
                    button.closest('.event-card').dataset.likes = parseInt(count.textContent);
                } else {
                    button.classList.remove('liked');
                    const count = document.getElementById('like-count-' + eventId);
                    count.textContent = parseInt(count.textContent) - 1;
                    button.closest('.event-card').dataset.likes = parseInt(count.textContent);
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
            const url = window.location.origin + '/campus-event-manager/student/browse_events.php?event=' + eventId;
            if(navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Check out this event: ' + title,
                    url: url
                });
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(url);
                alert('Event link copied to clipboard!');
            }
        }
    </script>
</body>
</html>