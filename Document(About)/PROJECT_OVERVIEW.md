# Campus Event Management System - Project Overview

## 📋 Project Summary
The **Campus Event Management System** is a comprehensive web-based application designed to manage campus events, merchandise, and user registrations. It supports multiple user roles with distinct functionalities and permissions, enabling students to discover and register for events, organizers to create and manage events, and administrators to oversee the entire system.

**Database**: `campus_events` (MySQL/MariaDB)  
**Server**: XAMPP (Apache + PHP 8.0.30)  
**Framework**: Vanilla PHP + jQuery  
**Status**: In Active Development

---

## 👥 User Roles & Responsibilities

### 1. **Student**
- **Location**: `student/` directory
- **Primary Functions**:
  - Browse available events and merchandise
  - Register for events
  - View registered events (`my_events.php`)
  - View detailed event information
  - Like/unlike events (AJAX functionality)
  - Purchase merchandise
  - View personal dashboard
  - Access profile management

**Key Files**:
- `dashboard.php` - Student home dashboard
- `browse_events.php` - Event discovery interface
- `event_detail.php` - Detailed event view
- `register_event.php` - Event registration
- `my_events.php` - Student's registered events
- `ajax_toggle_like.php` - Like/unlike functionality (AJAX)
- `browse_merchandise.php` - Merchandise browsing
- `view_merchandise.php` - Merchandise details
- `view_event.php` - Event view page

---

### 2. **Organizer**
- **Location**: `organizer/` directory
- **Primary Functions**:
  - Create and manage events
  - Create and manage merchandise
  - Manage co-organizers for events
  - Send notifications to registered attendees
  - Send merchandise notifications
  - Issue and manage event tickets
  - Verify tickets at event entrance
  - Generate verification reports
  - Handle co-organizer invitations
  - View event registrations and attendee details

**Key Files**:
- `dashboard.php` - Organizer home dashboard
- `create_event.php` - Create new events
- `manage_events.php` - Event management interface
- `edit_event.php` - Edit existing events
- `view_event.php` - View event details
- `browse_events.php` - Browse created events
- `view_registrations.php` - View event attendees
- `send_notification.php` - Send event notifications to attendees
- `send_tickets.php` - Issue tickets to attendees
- `verify_ticket.php` - Verify tickets during event
- `verification_report.php` - Generate verification reports
- `create_merchandise.php` - Create merchandise items
- `manage_merchandise.php` - Manage merchandise inventory
- `edit_merchandise.php` - Edit merchandise details
- `send_merch_notification.php` - Send merchandise notifications
- `manage_co_organizers.php` - Manage co-organizers for events
- `co_organizer_invitations.php` - Handle co-organizer invitations
- `browse_merchandise.php` - View created merchandise
- `view_merchandise.php` - View merchandise details

---

### 3. **Administrator**
- **Location**: `admin/` directory
- **Primary Functions**:
  - Manage all system users (create, edit, delete)
  - Browse all events in the system
  - Browse all merchandise in the system
  - Generate comprehensive reports
  - Export data (events, registrations, users)
  - Download system data for analysis
  - Send system emails
  - Monitor system activity
  - View detailed user information

**Key Files**:
- `dashboard.php` - Admin home dashboard
- `manage_users.php` - User management interface
- `create_user.php` - Create new users
- `edit_user.php` - Edit user details
- `view_user.php` - View user profile
- `download_users.php` - Export users data
- `browse_events.php` - View all system events
- `event_details.php` - View event details
- `download_events.php` - Export events data
- `view_event.php` - Event view page
- `browse_merchandise.php` - View all merchandise
- `view_merchandise.php` - View merchandise details
- `reports.php` - Generate system reports
- `export_report.php` - Export reports
- `download_registrations.php` - Export registration data
- `send_email.php` - Send system-wide emails

---

## 📁 Directory Structure & Organization

### **Root Level Files**
```
├── index.php                    # Landing page / Sign-up page
├── login.php                    # User login page
├── logout.php                   # User logout handler
├── composer.json                # PHP dependency manager
├── users.sql                    # Initial database schema
├── README.md                    # Project readme
└── PROJECT_OVERVIEW.md          # This file
```

### **Directory Breakdown**

#### 🔐 `/config/` - Configuration Files
Centralized configuration and helper functions for database and email operations.

```
config/
├── database.php                 # Database connection configuration
├── email_config.php             # Email configuration (SMTP, sender info)
└── co_organizer_helper.php      # Co-organizer utility functions
```

**Responsibility**: Database connectivity, mail service setup, helper functions

---

#### 📋 `/includes/` - Shared Components
Reusable HTML components used across all modules (header, footer, sidebar, navigation).

```
includes/
├── header.php                   # Top navigation and page header
├── footer.php                   # Page footer
└── sidebar.php                  # Navigation sidebar (context-specific)
```

**Purpose**: DRY principle - maintain consistent UI/UX across all user roles

---

#### 👨‍🎓 `/student/` - Student Module (9 files)
Student-facing features for discovering and registering for events.

**Main Functionalities**:
- Event discovery and filtering
- Event registration with attendance tracking
- Merchandise browsing and purchasing
- Personal dashboard with registered events
- Interactive features (like/unlike events via AJAX)
- Event detail viewing

**Database Tables Used**: `users`, `events`, `registrations`, `merchandise`, `event_likes/comments`

---

#### 🎯 `/organizer/` - Organizer Module (19 files)
Event and merchandise creation and management with attendee engagement tools.

**Main Functionalities**:
- Complete event lifecycle management (create, edit, view, manage)
- Ticket issuance and verification system
- Merchandise inventory management
- Co-organizer collaboration and invitations
- Bulk notifications to attendees
- Event analytics and verification reports
- Attendee registration tracking

**Database Tables Used**: `users`, `events`, `merchandise`, `event_organizers` (co-organizers), `registrations`, `tickets`, `notifications`

---

#### 👨‍💼 `/admin/` - Admin Module (16 files)
System-wide management and data oversight capabilities.

**Main Functionalities**:
- User account creation and management
- System-wide data browsing
- Comprehensive reporting and analytics
- Data export and download (CSV/Excel/PDF)
- System communications via email
- User role and permission management

**Database Tables Used**: All tables (can access all data)

---

#### 🎨 `/assets/` - Frontend Resources
Static files for styling and interactivity.

```
assets/
├── css/
│   └── style.css                # Main stylesheet (global styles)
├── js/
│   └── script.js                # Client-side JavaScript (AJAX, interactions)
└── images/                      # Image storage for UI elements
```

**Contents**:
- Global CSS styling for all pages
- JavaScript for dynamic interactions (AJAX requests, form validation, UI enhancement)

---

#### 📤 `/uploads/` - User-Generated Content
File storage for user profiles and merchandise items.

```
uploads/
├── profiles/                    # User profile images
│   └── profile_[user_id]_[timestamp].jpg
└── merchandise/                 # Merchandise product images
    └── product_[merch_id]_[timestamp].jpg
```

---

#### 📦 `/vendor/` - Dependencies
Third-party libraries managed via Composer.

```
vendor/
├── autoload.php                 # Composer autoloader
├── composer/                    # Composer metadata
└── phpmailer/                   # PHPMailer library for email functionality
    └── phpmailer/
        ├── src/                 # PHPMailer source files
        ├── language/            # Language files for email templates
        └── README.md
```

**Key Dependency**: PHPMailer for SMTP email sending

---

#### ❌ `/problams/` - Issues/Problems Documentation
(Note: This appears to be a documentation folder for tracking issues)

---

## 🗄️ Database Schema Overview

### **Core Tables**

#### `users` Table
User accounts with role-based access control.

```
Fields:
- id (PK)                       # User ID
- username (UNIQUE)             # Login username
- email (UNIQUE)                # Email address
- phone                         # Phone number
- password                      # Hashed password
- role (enum)                   # 'admin', 'organizer', 'student'
- full_name                     # Display name
- department                    # Academic/work department
- year                          # Academic year
- bio                           # User biography
- address                       # Mailing address
- profile_image                 # Profile photo path
- created_at (timestamp)        # Account creation date
- created_by (FK: users.id)    # Admin who created the account
```

**Indexes**:
- PRIMARY KEY: `id`
- UNIQUE: `username`, `email`
- FOREIGN KEY: `created_by` → `users.id`

---

#### Related Tables (Inferred from Code)
Based on the functionality, the following tables likely exist:

| Table | Purpose |
|-------|---------|
| `events` | Event information (title, description, date, location, organizer) |
| `registrations` | Student event registrations (tracks who registered for which event) |
| `merchandise` | Marketplace items for purchase |
| `event_organizers` | Co-organizer assignments for events |
| `tickets` | Event tickets (issued by organizers, verified at events) |
| `notifications` | Event announcements and notifications |
| `event_likes` / `event_comments` | Student engagement with events |

---

## 🔄 Common Workflow Scenarios

### **Student Workflow**
1. User visits `index.php` → Sign up / Log in
2. Redirected to `student/dashboard.php`
3. Browse events via `student/browse_events.php`
4. View event details in `student/event_detail.php`
5. Register for event via `student/register_event.php`
6. Track registered events in `student/my_events.php`
7. Like events via AJAX in `student/ajax_toggle_like.php`
8. Browse merchandise in `student/browse_merchandise.php`

### **Organizer Workflow**
1. User logs in → `organizer/dashboard.php`
2. Create new event via `organizer/create_event.php`
3. Manage events in `organizer/manage_events.php`
4. View registrations in `organizer/view_registrations.php`
5. Issue tickets via `organizer/send_tickets.php`
6. Create merchandise in `organizer/create_merchandise.php`
7. Send notifications to attendees via `organizer/send_notification.php`
8. Verify tickets at event via `organizer/verify_ticket.php`
9. Generate reports in `organizer/verification_report.php`

### **Admin Workflow**
1. User logs in → `admin/dashboard.php`
2. Create users via `admin/create_user.php`
3. Manage users in `admin/manage_users.php`
4. Browse all events in `admin/browse_events.php`
5. Generate reports via `admin/reports.php`
6. Export data using download endpoints
7. Send system emails via `admin/send_email.php`

---

## 🔐 Authentication & Authorization

### **Login Flow**
1. User submits credentials in `login.php`
2. System verifies credentials against `users` table
3. Session created with:
   - `$_SESSION['user_id']`
   - `$_SESSION['username']`
   - `$_SESSION['role']`
4. User redirected to role-specific dashboard:
   - Role: `student` → `student/dashboard.php`
   - Role: `organizer` → `organizer/dashboard.php`
   - Role: `admin` → `admin/dashboard.php`

### **Session Verification**
- Each module verifies `$_SESSION['user_id']` and `$_SESSION['role']`
- Unauthorized access redirected to `login.php`
- Password hashing: MD5 (Note: Consider upgrading to bcrypt)

---

## 📊 Technology Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.0.30 |
| **Database** | MySQL 10.4.32 (MariaDB) |
| **Frontend** | HTML5, CSS3, jQuery |
| **Server** | Apache (XAMPP) |
| **Email** | PHPMailer (SMTP) |
| **Package Manager** | Composer |

---

## 🚀 Key Features Summary

### **Event Management**
- ✅ Event creation and editing
- ✅ Event registration and attendance
- ✅ Ticket issuance and verification
- ✅ Co-organizer collaboration
- ✅ Event notifications
- ✅ Attendee analytics

### **Merchandise System**
- ✅ Product creation and management
- ✅ Product browsing and purchasing
- ✅ Merchandise notifications

### **User Management**
- ✅ Multi-role user system (Admin, Organizer, Student)
- ✅ User profile management
- ✅ Profile image uploads
- ✅ Role-based access control

### **Data Management**
- ✅ Comprehensive reporting
- ✅ Data export and download
- ✅ System-wide email communication

---

## 📝 Important Notes for Development

### **When Making Changes in One Module, Remember:**

1. **Session Management**: All modules rely on `$_SESSION['user_id']` and `$_SESSION['role']`
2. **Database Connection**: Imported via `config/database.php` in all files
3. **Email Configuration**: Centralized in `config/email_config.php`
4. **UI Consistency**: Use `includes/header.php`, `includes/footer.php`, `includes/sidebar.php`
5. **Assets**: CSS in `assets/css/style.css`, JS in `assets/js/script.js`
6. **File Uploads**: Store in `uploads/` directory with timestamped filenames
7. **Password Hashing**: Currently uses MD5 (should upgrade to bcrypt for security)
8. **Role-Based Access**: Always verify user role before displaying/processing data

### **Cross-Module Dependencies**
- 🔗 **Student** ↔ **Events** (registrations, likes, comments)
- 🔗 **Organizer** ↔ **Events** (creation, management, tickets)
- 🔗 **Organizer** ↔ **Co-Organizers** (invitations, collaboration)
- 🔗 **Admin** ↔ **All Modules** (oversight, user management, reporting)
- 🔗 **All Modules** ↔ **Merchandise** (browsing, purchasing)

---

## 🔍 Quick Reference: File Purpose

### **By Functionality**

**Authentication**: `login.php`, `logout.php`, `index.php`

**User Management**: `admin/manage_users.php`, `admin/create_user.php`, `admin/edit_user.php`

**Event Management**: 
- Create: `organizer/create_event.php`
- Edit: `organizer/edit_event.php`
- View: `*/view_event.php`, `*/event_detail.php`
- Browse: `*/browse_events.php`
- Manage: `organizer/manage_events.php`

**Event Operations**:
- Register: `student/register_event.php`
- Verify: `organizer/verify_ticket.php`
- Tickets: `organizer/send_tickets.php`
- Notifications: `organizer/send_notification.php`

**Merchandise**: 
- Create: `organizer/create_merchandise.php`
- Browse: `*/browse_merchandise.php`
- View: `*/view_merchandise.php`
- Manage: `organizer/manage_merchandise.php`

**Reporting & Export**:
- Reports: `admin/reports.php`
- Export: `admin/export_report.php`
- Downloads: `admin/download_*.php`

**Co-Organizers**:
- Manage: `organizer/manage_co_organizers.php`
- Invitations: `organizer/co_organizer_invitations.php`

---

## 📌 Development Guidelines

### **Before Making Changes:**
1. Review the user role that will be affected
2. Check what database tables are involved
3. Verify which other modules interact with these tables
4. Test across all affected user roles
5. Update this documentation if adding new features

### **Version Control Commitment:**
Update this file when:
- Adding new features or modules
- Changing database schema
- Adding new user roles or permissions
- Reorganizing files or directories

---

**Last Updated**: April 12, 2026  
**Project Status**: In Active Development  
**Maintained By**: Development Team
