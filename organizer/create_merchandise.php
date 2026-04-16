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
    $upi_id             = trim(strip_tags($_POST['upi_id']             ?? ''));
    $return_policy      = trim(strip_tags($_POST['return_policy']      ?? ''));
    $distribution_date  = trim($_POST['distribution_date']             ?? '');
    $distribution_venue = trim(strip_tags($_POST['distribution_venue'] ?? ''));
    $distribution_time  = trim($_POST['distribution_time']             ?? '');
    $organizer_id       = $_SESSION['user_id'];

    // ── Validation ─────────────────────────────────────────────────────────────
    $contact_is_phone = preg_match('/^\d{10}$/', $contact_info);
    $contact_is_email = filter_var($contact_info, FILTER_VALIDATE_EMAIL);
    $upi_valid        = preg_match('/^[\w.\-]+@[\w.\-]+$/', $upi_id) || preg_match('/^\d{10}$/', $upi_id);

    if(empty($name) || empty($description) || empty($category) || empty($contact_info) || empty($upi_id)) {
        $error = "Please fill all required fields.";
    } elseif(mb_strlen($name) < 3 || mb_strlen($name) > 150) {
        $error = "Product name must be between 3 and 150 characters.";
    } elseif(!preg_match('/^[a-zA-Z0-9\s\-\']+$/', $name)) {
        $error = "Product name must not contain special characters.";
    } elseif(!in_array($category, $allowed_cats)) {
        $error = "Please select a valid category.";
    } elseif($price <= 0 || $price > 1000000) {
        $error = "Price must be greater than ₹0 and realistic.";
    } elseif($quantity_available < 1 || $quantity_available > 100000) {
        $error = "Quantity must be at least 1 and no more than 100,000.";
    } elseif(!$contact_is_phone && !$contact_is_email) {
        $error = "Contact info must be a valid 10-digit phone number or a valid email address.";
    } elseif(!$upi_valid) {
        $error = "Please enter a valid UPI ID (e.g. name@bank or 9876543210@paytm).";
    } elseif(!empty($distribution_date) && strtotime($distribution_date) === false) {
        $error = "Please enter a valid distribution date.";
    } else {
        $dist_date_val = !empty($distribution_date) ? $distribution_date : null;
        $dist_time_val = !empty($distribution_time) ? $distribution_time : null;

        // ── Handle QR code image upload ───────────────────────────────────────
        $qr_image_name = null;
        if(isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
            $qr_mime      = mime_content_type($_FILES['qr_image']['tmp_name']);
            if(in_array($qr_mime, $allowed_mime) && $_FILES['qr_image']['size'] <= 5242880) {
                $ext           = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
                $qr_image_name = 'qr_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $upload_dir    = '../uploads/merchandise/';
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                if(!move_uploaded_file($_FILES['qr_image']['tmp_name'], $upload_dir . $qr_image_name)) {
                    $qr_image_name = null; // Non-fatal: continue without QR
                }
            }
        }

        // ── INSERT with prepared statement ────────────────────────────────────
        $ins = mysqli_prepare($conn,
            "INSERT INTO merchandise (organizer_id, name, description, price, category, sizes_available,
             size_guide, quantity_available, contact_info, upi_id, qr_image, return_policy,
             distribution_date, distribution_venue, distribution_time, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')"
        );
        mysqli_stmt_bind_param($ins, "issdsssisssssss",
            $organizer_id, $name, $description, $price, $category, $sizes_available,
            $size_guide, $quantity_available, $contact_info, $upi_id, $qr_image_name, $return_policy,
            $dist_date_val, $distribution_venue, $dist_time_val
        );

        if(mysqli_stmt_execute($ins)) {
            $merchandise_id = mysqli_insert_id($conn);

            // ── Handle multiple product image uploads ─────────────────────────
            if(isset($_FILES['merchandise_images']) && count($_FILES['merchandise_images']['name']) > 0) {
                $upload_dir   = '../uploads/merchandise/';
                if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
                $max_images   = 5;
                $uploaded_count = 0;

                for($i = 0; $i < min(count($_FILES['merchandise_images']['name']), $max_images); $i++) {
                    if($_FILES['merchandise_images']['error'][$i] !== 0) continue;
                    if($_FILES['merchandise_images']['size'][$i] > 5242880) continue;
                    $file_type = mime_content_type($_FILES['merchandise_images']['tmp_name'][$i]);
                    if(!in_array($file_type, $allowed_mime)) continue;
                    $ext        = pathinfo($_FILES['merchandise_images']['name'][$i], PATHINFO_EXTENSION);
                    $image_name = 'merch_' . $merchandise_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    if(move_uploaded_file($_FILES['merchandise_images']['tmp_name'][$i], $upload_dir . $image_name)) {
                        $is_primary = ($uploaded_count === 0) ? 1 : 0;
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
        .field-error  { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid, textarea.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid,   textarea.input-valid   { border-color:#48bb78 !important; }
        .info-box  { background:#eff6ff; border:1px solid #93c5fd; border-radius:8px; padding:14px 16px; margin-bottom:10px; font-size:14px; color:#1e40af; }
        .tip-box   { background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:14px 16px; margin-bottom:10px; font-size:14px; color:#92400e; }
        .qr-preview { max-width:180px; border-radius:8px; border:2px solid #e2e8f0; margin-top:10px; display:none; }
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

                    <!-- ── Basic Information ─────────────────────────────── -->
                    <h3 style="margin-bottom:20px;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📝 Basic Information</h3>

                    <div class="form-group">
                        <label>Product Name * <small style="color:#718096;">(3–150 chars, letters/numbers/spaces/hyphens only)</small></label>
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
                            <label>Total Quantity *</label>
                            <input type="number" name="quantity_available" id="mQty" min="1" max="100000" placeholder="e.g., 50" required>
                            <span class="field-error" id="err-qty">Quantity must be at least 1.</span>
                        </div>
                    </div>

                    <!-- ── Size Information ──────────────────────────────── -->
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

                    <!-- ── Product Images ────────────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📸 Product Images</h3>

                    <div class="form-group">
                        <label>Upload Images (Max 5, each max 5MB)</label>
                        <input type="file" name="merchandise_images[]" multiple accept="image/*" id="imageInput" onchange="previewImages(event)">
                        <span class="field-error" id="err-image">Only JPG, PNG, GIF allowed. Max 5MB each.</span>
                        <small style="color:#718096;">First image will be the primary display image</small>
                        <div id="imagePreviewContainer" class="image-preview-container"></div>
                    </div>

                    <!-- ── Payment Information ───────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">💳 Payment Information</h3>

                    <div class="info-box">
                        ℹ️ Students will pay via UPI when placing an order. They'll see your UPI ID (and QR code if uploaded) 
                        in a popup, then submit a screenshot of their payment for you to verify.
                    </div>

                    <div class="form-group">
                        <label>UPI ID * <small style="color:#718096;">(e.g., yourname@paytm, 9876543210@upi)</small></label>
                        <input type="text" name="upi_id" id="mUpiId" placeholder="yourname@bankname or 9876543210@paytm" required>
                        <span class="field-error" id="err-upi">Enter a valid UPI ID (e.g. name@bank or 10-digit phone).</span>
                        <small style="color:#718096;">This will be shown to students when they place an order.</small>
                    </div>

                    <div class="form-group">
                        <label>Payment QR Code / Scanner Image <small style="color:#718096;">(optional — JPG/PNG, max 5MB)</small></label>
                        <div class="tip-box">
                            💡 <strong>Tip:</strong> You can upload a screenshot of your payment QR code scanner here. 
                            Students will see this alongside your UPI ID when placing an order, making it easy to scan and pay. 
                            This is <strong>optional</strong> — your UPI ID alone is sufficient.
                        </div>
                        <input type="file" name="qr_image" accept="image/*" id="qrImageInput" onchange="previewQR(event)">
                        <img id="qrPreview" class="qr-preview" alt="QR Preview">
                    </div>

                    <!-- ── Contact Information ───────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📞 Contact Information</h3>

                    <div class="form-group">
                        <label>Contact Info * <small style="color:#718096;">(10-digit phone number or email address)</small></label>
                        <input type="text" name="contact_info" id="mContact" placeholder="9876543210 or contact@example.com" required>
                        <span class="field-error" id="err-contact">Enter a valid 10-digit phone number or email address.</span>
                    </div>

                    <!-- ── Distribution Details ──────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">🚚 Distribution Details</h3>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Distribution Date</label>
                            <input type="date" name="distribution_date" id="mDistDate">
                            <span class="field-error" id="err-distdate">Please enter a valid date.</span>
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
    function showErr(f,id,msg){ f.classList.add('input-invalid');f.classList.remove('input-valid');const el=document.getElementById(id);if(el){el.textContent=msg;el.style.display='block';} }
    function clearErr(f,id){ f.classList.remove('input-invalid');f.classList.add('input-valid');const el=document.getElementById(id);if(el)el.style.display='none'; }

    const fName    = document.getElementById('mName');
    const fDesc    = document.getElementById('mDesc');
    const fPrice   = document.getElementById('mPrice');
    const fCat     = document.getElementById('mCat');
    const fQty     = document.getElementById('mQty');
    const fContact = document.getElementById('mContact');
    const fUpi     = document.getElementById('mUpiId');

    fName.addEventListener('input', function(){
        const v=this.value.trim();
        if(v.length<3||v.length>150){ showErr(this,'err-name','Product name must be 3–150 characters.'); return; }
        if(!/^[a-zA-Z0-9\s\-']+$/.test(v)){ showErr(this,'err-name','No special characters allowed.'); return; }
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
        (v>=1&&v<=100000)?clearErr(this,'err-qty'):showErr(this,'err-qty','Quantity must be at least 1.');
    });
    fContact.addEventListener('input', function(){
        const v=this.value.trim();
        const isPhone=/^\d{10}$/.test(v);
        const isEmail=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
        if(!v){ showErr(this,'err-contact','Contact information is required.'); return; }
        (!isPhone&&!isEmail)?showErr(this,'err-contact','Enter a valid 10-digit phone or email.'):clearErr(this,'err-contact');
    });
    fUpi.addEventListener('input', function(){
        const v=this.value.trim();
        if(!v){ showErr(this,'err-upi','UPI ID is required.'); return; }
        // Accept format: something@something OR 10-digit phone
        const isUpi   = /^[\w.\-]+@[\w.\-]+$/.test(v);
        const isPhone = /^\d{10}$/.test(v);
        (!isUpi&&!isPhone)?showErr(this,'err-upi','Enter a valid UPI ID (e.g. name@bank).'):clearErr(this,'err-upi');
    });

    // QR image preview
    function previewQR(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('qrPreview');
        if(!file) { preview.style.display='none'; return; }
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display='block'; };
        reader.readAsDataURL(file);
    }

    // Product images preview
    let selectedFiles = [];
    function previewImages(event) {
        const files    = Array.from(event.target.files);
        const allowed  = ['image/jpeg','image/jpg','image/png','image/gif'];
        const container= document.getElementById('imagePreviewContainer');
        const errEl    = document.getElementById('err-image');
        const validFiles = files.filter(f => {
            if(!allowed.includes(f.type))  { errEl.textContent='Only JPG, PNG, GIF allowed.'; errEl.style.display='block'; return false; }
            if(f.size > 5*1024*1024)       { errEl.textContent='Each image must be under 5MB.'; errEl.style.display='block'; return false; }
            return true;
        });
        if(validFiles.length === files.length) errEl.style.display='none';
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
        if(nv.length<3||nv.length>150){ showErr(fName,'err-name','3–150 characters required.'); ok=false; }
        else if(!/^[a-zA-Z0-9\s\-']+$/.test(nv)){ showErr(fName,'err-name','No special characters.'); ok=false; }
        if(fDesc.value.trim().length<10){ showErr(fDesc,'err-desc','Min 10 characters.'); ok=false; }
        const pv=parseFloat(fPrice.value);
        if(isNaN(pv)||pv<=0){ showErr(fPrice,'err-price','Valid price required.'); ok=false; }
        if(!fCat.value){ showErr(fCat,'err-cat','Select a category.'); ok=false; }
        const qv=parseInt(fQty.value);
        if(isNaN(qv)||qv<1){ showErr(fQty,'err-qty','Quantity must be at least 1.'); ok=false; }
        const cv=fContact.value.trim();
        if(!cv){ showErr(fContact,'err-contact','Contact is required.'); ok=false; }
        else if(!/^\d{10}$/.test(cv) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cv)){ showErr(fContact,'err-contact','Valid phone or email required.'); ok=false; }
        const uv=fUpi.value.trim();
        if(!uv){ showErr(fUpi,'err-upi','UPI ID is required.'); ok=false; }
        else if(!/^[\w.\-]+@[\w.\-]+$/.test(uv) && !/^\d{10}$/.test(uv)){ showErr(fUpi,'err-upi','Valid UPI ID required.'); ok=false; }
        if(!ok) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>