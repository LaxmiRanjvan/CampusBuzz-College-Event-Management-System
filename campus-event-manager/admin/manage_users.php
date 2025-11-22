<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = "";
$error = "";

// Handle delete user
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    
    // Prevent admin from deleting themselves
    if($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        // Check if user exists and is not admin
        $check_query = "SELECT * FROM users WHERE id = $user_id AND role != 'admin'";
        $check_result = mysqli_query($conn, $check_query);
        
        if(mysqli_num_rows($check_result) > 0) {
            $user = mysqli_fetch_assoc($check_result);
            
            // Delete user's profile image if exists
            if(!empty($user['profile_image']) && file_exists('../uploads/' . $user['profile_image'])) {
                unlink('../uploads/' . $user['profile_image']);
            }
            
            // Delete user (registrations will be deleted due to CASCADE)
            $delete_query = "DELETE FROM users WHERE id = $user_id";
            if(mysqli_query($conn, $delete_query)) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user!";
            }
        } else {
            $error = "User not found or cannot be deleted!";
        }
    }
}

// Get filter and sort parameters
$role_filter = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$department_filter = isset($_GET['department']) ? mysqli_real_escape_string($conn, $_GET['department']) : '';
$year_filter = isset($_GET['year']) ? mysqli_real_escape_string($conn, $_GET['year']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$sort_by = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Build WHERE clause
$where_conditions = ["role != 'admin'"];

if(!empty($role_filter)) {
    $where_conditions[] = "role = '$role_filter'";
}

if(!empty($department_filter)) {
    $where_conditions[] = "department = '$department_filter'";
}

if(!empty($year_filter)) {
    $where_conditions[] = "year = '$year_filter'";
}

if(!empty($search_query)) {
    $where_conditions[] = "(full_name LIKE '%$search_query%' OR username LIKE '%$search_query%' OR email LIKE '%$search_query%')";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Validate sort column
$allowed_sort = ['full_name', 'username', 'email', 'role', 'department', 'year', 'created_at'];
if(!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'created_at';
}

// Fetch users
$users_query = "SELECT * FROM users $where_clause ORDER BY $sort_by $sort_order";
$users_result = mysqli_query($conn, $users_query);
$total_users = mysqli_num_rows($users_result);

// Get unique departments for filter
$departments_query = "SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department";
$departments_result = mysqli_query($conn, $departments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .sort-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        .sort-btn {
            padding: 8px 15px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
            color: #4a5568;
        }
        .sort-btn:hover {
            background: #e2e8f0;
        }
        .sort-btn.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>👥 Manage Users</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="create_user.php" class="btn btn-primary">➕ Create User</a>
                    <a href="download_users.php?<?php echo http_build_query($_GET); ?>" class="btn btn-success">📥 Download Data</a>
                </div>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="">
                    <h3 style="margin-bottom: 15px; color: #2d3748;">🔍 Filter & Search</h3>
                    
                    <div class="filter-grid">
                        <!-- Search -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <input type="text" name="search" placeholder="Search name, username, email..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        
                        <!-- Role Filter -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="role">
                                <option value="">All Roles</option>
                                <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="organizer" <?php echo $role_filter == 'organizer' ? 'selected' : ''; ?>>Organizers</option>
                            </select>
                        </div>
                        
                        <!-- Department Filter -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="department">
                                <option value="">All Departments</option>
                                <?php while($dept = mysqli_fetch_assoc($departments_result)): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                            <?php echo $department_filter == $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Year Filter (for students) -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="year">
                                <option value="">All Years</option>
                                <option value="First Year" <?php echo $year_filter == 'First Year' ? 'selected' : ''; ?>>First Year</option>
                                <option value="Second Year" <?php echo $year_filter == 'Second Year' ? 'selected' : ''; ?>>Second Year</option>
                                <option value="Third Year" <?php echo $year_filter == 'Third Year' ? 'selected' : ''; ?>>Third Year</option>
                                <option value="Fourth Year" <?php echo $year_filter == 'Fourth Year' ? 'selected' : ''; ?>>Fourth Year</option>
                                <option value="Graduate" <?php echo $year_filter == 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="manage_users.php" class="btn btn-secondary">Clear Filters</a>
                    </div>
                    
                    <!-- Sort Options -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <h4 style="margin-bottom: 10px; color: #2d3748;">📊 Sort By:</h4>
                        <div class="sort-buttons">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'full_name', 'order' => 'asc'])); ?>" 
                               class="sort-btn <?php echo $sort_by == 'full_name' ? 'active' : ''; ?>">
                                Name ↑
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'email', 'order' => 'asc'])); ?>" 
                               class="sort-btn <?php echo $sort_by == 'email' ? 'active' : ''; ?>">
                                Email ↑
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'role', 'order' => 'asc'])); ?>" 
                               class="sort-btn <?php echo $sort_by == 'role' ? 'active' : ''; ?>">
                                Role ↑
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'department', 'order' => 'asc'])); ?>" 
                               class="sort-btn <?php echo $sort_by == 'department' ? 'active' : ''; ?>">
                                Department ↑
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => 'desc'])); ?>" 
                               class="sort-btn <?php echo $sort_by == 'created_at' && $sort_order == 'DESC' ? 'active' : ''; ?>">
                                Newest First ↓
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => 'asc'])); ?>" 
                               class="sort-btn <?php echo $sort_by == 'created_at' && $sort_order == 'ASC' ? 'active' : ''; ?>">
                                Oldest First ↑
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Results Count -->
            <div style="margin-bottom: 20px; padding: 15px; background: #e6fffa; border-left: 4px solid #38b2ac; border-radius: 5px;">
                <strong>📊 Showing <?php echo $total_users; ?> user(s)</strong>
                <?php if(!empty($search_query) || !empty($role_filter) || !empty($department_filter) || !empty($year_filter)): ?>
                    <span style="color: #718096;"> (Filtered)</span>
                <?php endif; ?>
            </div>
            
            <!-- Users Table -->
            <?php if($total_users > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Phone</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            mysqli_data_seek($users_result, 0);
                            while($user = mysqli_fetch_assoc($users_result)): 
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $user['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['year'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-secondary" title="View Details"> View</a>
                                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Edit">✏️ Edit</a>
                                            <a href="?delete=<?php echo $user['id']; ?>&<?php echo http_build_query(array_diff_key($_GET, ['delete' => ''])); ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this user? All their data will be permanently removed!')" 
                                               title="Delete">🗑️ Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px;">
                    <h2 style="color: #718096; margin-bottom: 10px;">📭 No Users Found</h2>
                    <p style="color: #a0aec0; margin-bottom: 20px;">
                        <?php if(!empty($search_query) || !empty($role_filter) || !empty($department_filter)): ?>
                            Try adjusting your filters or search criteria.
                        <?php else: ?>
                            Start by creating your first user!
                        <?php endif; ?>
                    </p>
                    <a href="create_user.php" class="btn btn-primary">➕ Create First User</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>