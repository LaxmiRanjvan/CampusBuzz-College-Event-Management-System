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

// ── Fetch merchandise using prepared statement ──────────────────────────────────
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

// ── Fetch existing images ───────────────────────────────────────────────────────
$img_stmt = mysqli_prepare($conn, "SELECT * FROM merchandise_images WHERE merchandise_id = ? ORDER BY is_primary DESC");
mysqli_stmt_bind_param($img_stmt, "i", $merch_id);
mysqli_stmt_execute($img_stmt);
$images_result = mysqli_stmt_get_result($img_stmt);
mysqli_stmt_close($img_stmt);

// ─── Whitelist ──────────────────────────────────────────────────────────────────
$allowed_cats = ['t-shirt','oversized-tshirt','hoodie','cap','tote-bag','cup',
                 'sweatshirt','mask','diary','magazine','other'];

// ── Handle image deletion ───────────────────────────────────────────────────────
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
        // Refresh images
        $ri = mysqli_prepare($conn, "SELECT * FROM merchandise_images WHERE merchandise_id = ? ORDER BY is_primary DESC");
        mysqli_stmt_bind_param($ri, "i", $merch_id);
        mysqli_stmt_execute($ri);
        $images_result = mysqli_stmt_get_result($ri);
        mysqli_stmt_close($ri);
    }
}

// ── Handle update ───────────────────────────────────────────────────────────────
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_merchandise'])) {

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

    // Contact: phone (10 digits) or email
    $contact_is_phone = preg_match('/^\d{10}$/', $contact_info);
    $contact_is_email = filter_var($contact_info, FILTER_VALIDATE_EMAIL);

    // Validation
    if(empty($name) || empty($description) || empty($category) || empty($contact_info) || empty($order_form_link)) {
        $error = "Please fill all required fields.";
    } elseif(mb_strlen($name) < 3 || mb_strlen($name) > 150) {
        $error = "Product name must be between 3 and 150 characters.";
    } elseif(!preg_match('/^[a-zA-Z0-9\s\-\']+$/', $name)) {
        $error = "Product name must not contain special characters.";
    } elseif(!in_array($category, $allowed_cats)) {
        $error = "Please select a valid category.";
    } elseif($price <= 0) {
        $error = "Price must be greater than ₹0.";
    } elseif($quantity_available < 10 || $quantity_available > 100000) {
        $error = "Quantity must be at least 10 and no more than 100,000.";
    } elseif(!$contact_is_phone && !$contact_is_email) {
        $error = "Contact info must be a valid 10-digit phone number or a valid email address.";
    } elseif(!filter_var($order_form_link, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL for the order form link.";
    } else {
        $dist_date_val = !empty($distribution_date) ? $distribution_date : null;
        $dist_time_val = !empty($distribution_time) ? $distribution_time : null;

        // ── UPDATE with prepared statement ────────────────────────────────────
        $upd = mysqli_prepare($conn,
            "UPDATE merchandise SET name=?, description=?, price=?, category=?, sizes_available=?,
             size_guide=?, quantity_available=?, contact_info=?, order_form_link=?, return_policy=?,
             distribution_date=?, distribution_venue=?, distribution_time=?
             WHERE id=? AND organizer_id=?"
        );
        mysqli_stmt_bind_param($upd, "ssdsssissssssii",
            $name, $description, $price, $category, $sizes_available,
            $size_guide, $quantity_available, $contact_info, $order_form_link, $return_policy,
            $dist_date_val, $distribution_venue, $dist_time_val,
            $merch_id, $organizer_id
        );

        if(mysqli_stmt_execute($upd)) {
            // Handle new image uploads
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

            $ri2 = mysqli_prepare($conn, "SELECT * FROM merchandise_images WHERE merchandise_id=? ORDER BY is_primary DESC");
            mysqli_stmt_bind_param($ri2, "i", $merch_id);
            mysqli_stmt_execute($ri2);
            $images_result = mysqli_stmt_get_result($ri2);
            mysqli_stmt_close($ri2);
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
        .field-error { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid, textarea.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid,   textarea.input-valid   { border-color:#48bb78 !important; }
        .btn-primary:disabled { opacity:0.45; cursor:not-allowed; pointer-events:none; }
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

                <!-- Existing images (design unchanged) -->
                <?php
                // Re-fetch images for display (result may have been consumed)
                $disp_img = mysqli_prepare($conn, "SELECT * FROM merchandise_images WHERE merchandise_id=? ORDER BY is_primary DESC");
                mysqli_stmt_bind_param($disp_img, "i", $merch_id);
                mysqli_stmt_execute($disp_img);
                $disp_img_result = mysqli_stmt_get_result($disp_img);
                mysqli_stmt_close($disp_img);
                if(mysqli_num_rows($disp_img_result) > 0): ?>
                <div style="margin-bottom:30px;">
                    <h3 style="margin-bottom:15px;color:#2d3748;">Current Images</h3>
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
                            <span class="field-error" id="err-price">Enter a valid price greater than ₹0.</span>
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
                            <label>Quantity Available * </label>
                            <input type="number" name="quantity_available" id="mQty" min="10" max="100000" required value="<?php echo $merch['quantity_available']; ?>">
                            <span class="field-error" id="err-qty">Quantity must be at least 10.</span>
                        </div>
                    </div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📏 Size Information</h3>
                    <div class="form-group"><label>Sizes Available</label><input type="text" name="sizes_available" id="mSizes" value="<?php echo htmlspecialchars($merch['sizes_available'] ?? ''); ?>"></div>
                    <div class="form-group"><label>Size Guide</label><textarea name="size_guide" id="mSizeGuide" rows="3"><?php echo htmlspecialchars($merch['size_guide'] ?? ''); ?></textarea></div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📸 Add More Images</h3>
                    <div class="form-group">
                        <label>Upload Additional Images <small style="color:#718096;">(JPG, PNG, GIF — max 5MB each)</small></label>
                        <input type="file" name="new_images[]" multiple accept="image/*">
                    </div>

                    <h3 style="margin:30px 0 20px 0;color:#2d3748;border-bottom:2px solid #e2e8f0;padding-bottom:10px;">📞 Contact &amp; Order Information</h3>
                    <div class="form-group">
                        <label>Contact Information * <small style="color:#718096;">(10-digit phone number or email address)</small></label>
                        <input type="text" name="contact_info" id="mContact" required value="<?php echo htmlspecialchars($merch['contact_info']); ?>">
                        <span class="field-error" id="err-contact">Enter a valid 10-digit phone number or email address.</span>
                    </div>
                    <div class="form-group">
                        <label>Order Form Link *</label>
                        <input type="url" name="order_form_link" id="mOrderLink" required value="<?php echo htmlspecialchars($merch['order_form_link']); ?>">
                        <span class="field-error" id="err-link">Enter a valid URL.</span>
                    </div>

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
    /* ── Real-time validation — edit_merchandise.php ── */
    function showErr(f,id,msg){ f.classList.add('input-invalid');f.classList.remove('input-valid');const el=document.getElementById(id);if(el){el.textContent=msg;el.style.display='block';} }
    function clearErr(f,id){ f.classList.remove('input-invalid');f.classList.add('input-valid');const el=document.getElementById(id);if(el)el.style.display='none'; }

    const fName       = document.getElementById('mName');
    const fDesc       = document.getElementById('mDesc');
    const fPrice      = document.getElementById('mPrice');
    const fCat        = document.getElementById('mCat');
    const fQty        = document.getElementById('mQty');
    const fContact    = document.getElementById('mContact');
    const fLink       = document.getElementById('mOrderLink');
    const fSizes      = document.getElementById('mSizes');
    const fSizeGuide  = document.getElementById('mSizeGuide');
    const fDistDate   = document.getElementById('mDistDate');
    const fDistTime   = document.getElementById('mDistTime');
    const fDistVenue  = document.getElementById('mDistVenue');
    const fRetPolicy  = document.getElementById('mReturnPolicy');
    const updateBtn   = document.getElementById('updateBtn');

    /* ── Snapshot original values on page load ── */
    const orig = {
        name:       fName.value,
        desc:       fDesc.value,
        price:      fPrice.value,
        category:   fCat.value,
        qty:        fQty.value,
        contact:    fContact.value,
        link:       fLink.value,
        sizes:      fSizes ? fSizes.value : '',
        sizeGuide:  fSizeGuide ? fSizeGuide.value : '',
        distDate:   fDistDate ? fDistDate.value : '',
        distTime:   fDistTime ? fDistTime.value : '',
        distVenue:  fDistVenue ? fDistVenue.value : '',
        retPolicy:  fRetPolicy ? fRetPolicy.value : '',
        newImage:   false
    };

    /* ── Check if anything has actually changed ── */
    function isDirty() {
        return fName.value          !== orig.name     ||
               fDesc.value          !== orig.desc     ||
               fPrice.value         !== orig.price    ||
               fCat.value           !== orig.category ||
               fQty.value           !== orig.qty      ||
               fContact.value       !== orig.contact  ||
               fLink.value          !== orig.link     ||
               (fSizes     && fSizes.value     !== orig.sizes)     ||
               (fSizeGuide && fSizeGuide.value !== orig.sizeGuide) ||
               (fDistDate  && fDistDate.value  !== orig.distDate)  ||
               (fDistTime  && fDistTime.value  !== orig.distTime)  ||
               (fDistVenue && fDistVenue.value !== orig.distVenue) ||
               (fRetPolicy && fRetPolicy.value !== orig.retPolicy) ||
               orig.newImage;
    }
    function syncBtn() { updateBtn.disabled = !isDirty(); }

    /* Attach change listeners to all fields */
    [fName,fDesc,fPrice,fQty,fContact,fLink].forEach(f => f.addEventListener('input', syncBtn));
    [fCat].forEach(f => f.addEventListener('change', syncBtn));
    [fSizes,fSizeGuide,fDistDate,fDistTime,fDistVenue,fRetPolicy].forEach(f => { if(f) f.addEventListener('input', syncBtn); if(f) f.addEventListener('change', syncBtn); });

    /* Image upload = always a change */
    const newImgInput = document.querySelector('input[name="new_images[]"]');
    if(newImgInput) newImgInput.addEventListener('change', function(){ orig.newImage = this.files.length > 0; syncBtn(); });

    fName.addEventListener('input',function(){
        const v=this.value.trim();
        if(v.length<3||v.length>150){ showErr(this,'err-name','3–150 characters required.'); return; }
        if(!/^[a-zA-Z0-9\s\-']+$/.test(v)){ showErr(this,'err-name','No special characters allowed.'); return; }
        clearErr(this,'err-name');
    });
    fDesc.addEventListener('input',function(){ this.value.trim().length>=10?clearErr(this,'err-desc'):showErr(this,'err-desc','Minimum 10 characters.'); });
    fPrice.addEventListener('input',function(){ const v=parseFloat(this.value); (v>0)?clearErr(this,'err-price'):showErr(this,'err-price','Enter a valid price greater than ₹0.'); });
    fCat.addEventListener('change',function(){ this.value?clearErr(this,'err-cat'):showErr(this,'err-cat','Select a category.'); });
    fQty.addEventListener('input',function(){ const v=parseInt(this.value); (v>=10&&v<=100000)?clearErr(this,'err-qty'):showErr(this,'err-qty','Quantity must be at least 10.'); });
    fContact.addEventListener('input',function(){ validateContact(this); });
    fLink.addEventListener('input',function(){ const v=this.value.trim(); if(!v){showErr(this,'err-link','Required.'); return;} try{new URL(v);clearErr(this,'err-link');}catch(e){showErr(this,'err-link','Valid URL required.');} });

    function validateContact(field) {
        const v = field.value.trim();
        if(!v){ showErr(field,'err-contact','Contact information is required.'); return false; }
        const isAllDigits = /^\d+$/.test(v);
        if(isAllDigits) {
            if(v.length === 10){ clearErr(field,'err-contact'); return true; }
            else { showErr(field,'err-contact','Phone number must be exactly 10 digits (no letters or symbols).'); return false; }
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if(emailRegex.test(v)){ clearErr(field,'err-contact'); return true; }
        showErr(field,'err-contact','Enter a valid 10-digit phone number or a valid email address.'); return false;
    }

    document.getElementById('editMerchForm').addEventListener('submit',function(e){
        let ok=true;
        const nv=fName.value.trim();
        if(nv.length<3||nv.length>150){ showErr(fName,'err-name','3–150 chars required.'); ok=false; }
        else if(!/^[a-zA-Z0-9\s\-']+$/.test(nv)){ showErr(fName,'err-name','No special characters allowed.'); ok=false; }
        if(fDesc.value.trim().length<10){ showErr(fDesc,'err-desc','Min 10 chars.'); ok=false; }
        if(parseFloat(fPrice.value)<=0){ showErr(fPrice,'err-price','Valid price required.'); ok=false; }
        if(!fCat.value){ showErr(fCat,'err-cat','Select category.'); ok=false; }
        const qv=parseInt(fQty.value);
        if(isNaN(qv)||qv<10){ showErr(fQty,'err-qty','Quantity must be at least 10.'); ok=false; }
        if(!validateContact(fContact)) ok=false;
        const lv=fLink.value.trim();
        if(!lv){ showErr(fLink,'err-link','Required.'); ok=false; }
        else { try{new URL(lv);}catch(ex){ showErr(fLink,'err-link','Valid URL required.'); ok=false; } }
        if(!ok) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>