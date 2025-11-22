<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is student
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? mysqli_real_escape_string($conn, $_GET['category']) : '';
$search_query = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$sort_by = isset($_GET['sort']) ? mysqli_real_escape_string($conn, $_GET['sort']) : 'newest';

// Build WHERE clause
$where_conditions = ["m.status = 'available'"];

if(!empty($category_filter)) {
    $where_conditions[] = "m.category = '$category_filter'";
}

if(!empty($search_query)) {
    $where_conditions[] = "(m.name LIKE '%$search_query%' OR m.description LIKE '%$search_query%')";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Determine ORDER BY
$order_clause = "ORDER BY m.created_at DESC";
switch($sort_by) {
    case 'price_low':
        $order_clause = "ORDER BY m.price ASC";
        break;
    case 'price_high':
        $order_clause = "ORDER BY m.price DESC";
        break;
    case 'popular':
        $order_clause = "ORDER BY order_count DESC";
        break;
    case 'newest':
    default:
        $order_clause = "ORDER BY m.created_at DESC";
        break;
}

// Fetch merchandise
$merch_query = "SELECT m.*, 
                (SELECT image_path FROM merchandise_images WHERE merchandise_id = m.id AND is_primary = 1 LIMIT 1) as primary_image,
                u.full_name as organizer_name,
                (SELECT COUNT(*) FROM merchandise_orders WHERE merchandise_id = m.id) as order_count
                FROM merchandise m
                JOIN users u ON m.organizer_id = u.id
                $where_clause
                $order_clause";
$merch_result = mysqli_query($conn, $merch_query);
$total_items = mysqli_num_rows($merch_result);

// Get categories for filter
$categories = ['t-shirt', 'oversized-tshirt', 'hoodie', 'cap', 'tote-bag', 'cup', 'sweatshirt', 'mask', 'diary', 'magazine', 'other'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Merchandise - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .merch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .merch-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }
        
        .merch-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .merch-image {
            height: 250px;
            background: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .merch-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .merch-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(102, 126, 234, 0.95);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .merch-content {
            padding: 20px;
        }
        
        .merch-category {
            display: inline-block;
            padding: 4px 10px;
            background: #bee3f8;
            color: #2c5282;
            border-radius: 12px;
            font-size: 11px;
            margin-bottom: 10px;
        }
        
        .merch-name {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .merch-price {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .merch-stock {
            font-size: 13px;
            color: #718096;
            margin-bottom: 15px;
        }
        
        .filter-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .filter-pill {
            padding: 8px 16px;
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.3s;
        }
        
        .filter-pill:hover, .filter-pill.active {
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
                <h1>🛍️ Campus Merchandise Store</h1>
                <div style="display: flex; gap: 10px;">
                    <select onchange="window.location.href='?category=<?php echo $category_filter; ?>&search=<?php echo urlencode($search_query); ?>&sort=' + this.value" 
                            style="padding: 10px; border: 2px solid #e2e8f0; border-radius: 6px;">
                        <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo $sort_by == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort_by == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                    </select>
                </div>
            </div>
            
            <!-- Search Bar -->
            <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <form method="GET" action="">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="search" placeholder="Search merchandise..." 
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               style="flex: 1; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 6px;">
                        <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                        <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
                        <button type="submit" class="btn btn-primary">🔍 Search</button>
                    </div>
                </form>
            </div>
            
            <!-- Category Filters -->
            <div style="background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <h3 style="margin-bottom: 15px; color: #2d3748;">🏷️ Categories</h3>
                <div class="filter-pills">
                    <a href="?search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>" 
                       class="filter-pill <?php echo empty($category_filter) ? 'active' : ''; ?>">
                        All Products
                    </a>
                    <?php foreach($categories as $cat): ?>
                        <a href="?category=<?php echo $cat; ?>&search=<?php echo urlencode($search_query); ?>&sort=<?php echo $sort_by; ?>" 
                           class="filter-pill <?php echo $category_filter == $cat ? 'active' : ''; ?>">
                            <?php echo ucwords(str_replace('-', ' ', $cat)); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Results Count -->
            <div style="margin-bottom: 20px; padding: 15px; background: #e6fffa; border-left: 4px solid #38b2ac; border-radius: 5px;">
                <strong>📊 Showing <?php echo $total_items; ?> product(s)</strong>
            </div>
            
            <!-- Merchandise Grid -->
            <?php if($total_items > 0): ?>
                <div class="merch-grid">
                    <?php while($merch = mysqli_fetch_assoc($merch_result)): ?>
                        <div class="merch-card" onclick="window.location.href='view_merchandise.php?id=<?php echo $merch['id']; ?>'">
                            <div class="merch-image">
                                <?php if($merch['primary_image']): ?>
                                    <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['primary_image']); ?>" alt="Product">
                                <?php else: ?>
                                    <div style="font-size: 80px;">🛍️</div>
                                <?php endif; ?>
                                
                                <?php if($merch['quantity_available'] <= 5 && $merch['quantity_available'] > 0): ?>
                                    <span class="merch-badge" style="background: #ed8936;">Only <?php echo $merch['quantity_available']; ?> left!</span>
                                <?php elseif($merch['quantity_available'] == 0): ?>
                                    <span class="merch-badge" style="background: #f56565;">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="merch-content">
                                <span class="merch-category"><?php echo ucwords(str_replace('-', ' ', $merch['category'])); ?></span>
                                
                                <div class="merch-name"><?php echo htmlspecialchars($merch['name']); ?></div>
                                
                                <div class="merch-price">₹<?php echo number_format($merch['price'], 2); ?></div>
                                
                                <div class="merch-stock">
                                    <?php if($merch['quantity_available'] > 0): ?>
                                        <span style="color: #48bb78;">✓ In Stock (<?php echo $merch['quantity_available']; ?> available)</span>
                                    <?php else: ?>
                                        <span style="color: #f56565;">✗ Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="font-size: 13px; color: #718096; margin-bottom: 10px;">
                                    By <?php echo htmlspecialchars($merch['organizer_name']); ?>
                                </div>
                                
                                <button class="btn btn-primary btn-sm" style="width: 100%;">
                                    View Details →
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 10px;">
                    <h2 style="color: #718096; margin-bottom: 10px;">📭 No Products Found</h2>
                    <p style="color: #a0aec0;">Try adjusting your search or filters.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>