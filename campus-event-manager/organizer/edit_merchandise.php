<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get merchandise ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_merchandise.php");
    exit();
}

$merch_id = intval($_GET['id']);

// Fetch merchandise details
$merch_query = "SELECT * FROM merchandise WHERE id = $merch_id AND organizer_id = $organizer_id";
$merch_result = mysqli_query($conn, $merch_query);

if(mysqli_num_rows($merch_result) == 0) {
    header("Location: manage_merchandise.php");
    exit();
}

$merch = mysqli_fetch_assoc($merch_result);

// Fetch existing images
$images_query = "SELECT * FROM merchandise_images WHERE merchandise_id = $merch_id ORDER BY is_primary DESC";
$images_result = mysqli_query($conn, $images_query);

// Handle update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_merchandise'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));
    $price = floatval($_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $sizes_available = mysqli_real_escape_string($conn, trim($_POST['sizes_available']));
    $size_guide = mysqli_real_escape_string($conn, trim($_POST['size_guide']));
    $quantity_available = intval($_POST['quantity_available']);
    $contact_info = mysqli_real_escape_string($conn, trim($_POST['contact_info']));
    $order_form_link = mysqli_real_escape_string($conn, trim($_POST['order_form_link']));
    $return_policy = mysqli_real_escape_string($conn, trim($_POST['return_policy']));
    $distribution_date = mysqli_real_escape_string($conn, $_POST['distribution_date']);
    $distribution_venue = mysqli_real_escape_string($conn, trim($_POST['distribution_venue']));
    $distribution_time = mysqli_real_escape_string($conn, $_POST['distribution_time']);
    
    // Validation
    if(empty($name) || empty($price) || empty($category) || empty($contact_info)) {
        $error = "Please fill all required fields!";
    } elseif($price <= 0) {
        $error = "Price must be greater than 0!";
    } else {
        // Update merchandise
        $sql = "UPDATE merchandise SET 
                name = '$name',
                description = '$description',
                price = $price,
                category = '$category',
                sizes_available = '$sizes_available',
                size_guide = '$size_guide',
                quantity_available = $quantity_available,
                contact_info = '$contact_info',
                order_form_link = '$order_form_link',
                return_policy = '$return_policy',
                distribution_date = ".($distribution_date ? "'$distribution_date'" : "NULL").",
                distribution_venue = '$distribution_venue',
                distribution_time = ".($distribution_time ? "'$distribution_time'" : "NULL")."
                WHERE id = $merch_id AND organizer_id = $organizer_id";
        
        if(mysqli_query($conn, $sql)) {
            // Handle new image uploads
            if(isset($_FILES['new_images']) && count($_FILES['new_images']['name']) > 0) {
                $upload_dir = '../uploads/merchandise/';
                
                for($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                    if($_FILES['new_images']['error'][$i] == 0) {
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        $file_type = $_FILES['new_images']['type'][$i];
                        
                        if(in_array($file_type, $allowed_types)) {
                            $file_extension = pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION);
                            $image_name = 'merch_' . $merch_id . '_' . time() . '_' . $i . '.' . $file_extension;
                            $upload_path = $upload_dir . $image_name;
                            
                            if(move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $upload_path)) {
                                $image_sql = "INSERT INTO merchandise_images (merchandise_id, image_path, is_primary) 
                                             VALUES ($merch_id, '$image_name', 0)";
                                mysqli_query($conn, $image_sql);
                            }
                        }
                    }
                }
            }
            
            $success = "Merchandise updated successfully!";
            
            // Refresh data
            $merch_result = mysqli_query($conn, "SELECT * FROM merchandise WHERE id = $merch_id");
            $merch = mysqli_fetch_assoc($merch_result);
            $images_result = mysqli_query($conn, "SELECT * FROM merchandise_images WHERE merchandise_id = $merch_id ORDER BY is_primary DESC");
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle image deletion
if(isset($_GET['delete_image']) && is_numeric($_GET['delete_image'])) {
    $img_id = intval($_GET['delete_image']);
    
    // Get image path
    $img_query = "SELECT image_path FROM merchandise_images WHERE id = $img_id AND merchandise_id = $merch_id";
    $img_result = mysqli_query($conn, $img_query);
    
    if(mysqli_num_rows($img_result) > 0) {
        $img = mysqli_fetch_assoc($img_result);
        
        // Delete file
        if(file_exists('../uploads/merchandise/' . $img['image_path'])) {
            unlink('../uploads/merchandise/' . $img['image_path']);
        }
        
        // Delete from database
        mysqli_query($conn, "DELETE FROM merchandise_images WHERE id = $img_id");
        $success = "Image deleted successfully!";
        
        // Refresh images
        $images_result = mysqli_query($conn, "SELECT * FROM merchandise_images WHERE merchandise_id = $merch_id ORDER BY is_primary DESC");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Merchandise - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>✏️ Edit Merchandise</h1>
                <a href="manage_merchandise.php" class="btn btn-secondary">← Back to Merchandise</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                
                <!-- Existing Images -->
                <?php if(mysqli_num_rows($images_result) > 0): ?>
                    <div style="margin-bottom: 30px;">
                        <h3 style="margin-bottom: 15px; color: #2d3748;">Current Images</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                            <?php while($img = mysqli_fetch_assoc($images_result)): ?>
                                <div style="position: relative; border-radius: 8px; overflow: hidden; border: 2px solid #e2e8f0;">
                                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($img['image_path']); ?>" 
                                         style="width: 100%; height: 150px; object-fit: cover;">
                                    <?php if($img['is_primary']): ?>
                                        <div style="position: absolute; top: 5px; left: 5px; background: #667eea; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">
                                            Primary
                                        </div>
                                    <?php endif; ?>
                                    <a href="?id=<?php echo $merch_id; ?>&delete_image=<?php echo $img['id']; ?>" 
                                       style="position: absolute; top: 5px; right: 5px; background: #f56565; color: white; width: 25px; height: 25px; display: flex; align-items: center; justify-content: center; border-radius: 50%; text-decoration: none; font-size: 14px;"
                                       onclick="return confirm('Delete this image?')">×</a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <!-- Basic Information -->
                    <h3 style="margin-bottom: 20px; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📝 Basic Information</h3>
                    
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($merch['name']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="4" required><?php echo htmlspecialchars($merch['description']); ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Price (₹) *</label>
                            <input type="number" name="price" step="0.01" min="0" required value="<?php echo $merch['price']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="t-shirt" <?php echo $merch['category'] == 't-shirt' ? 'selected' : ''; ?>>T-Shirt</option>
                                <option value="oversized-tshirt" <?php echo $merch['category'] == 'oversized-tshirt' ? 'selected' : ''; ?>>Oversized T-Shirt</option>
                                <option value="hoodie" <?php echo $merch['category'] == 'hoodie' ? 'selected' : ''; ?>>Hoodie</option>
                                <option value="cap" <?php echo $merch['category'] == 'cap' ? 'selected' : ''; ?>>Cap</option>
                                <option value="tote-bag" <?php echo $merch['category'] == 'tote-bag' ? 'selected' : ''; ?>>Tote Bag</option>
                                <option value="cup" <?php echo $merch['category'] == 'cup' ? 'selected' : ''; ?>>Cup/Mug</option>
                                <option value="sweatshirt" <?php echo $merch['category'] == 'sweatshirt' ? 'selected' : ''; ?>>Sweatshirt</option>
                                <option value="mask" <?php echo $merch['category'] == 'mask' ? 'selected' : ''; ?>>Mask</option>
                                <option value="diary" <?php echo $merch['category'] == 'diary' ? 'selected' : ''; ?>>Diary/Notebook</option>
                                <option value="magazine" <?php echo $merch['category'] == 'magazine' ? 'selected' : ''; ?>>Magazine</option>
                                <option value="other" <?php echo $merch['category'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity Available *</label>
                            <input type="number" name="quantity_available" min="0" required value="<?php echo $merch['quantity_available']; ?>">
                        </div>
                    </div>
                    
                    <!-- Size Information -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📏 Size Information</h3>
                    
                    <div class="form-group">
                        <label>Sizes Available</label>
                        <input type="text" name="sizes_available" value="<?php echo htmlspecialchars($merch['sizes_available']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Size Guide</label>
                        <textarea name="size_guide" rows="3"><?php echo htmlspecialchars($merch['size_guide']); ?></textarea>
                    </div>
                    
                    <!-- Add New Images -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📸 Add More Images</h3>
                    
                    <div class="form-group">
                        <label>Upload Additional Images</label>
                        <input type="file" name="new_images[]" multiple accept="image/*">
                    </div>
                    
                    <!-- Contact & Order Information -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📞 Contact & Order Information</h3>
                    
                    <div class="form-group">
                        <label>Contact Information *</label>
                        <input type="text" name="contact_info" required value="<?php echo htmlspecialchars($merch['contact_info']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Order Form Link *</label>
                        <input type="url" name="order_form_link" required value="<?php echo htmlspecialchars($merch['order_form_link']); ?>">
                    </div>
                    
                    <!-- Distribution Details -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">🚚 Distribution Details</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Distribution Date</label>
                            <input type="date" name="distribution_date" value="<?php echo $merch['distribution_date']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Distribution Time</label>
                            <input type="time" name="distribution_time" value="<?php echo $merch['distribution_time']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Distribution Venue</label>
                            <input type="text" name="distribution_venue" value="<?php echo htmlspecialchars($merch['distribution_venue']); ?>">
                        </div>
                    </div>
                    
                    <!-- Return Policy -->
                    <div class="form-group">
                        <label>Return Policy</label>
                        <textarea name="return_policy" rows="3"><?php echo htmlspecialchars($merch['return_policy']); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="update_merchandise" class="btn btn-primary">
                            ✓ Update Merchandise
                        </button>
                        <a href="manage_merchandise.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>