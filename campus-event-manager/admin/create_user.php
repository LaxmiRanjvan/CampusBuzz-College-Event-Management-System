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

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
    $department = mysqli_real_escape_string($conn, trim($_POST['department']));
    $year = mysqli_real_escape_string($conn, trim($_POST['year']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    
    // Validation
    if(empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role) || empty($department)) {
        $error = "Please fill all required fields!";
    } elseif($role == 'student' && empty($year)) {
        $error = "Year is required for students!";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        // Check if user exists
        $check = "SELECT * FROM users WHERE username='$username' OR email='$email'";
        $result = mysqli_query($conn, $check);
        
        if(mysqli_num_rows($result) > 0) {
            $error = "Username or Email already exists!";
        } else {
            // Hash password
            $hashed_password = md5($password);
            $created_by = $_SESSION['user_id'];
            
            $sql = "INSERT INTO users (username, email, password, role, full_name, department, year, phone, created_by) 
                    VALUES ('$username', '$email', '$hashed_password', '$role', '$full_name', '$department', '$year', '$phone', $created_by)";
            
            if(mysqli_query($conn, $sql)) {
                $success = "User created successfully!";
                
                // Store credentials for email option
                $_SESSION['new_user_credentials'] = [
                    'username' => $username,
                    'password' => $password,
                    'email' => $email,
                    'role' => $role,
                    'full_name' => $full_name
                ];
            } else {
                $error = "Error: " . mysqli_error($conn);
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
    <title>Create User - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>➕ Create New User</h1>
                <a href="manage_users.php" class="btn btn-secondary">← Back to Users</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <div style="margin-top: 15px; display: flex; gap: 10px;">
                        <a href="send_credentials.php" class="btn btn-success btn-sm">📧 Send Credentials via Email</a>
                        <a href="create_user.php" class="btn btn-primary btn-sm">➕ Create Another User</a>
                        <a href="manage_users.php" class="btn btn-secondary btn-sm">👥 View All Users</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="">
                    <!-- Role Selection -->
                    <div class="form-group">
                        <label>User Type *</label>
                        <select name="role" id="roleSelect" required onchange="toggleYearField()">
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="organizer">Organizer</option>
                        </select>
                    </div>
                    
                    <!-- Basic Information -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" placeholder="Enter full name" required
                                   value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" placeholder="Choose username" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" placeholder="email@example.com" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" placeholder="+1234567890"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="password" placeholder="Minimum 6 characters" required>
                        <small style="color: #718096;">User can change this later</small>
                    </div>
                    
                    <!-- Department and Year -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Department *</label>
                            <select name="department" required>
                                <option value="">Select Department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Information Technology">Information Technology</option>
                                <option value="Electronics">Electronics</option>
                                <option value="Mechanical">Mechanical</option>
                                <option value="Civil">Civil</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Business Administration">Business Administration</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="yearField" style="display: none;">
                            <label>Year * <span style="color: #f56565;">(Required for Students)</span></label>
                            <select name="year">
                                <option value="">Select Year</option>
                                <option value="First Year">First Year</option>
                                <option value="Second Year">Second Year</option>
                                <option value="Third Year">Third Year</option>
                                <option value="Fourth Year">Fourth Year</option>
                                <option value="Graduate">Graduate</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="create_user" class="btn btn-primary">
                            ✓ Create User
                        </button>
                        <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        // Show/hide year field based on role
        function toggleYearField() {
            const role = document.getElementById('roleSelect').value;
            const yearField = document.getElementById('yearField');
            
            if(role === 'student') {
                yearField.style.display = 'block';
                yearField.querySelector('select').required = true;
            } else {
                yearField.style.display = 'none';
                yearField.querySelector('select').required = false;
                yearField.querySelector('select').value = '';
            }
        }
        
        // Initialize on page load
        toggleYearField();
    </script>
</body>
</html>