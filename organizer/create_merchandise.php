<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$error   = "";
$success = "";

// ─── Server-side whitelist ────────────────────────────────────────────────────
$allowed_cats = ['t-shirt','oversized-tshirt','hoodie','cap','tote-bag','cup',
                 'sweatshirt','mask','diary','magazine','other'];

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_merchandise'])) {

    // Sanitize inputs
    $name               = trim(strip_tags($_POST['name']               ?? ''));
    $description        = trim(strip_tags($_POST['description']        ?? ''));
    $price              = floatval($_POST['price']                     ?? 0);
    $category           = trim(strip_tags($_POST['category']           ?? ''));
    $sizes_available    = trim(strip_tags($_POST['sizes_available']    ?? ''));
    $size_guide         = trim(strip_tags($_POST['size_guide']         ?? ''));
    $quantity_available = intval($_POST['quantity_available']          ?? 0);
    $contact_info       = trim(strip_tags($_POST['contact_info']       ?? ''));
    $order_form_link    = trim(strip_tags($_POST['order_form_link']    ?? ''));
    $return_policy      = trim(strip_tags($_POST['return_policy']      ?? ''));
    $distribution_date  = trim($_POST['distribution_date']             ?? '');
    $distribution_venue = trim(strip_tags($_POST['distribution_venue'] ?? ''));
    $distribution_time  = trim($_POST['distribution_time']             ?? '');
    $organizer_id       = $_SESSION['user_id'];

    // ── Validation ─────────────────────────────────────────────────────────────
    // Contact: phone (10 digits) or email
    $contact_is_phone = preg_match('/^\d{10}$/', $contact_info);
    $contact_is_email = filter_var($contact_info, FILTER_VALIDATE_EMAIL);

    if(empty($name) || empty($description) || empty($category) || empty($contact_info) || empty($order_form_link)) {
        $error = "Please fill all required fields.";
    } elseif(mb_strlen($name) < 3 || mb_strlen($name) > 150) {
        $error = "Product name must be between 3 and 150 characters.";
    } elseif(!preg_match('/^[a-zA-Z0-9\s\-\']+$/', $name)) {
        $error = "Product name must not contain special characters.";
    } elseif(!in_array($category, $allowed_cats)) {
        $error = "Please select a valid category.";
    } elseif($price <= 0 || $price > 1000000) {
        $error = "Price must be greater than ₹0 and realistic.";
    } elseif($quantity_available < 10 || $quantity_available > 100000) {
        $error = "Quantity must be at least 10 and no more than 100,000.";
    } elseif(!$contact_is_phone && !$contact_is_email) {
        $error = "Contact info must be a valid 10-digit phone number or a valid email address.";
    } elseif(!filter_var($order_form_link, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL for the order form link.";
    } elseif(!empty($distribution_date) && strtotime($distribution_date) === false) {
        $error = "Please enter a valid distribution date.";
    } else {
        // ── INSERT with prepared statement ────────────────────────────────────
        $dist_date_val = !empty($distribution_date) ? $distribution_date : null;
        $dist_time_val = !empty($distribution_time) ? $distribution_time : null;

        $ins = mysqli_prepare($conn,
            "INSERT INTO merchandise (organizer_id, name, description, price, category, sizes_available,
             size_guide, quantity_available, contact_info, order_form_link, return_policy,
             distribution_date, distribution_venue, distribution_time, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')"
        );
        mysqli_stmt_bind_param($ins, "issdsssissssss",
            $organizer_id, $name, $description, $price, $category, $sizes_available,
            $size_guide, $quantity_available, $contact_info, $order_form_link, $return_policy,
            $dist_date_val, $distribution_venue, $dist_time_val
        );

        if(mysqli_stmt_execute($ins)) {
            $merchandise_id = mysqli_insert_id($conn);

            // ── Handle multiple image uploads ─────────────────────────────────
            if(isset($_FILES['merchandise_images']) && count($_FILES['merchandise_images']['name']) > 0) {
                $upload_dir  = '../uploads/merchandise/';
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
                $max_images   = 5;
                $uploaded_count = 0;

                for($i = 0; $i < min(count($_FILES['merchandise_images']['name']), $max_images); $i++) {
                    if($_FILES['merchandise_images']['error'][$i] !== 0) continue;
                    if($_FILES['merchandise_images']['size'][$i] > 5242880) continue; // 5MB

                    // Use mime_content_type for accurate type detection
                    $file_type = mime_content_type($_FILES['merchandise_images']['tmp_name'][$i]);
                    if(!in_array($file_type, $allowed_mime)) continue;

                    $ext        = pathinfo($_FILES['merchandise_images']['name'][$i], PATHINFO_EXTENSION);
                    $image_name = 'merch_' . $merchandise_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    $upload_path= $upload_dir . $image_name;

                    if(move_uploaded_file($_FILES['merchandise_images']['tmp_name'][$i], $upload_path)) {
                        $is_primary = ($uploaded_count === 0) ? 1 : 0;
                        // Insert image record with prepared statement
                        $img_ins = mysqli_prepare($conn,
                            "INSERT INTO merchandise_images (merchandise_id, image_path, is_primary) VALUES (?, ?, ?)"
                        );
                        mysqli_stmt_bind_param($img_ins, "isi", $merchandise_id, $image_name, $is_primary);
                        mysqli_stmt_execute($img_ins);
                        mysqli_stmt_close($img_ins);
                        $uploaded_count++;
                    }
                }
            }
            mysqli_stmt_close($ins);
            $success = "Merchandise created successfully!";
            header("refresh:2;url=manage_merchandise.php");
        } else {
            $error = "An error occurred while creating the merchandise. Please try again.";
            mysqli_stmt_close($ins);
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
        .image-preview-container { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:15px; margin-top:15px; }
        .image-preview            { position:relative; width:100%; height:150px; border-radius:8px; overflow:hidden; border:2px solid #e2e8f0; }
        .image-preview img        { width:100%; height:100%; object-fit:cover; }
        .image-preview-remove     { position:absolute; top:5px; right:5px; background:#f56565; color:white; border:none; border-radius:50%; width:25px; height:25px; cursor:pointer; font-size:14px; }
        /* Validation */
        .field-error { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid, textarea.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid,   textarea.input-valid   { border-color:#48bb78 !important; }
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

            <?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <div style="background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="" enctype="multipart/form-data" id="createMerchForm" novalidate>

                    <h3 style="margin-bottom:20px;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📝 Basic Information</h3>

                    <div class="form-group">
                        <label>Product Name * <small style="color:#718096;">(3–150 chars)</small></label>
                        <input type="text" name="name" id="mName" placeholder="e.g., College Hoodie 2025" required maxlength="150">
                        <span class="field-error" id="err-name">Product name must be 3–150 characters.</span>
                    </div>

                    <div class="form-group">
                        <label>Description * <small style="color:#718096;">(min 10 chars)</small></label>
                        <textarea name="description" id="mDesc" rows="4" placeholder="Describe the product..." required></textarea>
                        <span class="field-error" id="err-desc">Description must be at least 10 characters.</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Price (₹) *</label>
                            <input type="number" name="price" id="mPrice" step="0.01" min="1" placeholder="299.00" required>
                            <span class="field-error" id="err-price">Enter a valid price greater than ₹0.</span>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" id="mCat" required>
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
                            <span class="field-error" id="err-cat">Please select a category.</span>
                        </div>
                        <div class="form-group">
                            <label>Quantity Available * </label>
                            <input type="number" name="quantity_available" id="mQty" min="10" max="100000" placeholder="e.g., 50" required>
                            <span class="field-error" id="err-qty">Quantity must be at least 10.</span>
                        </div>
                    </div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📏 Size Information (Optional)</h3>

                    <div class="form-group">
                        <label>Sizes Available</label>
                        <input type="text" name="sizes_available" placeholder="e.g., S, M, L, XL, XXL">
                        <small style="color:#718096;">Separate sizes with commas</small>
                    </div>
                    <div class="form-group">
                        <label>Size Guide (Optional)</label>
                        <textarea name="size_guide" rows="3" placeholder="Size chart or measurements..."></textarea>
                    </div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📸 Product Images</h3>

                    <div class="form-group">
                        <label>Upload Images (Max 5, each max 5MB)</label>
                        <input type="file" name="merchandise_images[]" multiple accept="image/*" id="imageInput" onchange="previewImages(event)">
                        <span class="field-error" id="err-image">Only JPG, PNG, GIF allowed. Max 5MB each.</span>
                        <small style="color:#718096;">First image will be the primary display image</small>
                        <div id="imagePreviewContainer" class="image-preview-container"></div>
                    </div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📞 Contact &amp; Order Information</h3>

                    <div class="form-group">
                        <label>Contact Information * <small style="color:#718096;">(10-digit phone number or email address)</small></label>
                        <input type="text" name="contact_info" id="mContact" placeholder="9876543210 or contact@example.com" required>
                        <span class="field-error" id="err-contact">Enter a valid 10-digit phone number or email address.</span>
                    </div>
                    <div class="form-group">
                        <label>Order Form Link * <small style="color:#718096;">(must start with https://)</small></label>
                        <input type="url" name="order_form_link" id="mOrderLink" placeholder="https://forms.google.com/..." required>
                        <span class="field-error" id="err-link">Enter a valid URL (e.g. https://forms.google.com/...).</span>
                        <small style="color:#718096;">Students will use this link to place orders</small>
                    </div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">🚚 Distribution Details</h3>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Distribution Date</label>
                            <input type="date" name="distribution_date" id="mDistDate">
                            <span class="field-error" id="err-distdate">Distribution date must be in the future.</span>
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

                    <div class="form-group">
                        <label>Return Policy (Optional)</label>
                        <textarea name="return_policy" rows="3" placeholder="Describe your return/exchange policy..."></textarea>
                    </div>

                    <div style="display:flex;gap:15px;margin-top:30px;">
                        <button type="submit" name="create_merchandise" class="btn btn-primary">✓ Create Merchandise</button>
                        <a href="manage_merchandise.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    /* ── Real-time validation — create_merchandise.php ── */
    function showErr(f,id,msg){ f.classList.add('input-invalid');f.classList.remove('input-valid');const el=document.getElementById(id);if(el){el.textContent=msg;el.style.display='block';} }
    function clearErr(f,id){ f.classList.remove('input-invalid');f.classList.add('input-valid');const el=document.getElementById(id);if(el)el.style.display='none'; }

    const fName    = document.getElementById('mName');
    const fDesc    = document.getElementById('mDesc');
    const fPrice   = document.getElementById('mPrice');
    const fCat     = document.getElementById('mCat');
    const fQty     = document.getElementById('mQty');
    const fContact = document.getElementById('mContact');
    const fLink    = document.getElementById('mOrderLink');

    fName.addEventListener('input', function(){
        const v=this.value.trim();
        if(v.length<3||v.length>150){ showErr(this,'err-name','Product name must be 3–150 characters.'); return; }
        if(!/^[a-zA-Z0-9\s\-']+$/.test(v)){ showErr(this,'err-name','Product name must not contain special characters.'); return; }
        clearErr(this,'err-name');
    });
    fDesc.addEventListener('input', function(){
        this.value.trim().length>=10?clearErr(this,'err-desc'):showErr(this,'err-desc','Description must be at least 10 characters.');
    });
    fPrice.addEventListener('input', function(){
        const v=parseFloat(this.value);
        (v>0&&v<=1000000)?clearErr(this,'err-price'):showErr(this,'err-price','Enter a valid price greater than ₹0.');
    });
    fCat.addEventListener('change', function(){
        this.value?clearErr(this,'err-cat'):showErr(this,'err-cat','Please select a category.');
    });
    fQty.addEventListener('input', function(){
        const v=parseInt(this.value);
        (v>=10&&v<=100000)?clearErr(this,'err-qty'):showErr(this,'err-qty','Quantity must be at least 10.');
    });
    fContact.addEventListener('input', function(){
        const v=this.value.trim();
        const isPhone=/^\d{10}$/.test(v);
        const isEmail=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        if(!v){ showErr(this,'err-contact','Contact information is required.'); return; }
        if(!isPhone&&!isEmail){ showErr(this,'err-contact','Enter a valid 10-digit phone number (digits only) or a valid email address.'); return; }
        clearErr(this,'err-contact');
    });
    fLink.addEventListener('input', function(){
        const v=this.value.trim();
        if(!v){showErr(this,'err-link','Order form link is required.');return;}
        try{new URL(v);clearErr(this,'err-link');}catch(e){showErr(this,'err-link','Enter a valid URL.');}
    });

    // Image preview with client-side validation
    let selectedFiles = [];
    function previewImages(event) {
        const files    = Array.from(event.target.files);
        const allowed  = ['image/jpeg','image/jpg','image/png','image/gif'];
        const container= document.getElementById('imagePreviewContainer');
        const imgInput = document.getElementById('imageInput');
        const errEl    = document.getElementById('err-image');

        // Filter valid files
        const validFiles = files.filter(f => {
            if(!allowed.includes(f.type))   { errEl.textContent='Only JPG, PNG, GIF allowed.'; errEl.style.display='block'; return false; }
            if(f.size > 5*1024*1024)        { errEl.textContent='Each image must be under 5MB.'; errEl.style.display='block'; return false; }
            return true;
        });

        if(validFiles.length === files.length) { errEl.style.display='none'; }
        selectedFiles = validFiles.slice(0,5);
        container.innerHTML = '';

        selectedFiles.forEach((file, i) => {
            const reader = new FileReader();
            reader.onload = e => {
                const div = document.createElement('div');
                div.className = 'image-preview';
                div.innerHTML = `<img src="${e.target.result}" alt="Preview ${i+1}">
                    <button type="button" class="image-preview-remove" onclick="removeImage(${i})">×</button>
                    ${i===0?'<div style="position:absolute;bottom:5px;left:5px;background:#667eea;color:white;padding:3px 8px;border-radius:4px;font-size:11px;">Primary</div>':''}`;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }

    function removeImage(index) {
        selectedFiles.splice(index,1);
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        document.getElementById('imageInput').files = dt.files;
        previewImages({ target: { files: dt.files } });
    }

    document.getElementById('createMerchForm').addEventListener('submit', function(e) {
        let ok = true;
        const nv = fName.value.trim();
        if(nv.length<3||nv.length>150){ showErr(fName,'err-name','Product name must be 3–150 characters.'); ok=false; }
        else if(!/^[a-zA-Z0-9\s\-']+$/.test(nv)){ showErr(fName,'err-name','Product name must not contain special characters.'); ok=false; }
        if(fDesc.value.trim().length<10){ showErr(fDesc,'err-desc','Description must be at least 10 characters.'); ok=false; }
        const pv=parseFloat(fPrice.value);
        if(isNaN(pv)||pv<=0){ showErr(fPrice,'err-price','Enter a valid price greater than ₹0.'); ok=false; }
        if(!fCat.value){ showErr(fCat,'err-cat','Please select a category.'); ok=false; }
        const qv=parseInt(fQty.value);
        if(isNaN(qv)||qv<10){ showErr(fQty,'err-qty','Quantity must be at least 10.'); ok=false; }
        const cv=fContact.value.trim();
        if(!cv){ showErr(fContact,'err-contact','Contact information is required.'); ok=false; }
        else if(!/^\d{10}$/.test(cv) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cv)){ showErr(fContact,'err-contact','Enter a valid 10-digit phone number or email address.'); ok=false; }
        const lv=fLink.value.trim();
        if(!lv){ showErr(fLink,'err-link','Order form link is required.'); ok=false; }
        else { try{new URL(lv);}catch(err){showErr(fLink,'err-link','Enter a valid URL.'); ok=false;} }
        if(!ok) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>