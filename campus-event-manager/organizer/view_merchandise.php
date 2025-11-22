<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

// Get merchandise ID
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: browse_merchandise.php");
    exit();
}

$merch_id = intval($_GET['id']);

// Fetch merchandise details
$merch_query = "SELECT m.*, u.full_name as organizer_name, u.department as organizer_dept
                FROM merchandise m
                JOIN users u ON m.organizer_id = u.id
                WHERE m.id = $merch_id";
$merch_result = mysqli_query($conn, $merch_query);

if(mysqli_num_rows($merch_result) == 0) {
    header("Location: browse_merchandise.php");
    exit();
}

$merch = mysqli_fetch_assoc($merch_result);

// Fetch all images
$images_query = "SELECT * FROM merchandise_images WHERE merchandise_id = $merch_id ORDER BY is_primary DESC";
$images_result = mysqli_query($conn, $images_query);

// Parse sizes
$sizes = !empty($merch['sizes_available']) ? explode(',', $merch['sizes_available']) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($merch['name']); ?> - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .product-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .image-gallery {
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 10px;
        }
        
        .thumbnail {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .thumbnail:hover, .thumbnail.active {
            border-color: #667eea;
        }
        
        .product-info {
            background: white;
            padding: 30px;
            border-radius: 10px;
        }
        
        .size-option {
            display: inline-block;
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            margin: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .size-option:hover, .size-option.selected {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        
        @media (max-width: 768px) {
            .product-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="content-header">
                <h1>🛍️ Product Details</h1>
                <a href="browse_merchandise.php" class="btn btn-secondary">← Back to Store</a>
            </div>
            
            <div class="product-container">
                <!-- Image Gallery -->
                <div class="image-gallery">
                    <?php 
                    $first_image = true;
                    $images_array = [];
                    mysqli_data_seek($images_result, 0);
                    while($img = mysqli_fetch_assoc($images_result)) {
                        $images_array[] = $img;
                    }
                    ?>
                    
                    <?php if(count($images_array) > 0): ?>
                        <img id="mainImage" src="../uploads/merchandise/<?php echo htmlspecialchars($images_array[0]['image_path']); ?>" 
                             class="main-image" alt="Product">
                        
                        <?php if(count($images_array) > 1): ?>
                            <div class="thumbnail-grid">
                                <?php foreach($images_array as $index => $img): ?>
                                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($img['image_path']); ?>" 
                                         class="thumbnail <?php echo $index == 0 ? 'active' : ''; ?>" 
                                         onclick="changeImage('<?php echo htmlspecialchars($img['image_path']); ?>', this)"
                                         alt="Thumbnail">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="height: 400px; display: flex; align-items: center; justify-content: center; background: #f7fafc; border-radius: 10px;">
                            <div style="font-size: 100px;">🛍️</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Information -->
                <div class="product-info">
                    <span style="display: inline-block; padding: 5px 12px; background: #bee3f8; color: #2c5282; border-radius: 12px; font-size: 13px; margin-bottom: 15px;">
                        <?php echo ucwords(str_replace('-', ' ', $merch['category'])); ?>
                    </span>
                    
                    <h2 style="margin-bottom: 15px; color: #2d3748; font-size: 28px;">
                        <?php echo htmlspecialchars($merch['name']); ?>
                    </h2>
                    
                    <div style="font-size: 32px; font-weight: 700; color: #667eea; margin-bottom: 20px;">
                        ₹<?php echo number_format($merch['price'], 2); ?>
                    </div>
                    
                    <!-- Stock Status -->
                    <div style="padding: 15px; background: <?php echo $merch['quantity_available'] > 0 ? '#c6f6d5' : '#fed7d7'; ?>; border-radius: 8px; margin-bottom: 20px;">
                        <?php if($merch['quantity_available'] > 0): ?>
                            <strong style="color: #276749;">✓ In Stock</strong>
                            <p style="margin: 5px 0 0 0; color: #276749; font-size: 14px;">
                                <?php echo $merch['quantity_available']; ?> units available
                            </p>
                        <?php else: ?>
                            <strong style="color: #c53030;">✗ Out of Stock</strong>
                            <p style="margin: 5px 0 0 0; color: #c53030; font-size: 14px;">
                                This item is currently unavailable
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                        <h3 style="margin-bottom: 10px; color: #2d3748;">Description</h3>
                        <p style="line-height: 1.8; color: #4a5568;">
                            <?php echo nl2br(htmlspecialchars($merch['description'])); ?>
                        </p>
                    </div>
                    
                    <!-- Sizes -->
                    <?php if(count($sizes) > 0): ?>
                        <div style="margin-bottom: 20px;">
                            <h3 style="margin-bottom: 10px; color: #2d3748;">Available Sizes</h3>
                            <div>
                                <?php foreach($sizes as $size): ?>
                                    <span class="size-option"><?php echo trim($size); ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if(!empty($merch['size_guide'])): ?>
                                <details style="margin-top: 15px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                                    <summary style="cursor: pointer; font-weight: 600; color: #2d3748;">
                                        📏 Size Guide
                                    </summary>
                                    <p style="margin-top: 10px; line-height: 1.6; color: #4a5568;">
                                        <?php echo nl2br(htmlspecialchars($merch['size_guide'])); ?>
                                    </p>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Distribution Details -->
                    <?php if($merch['distribution_date'] || $merch['distribution_venue']): ?>
                        <div style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 8px;">
                            <h4 style="margin-bottom: 10px; color: #856404;">🚚 Distribution Details</h4>
                            <?php if($merch['distribution_date']): ?>
                                <p style="margin: 5px 0; color: #856404;">
                                    <strong>Date:</strong> <?php echo date('M d, Y', strtotime($merch['distribution_date'])); ?>
                                    <?php if($merch['distribution_time']): ?>
                                        at <?php echo date('h:i A', strtotime($merch['distribution_time'])); ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <?php if($merch['distribution_venue']): ?>
                                <p style="margin: 5px 0; color: #856404;">
                                    <strong>Venue:</strong> <?php echo htmlspecialchars($merch['distribution_venue']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Return Policy -->
                    <?php if(!empty($merch['return_policy'])): ?>
                        <details style="margin-bottom: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                            <summary style="cursor: pointer; font-weight: 600; color: #2d3748;">
                                🔄 Return Policy
                            </summary>
                            <p style="margin-top: 10px; line-height: 1.6; color: #4a5568;">
                                <?php echo nl2br(htmlspecialchars($merch['return_policy'])); ?>
                            </p>
                        </details>
                    <?php endif; ?>
                    
                    <!-- Contact & Order -->
                    <div style="padding: 20px; background: #f7fafc; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="margin-bottom: 10px; color: #2d3748;">📞 Contact Information</h4>
                        <p style="color: #4a5568;"><?php echo htmlspecialchars($merch['contact_info']); ?></p>
                    </div>
                    
                    <!-- Order Button -->
                    <?php if($merch['quantity_available'] > 0 && !empty($merch['order_form_link'])): ?>
                        <a href="<?php echo htmlspecialchars($merch['order_form_link']); ?>" 
                           target="_blank" 
                           class="btn btn-primary" 
                           style="width: 100%; font-size: 18px; padding: 15px; text-align: center; display: block;">
                            🛒 Place Order (Google Form)
                        </a>
                    <?php else: ?>
                        <button class="btn btn-secondary" style="width: 100%; font-size: 18px; padding: 15px;" disabled>
                            Currently Unavailable
                        </button>
                    <?php endif; ?>
                    
                    <!-- Organizer Info -->
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <p style="font-size: 13px; color: #718096;">
                            Sold by: <strong><?php echo htmlspecialchars($merch['organizer_name']); ?></strong>
                            <?php if($merch['organizer_dept']): ?>
                                (<?php echo htmlspecialchars($merch['organizer_dept']); ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function changeImage(imagePath, thumbnail) {
            document.getElementById('mainImage').src = '../uploads/merchandise/' + imagePath;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            thumbnail.classList.add('active');
        }
        
        // Size selection
        document.querySelectorAll('.size-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.size-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>