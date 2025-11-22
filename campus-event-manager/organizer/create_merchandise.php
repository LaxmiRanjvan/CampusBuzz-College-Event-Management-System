<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$error = "";
$success = "";

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_merchandise'])) {
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
    $organizer_id = $_SESSION['user_id'];
    
    // Validation
    if(empty($name) || empty($price) || empty($category) || empty($contact_info)) {
        $error = "Please fill all required fields!";
    } elseif($price <= 0) {
        $error = "Price must be greater than 0!";
    } else {
        // Insert merchandise
        $sql = "INSERT INTO merchandise (organizer_id, name, description, price, category, sizes_available, 
                size_guide, quantity_available, contact_info, order_form_link, return_policy, 
                distribution_date, distribution_venue, distribution_time, status) 
                VALUES ($organizer_id, '$name', '$description', $price, '$category', '$sizes_available', 
                '$size_guide', $quantity_available, '$contact_info', '$order_form_link', '$return_policy', 
                ".($distribution_date ? "'$distribution_date'" : "NULL").", '$distribution_venue', 
                ".($distribution_time ? "'$distribution_time'" : "NULL").", 'available')";
        
        if(mysqli_query($conn, $sql)) {
            $merchandise_id = mysqli_insert_id($conn);
            
            // Handle multiple image uploads
            if(isset($_FILES['merchandise_images']) && count($_FILES['merchandise_images']['name']) > 0) {
                $upload_dir = '../uploads/merchandise/';
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $total_images = count($_FILES['merchandise_images']['name']);
                $uploaded_count = 0;
                
                for($i = 0; $i < $total_images; $i++) {
                    if($_FILES['merchandise_images']['error'][$i] == 0) {
                        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        $file_type = $_FILES['merchandise_images']['type'][$i];
                        
                        if(in_array($file_type, $allowed_types)) {
                            $file_extension = pathinfo($_FILES['merchandise_images']['name'][$i], PATHINFO_EXTENSION);
                            $image_name = 'merch_' . $merchandise_id . '_' . time() . '_' . $i . '.' . $file_extension;
                            $upload_path = $upload_dir . $image_name;
                            
                            if(move_uploaded_file($_FILES['merchandise_images']['tmp_name'][$i], $upload_path)) {
                                $is_primary = ($i == 0) ? 1 : 0; // First image is primary
                                $image_sql = "INSERT INTO merchandise_images (merchandise_id, image_path, is_primary) 
                                             VALUES ($merchandise_id, '$image_name', $is_primary)";
                                mysqli_query($conn, $image_sql);
                                $uploaded_count++;
                            }
                        }
                    }
                }
            }
            
            $success = "Merchandise created successfully!";
            header("refresh:2;url=manage_merchandise.php");
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Merchandise - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .image-preview {
            position: relative;
            width: 100%;
            height: 150px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e2e8f0;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-preview-remove {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #f56565;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🛍️ Create New Merchandise</h1>
                <a href="manage_merchandise.php" class="btn btn-secondary">← Back to Merchandise</a>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <!-- Basic Information -->
                    <h3 style="margin-bottom: 20px; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📝 Basic Information</h3>
                    
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" name="name" placeholder="e.g., College Hoodie 2025" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" rows="4" placeholder="Describe the product..." required></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Price (₹) *</label>
                            <input type="number" name="price" step="0.01" min="0" placeholder="299.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="t-shirt">T-Shirt</option>
                                <option value="oversized-tshirt">Oversized T-Shirt</option>
                                <option value="hoodie">Hoodie</option>
                                <option value="cap">Cap</option>
                                <option value="tote-bag">Tote Bag</option>
                                <option value="cup">Cup/Mug</option>
                                <option value="sweatshirt">Sweatshirt</option>
                                <option value="mask">Mask</option>
                                <option value="diary">Diary/Notebook</option>
                                <option value="magazine">Magazine</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity Available *</label>
                            <input type="number" name="quantity_available" min="0" value="50" required>
                        </div>
                    </div>
                    
                    <!-- Size Information -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📏 Size Information (Optional)</h3>
                    
                    <div class="form-group">
                        <label>Sizes Available</label>
                        <input type="text" name="sizes_available" placeholder="e.g., S, M, L, XL, XXL">
                        <small style="color: #718096;">Separate sizes with commas</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Size Guide (Optional)</label>
                        <textarea name="size_guide" rows="3" placeholder="Size chart or measurements..."></textarea>
                    </div>
                    
                    <!-- Product Images -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📸 Product Images</h3>
                    
                    <div class="form-group">
                        <label>Upload Images (Max 5)</label>
                        <input type="file" name="merchandise_images[]" multiple accept="image/*" id="imageInput" onchange="previewImages(event)">
                        <small style="color: #718096;">First image will be the primary display image</small>
                        
                        <div id="imagePreviewContainer" class="image-preview-container"></div>
                    </div>
                    
                    <!-- Contact & Order Information -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">📞 Contact & Order Information</h3>
                    
                    <div class="form-group">
                        <label>Contact Information *</label>
                        <input type="text" name="contact_info" placeholder="Phone: +91 1234567890 or Email: contact@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Order Form Link (Google Form) *</label>
                        <input type="url" name="order_form_link" placeholder="https://forms.google.com/..." required>
                        <small style="color: #718096;">Students will use this link to place orders</small>
                    </div>
                    
                    <!-- Distribution Details -->
                    <h3 style="margin: 30px 0 20px 0; color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">🚚 Distribution Details</h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Distribution Date</label>
                            <input type="date" name="distribution_date">
                        </div>
                        
                        <div class="form-group">
                            <label>Distribution Time</label>
                            <input type="time" name="distribution_time">
                        </div>
                        
                        <div class="form-group">
                            <label>Distribution Venue</label>
                            <input type="text" name="distribution_venue" placeholder="e.g., Student Center">
                        </div>
                    </div>
                    
                    <!-- Return Policy -->
                    <div class="form-group">
                        <label>Return Policy (Optional)</label>
                        <textarea name="return_policy" rows="3" placeholder="Describe your return/exchange policy..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" name="create_merchandise" class="btn btn-primary">
                            ✓ Create Merchandise
                        </button>
                        <a href="manage_merchandise.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <script>
        let selectedFiles = [];
        
        function previewImages(event) {
            const files = Array.from(event.target.files);
            const container = document.getElementById('imagePreviewContainer');
            container.innerHTML = '';
            selectedFiles = files.slice(0, 5); // Max 5 images
            
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'image-preview';
                    div.innerHTML = `
                        <img src="${e.target.result}" alt="Preview ${index + 1}">
                        <button type="button" class="image-preview-remove" onclick="removeImage(${index})">×</button>
                        ${index === 0 ? '<div style="position: absolute; bottom: 5px; left: 5px; background: #667eea; color: white; padding: 3px 8px; border-radius: 4px; font-size: 11px;">Primary</div>' : ''}
                    `;
                    container.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }
        
        function removeImage(index) {
            selectedFiles.splice(index, 1);
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            document.getElementById('imageInput').files = dataTransfer.files;
            
            // Re-trigger preview
            const event = { target: { files: dataTransfer.files } };
            previewImages(event);
        }
    </script>
</body>
</html>