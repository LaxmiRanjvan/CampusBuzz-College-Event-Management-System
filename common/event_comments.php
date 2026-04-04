<?php
// event_comments.php - Place this in /common folder
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to comment']);
    exit();
}

$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['role'];

// Handle AJAX requests
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    // ADD COMMENT
    if($action === 'add_comment') {
        $event_id = intval($_POST['event_id']);
        $comment_text = trim($_POST['comment_text']);
        $parent_comment_id = isset($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : NULL;
        
        // Validate
        if(empty($comment_text)) {
            echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
            exit();
        }
        
        if(strlen($comment_text) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Comment too long (max 1000 characters)']);
            exit();
        }
        
        // Check if event exists
        $check_event = "SELECT id FROM events WHERE id = $event_id";
        if(mysqli_num_rows(mysqli_query($conn, $check_event)) == 0) {
            echo json_encode(['success' => false, 'message' => 'Event not found']);
            exit();
        }
        
        // Insert comment
        $comment_text_safe = mysqli_real_escape_string($conn, $comment_text);
        
        if($parent_comment_id) {
            $sql = "INSERT INTO event_comments (event_id, user_id, parent_comment_id, comment_text) 
                    VALUES ($event_id, $user_id, $parent_comment_id, '$comment_text_safe')";
        } else {
            $sql = "INSERT INTO event_comments (event_id, user_id, comment_text) 
                    VALUES ($event_id, $user_id, '$comment_text_safe')";
        }
        
        if(mysqli_query($conn, $sql)) {
            $comment_id = mysqli_insert_id($conn);
            
            // Fetch the newly created comment with user details
            $fetch_sql = "SELECT c.*, u.full_name, u.role 
                         FROM event_comments c
                         JOIN users u ON c.user_id = u.id
                         WHERE c.id = $comment_id";
            $result = mysqli_query($conn, $fetch_sql);
            $comment = mysqli_fetch_assoc($result);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Comment added successfully',
                'comment' => $comment
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add comment']);
        }
    }
    
    // EDIT COMMENT
    elseif($action === 'edit_comment') {
        $comment_id = intval($_POST['comment_id']);
        $comment_text = trim($_POST['comment_text']);
        
        if(empty($comment_text)) {
            echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']);
            exit();
        }
        
        // Check if user owns the comment
        $check = "SELECT user_id FROM event_comments WHERE id = $comment_id";
        $result = mysqli_query($conn, $check);
        $comment = mysqli_fetch_assoc($result);
        
        if(!$comment || $comment['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        $comment_text_safe = mysqli_real_escape_string($conn, $comment_text);
        $sql = "UPDATE event_comments 
                SET comment_text = '$comment_text_safe', is_edited = TRUE 
                WHERE id = $comment_id";
        
        if(mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Comment updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update']);
        }
    }
    
    // DELETE COMMENT
    elseif($action === 'delete_comment') {
        $comment_id = intval($_POST['comment_id']);
        
        // Check if user owns the comment or is admin
        $check = "SELECT user_id FROM event_comments WHERE id = $comment_id";
        $result = mysqli_query($conn, $check);
        $comment = mysqli_fetch_assoc($result);
        
        if(!$comment || ($comment['user_id'] != $user_id && $user_role != 'admin')) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        // Delete comment (this will also delete replies due to CASCADE)
        $sql = "DELETE FROM event_comments WHERE id = $comment_id";
        
        if(mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => 'Comment deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete']);
        }
    }
    
    // FETCH COMMENTS
    elseif($action === 'fetch_comments') {
        $event_id = intval($_POST['event_id']);
        
        // Fetch all comments with user details
        $sql = "SELECT c.*, u.full_name, u.role 
                FROM event_comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.event_id = $event_id
                ORDER BY c.created_at DESC";
        
        $result = mysqli_query($conn, $sql);
        $comments = [];
        
        while($row = mysqli_fetch_assoc($result)) {
            $comments[] = $row;
        }
        
        echo json_encode(['success' => true, 'comments' => $comments]);
    }
    
    exit();
}

// Function to display comments (for including in event pages)
function displayComments($conn, $event_id, $current_user_id, $current_user_role) {
    // Fetch parent comments (not replies)
    $sql = "SELECT c.*, u.full_name, u.role 
            FROM event_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.event_id = $event_id AND c.parent_comment_id IS NULL
            ORDER BY c.created_at DESC";
    
    $result = mysqli_query($conn, $sql);
    $comments = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $row['replies'] = getReplies($conn, $row['id'], $current_user_id, $current_user_role);
        $comments[] = $row;
    }
    
    return $comments;
}

// Function to get replies to a comment
function getReplies($conn, $parent_id, $current_user_id, $current_user_role) {
    $sql = "SELECT c.*, u.full_name, u.role 
            FROM event_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.parent_comment_id = $parent_id
            ORDER BY c.created_at ASC";
    
    $result = mysqli_query($conn, $sql);
    $replies = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $replies[] = $row;
    }
    
    return $replies;
}

// Helper function to get comment count
function getCommentCount($conn, $event_id) {
    $sql = "SELECT COUNT(*) as count FROM event_comments WHERE event_id = $event_id";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}
?>