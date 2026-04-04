<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is organizer
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'organizer') {
    header("Location: ../login.php");
    exit();
}

$error   = "";
$success = "";

// ─── Server-side whitelist constants ─────────────────────────────────────────
$allowed_types = ['offline', 'online', 'hybrid'];
$allowed_cats  = ['Technical','Cultural','Sports','Workshop','Seminar','Competition','Other'];

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_event'])) {

    // Sanitize all text inputs
    $title                = trim(strip_tags($_POST['title']               ?? ''));
    $description          = trim(strip_tags($_POST['description']         ?? ''));
    $event_date           = trim($_POST['event_date']                     ?? '');
    $venue                = trim(strip_tags($_POST['venue']               ?? ''));
    $event_type           = trim(strip_tags($_POST['event_type']          ?? ''));
    $category             = trim(strip_tags($_POST['category']            ?? ''));
    $max_participants     = intval($_POST['max_participants']              ?? 0);
    $registration_deadline= trim($_POST['registration_deadline']          ?? '');
    $registration_link    = trim(strip_tags($_POST['registration_link']   ?? ''));
    $organizer_id         = $_SESSION['user_id'];

    // ── Required field checks ────────────────────────────────────────────────
    if(empty($title) || empty($description) || empty($event_date) || empty($venue) || empty($event_type) || empty($category)) {
        $error = "Please fill all required fields.";
    }
    // Title length
    elseif(mb_strlen($title) < 3 || mb_strlen($title) > 150) {
        $error = "Event title must be between 3 and 150 characters.";
    }
    // Description minimum
    elseif(mb_strlen($description) < 10) {
        $error = "Event description must be at least 10 characters.";
    }
    // Event type whitelist
    elseif(!in_array($event_type, $allowed_types)) {
        $error = "Please select a valid event type.";
    }
    // Category whitelist (now required)
    elseif(!in_array($category, $allowed_cats)) {
        $error = "Please select a valid category.";
    }
    // Max participants
    elseif($max_participants < 1 || $max_participants > 100000) {
        $error = "Maximum participants must be between 1 and 100,000.";
    }
    // Event date must be in the future
    elseif(strtotime($event_date) <= time()) {
        $error = "Event date must be in the future.";
    }
    // Registration deadline must be before event date
    elseif(!empty($registration_deadline) && strtotime($registration_deadline) >= strtotime($event_date)) {
        $error = "Registration deadline must be before the event date.";
    }
    // Registration link: validate URL if provided
    elseif(!empty($registration_link) && !filter_var($registration_link, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL for the external registration link.";
    } else {
        // ── Image upload handling ────────────────────────────────────────────
        $image_name = "";
        if(isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0) {
            $allowed_mime = ['image/jpeg','image/jpg','image/png','image/gif'];
            $file_type    = mime_content_type($_FILES['event_image']['tmp_name']); // more secure than $_FILES type
            $file_size    = $_FILES['event_image']['size'];

            if(!in_array($file_type, $allowed_mime)) {
                $error = "Only JPG, PNG & GIF image files are allowed.";
            } elseif($file_size > 5242880) {
                $error = "Image file size must be less than 5MB.";
            } else {
                $ext        = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                $image_name = 'event_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
                $upload_path= '../uploads/' . $image_name;
                if(!move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                    $error      = "Failed to upload image. Please try again.";
                    $image_name = "";
                }
            }
        }

        // ── Insert with prepared statement ───────────────────────────────────
        if(empty($error)) {
            $deadline_val = !empty($registration_deadline) ? $registration_deadline : $event_date;
            $link_val     = !empty($registration_link)     ? $registration_link     : null;
            $cat_val      = !empty($category)              ? $category              : null;

            $ins = mysqli_prepare($conn,
                "INSERT INTO events
                 (title, description, organizer_id, event_date, venue, event_type, category,
                  max_participants, registration_deadline, registration_link, image, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')"
            );
            mysqli_stmt_bind_param($ins, "ssissssiss",
                $title, $description, $organizer_id, $event_date, $venue,
                $event_type, $cat_val, $max_participants, $deadline_val, $link_val, $image_name
            );

            if(mysqli_stmt_execute($ins)) {
                $success = "Event created successfully! Redirecting...";
                header("refresh:2;url=manage_events.php");
            } else {
                $error = "An error occurred while creating the event. Please try again.";
                // Clean up uploaded image if DB insert failed
                if(!empty($image_name) && file_exists('../uploads/' . $image_name)) {
                    unlink('../uploads/' . $image_name);
                }
            }
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
    <title>Create Event - Campus Event Manager</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .field-error   { color:#c53030; font-size:13px; margin-top:4px; display:none; }
        input.input-invalid, select.input-invalid, textarea.input-invalid { border-color:#f56565 !important; }
        input.input-valid,   select.input-valid,   textarea.input-valid   { border-color:#48bb78 !important; }
        .char-counter  { font-size:12px; color:#718096; margin-top:3px; text-align:right; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1>➕ Create New Event</h1>
                <a href="manage_events.php" class="btn btn-secondary">← Back to Events</a>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div style="background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                <form method="POST" action="" enctype="multipart/form-data" id="createEventForm" novalidate>

                    <div class="form-group">
                        <label>Event Title * <small style="color:#718096;">(3–150 characters)</small></label>
                        <input type="text" name="title" id="evTitle" placeholder="e.g., Tech Fest 2025" required
                               maxlength="150"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                        <div class="char-counter"><span id="titleCount">0</span>/150</div>
                        <span class="field-error" id="err-title">Event title is required (3–150 characters).</span>
                    </div>

                    <div class="form-group">
                        <label>Description * <small style="color:#718096;">(min 10 characters)</small></label>
                        <textarea name="description" id="evDesc" rows="5"
                                  placeholder="Describe your event..." required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        <span class="field-error" id="err-desc">Description must be at least 10 characters.</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Event Date &amp; Time *</label>
                            <input type="datetime-local" name="event_date" id="evDate" required
                                   value="<?php echo isset($_POST['event_date']) ? htmlspecialchars($_POST['event_date']) : ''; ?>">
                            <span class="field-error" id="err-date">Event date must be in the future.</span>
                        </div>
                        <div class="form-group">
                            <label>Registration Deadline</label>
                            <input type="datetime-local" name="registration_deadline" id="evDeadline"
                                   value="<?php echo isset($_POST['registration_deadline']) ? htmlspecialchars($_POST['registration_deadline']) : ''; ?>">
                            <span class="field-error" id="err-deadline">Deadline must be before the event date.</span>
                            <small style="color:#718096;font-size:12px;">ℹ️ Optional — if not set, registration will automatically close at the event date &amp; time.</small>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Venue *</label>
                            <input type="text" name="venue" id="evVenue" placeholder="e.g., Main Auditorium" required
                                   value="<?php echo isset($_POST['venue']) ? htmlspecialchars($_POST['venue']) : ''; ?>">
                            <span class="field-error" id="err-venue">Venue is required.</span>
                        </div>
                        <div class="form-group">
                            <label>Event Type *</label>
                            <select name="event_type" id="evType" required>
                                <option value="">Select Type</option>
                                <option value="offline" <?php echo (isset($_POST['event_type']) && $_POST['event_type']=='offline') ? 'selected' : ''; ?>>Offline (In-Person)</option>
                                <option value="online"  <?php echo (isset($_POST['event_type']) && $_POST['event_type']=='online')  ? 'selected' : ''; ?>>Online (Virtual)</option>
                                <option value="hybrid"  <?php echo (isset($_POST['event_type']) && $_POST['event_type']=='hybrid')  ? 'selected' : ''; ?>>Hybrid (Both)</option>
                            </select>
                            <span class="field-error" id="err-type">Please select an event type.</span>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" id="evCat" required>
                                <option value="">Select Category</option>
                                <?php $cats = ['Technical','Cultural','Sports','Workshop','Seminar','Competition','Other'];
                                foreach($cats as $c): $sel = (isset($_POST['category']) && $_POST['category']==$c) ? 'selected' : ''; ?>
                                <option value="<?php echo $c; ?>" <?php echo $sel; ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-error" id="err-cat">Please select a category.</span>
                        </div>
                        <div class="form-group">
                            <label>Maximum Participants *</label>
                            <input type="number" name="max_participants" id="evMax" min="1" max="100000"
                                   value="<?php echo isset($_POST['max_participants']) ? intval($_POST['max_participants']) : 100; ?>" required>
                            <small style="color:#718096;font-size:13px;">Set the maximum number of registrations allowed.</small>
                            <span class="field-error" id="err-max">Enter a number between 1 and 100,000.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>External Registration Link <small style="color:#718096;">(Optional — must start with https://)</small></label>
                        <input type="url" name="registration_link" id="evLink"
                               placeholder="https://forms.google.com/..."
                               value="<?php echo isset($_POST['registration_link']) ? htmlspecialchars($_POST['registration_link']) : ''; ?>">
                        <span class="field-error" id="err-link">Enter a valid URL (e.g. https://example.com).</span>
                        <small style="color:#718096;font-size:13px;">Use this for Google Forms or external registration platforms.</small>
                    </div>

                    <div class="form-group">
                        <label>Event Image <small style="color:#718096;">(Optional — JPG, PNG, GIF, max 5MB)</small></label>
                        <input type="file" name="event_image" accept="image/*" id="eventImage" onchange="previewImage(event)">
                        <span class="field-error" id="err-image">Only JPG, PNG, GIF allowed. Max 5MB.</span>
                        <div id="imagePreview" style="margin-top:15px;display:none;">
                            <p style="font-weight:500;margin-bottom:10px;">Image Preview:</p>
                            <img id="preview" style="max-width:300px;border-radius:8px;border:2px solid #e2e8f0;">
                        </div>
                    </div>

                    <div style="display:flex;gap:15px;margin-top:30px;">
                        <button type="submit" name="create_event" class="btn btn-primary">✓ Create Event</button>
                        <a href="manage_events.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
    /* ── Real-time validation — create_event.php ── */

    function showErr(field, errId, msg) {
        field.classList.add('input-invalid'); field.classList.remove('input-valid');
        const el = document.getElementById(errId);
        if(el){ el.textContent = msg; el.style.display='block'; }
    }
    function clearErr(field, errId) {
        field.classList.remove('input-invalid'); field.classList.add('input-valid');
        const el = document.getElementById(errId);
        if(el) el.style.display='none';
    }

    const fTitle    = document.getElementById('evTitle');
    const fDesc     = document.getElementById('evDesc');
    const fDate     = document.getElementById('evDate');
    const fDeadline = document.getElementById('evDeadline');
    const fVenue    = document.getElementById('evVenue');
    const fType     = document.getElementById('evType');
    const fCat      = document.getElementById('evCat');
    const fMax      = document.getElementById('evMax');
    const fLink     = document.getElementById('evLink');

    // Character counter for title
    fTitle.addEventListener('input', function(){
        document.getElementById('titleCount').textContent = this.value.length;
        const v = this.value.trim();
        (v.length >= 3 && v.length <= 150) ? clearErr(this,'err-title') : showErr(this,'err-title','Event title must be 3–150 characters.');
    });

    fDesc.addEventListener('input', function(){
        this.value.trim().length >= 10 ? clearErr(this,'err-desc') : showErr(this,'err-desc','Description must be at least 10 characters.');
    });

    fDate.addEventListener('change', function(){
        const now = new Date();
        const sel = new Date(this.value);
        if(!this.value || sel <= now) { showErr(this,'err-date','Event date must be in the future.'); return; }
        clearErr(this,'err-date');
        // Re-validate deadline
        if(fDeadline.value) valDeadline();
    });

    function valDeadline() {
        if(!fDeadline.value) {
            // Empty deadline is fine — just remove any error/valid styling, don't go green
            fDeadline.classList.remove('input-invalid','input-valid');
            const el = document.getElementById('err-deadline');
            if(el) el.style.display = 'none';
            return true;
        }
        const evDate = new Date(fDate.value);
        const dl     = new Date(fDeadline.value);
        if(fDate.value && dl >= evDate) {
            showErr(fDeadline,'err-deadline','Deadline must be before the event date.'); return false;
        }
        clearErr(fDeadline,'err-deadline'); return true;
    }
    fDeadline.addEventListener('change', valDeadline);

    fVenue.addEventListener('input', function(){
        this.value.trim() ? clearErr(this,'err-venue') : showErr(this,'err-venue','Venue is required.');
    });
    fType.addEventListener('change', function(){
        this.value ? clearErr(this,'err-type') : showErr(this,'err-type','Please select an event type.');
    });
    fCat.addEventListener('change', function(){
        this.value ? clearErr(this,'err-cat') : showErr(this,'err-cat','Please select a category.');
    });
    fMax.addEventListener('input', function(){
        const v = parseInt(this.value);
        (v >= 1 && v <= 100000) ? clearErr(this,'err-max') : showErr(this,'err-max','Must be between 1 and 100,000.');
    });

    fLink.addEventListener('input', function(){
        const v = this.value.trim();
        if(!v){ clearErr(this,'err-link'); return; }
        try { new URL(v); clearErr(this,'err-link'); }
        catch(e){ showErr(this,'err-link','Enter a valid URL (e.g. https://example.com).'); }
    });

    // Image preview + client-side type/size check
    function previewImage(event) {
        const file = event.target.files[0];
        const el   = document.getElementById('eventImage');
        if(!file) return;
        const allowed = ['image/jpeg','image/jpg','image/png','image/gif'];
        if(!allowed.includes(file.type))  { showErr(el,'err-image','Only JPG, PNG, GIF images are allowed.'); el.value=''; return; }
        if(file.size > 5 * 1024 * 1024)  { showErr(el,'err-image','Image must be less than 5MB.'); el.value=''; return; }
        clearErr(el,'err-image');
        const reader = new FileReader();
        reader.onload = e => { document.getElementById('preview').src = e.target.result; document.getElementById('imagePreview').style.display='block'; };
        reader.readAsDataURL(file);
    }

    // Pre-submit validation
    document.getElementById('createEventForm').addEventListener('submit', function(e) {
        let ok = true;
        const now = new Date();

        const tv = fTitle.value.trim();
        if(tv.length < 3 || tv.length > 150){ showErr(fTitle,'err-title','Event title must be 3–150 characters.'); ok=false; }

        if(fDesc.value.trim().length < 10){ showErr(fDesc,'err-desc','Description must be at least 10 characters.'); ok=false; }

        if(!fDate.value || new Date(fDate.value) <= now){ showErr(fDate,'err-date','Event date must be in the future.'); ok=false; }

        if(!valDeadline()) ok=false;

        if(!fVenue.value.trim()){ showErr(fVenue,'err-venue','Venue is required.'); ok=false; }

        if(!fType.value){ showErr(fType,'err-type','Please select an event type.'); ok=false; }

        if(!fCat.value){ showErr(fCat,'err-cat','Please select a category.'); ok=false; }

        const mp = parseInt(fMax.value);
        if(isNaN(mp) || mp < 1 || mp > 100000){ showErr(fMax,'err-max','Must be between 1 and 100,000.'); ok=false; }

        if(fLink.value.trim()) {
            try { new URL(fLink.value.trim()); }
            catch(e){ showErr(fLink,'err-link','Enter a valid URL.'); ok=false; }
        }

        if(!ok) e.preventDefault();
    });

    // Initialize title counter
    document.getElementById('titleCount').textContent = fTitle.value.length;
    </script>
    <script src="../assets/js/script.js"></script>
</body>
</html>