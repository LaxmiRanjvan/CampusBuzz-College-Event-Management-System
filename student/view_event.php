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
$user_role = $_SESSION['role'];

// Include the comment functions
require_once '../common/event_comments.php';

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
$check_reg = "SELECT * FROM registrations WHERE event_id = $event_id AND user_id = $user_id AND status='registered';";
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
            
            <!-- Comments Section -->
            <style>
                /* Comments Section Styling */
                .comments-section {
                    background: white;
                    border-radius: 12px;
                    padding: 30px;
                    margin-top: 30px;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
                }

                .comments-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 25px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #e2e8f0;
                }

                .comments-header h3 {
                    font-size: 24px;
                    color: #2d3748;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }

                .comment-count {
                    background: #667eea;
                    color: white;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 600;
                }

                /* Comment Form */
                .comment-form {
                    margin-bottom: 30px;
                }

                .comment-input-wrapper {
                    display: flex;
                    gap: 10px;
                    align-items: flex-start;
                }

                .user-avatar {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 600;
                    font-size: 16px;
                    flex-shrink: 0;
                }

                .comment-input-container {
                    flex: 1;
                }

                .comment-textarea {
                    width: 100%;
                    padding: 12px 16px;
                    border: 2px solid #e2e8f0;
                    border-radius: 10px;
                    font-size: 14px;
                    font-family: inherit;
                    resize: vertical;
                    min-height: 80px;
                    transition: all 0.3s;
                }

                .comment-textarea:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }

                .comment-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    margin-top: 10px;
                }

                .btn-comment {
                    padding: 8px 20px;
                    border: none;
                    border-radius: 6px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.3s;
                }

                .btn-comment-primary {
                    background: #667eea;
                    color: white;
                }

                .btn-comment-primary:hover {
                    background: #5568d3;
                    transform: translateY(-2px);
                }

                .btn-comment-secondary {
                    background: #e2e8f0;
                    color: #4a5568;
                }

                .btn-comment-secondary:hover {
                    background: #cbd5e0;
                }

                /* Comments List */
                .comments-list {
                    display: flex;
                    flex-direction: column;
                    gap: 20px;
                }

                .comment-item {
                    display: flex;
                    gap: 12px;
                    padding: 15px;
                    border-radius: 10px;
                    background: #f7fafc;
                    transition: all 0.3s;
                }

                .comment-item:hover {
                    background: #edf2f7;
                }

                .comment-content {
                    flex: 1;
                }

                .comment-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 8px;
                }

                .comment-author {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }

                .author-name {
                    font-weight: 600;
                    color: #2d3748;
                    font-size: 15px;
                }

                .author-role {
                    font-size: 12px;
                    color: #718096;
                    background: white;
                    padding: 2px 8px;
                    border-radius: 4px;
                    display: inline-block;
                    width: fit-content;
                }

                .comment-time {
                    font-size: 12px;
                    color: #a0aec0;
                }

                .comment-text {
                    color: #4a5568;
                    line-height: 1.6;
                    margin-bottom: 10px;
                    word-wrap: break-word;
                }

                .comment-edited {
                    font-size: 11px;
                    color: #a0aec0;
                    font-style: italic;
                    margin-top: 5px;
                }

                .comment-buttons {
                    display: flex;
                    gap: 15px;
                    margin-top: 10px;
                }

                .comment-btn {
                    background: none;
                    border: none;
                    color: #718096;
                    font-size: 13px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    padding: 5px 10px;
                    border-radius: 6px;
                    transition: all 0.3s;
                    font-weight: 500;
                }

                .comment-btn:hover {
                    background: white;
                    color: #667eea;
                }

                .comment-btn.delete:hover {
                    color: #f56565;
                }

                /* Replies Section */
                .replies-section {
                    margin-left: 52px;
                    margin-top: 15px;
                    padding-left: 20px;
                    border-left: 3px solid #e2e8f0;
                }

                .reply-item {
                    background: white;
                    margin-bottom: 15px;
                    padding: 12px;
                    border-radius: 8px;
                    border: 1px solid #e2e8f0;
                }

                .reply-form {
                    margin-top: 15px;
                    margin-left: 52px;
                    background: white;
                    padding: 15px;
                    border-radius: 10px;
                    border: 2px solid #e2e8f0;
                }

                .reply-form textarea {
                    min-height: 60px;
                }

                /* Edit Mode */
                .edit-mode {
                    background: white;
                    padding: 15px;
                    border-radius: 8px;
                    border: 2px solid #667eea;
                    margin-top: 10px;
                }

                /* Empty State */
                .no-comments {
                    text-align: center;
                    padding: 60px 20px;
                    color: #718096;
                }

                .no-comments-icon {
                    font-size: 60px;
                    margin-bottom: 15px;
                    opacity: 0.5;
                }

                /* Responsive */
                @media (max-width: 768px) {
                    .comments-section {
                        padding: 20px 15px;
                    }
                    
                    .comment-item {
                        flex-direction: column;
                    }
                    
                    .replies-section {
                        margin-left: 20px;
                        padding-left: 15px;
                    }
                    
                    .reply-form {
                        margin-left: 20px;
                    }
                }
            </style>

            <div class="comments-section" id="commentsSection">
                <div class="comments-header">
                    <h3>
                        💬 Comments
                        <span class="comment-count" id="commentCount">0</span>
                    </h3>
                </div>
                
                <!-- Comment Form -->
                <div class="comment-form">
                    <div class="comment-input-wrapper">
                        <div class="user-avatar" id="userAvatar">U</div>
                        <div class="comment-input-container">
                            <textarea 
                                class="comment-textarea" 
                                id="commentInput" 
                                placeholder="Share your thoughts about this event..."
                                maxlength="1000"
                            ></textarea>
                            <div class="comment-actions">
                                <button class="btn-comment btn-comment-secondary" onclick="clearComment()">
                                    Cancel
                                </button>
                                <button class="btn-comment btn-comment-primary" onclick="submitComment()">
                                    💬 Post Comment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Comments List -->
                <div class="comments-list" id="commentsList">
                    <!-- Comments will be loaded here dynamically -->
                </div>
                
                <!-- No Comments State -->
                <div class="no-comments" id="noComments" style="display: none;">
                    <div class="no-comments-icon">💭</div>
                    <p>No comments yet. Be the first to share your thoughts!</p>
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

        // ===== COMMENT SYSTEM JAVASCRIPT =====
        const eventId = <?php echo $event_id; ?>;
        const currentUserId = <?php echo $user_id; ?>;
        const currentUserRole = '<?php echo $user_role; ?>';
        const currentUserName = '<?php echo $_SESSION['full_name'] ?? 'User'; ?>';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set user avatar initial
            const initial = currentUserName.charAt(0).toUpperCase();
            document.getElementById('userAvatar').textContent = initial;
            
            // Load comments
            loadComments();
        });

        // Submit Comment
        function submitComment(parentCommentId = null) {
            const inputId = parentCommentId ? `replyInput-${parentCommentId}` : 'commentInput';
            const textarea = document.getElementById(inputId);
            const commentText = textarea.value.trim();
            
            if(!commentText) {
                alert('Please write a comment');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('event_id', eventId);
            formData.append('comment_text', commentText);
            if(parentCommentId) {
                formData.append('parent_comment_id', parentCommentId);
            }
            
            fetch('../common/event_comments.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    textarea.value = '';
                    loadComments(); // Reload all comments
                    if(parentCommentId) {
                        document.getElementById(`replyForm-${parentCommentId}`).style.display = 'none';
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Failed to post comment');
            });
        }

        // Load Comments
        function loadComments() {
            const formData = new FormData();
            formData.append('action', 'fetch_comments');
            formData.append('event_id', eventId);
            
            fetch('../common/event_comments.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    displayComments(data.comments);
                    document.getElementById('commentCount').textContent = data.comments.length;
                }
            })
            .catch(err => {
                console.error('Error loading comments:', err);
            });
        }

        // Display Comments
        function displayComments(comments) {
            const commentsList = document.getElementById('commentsList');
            const noComments = document.getElementById('noComments');
            
            if(comments.length === 0) {
                commentsList.innerHTML = '';
                noComments.style.display = 'block';
                return;
            }
            
            noComments.style.display = 'none';
            
            // Separate parent comments and replies
            const parentComments = comments.filter(c => !c.parent_comment_id);
            const repliesMap = {};
            
            comments.forEach(c => {
                if(c.parent_comment_id) {
                    if(!repliesMap[c.parent_comment_id]) {
                        repliesMap[c.parent_comment_id] = [];
                    }
                    repliesMap[c.parent_comment_id].push(c);
                }
            });
            
            // Build HTML
            let html = '';
            parentComments.forEach(comment => {
                html += buildCommentHTML(comment, repliesMap[comment.id] || []);
            });
            
            commentsList.innerHTML = html;
        }

        // Build Comment HTML
        function buildCommentHTML(comment, replies = []) {
            const canModify = comment.user_id == currentUserId || currentUserRole === 'admin';
            const timeAgo = getTimeAgo(comment.created_at);
            const userInitial = comment.full_name.charAt(0).toUpperCase();
            
            let html = `
                <div class="comment-item" id="comment-${comment.id}">
                    <div class="user-avatar">${userInitial}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <div class="comment-author">
                                <span class="author-name">${escapeHtml(comment.full_name)}</span>
                                <span class="author-role">${comment.role}</span>
                            </div>
                            <span class="comment-time">${timeAgo}</span>
                        </div>
                        
                        <div class="comment-text" id="text-${comment.id}">
                            ${escapeHtml(comment.comment_text)}
                        </div>
                        
                        ${comment.is_edited == 1 ? '<div class="comment-edited">(edited)</div>' : ''}
                        
                        <div class="comment-buttons">
                            <button class="comment-btn" onclick="toggleReplyForm(${comment.id})">
                                \ud83d\udcac Reply
                            </button>
                            ${canModify ? `
                                <button class="comment-btn" onclick="editComment(${comment.id})">
                                    \u270f\ufe0f Edit
                                </button>
                                <button class="comment-btn delete" onclick="deleteComment(${comment.id})">
                                    \ud83d\uddd1\ufe0f Delete
                                </button>
                            ` : ''}
                        </div>
                        
                        <!-- Reply Form -->
                        <div id="replyForm-${comment.id}" class="reply-form" style="display: none;">
                            <textarea 
                                id="replyInput-${comment.id}" 
                                class="comment-textarea" 
                                placeholder="Write a reply..."
                                maxlength="1000"
                            ></textarea>
                            <div class="comment-actions">
                                <button class="btn-comment btn-comment-secondary" onclick="toggleReplyForm(${comment.id})">
                                    Cancel
                                </button>
                                <button class="btn-comment btn-comment-primary" onclick="submitComment(${comment.id})">
                                    Reply
                                </button>
                            </div>
                        </div>
                        
                        <!-- Replies -->
                        ${replies.length > 0 ? `
                            <div class="replies-section">
                                ${replies.map(reply => buildReplyHTML(reply)).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            return html;
        }

        // Build Reply HTML
        function buildReplyHTML(reply) {
            const canModify = reply.user_id == currentUserId || currentUserRole === 'admin';
            const timeAgo = getTimeAgo(reply.created_at);
            const userInitial = reply.full_name.charAt(0).toUpperCase();
            
            return `
                <div class="reply-item" id="comment-${reply.id}">
                    <div style="display: flex; gap: 10px;">
                        <div class="user-avatar" style="width: 32px; height: 32px; font-size: 14px;">
                            ${userInitial}
                        </div>
                        <div style="flex: 1;">
                            <div class="comment-header">
                                <div class="comment-author">
                                    <span class="author-name">${escapeHtml(reply.full_name)}</span>
                                    <span class="author-role">${reply.role}</span>
                                </div>
                                <span class="comment-time">${timeAgo}</span>
                            </div>
                            
                            <div class="comment-text" id="text-${reply.id}">
                                ${escapeHtml(reply.comment_text)}
                            </div>
                            
                            ${reply.is_edited == 1 ? '<div class="comment-edited">(edited)</div>' : ''}
                            
                            ${canModify ? `
                                <div class="comment-buttons">
                                    <button class="comment-btn" onclick="editComment(${reply.id})">
                                        \u270f\ufe0f Edit
                                    </button>
                                    <button class="comment-btn delete" onclick="deleteComment(${reply.id})">
                                        \ud83d\uddd1\ufe0f Delete
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        // Toggle Reply Form
        function toggleReplyForm(commentId) {
            const form = document.getElementById(`replyForm-${commentId}`);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Edit Comment
        function editComment(commentId) {
            const textDiv = document.getElementById(`text-${commentId}`);
            const currentText = textDiv.textContent.trim();
            
            const editHTML = `
                <div class="edit-mode">
                    <textarea id="editInput-${commentId}" class="comment-textarea">${currentText}</textarea>
                    <div class="comment-actions">
                        <button class="btn-comment btn-comment-secondary" onclick="cancelEdit(${commentId}, '${escapeHtml(currentText)}')">
                            Cancel
                        </button>
                        <button class="btn-comment btn-comment-primary" onclick="saveEdit(${commentId})">
                            Save Changes
                        </button>
                    </div>
                </div>
            `;
            
            textDiv.innerHTML = editHTML;
        }

        // Save Edit
        function saveEdit(commentId) {
            const newText = document.getElementById(`editInput-${commentId}`).value.trim();
            
            if(!newText) {
                alert('Comment cannot be empty');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'edit_comment');
            formData.append('comment_id', commentId);
            formData.append('comment_text', newText);
            
            fetch('../common/event_comments.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    loadComments();
                } else {
                    alert(data.message);
                }
            });
        }

        // Cancel Edit
        function cancelEdit(commentId, originalText) {
            const textDiv = document.getElementById(`text-${commentId}`);
            textDiv.textContent = originalText;
        }

        // Delete Comment
        function deleteComment(commentId) {
            if(!confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);
            
            fetch('../common/event_comments.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    loadComments();
                } else {
                    alert(data.message);
                }
            });
        }

        // Clear Comment
        function clearComment() {
            document.getElementById('commentInput').value = '';
        }

        // Helper: Get Time Ago
        function getTimeAgo(datetime) {
            const now = new Date();
            const past = new Date(datetime);
            const seconds = Math.floor((now - past) / 1000);
            
            if(seconds < 60) return 'Just now';
            if(seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if(seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if(seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return past.toLocaleDateString();
        }

        // Helper: Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>