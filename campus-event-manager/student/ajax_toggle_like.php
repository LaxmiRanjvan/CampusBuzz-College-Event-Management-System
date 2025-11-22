<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if($event_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid event']);
    exit();
}

// Check if already liked
$check_stmt = mysqli_prepare($conn, "SELECT id FROM event_likes WHERE event_id = ? AND user_id = ?");
mysqli_stmt_bind_param($check_stmt, "ii", $event_id, $user_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if(mysqli_num_rows($check_result) > 0) {
    // Unlike
    $delete_stmt = mysqli_prepare($conn, "DELETE FROM event_likes WHERE event_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($delete_stmt, "ii", $event_id, $user_id);
    mysqli_stmt_execute($delete_stmt);
    mysqli_stmt_close($delete_stmt);
    $liked = false;
} else {
    // Like
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO event_likes (event_id, user_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($insert_stmt, "ii", $event_id, $user_id);
    mysqli_stmt_execute($insert_stmt);
    mysqli_stmt_close($insert_stmt);
    $liked = true;
}

mysqli_stmt_close($check_stmt);

// Get updated count
$count_query = "SELECT COUNT(*) as count FROM event_likes WHERE event_id = $event_id";
$count_result = mysqli_query($conn, $count_query);
$count = mysqli_fetch_assoc($count_result)['count'];

echo json_encode([
    'success' => true,
    'liked' => $liked,
    'count' => $count
]);
?>