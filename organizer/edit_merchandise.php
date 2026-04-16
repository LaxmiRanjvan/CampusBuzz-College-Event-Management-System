<?php
session_start();
require_once '../config/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$organizer_id = $_SESSION['user_id'];
$error   = "";
$success = "";

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_merchandise.php");
    exit();
}
$merch_id = intval($_GET['id']);

// ── Fetch merchandise ───────────────────────────────────────────────────────────
$fetch = mysqli_prepare($conn, "SELECT * FROM merchandise WHERE id = ? AND organizer_id = ?");
mysqli_stmt_bind_param($fetch, "ii", $merch_id, $organizer_id);
mysqli_stmt_execute($fetch);
$merch_result = mysqli_stmt_get_result($fetch);
if(mysqli_num_rows($merch_result) == 0) {
    mysqli_stmt_close($fetch);
    header("Location: manage_merchandise.php");
    exit();
}
$merch = mysqli_fetch_assoc($merch_result);
mysqli_stmt_close($fetch);

// ── Whitelist ───────────────────────────────────────────────────────────────────
$allowed_cats = ['t-shirt','oversized-tshirt','hoodie','cap','tote-bag','cup',
                 'sweatshirt','mask','diary','magazine','other'];

// ── Handle product image deletion ───────────────────────────────────────────────
if(isset($_GET['delete_image']) && is_numeric($_GET['delete_image'])) {
    $img_id = intval($_GET['delete_image']);
    $del_img = mysqli_prepare($conn, "SELECT image_path FROM merchandise_images WHERE id = ? AND merchandise_id = ?");
    mysqli_stmt_bind_param($del_img, "ii", $img_id, $merch_id);
    mysqli_stmt_execute($del_img);
    $del_result = mysqli_stmt_get_result($del_img);
    mysqli_stmt_close($del_img);
    if(mysqli_num_rows($del_result) > 0) {
        $img_row = mysqli_fetch_assoc($del_result);
        if(file_exists('../uploads/merchandise/' . $img_row['image_path'])) unlink('../uploads/merchandise/' . $img_row['image_path']);
        $del = mysqli_prepare($conn, "DELETE FROM merchandise_images WHERE id = ?");
        mysqli_stmt_bind_param($del, "i", $img_id);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
        $success = "Image deleted successfully!";
    }
}

// ── Handle QR image deletion ─────────────────────────────────────────────────────
if(isset($_GET['delete_qr'])) {
    if(!empty($merch['qr_image']) && file_exists('../uploads/merchandise/' . $merch['qr_image'])) {
        unlink('../uploads/merchandise/' . $merch['qr_image']);
    }
    $clr = mysqli_prepare($conn, "UPDATE merchandise SET qr_image = NULL WHERE id = ? AND organizer_id = ?");
    mysqli_stmt_bind_param($clr, "ii", $merch_id, $organizer_id);
    mysqli_stmt_execute($clr);
    mysqli_stmt_close($clr);
    $merch['qr_image'] = null;
    $success = "QR image removed.";
}

// ── Handle update ────────────────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_merchandise'])) {

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
    } elseif($price <= 0) {
        $error = "Price must be greater than ₹0.";
    } elseif($quantity_available < 0 || $quantity_available > 100000) {
        $error = "Quantity must be between 0 and 100,000.";
    } elseif(!$contact_is_phone && !$contact_is_email) {
        $error = "Contact info must be a valid 10-digit phone number or a valid email address.";
    } elseif(!$upi_valid) {
        $error = "Please enter a valid UPI ID (e.g. name@bank or 9876543210@paytm).";
    } else {
        $dist_date_val = !empty($distribution_date) ? $distribution_date : null;
        $dist_time_val = !empty($distribution_time) ? $distribution_time : null;

        // ── Handle new QR image upload ────────────────────────────────────────
        $new_qr_name = $merch['qr_image']; // keep existing unless replaced
        if(isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
            $qr_mime      = mime_content_type($_FILES['qr_image']['tmp_name']);
            if(in_array($qr_mime, $allowed_mime) && $_FILES['qr_image']['size'] <= 5242880) {
                // Delete old QR
                if(!empty($merch['qr_image']) && file_exists('../uploads/merchandise/' . $merch['qr_image'])) {
                    unlink('../uploads/merchandise/' . $merch['qr_image']);
                }
                $ext         = pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION);
                $new_qr_name = 'qr_' . $merch_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                $upload_dir  = '../uploads/merchandise/';
                if(!move_uploaded_file($_FILES['qr_image']['tmp_name'], $upload_dir . $new_qr_name)) {
                    $new_qr_name = $merch['qr_image']; // revert on failure
                }
            }
        }

        // ── Determine auto-status based on quantity ───────────────────────────
        $auto_status = $quantity_available > 0 ? 'available' : 'out_of_stock';
        // Respect 'discontinued' — don't override it automatically
        if($merch['status'] === 'discontinued') $auto_status = 'discontinued';

        $upd = mysqli_prepare($conn,
            "UPDATE merchandise SET name=?, description=?, price=?, category=?, sizes_available=?,
             size_guide=?, quantity_available=?, contact_info=?, upi_id=?, qr_image=?, return_policy=?,
             distribution_date=?, distribution_venue=?, distribution_time=?, status=?
             WHERE id=? AND organizer_id=?"
        );
        mysqli_stmt_bind_param($upd, "ssdsssissssssssii",
            $name, $description, $price, $category, $sizes_available,
            $size_guide, $quantity_available, $contact_info, $upi_id, $new_qr_name, $return_policy,
            $dist_date_val, $distribution_venue, $dist_time_val, $auto_status,
            $merch_id, $organizer_id
        );

        if(mysqli_stmt_execute($upd)) {
            // Handle new product image uploads
            if(isset($_FILES['new_images']) && count($_FILES['new_images']['name']) > 0) {
                $upload_dir   = '../uploads/merchandise/';
                $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
                for($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                    if($_FILES['new_images']['error'][$i] !== 0) continue;
                    if($_FILES['new_images']['size'][$i] > 5242880)   continue;
                    $ft = mime_content_type($_FILES['new_images']['tmp_name'][$i]);
                    if(!in_array($ft, $allowed_mime)) continue;
                    $ext        = pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION);
                    $image_name = 'merch_' . $merch_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
                    if(move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $upload_dir . $image_name)) {
                        $img_ins = mysqli_prepare($conn, "INSERT INTO merchandise_images (merchandise_id, image_path, is_primary) VALUES (?,?,0)");
                        mysqli_stmt_bind_param($img_ins, "is", $merch_id, $image_name);
                        mysqli_stmt_execute($img_ins);
                        mysqli_stmt_close($img_ins);
                    }
                }
            }
            mysqli_stmt_close($upd);
            $success = "Merchandise updated successfully!";

            // Refresh data
            $rf = mysqli_prepare($conn, "SELECT * FROM merchandise WHERE id=?");
            mysqli_stmt_bind_param($rf, "i", $merch_id);
            mysqli_stmt_execute($rf);
            $merch = mysqli_fetch_assoc(mysqli_stmt_get_result($rf));
            mysqli_stmt_close($rf);
        } else {
            $error = "An error occurred while updating. Please try again.";
            mysqli_stmt_close($upd);
        }
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
    <style>
        .field-error  { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid, textarea.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid,   textarea.input-valid   { border-color:#48bb78 !important; }
        .btn-primary:disabled { opacity:0.45; cursor:not-allowed; pointer-events:none; }
        .info-box { background:#eff6ff; border:1px solid #93c5fd; border-radius:8px; padding:14px 16px; margin-bottom:10px; font-size:14px; color:#1e40af; }
        .tip-box  { background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:14px 16px; margin-bottom:10px; font-size:14px; color:#92400e; }
        .qr-current { border:2px solid #e2e8f0; border-radius:8px; padding:10px; display:inline-block; margin-bottom:10px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1>✏️ Edit Merchandise</h1>
                <a href="manage_merchandise.php" class="btn btn-secondary">← Back to Merchandise</a>
            </div>

            <?php if($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <div style="background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">

                <!-- Current product images -->
                <?php
                $disp_img = mysqli_prepare($conn, "SELECT * FROM merchandise_images WHERE merchandise_id=? ORDER BY is_primary DESC");
                mysqli_stmt_bind_param($disp_img, "i", $merch_id);
                mysqli_stmt_execute($disp_img);
                $disp_img_result = mysqli_stmt_get_result($disp_img);
                mysqli_stmt_close($disp_img);
                if(mysqli_num_rows($disp_img_result) > 0): ?>
                <div style="margin-bottom:30px;">
                    <h3 style="margin-bottom:15px;color:#2d3748;">Current Product Images</h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:15px;">
                        <?php while($img = mysqli_fetch_assoc($disp_img_result)): ?>
                        <div style="position:relative;border-radius:8px;overflow:hidden;border:2px solid #e2e8f0;">
                            <img src="../uploads/merchandise/<?php echo htmlspecialchars($img['image_path']); ?>" style="width:100%;height:150px;object-fit:cover;">
                            <?php if($img['is_primary']): ?>
                                <div style="position:absolute;top:5px;left:5px;background:#667eea;color:white;padding:3px 8px;border-radius:4px;font-size:11px;">Primary</div>
                            <?php endif; ?>
                            <a href="?id=<?php echo $merch_id; ?>&delete_image=<?php echo $img['id']; ?>"
                               style="position:absolute;top:5px;right:5px;background:#f56565;color:white;width:25px;height:25px;display:flex;align-items:center;justify-content:center;border-radius:50%;text-decoration:none;font-size:14px;"
                               onclick="return confirm('Delete this image?')">×</a>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="editMerchForm" novalidate>

                    <!-- ── Basic Information ──────────────────────────────── -->
                    <h3 style="margin-bottom:20px;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📝 Basic Information</h3>

                    <div class="form-group">
                        <label>Product Name * <small style="color:#718096;">(3–150 chars)</small></label>
                        <input type="text" name="name" id="mName" required maxlength="150" value="<?php echo htmlspecialchars($merch['name']); ?>">
                        <span class="field-error" id="err-name">Product name must be 3–150 characters.</span>
                    </div>
                    <div class="form-group">
                        <label>Description *</label>
                        <textarea name="description" id="mDesc" rows="4" required><?php echo htmlspecialchars($merch['description']); ?></textarea>
                        <span class="field-error" id="err-desc">Description must be at least 10 characters.</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Price (₹) *</label>
                            <input type="number" name="price" id="mPrice" step="0.01" min="1" required value="<?php echo $merch['price']; ?>">
                            <span class="field-error" id="err-price">Enter a valid price.</span>
                        </div>
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" id="mCat" required>
                                <option value="">Select Category</option>
                                <?php $cats=['t-shirt'=>'T-Shirt','oversized-tshirt'=>'Oversized T-Shirt','hoodie'=>'Hoodie','cap'=>'Cap','tote-bag'=>'Tote Bag','cup'=>'Cup/Mug','sweatshirt'=>'Sweatshirt','mask'=>'Mask','diary'=>'Diary/Notebook','magazine'=>'Magazine','other'=>'Other'];
                                foreach($cats as $v=>$l): $sel=($merch['category']==$v)?'selected':''; ?>
                                <option value="<?php echo $v; ?>" <?php echo $sel; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-cat">Please select a category.</span>
                        </div>
                        <div class="form-group">
                            <label>Quantity Available *</label>
                            <input type="number" name="quantity_available" id="mQty" min="0" max="100000" required value="<?php echo $merch['quantity_available']; ?>">
                            <span class="field-error" id="err-qty">Enter a valid quantity (0 or more).</span>
                            <small style="color:#718096;">Auto-updates status when set to 0 → Out of Stock</small>
                        </div>
                    </div>

                    <!-- ── Size Information ──────────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📏 Size Information</h3>
                    <div class="form-group"><label>Sizes Available</label><input type="text" name="sizes_available" id="mSizes" value="<?php echo htmlspecialchars($merch['sizes_available'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Size Guide</label><textarea name="size_guide" id="mSizeGuide" rows="3"><?php echo htmlspecialchars($merch['size_guide'] ?? ''); ?></textarea></div>

                    <!-- ── Add More Product Images ───────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📸 Add More Product Images</h3>
                    <div class="form-group">
                        <label>Upload Additional Images <small style="color:#718096;">(JPG, PNG, GIF — max 5MB each)</small></label>
                        <input type="file" name="new_images[]" multiple accept="image/*" id="newImagesInput">
                    </div>

                    <!-- ── Payment Information ───────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">💳 Payment Information</h3>

                    <div class="info-box">
                        ℹ️ Students pay via UPI when ordering. They'll see your UPI ID and QR code in a popup, then submit a payment screenshot for you to verify.
                    </div>

                    <div class="form-group">
                        <label>UPI ID * <small style="color:#718096;">(e.g., yourname@paytm, 9876543210@upi)</small></label>
                        <input type="text" name="upi_id" id="mUpiId" required placeholder="yourname@bankname" value="<?php echo htmlspecialchars($merch['upi_id'] ?? ''); ?>">
                        <span class="field-error" id="err-upi">Enter a valid UPI ID.</span>
                    </div>

                    <div class="form-group">
                        <label>Payment QR Code / Scanner Image <small style="color:#718096;">(optional)</small></label>
                        <div class="tip-box">
                            💡 You can upload a QR scanner image here. Students will see it when placing an order. This is optional — UPI ID alone is sufficient.
                        </div>
                        <?php if(!empty($merch['qr_image'])): ?>
                            <div class="qr-current">
                                <p style="margin:0 0 8px 0;font-size:13px;color:#4a5568;font-weight:600;">Current QR Image:</p>
                                <img src="../uploads/merchandise/<?php echo htmlspecialchars($merch['qr_image']); ?>"
                                     style="max-width:180px;border-radius:6px;display:block;margin-bottom:8px;">
                                <a href="?id=<?php echo $merch_id; ?>&delete_qr=1"
                                   class="btn btn-sm btn-danger"
                                   onclick="return confirm('Remove QR image?')" style="font-size:12px;">🗑️ Remove QR Image</a>
                            </div>
                        <?php endif; ?>
                        <label style="margin-top:10px;display:block;"><?php echo !empty($merch['qr_image']) ? 'Replace QR Image:' : 'Upload QR Image:'; ?></label>
                        <input type="file" name="qr_image" accept="image/*" id="qrImageInput" onchange="previewQR(event)">
                        <img id="qrPreview" style="max-width:180px;border-radius:8px;border:2px solid #e2e8f0;margin-top:10px;display:none;" alt="QR Preview">
                    </div>

                    <!-- ── Contact Information ───────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📞 Contact Information</h3>
                    <div class="form-group">
                        <label>Contact Info * <small style="color:#718096;">(10-digit phone or email)</small></label>
                        <input type="text" name="contact_info" id="mContact" required value="<?php echo htmlspecialchars($merch['contact_info']); ?>">
                        <span class="field-error" id="err-contact">Enter a valid phone or email.</span>
                    </div>

                    <!-- ── Distribution Details ──────────────────────────── -->
                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">🚚 Distribution Details</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
                        <div class="form-group"><label>Distribution Date</label><input type="date" name="distribution_date" id="mDistDate" value="<?php echo htmlspecialchars($merch['distribution_date'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Distribution Time</label><input type="time" name="distribution_time" id="mDistTime" value="<?php echo htmlspecialchars($merch['distribution_time'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Distribution Venue</label><input type="text" name="distribution_venue" id="mDistVenue" value="<?php echo htmlspecialchars($merch['distribution_venue'] ?? ''); ?>"></div>
                    </div>

                    <div class="form-group"><label>Return Policy</label><textarea name="return_policy" id="mReturnPolicy" rows="3"><?php echo htmlspecialchars($merch['return_policy'] ?? ''); ?></textarea></div>

                    <div style="display:flex;gap:15px;margin-top:30px;">
                        <button type="submit" name="update_merchandise" id="updateBtn" class="btn btn-primary" disabled>✓ Update Merchandise</button>
                        <a href="manage_merchandise.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    function showErr(f,id,msg){ f.classList.add('input-invalid');f.classList.remove('input-valid');const el=document.getElementById(id);if(el){el.textContent=msg;el.style.display='block';} }
    function clearErr(f,id){ f.classList.remove('input-invalid');f.classList.add('input-valid');const el=document.getElementById(id);if(el)el.style.display='none'; }

    const fName      = document.getElementById('mName');
    const fDesc      = document.getElementById('mDesc');
    const fPrice     = document.getElementById('mPrice');
    const fCat       = document.getElementById('mCat');
    const fQty       = document.getElementById('mQty');
    const fContact   = document.getElementById('mContact');
    const fUpi       = document.getElementById('mUpiId');
    const fSizes     = document.getElementById('mSizes');
    const fSizeGuide = document.getElementById('mSizeGuide');
    const fDistDate  = document.getElementById('mDistDate');
    const fDistTime  = document.getElementById('mDistTime');
    const fDistVenue = document.getElementById('mDistVenue');
    const fRetPolicy = document.getElementById('mReturnPolicy');
    const updateBtn  = document.getElementById('updateBtn');

    const orig = {
        name:      fName.value,    desc:      fDesc.value,
        price:     fPrice.value,   category:  fCat.value,
        qty:       fQty.value,     contact:   fContact.value,
        upi:       fUpi.value,
        sizes:     fSizes     ? fSizes.value     : '',
        sizeGuide: fSizeGuide ? fSizeGuide.value : '',
        distDate:  fDistDate  ? fDistDate.value  : '',
        distTime:  fDistTime  ? fDistTime.value  : '',
        distVenue: fDistVenue ? fDistVenue.value : '',
        retPolicy: fRetPolicy ? fRetPolicy.value : '',
        newImage:  false, newQr: false
    };

    function isDirty() {
        return fName.value    !== orig.name     || fDesc.value    !== orig.desc  ||
               fPrice.value   !== orig.price    || fCat.value     !== orig.category ||
               fQty.value     !== orig.qty      || fContact.value !== orig.contact ||
               fUpi.value     !== orig.upi      ||
               (fSizes     && fSizes.value     !== orig.sizes)     ||
               (fSizeGuide && fSizeGuide.value !== orig.sizeGuide) ||
               (fDistDate  && fDistDate.value  !== orig.distDate)  ||
               (fDistTime  && fDistTime.value  !== orig.distTime)  ||
               (fDistVenue && fDistVenue.value !== orig.distVenue) ||
               (fRetPolicy && fRetPolicy.value !== orig.retPolicy) ||
               orig.newImage || orig.newQr;
    }
    function syncBtn() { updateBtn.disabled = !isDirty(); }

    [fName,fDesc,fPrice,fQty,fContact,fUpi].forEach(f => f.addEventListener('input', syncBtn));
    [fCat].forEach(f => f.addEventListener('change', syncBtn));
    [fSizes,fSizeGuide,fDistDate,fDistTime,fDistVenue,fRetPolicy].forEach(f => { if(f){ f.addEventListener('input', syncBtn); f.addEventListener('change', syncBtn); }});

    document.querySelector('input[name="new_images[]"]')?.addEventListener('change', function(){ orig.newImage = this.files.length > 0; syncBtn(); });
    document.getElementById('qrImageInput')?.addEventListener('change', function(){ orig.newQr = this.files.length > 0; syncBtn(); });

    // Validation listeners
    fName.addEventListener('input',function(){
        const v=this.value.trim();
        if(v.length<3||v.length>150){ showErr(this,'err-name','3–150 characters required.'); return; }
        if(!/^[a-zA-Z0-9\s\-']+$/.test(v)){ showErr(this,'err-name','No special characters.'); return; }
        clearErr(this,'err-name');
    });
    fDesc.addEventListener('input',function(){ this.value.trim().length>=10?clearErr(this,'err-desc'):showErr(this,'err-desc','Min 10 characters.'); });
    fPrice.addEventListener('input',function(){ parseFloat(this.value)>0?clearErr(this,'err-price'):showErr(this,'err-price','Valid price required.'); });
    fCat.addEventListener('change',function(){ this.value?clearErr(this,'err-cat'):showErr(this,'err-cat','Select a category.'); });
    fQty.addEventListener('input',function(){ const v=parseInt(this.value); (v>=0&&v<=100000)?clearErr(this,'err-qty'):showErr(this,'err-qty','Enter 0 or more.'); });
    fContact.addEventListener('input',function(){
        const v=this.value.trim();
        if(!v){ showErr(this,'err-contact','Required.'); return; }
        (/^\d{10}$/.test(v)||/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))?clearErr(this,'err-contact'):showErr(this,'err-contact','Valid phone or email required.');
    });
    fUpi.addEventListener('input',function(){
        const v=this.value.trim();
        if(!v){ showErr(this,'err-upi','UPI ID is required.'); return; }
        (/^[\w.\-]+@[\w.\-]+$/.test(v)||/^\d{10}$/.test(v))?clearErr(this,'err-upi'):showErr(this,'err-upi','Valid UPI ID required (e.g. name@bank).');
    });

    function previewQR(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('qrPreview');
        if(!file) { preview.style.display='none'; return; }
        const reader = new FileReader();
        reader.onload = e => { preview.src = e.target.result; preview.style.display='block'; };
        reader.readAsDataURL(file);
    }

    document.getElementById('editMerchForm').addEventListener('submit', function(e) {
        let ok=true;
        const nv=fName.value.trim();
        if(nv.length<3||nv.length>150){ showErr(fName,'err-name','3–150 chars.'); ok=false; }
        else if(!/^[a-zA-Z0-9\s\-']+$/.test(nv)){ showErr(fName,'err-name','No special chars.'); ok=false; }
        if(fDesc.value.trim().length<10){ showErr(fDesc,'err-desc','Min 10 chars.'); ok=false; }
        if(parseFloat(fPrice.value)<=0){ showErr(fPrice,'err-price','Valid price required.'); ok=false; }
        if(!fCat.value){ showErr(fCat,'err-cat','Select category.'); ok=false; }
        const qv=parseInt(fQty.value);
        if(isNaN(qv)||qv<0){ showErr(fQty,'err-qty','0 or more required.'); ok=false; }
        const cv=fContact.value.trim();
        if(!cv||(!(/^\d{10}$/.test(cv))&&!(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(cv)))){ showErr(fContact,'err-contact','Valid phone or email required.'); ok=false; }
        const uv=fUpi.value.trim();
        if(!uv||(!(/^[\w.\-]+@[\w.\-]+$/.test(uv))&&!(/^\d{10}$/.test(uv)))){ showErr(fUpi,'err-upi','Valid UPI ID required.'); ok=false; }
        if(!ok) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>