<!-- Mobile Menu Toggle Button -->
<button id="sidebarToggle" class="sidebar-toggle" onclick="toggleSidebar()">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- Sidebar Overlay (for mobile) -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Close button (mobile only) -->
    <button class="sidebar-close" onclick="toggleSidebar()">✕</button>
    
    <div class="sidebar-header">
        <h2>🎓 Campus Events</h2>
        <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong></p>
        <span class="role-badge role-<?php echo $_SESSION['role']; ?>">
            <?php echo ucfirst($_SESSION['role']); ?>
        </span>
    </div>
    
    <nav class="sidebar-nav">
        <a href="../common/home.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : ''; ?>">
            <span>🏠</span> Home
        </a>
        
        <a href="../<?php echo $_SESSION['role']; ?>/dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <span>📊</span> My Dashboard
        </a>
        
        <?php if($_SESSION['role'] == 'student'): ?>
            <a href="../student/my_events.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'my_events.php' ? 'active' : ''; ?>">
                <span>🎫</span> My Events
            </a>
            <a href="../student/browse_events.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'browse_events.php' ? 'active' : ''; ?>">
                <span>🔍</span> Browse Events
            </a>
            <a href="../student/browse_merchandise.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'merchandise_store.php' ? 'active' : ''; ?>">
                <span>🛒</span> Merchandise Store
            </a>


        <?php endif; ?>
        
        <?php if($_SESSION['role'] == 'organizer'): ?>
            <a href="../organizer/create_event.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'create_event.php' ? 'active' : ''; ?>">
                <span>➕</span> Create Event
            </a>
            <a href="../organizer/manage_events.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_events.php' ? 'active' : ''; ?>">
                <span>📋</span> Manage Events
            </a>
            <a href="../organizer/view_registrations.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'view_registrations.php' ? 'active' : ''; ?>">
                <span>👥</span> Registrations
            </a>
            <!-- NEW: Merchandise Section -->
             <a href="../organizer/create_merchandise.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'create_merchandise.php' ? 'active' : ''; ?>">
                <span>🛍️</span> Add Merchandise
            </a>
            <a href="../organizer/manage_merchandise.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_merchandise.php' ? 'active' : ''; ?>">
                <span>📦</span> Manage Merchandise
            </a>
        <?php endif; ?>
        
        <?php if($_SESSION['role'] == 'admin'): ?>
            <a href="../admin/create_user.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'create_user.php' ? 'active' : ''; ?>">
                <span>👤</span> Create User
            </a>
            <a href="../admin/manage_users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                <span>👥</span> Manage Users
            </a>
            <a href="../admin/reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <span>📄</span> Reports
            </a>
        <?php endif; ?>
        
        <a href="../common/profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <span>⚙️</span> Profile
        </a>
        
        <a href="../common/help.php" class="nav-item">
            <span>❓</span> Help
        </a>
        
        <a href="../logout.php" class="nav-item logout" onclick="return confirm('Are you sure you want to logout?')">
            <span>🚪</span> Logout
        </a>
    </nav>
</aside>