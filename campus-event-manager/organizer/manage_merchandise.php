<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Handle delete merchandise
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $merch_id = intval($_GET['delete']);
    
    // Check if merchandise belongs to this organizer
    $check_query = "SELECT * FROM merchandise WHERE id = $merch_id AND organizer_id = $organizer_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if(mysqli_num_rows($check_result) > 0) {
        // Delete images from folder
        $images_query = "SELECT image_path FROM merchandise_images WHERE merchandise_id = $merch_id";
        $images_result = mysqli_query($conn, $images_query);
        while($img = mysqli_fetch_assoc($images_result)) {
            if(file_exists('../uploads/merchandise/' . $img['image_path'])) {
                unlink('../uploads/merchandise/' . $img['image_path']);
            }
        }
        
        // Delete merchandise (images will be deleted due to CASCADE)
        $delete_query = "DELETE FROM merchandise WHERE id = $merch_id";
        if(mysqli_query($conn, $delete_query)) {
            $success = "Merchandise deleted successfully!";
        } else {
            $error = "Failed to delete merchandise!";
        }
    } else {
        $error = "You don't have permission to delete this item!";
    }
}

// Handle status change
if(isset($_GET['status_change']) && is_numeric($_GET['status_change']) && isset($_GET['new_status'])) {
    $merch_id = intval($_GET['status_change']);
    $new_status = mysqli_real_escape_string($conn, $_GET['new_status']);
    
    $allowed_statuses = ['available', 'out_of_stock', 'discontinued'];
    if(in_array($new_status, $allowed_statuses)) {
        $update_query = "UPDATE merchandise SET status = '$new_status' WHERE id = $merch_id AND organizer_id = $organizer_id";
        if(mysqli_query($conn, $update_query)) {
            $success = "Status updated successfully!";
        } else {
            $error = "Failed to update status!";
        }
    }
}

// Fetch all merchandise by this organizer
$merch_query = "SELECT m.*, 
                (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image,
                (SELECT COUNT(*) FROM merchandise_orders WHERE merchandise_id = m.id) as total_orders
                FROM merchandise m 
                WHERE m.organizer_id = $organizer_id 
                ORDER BY m.created_at DESC";
$merch_result = mysqli_query($conn, $merch_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Merchandise - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🛍️ My Merchandise</h1>
                <a href="create_merchandise.php" class="btn btn-primary">➕ Add New Product</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if(mysqli_num_rows($merch_result) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Details</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($merch = mysqli_fetch_assoc($merch_result)): ?>
                                <tr>
                                    <td>
                                        <?php if($merch['primary_image']): ?>
                                            <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['primary_image']); ?>" 
                                                 alt="Product" style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px;">
                                        <?php else: ?>
                                            <div style="width: 80px; height: 80px; background: #e2e8f0; display: flex; align-items: center; justify-content: center; border-radius: 5px; font-size: 30px;">
                                                🛍️
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color: #2d3748;"><?php echo htmlspecialchars($merch['name']); ?></strong><br>
                                        <small style="color: #718096;">
                                            <?php echo htmlspecialchars(substr($merch['description'], 0, 60)); ?>...
                                        </small>
                                    </td>
                                    <td>
                                        <span style="padding: 4px 10px; background: #bee3f8; color: #2c5282; border-radius: 12px; font-size: 12px;">
                                            <?php echo ucwords(str_replace('-', ' ', $merch['category'])); ?>
                                        </span>
                                    </td>
                                    <td><strong>₹<?php echo number_format($merch['price'], 2); ?></strong></td>
                                    <td>
                                        <strong style="color: <?php echo $merch['quantity_available'] > 0 ? '#48bb78' : '#f56565'; ?>">
                                            <?php echo $merch['quantity_available']; ?>
                                        </strong>
                                    </td>
                                    <td><?php echo $merch['total_orders']; ?></td>
                                    <td>
                                        <select onchange="changeStatus(<?php echo $merch['id']; ?>, this.value)" 
                                                style="padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 5px; font-size: 13px;">
                                            <option value="available" <?php echo $merch['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                            <option value="out_of_stock" <?php echo $merch['status'] == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                            <option value="discontinued" <?php echo $merch['status'] == 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_merchandise.php?id=<?php echo $merch['id']; ?>" 
                                               class="btn btn-sm btn-secondary" title="Edit">✏️ Edit</a>
                                            <a href="view_merchandise.php?id=<?php echo $merch['id'];?>" 
                                               class="btn btn-sm btn-success" >View</a>
                                            <a href="view_orders.php?merch_id=<?php echo $merch['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="View Orders">📦 Orders</a>
                                            <a href="?delete=<?php echo $merch['id']; ?>" 
                                               class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this product?')" 
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
                    <h2 style="color: #718096; margin-bottom: 10px;">📭 No Products Yet</h2>
                    <p style="color: #a0aec0; margin-bottom: 20px;">Start selling merchandise to your students!</p>
                    <a href="create_merchandise.php" class="btn btn-primary">➕ Add Your First Product</a>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function changeStatus(merchId, newStatus) {
            if(confirm('Are you sure you want to change the status?')) {
                window.location.href = '?status_change=' + merchId + '&new_status=' + newStatus;
            }
        }
    </script>
</body>
</html>