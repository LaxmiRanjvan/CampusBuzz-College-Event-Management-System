<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";

// Get user ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Fetch user details using prepared statement
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? AND role != 'admin'");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);

if(mysqli_num_rows($user_result) == 0) {
    mysqli_stmt_close($stmt);
    header("Location: manage_users.php");
    exit();
}

$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

// Handle update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $department = trim($_POST['department']);
    $year = trim($_POST['year']);
    $phone = trim($_POST['phone']);
    $new_password = $_POST['new_password'];
    
    // Validation
    if(empty($username) || empty($email) || empty($full_name) || empty($department)) {
        $error = "Please fill all required fields!";
    } else {
        // Check if username/email already exists (excluding current user)
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        mysqli_stmt_bind_param($check_stmt, "ssi", $username, $email, $user_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        
        if(mysqli_num_rows($check_result) > 0) {
            $error = "Username or Email already exists!";
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);
            
            // Check if password needs updating
            $update_password = false;
            $hashed_password = "";
            
            if(!empty($new_password)) {
                if(strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters!";
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password = true;
                }
            }
            
            if(empty($error)) {
                // Update user with or without password
                if($update_password) {
                    $update_stmt = mysqli_prepare($conn, 
                        "UPDATE users SET username = ?, email = ?, full_name = ?, department = ?, year = ?, phone = ?, password = ? WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($update_stmt, "sssssssi", $username, $email, $full_name, $department, $year, $phone, $hashed_password, $user_id);
                } else {
                    $update_stmt = mysqli_prepare($conn, 
                        "UPDATE users SET username = ?, email = ?, full_name = ?, department = ?, year = ?, phone = ? WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($update_stmt, "ssssssi", $username, $email, $full_name, $department, $year, $phone, $user_id);
                }
                
                if(mysqli_stmt_execute($update_stmt)) {
                    $success = "User updated successfully!";
                    
                    // Refresh user data
                    mysqli_stmt_close($update_stmt);
                    $refresh_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($refresh_stmt, "i", $user_id);
                    mysqli_stmt_execute($refresh_stmt);
                    $user_result = mysqli_stmt_get_result($refresh_stmt);
                    $user = mysqli_fetch_assoc($user_result);
                    mysqli_stmt_close($refresh_stmt);
                } else {
                    $error = "Error updating user: " . mysqli_error($conn);
                    mysqli_stmt_close($update_stmt);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>✏️ Edit User</h1>
                <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <div style="background: #f7fafc; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>Editing:</strong> <?php echo htmlspecialchars($user['full_name']); ?> 
                        <span class="role-badge role-<?php echo $user['role']; ?>" style="margin-left: 10px;">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                    <!-- Send Credentials Button -->
                    <a href="send_email.php?user_id=<?php echo $user_id; ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                        <span>📧</span> Send Credentials
                    </a>
                </div>
                
                <form method="POST" action="">
                    <!-- Basic Information -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars(isset($user['phone']) ? $user['phone'] : ''); ?>">
                        </div>
                    </div>
                    
                    <!-- Department and Year -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="department" required>
                                <option value="">Select Department</option>
                                <option value="Computer Science" <?php echo $user['department'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Information Technology" <?php echo $user['department'] == 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Electronics" <?php echo $user['department'] == 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                <option value="Mechanical" <?php echo $user['department'] == 'Mechanical' ? 'selected' : ''; ?>>Mechanical</option>
                                <option value="Civil" <?php echo $user['department'] == 'Civil' ? 'selected' : ''; ?>>Civil</option>
                                <option value="Electrical" <?php echo $user['department'] == 'Electrical' ? 'selected' : ''; ?>>Electrical</option>
                                <option value="Business Administration" <?php echo $user['department'] == 'Business Administration' ? 'selected' : ''; ?>>Business Administration</option>
                                <option value="Other" <?php echo $user['department'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <?php if($user['role'] == 'student'): ?>
                        <div class="form-group">
                            <label>Year</label>
                            <select name="year">
                                <option value="">Select Year</option>
                                <option value="First Year" <?php echo $user['year'] == 'First Year' ? 'selected' : ''; ?>>First Year</option>
                                <option value="Second Year" <?php echo $user['year'] == 'Second Year' ? 'selected' : ''; ?>>Second Year</option>
                                <option value="Third Year" <?php echo $user['year'] == 'Third Year' ? 'selected' : ''; ?>>Third Year</option>
                                <option value="Fourth Year" <?php echo $user['year'] == 'Fourth Year' ? 'selected' : ''; ?>>Fourth Year</option>
                                <option value="Graduate" <?php echo $user['year'] == 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="year" value="">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Password Change -->
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" placeholder="Enter new password (min 6 characters)">
                        <small style="color: #718096;">Only fill this if you want to change the password</small>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="update_user" class="btn btn-primary">
                            ✓ Update User
                        </button>
                        <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                        <a href="view_user.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">👁️ View Full Profile</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>