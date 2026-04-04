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
    header("Location: manage_events.php");
    exit();
}
$event_id = intval($_GET['id']);

// ── Fetch event using prepared statement ──────────────────────────────────────
$fetch = mysqli_prepare($conn, "SELECT * FROM events WHERE id = ? AND organizer_id = ?");
mysqli_stmt_bind_param($fetch, "ii", $event_id, $organizer_id);
mysqli_stmt_execute($fetch);
$event_result = mysqli_stmt_get_result($fetch);
if(mysqli_num_rows($event_result) == 0) {
    mysqli_stmt_close($fetch);
    header("Location: manage_events.php");
    exit();
}
$event = mysqli_fetch_assoc($event_result);
mysqli_stmt_close($fetch);

// ─── Whitelist constants ──────────────────────────────────────────────────────
$allowed_cats = ['Technical','Cultural','Sports','Workshop','Seminar','Competition','Other'];

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_event'])) {

    $title                 = trim(strip_tags($_POST['title']                ?? ''));
    $description           = trim(strip_tags($_POST['description']          ?? ''));
    $event_date            = trim($_POST['event_date']                       ?? '');
    $venue                 = trim(strip_tags($_POST['venue']                 ?? ''));
    $category              = trim(strip_tags($_POST['category']              ?? ''));
    $max_participants      = intval($_POST['max_participants']               ?? 0);
    $registration_deadline = trim($_POST['registration_deadline']            ?? '');

    // ── Validation ────────────────────────────────────────────────────────────
    if(empty($title) || empty($description) || empty($event_date) || empty($venue)) {
        $error = "Please fill all required fields.";
    } elseif(mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $error = "Event title must be between 3 and 150 characters.";
    } elseif(mb_strlen($description) < 10) {
        $error = "Event description must be at least 10 characters.";
    } elseif(!empty($category) && !in_array($category, $allowed_cats)) {
        $error = "Please select a valid category.";
    } elseif($max_participants < 1 || $max_participants > 100000) {
        $error = "Maximum participants must be between 1 and 100,000.";
    } elseif(!empty($registration_deadline) && !empty($event_date) &&
             strtotime($registration_deadline) >= strtotime($event_date)) {
        $error = "Registration deadline must be before the event date.";
    } else {
        $image_name = $event['image']; // Keep existing image by default

        // ── Handle new image upload ───────────────────────────────────────────
        if(isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
            $file_type    = mime_content_type($_FILES['event_image']['tmp_name']);
            $file_size    = $_FILES['event_image']['size'];

            if(!in_array($file_type, $allowed_mime)) {
                $error = "Only JPG, PNG & GIF files are allowed.";
            } elseif($file_size > 5242880) {
                $error = "Image must be less than 5MB.";
            } else {
                // Delete old image safely
                if(!empty($event['image']) && file_exists('../uploads/' . $event['image'])) {
                    unlink('../uploads/' . $event['image']);
                }
                $ext        = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                $image_name = 'event_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
                if(!move_uploaded_file($_FILES['event_image']['tmp_name'], '../uploads/' . $image_name)) {
                    $error      = "Failed to upload image. Please try again.";
                    $image_name = $event['image'];
                }
            }
        }

        // Handle image removal
        if(isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if(!empty($event['image']) && file_exists('../uploads/' . $event['image'])) {
                unlink('../uploads/' . $event['image']);
            }
            $image_name = "";
        }

        // ── UPDATE with prepared statement ────────────────────────────────────
        if(empty($error)) {
            $deadline_val = !empty($registration_deadline) ? $registration_deadline : $event_date;
            $cat_val      = !empty($category)              ? $category              : null;

            $upd = mysqli_prepare($conn,
                "UPDATE events SET
                 title=?, description=?, event_date=?, venue=?, category=?,
                 max_participants=?, registration_deadline=?, image=?
                 WHERE id=? AND organizer_id=?"
            );
            mysqli_stmt_bind_param($upd, "sssssissii",
                $title, $description, $event_date, $venue, $cat_val,
                $max_participants, $deadline_val, $image_name,
                $event_id, $organizer_id
            );

            if(mysqli_stmt_execute($upd)) {
                $success = "Event updated successfully! Redirecting...";
                header("refresh:2;url=manage_events.php");
            } else {
                $error = "An error occurred while updating. Please try again.";
            }
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
    <title>Edit Event - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .field-error   { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid, textarea.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid,   textarea.input-valid   { border-color:#48bb78 !important; }
        .char-counter  { font-size:12px; color:#718096; margin-top:3px; text-align:right; }
        .btn-primary:disabled { opacity:0.45; cursor:not-allowed; pointer-events:none; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1>✏️ Edit Event</h1>
                <a href="manage_events.php" class="btn btn-secondary">← Back to Events</a>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div style="background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="" enctype="multipart/form-data" id="editEventForm" novalidate>

                    <div class="form-group">
                        <label>Event Title * <small style="color:#718096;">(3–150 chars)</small></label>
                        <input type="text" name="title" id="evTitle" required maxlength="150"
                               value="<?php echo htmlspecialchars($event['title']); ?>">
                        <div class="char-counter"><span id="titleCount"><?php echo mb_strlen($event['title']); ?></span>/150</div>
                        <span class="field-error" id="err-title">Event title must be 3–150 characters.</span>
                    </div>

                    <div class="form-group">
                        <label>Description * <small style="color:#718096;">(min 10 chars)</small></label>
                        <textarea name="description" id="evDesc" rows="5" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                        <span class="field-error" id="err-desc">Description must be at least 10 characters.</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Event Date &amp; Time *</label>
                            <input type="datetime-local" name="event_date" id="evDate" required
                                   value="<?php echo date('Y-m-d\TH:i', strtotime($event['event_date'])); ?>">
                            <span class="field-error" id="err-date">Please select a valid event date.</span>
                        </div>
                        <div class="form-group">
                            <label>Registration Deadline</label>
                            <input type="datetime-local" name="registration_deadline" id="evDeadline"
                                   value="<?php 
                                       if($event['registration_deadline']) {
                                           echo date('Y-m-d\TH:i', strtotime($event['registration_deadline']));
                                       } else {
                                           echo date('Y-m-d\TH:i', strtotime($event['event_date']));
                                       }
                                   ?>">
                            <span class="field-error" id="err-deadline">Deadline must be before the event date.</span>
                            <small style="color:#718096;font-size:12px;">ℹ️ Defaults to event date &amp; time if left unchanged.</small>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Venue *</label>
                            <input type="text" name="venue" id="evVenue" required
                                   value="<?php echo htmlspecialchars($event['venue']); ?>">
                            <span class="field-error" id="err-venue">Venue is required.</span>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="">Select Category</option>
                                <?php $cats = ['Technical','Cultural','Sports','Workshop','Seminar','Competition','Other'];
                                foreach($cats as $c): $sel = ($event['category']==$c) ? 'selected' : ''; ?>
                                <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Maximum Participants *</label>
                        <input type="number" name="max_participants" id="evMax" min="1" max="100000" required
                               value="<?php echo $event['max_participants']; ?>">
                        <span class="field-error" id="err-max">Enter a number between 1 and 100,000.</span>
                    </div>

                    <!-- Image section (design unchanged) -->
                    <div class="form-group">
                        <label>Event Image</label>
                        <?php if($event['image']): ?>
                            <div style="margin-bottom:15px;padding:15px;background:#f7fafc;border-radius:8px;">
                                <p style="font-weight:500;margin-bottom:10px;">Current Image:</p>
                                <img src="../uploads/<?php echo htmlspecialchars($event['image']); ?>"
                                     style="max-width:300px;border-radius:8px;border:2px solid #e2e8f0;">
                                <br><br>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="remove_image" value="1" id="removeImage">
                                    <span style="color:#f56565;font-weight:500;">Remove this image</span>
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="event_image" accept="image/*" id="eventImage" onchange="previewImage(event)">
                        <span class="field-error" id="err-image">Only JPG, PNG, GIF allowed. Max 5MB.</span>
                        <small style="color:#718096;font-size:13px;">Upload new image (JPG, PNG, GIF – max 5MB)</small>
                        <div id="imagePreview" style="margin-top:15px;display:none;">
                            <p style="font-weight:500;margin-bottom:10px;">New Image Preview:</p>
                            <img id="preview" style="max-width:300px;border-radius:8px;border:2px solid #e2e8f0;">
                        </div>
                    </div>

                    <div style="display:flex;gap:15px;margin-top:30px;">
                        <button type="submit" name="update_event" id="updateBtn" class="btn btn-primary" disabled>✓ Update Event</button>
                        <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    /* ── Real-time validation — edit_event.php ── */
    function showErr(f,id,msg){ f.classList.add('input-invalid'); f.classList.remove('input-valid'); const el=document.getElementById(id); if(el){el.textContent=msg;el.style.display='block';} }
    function clearErr(f,id){ f.classList.remove('input-invalid'); f.classList.add('input-valid'); const el=document.getElementById(id); if(el)el.style.display='none'; }

    const fTitle    = document.getElementById('evTitle');
    const fDesc     = document.getElementById('evDesc');
    const fDate     = document.getElementById('evDate');
    const fDeadline = document.getElementById('evDeadline');
    const fVenue    = document.getElementById('evVenue');
    const fMax      = document.getElementById('evMax');
    const fCat      = document.querySelector('select[name="category"]');
    const updateBtn = document.getElementById('updateBtn');

    /* ── Snapshot original values on page load ── */
    const orig = {
        title:    fTitle.value,
        desc:     fDesc.value,
        date:     fDate.value,
        deadline: fDeadline.value,
        venue:    fVenue.value,
        max:      fMax.value,
        category: fCat.value,
        imageFile: false,
        removeImage: false
    };

    /* ── Check if anything has actually changed ── */
    function isDirty() {
        return fTitle.value    !== orig.title    ||
               fDesc.value     !== orig.desc     ||
               fDate.value     !== orig.date     ||
               fDeadline.value !== orig.deadline ||
               fVenue.value    !== orig.venue    ||
               fMax.value      !== orig.max      ||
               fCat.value      !== orig.category ||
               orig.imageFile  ||
               orig.removeImage;
    }
    function syncBtn() { updateBtn.disabled = !isDirty(); }

    fTitle.addEventListener('input', function(){
        document.getElementById('titleCount').textContent = this.value.length;
        const v = this.value.trim();
        (v.length >= 3 && v.length <= 150) ? clearErr(this,'err-title') : showErr(this,'err-title','Event title must be 3–150 characters.');
        syncBtn();
    });
    fDesc.addEventListener('input', function(){
        this.value.trim().length >= 10 ? clearErr(this,'err-desc') : showErr(this,'err-desc','Description must be at least 10 characters.');
        syncBtn();
    });
    fDate.addEventListener('change', function(){ if(fDeadline.value) valDeadline(); syncBtn(); });
    function valDeadline() {
        if(fDeadline.value && fDate.value && new Date(fDeadline.value) >= new Date(fDate.value)){
            showErr(fDeadline,'err-deadline','Deadline must be before the event date.'); return false;
        }
        clearErr(fDeadline,'err-deadline'); return true;
    }
    fDeadline.addEventListener('change', function(){ valDeadline(); syncBtn(); });
    fVenue.addEventListener('input', function(){ this.value.trim() ? clearErr(this,'err-venue') : showErr(this,'err-venue','Venue is required.'); syncBtn(); });
    fMax.addEventListener('input', function(){ const v=parseInt(this.value); (v>=1&&v<=100000)?clearErr(this,'err-max'):showErr(this,'err-max','Must be between 1 and 100,000.'); syncBtn(); });
    fCat.addEventListener('change', function(){ syncBtn(); });

    function previewImage(event) {
        const file = event.target.files[0];
        const el   = document.getElementById('eventImage');
        if(!file) { orig.imageFile = false; syncBtn(); return; }
        const allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
        if(!allowed.includes(file.type)){ showErr(el,'err-image','Only JPG, PNG, GIF images are allowed.'); el.value=''; orig.imageFile=false; syncBtn(); return; }
        if(file.size > 5*1024*1024)     { showErr(el,'err-image','Image must be less than 5MB.'); el.value=''; orig.imageFile=false; syncBtn(); return; }
        clearErr(el,'err-image');
        orig.imageFile = true;
        syncBtn();
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('preview').src=e.target.result; document.getElementById('imagePreview').style.display='block'; };
        reader.readAsDataURL(file);
        const cb = document.getElementById('removeImage');
        if(cb) cb.checked = false;
    }

    const removeImageCb = document.getElementById('removeImage');
    if(removeImageCb) {
        removeImageCb.addEventListener('change', function(){
            orig.removeImage = this.checked;
            if(this.checked){ document.getElementById('imagePreview').style.display='none'; document.getElementById('eventImage').value=''; orig.imageFile=false; }
            syncBtn();
        });
    }

    document.getElementById('editEventForm').addEventListener('submit', function(e){
        let ok = true;
        const tv = fTitle.value.trim();
        if(tv.length < 3 || tv.length > 150){ showErr(fTitle,'err-title','Event title must be 3–150 characters.'); ok=false; }
        if(fDesc.value.trim().length < 10){ showErr(fDesc,'err-desc','Description must be at least 10 characters.'); ok=false; }
        if(!fDate.value){ showErr(fDate,'err-date','Please select an event date.'); ok=false; }
        if(!valDeadline()) ok=false;
        if(!fVenue.value.trim()){ showErr(fVenue,'err-venue','Venue is required.'); ok=false; }
        const mp = parseInt(fMax.value);
        if(isNaN(mp)||mp<1||mp>100000){ showErr(fMax,'err-max','Must be between 1 and 100,000.'); ok=false; }
        if(!ok) e.preventDefault();
    });
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>