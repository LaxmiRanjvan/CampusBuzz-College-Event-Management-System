<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get user ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Fetch user details
$user_query = "SELECT * FROM users WHERE id = $user_id AND role != 'admin'";
$user_result = mysqli_query($conn, $user_query);

if(mysqli_num_rows($user_result) == 0) {
    header("Location: manage_users.php");
    exit();
}

$user = mysqli_fetch_assoc($user_result);

// Get user statistics
if($user['role'] == 'student') {
    $registered_events = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations WHERE user_id = $user_id"))['count'];
} elseif($user['role'] == 'organizer') {
    $created_events = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM events WHERE organizer_id = $user_id"))['count'];
    $total_registrations = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM registrations r JOIN events e ON r.event_id = e.id WHERE e.organizer_id = $user_id"))['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .info-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .info-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>👁️ User Profile</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-primary">✏️ Edit User</a>
                    <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
                </div>
            </div>
            
            <!-- Profile Header -->
            <div class="profile-header">
                <div class ="profile-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                </div>
                <div style="flex: 1;">
<h2 style="margin-bottom: 10px; font-size: 32px;"><?php echo htmlspecialchars($user['full_name']); ?></h2>
<p style="opacity: 0.9; margin-bottom: 10px; font-size: 16px;">
@<?php echo htmlspecialchars($user['username']); ?>
</p>
<span class="role-badge" style="background: rgba(255,255,255,0.3); font-size: 14px;">
<?php echo ucfirst($user['role']); ?>
</span>
<p style="margin-top: 15px; opacity: 0.8; font-size: 14px;">
Member since: <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
</p>
</div>
</div>
             <!-- Statistics Cards -->
        <?php if($user['role'] == 'student'): ?>
            <div class="dashboard-grid" style="margin-bottom: 30px;">
                <div class="dashboard-card">
                    <div class="card-icon blue">
                        <span>🎫</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $registered_events; ?></h3>
                        <p>Events Registered</p>
                    </div>
                </div>
            </div>
        <?php elseif($user['role'] == 'organizer'): ?>
            <div class="dashboard-grid" style="margin-bottom: 30px;">
                <div class="dashboard-card">
                    <div class="card-icon blue">
                        <span>📅</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $created_events; ?></h3>
                        <p>Events Created</p>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="card-icon green">
                        <span>👥</span>
                    </div>
                    <div class="card-content">
                        <h3><?php echo $total_registrations; ?></h3>
                        <p>Total Registrations</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- User Information -->
        <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h2 style="margin-bottom: 20px; color: #2d3748;">📋 User Information</h2>
            
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">User ID</div>
                    <div class="info-value">#<?php echo $user['id']; ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : 'Not provided'; ?></div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo $user['department'] ? htmlspecialchars($user['department']) : 'Not specified'; ?></div>
                </div>
                
                <?php if($user['role'] == 'student'): ?>
                <div class="info-card">
                    <div class="info-label">Year</div>
                    <div class="info-value"><?php echo $user['year'] ? htmlspecialchars($user['year']) : 'Not specified'; ?></div>
                </div>
                <?php endif; ?>
                
                <div class="info-card">
                    <div class="info-label">Account Created</div>
                    <div class="info-value"><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
            
            <!-- Additional Profile Information (if filled by user) -->
            <?php if(!empty($user['bio']) || !empty($user['address'])): ?>
                <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e2e8f0;">
                    <h3 style="margin-bottom: 15px; color: #2d3748;">Additional Information</h3>
                    
                    <?php if(!empty($user['bio'])): ?>
                    <div style="margin-bottom: 20px;">
                        <div class="info-label">Bio</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($user['address'])): ?>
                    <div>
                        <div class="info-label">Address</div>
                        <div class="info-value"><?php echo nl2br(htmlspecialchars($user['address'])); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div style="margin-top: 30px; padding-top: 30px; border-top: 1px solid #e2e8f0; display: flex; gap: 15px;">
                <a href="edit_user.php?id=<?php echo $user_id; ?>" class="btn btn-primary">✏️ Edit User</a>
                <a href="manage_users.php?delete=<?php echo $user_id; ?>" 
                   class="btn btn-danger" 
                   onclick="return confirm('Are you sure you want to delete this user? All their data will be permanently removed!')">
                    🗑️ Delete User
                </a>
                <a href="manage_users.php" class="btn btn-secondary">← Back to All Users</a>
            </div>
        </div>
    </main>
</div>           

