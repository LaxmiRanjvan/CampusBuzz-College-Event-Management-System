# Campus Event Management System

A comprehensive web-based application for managing campus events, merchandise, and user registrations with support for multiple user roles (Students, Organizers, and Administrators).

---

## 📋 Overview

**Campus Event Management System** enables:
- **Students** to discover events, register, manage their participation, purchase merchandise, and track orders
- **Organizers** to create and manage events, issue tickets, generate certificates, manage merchandise inventory, and track orders
- **Administrators** to oversee the entire system, manage users, and generate comprehensive reports

**Tech Stack**: PHP 8.0.30 | MySQL/MariaDB | jQuery | XAMPP

---

## 🎯 Key Features

### **Student Features**
- ✅ Browse and register for events
- ✅ Like/unlike events
- ✅ View registered events and event details
- ✅ Purchase and browse merchandise
- ✅ Track merchandise orders and delivery status
- ✅ Manage personal profile

### **Organizer Features**
- ✅ Create and manage events
- ✅ Send event notifications and tickets to attendees
- ✅ Issue and verify event tickets
- ✅ Generate certificates for event attendees
- ✅ Create and manage merchandise inventory
- ✅ Track merchandise orders and manage payment verification
- ✅ Send order status notifications
- ✅ Generate attendance and verification reports

### **Administrator Features**
- ✅ Manage all system users (create, edit, delete)
- ✅ Browse all events and merchandise
- ✅ Generate comprehensive system reports
- ✅ Export data (users, events, registrations, orders)
- ✅ Monitor system activity
- ✅ Send system-wide communications

---

## 📁 Project Structure

```
campus-event-manager/
├── admin/                  # Administrator module
├── organizer/             # Event organizer module
├── student/               # Student module
├── common/                # Shared pages (help, profile)
├── config/                # Database & email configuration
├── includes/              # Shared UI components (header, footer, sidebar)
├── assets/                # CSS, JavaScript, images
├── uploads/               # User-generated content (profiles, merchandise images)
├── vendor/                # Composer dependencies (PHPMailer)
├── Document(About)/       # Comprehensive documentation
├── index.php              # Landing page
├── login.php              # User login
├── logout.php             # User logout
├── users.sql              # Database schema
└── README.md              # This file
```

---

## 🚀 Getting Started

### **Prerequisites**
- XAMPP (Apache + PHP 8.0.30)
- MySQL/MariaDB
- Web browser (Chrome, Firefox, Safari, Edge)

### **Installation**

1. **Clone/Download the project** to `C:\xampp\htdocs\campus-event-manager`

2. **Import the database schema**:
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database: `campus_events`
   - Import `users.sql` file

3. **Configure email settings** (Optional):
   - Edit `config/email_config.php`
   - Set your SMTP credentials (Gmail, custom server, etc.)

4. **Access the application**:
   - Navigate to `http://localhost/campus-event-manager/`

### **Demo Credentials** (After running users.sql)
```
Admin:
  Email: admin@example.com
  Password: admin123

Organizer:
  Email: organizer@example.com
  Password: organizer123

Student:
  Email: student@example.com
  Password: student123
```

---

## 📚 Documentation

Comprehensive documentation is available in the `Document(About)/` folder:

- **PROJECT_OVERVIEW.md** - Complete feature guide and module descriptions
- **FOLDER_STRUCTURE.md** - Detailed directory organization
- **MODULE_DEPENDENCIES.md** - Database schema and module interactions
- **QUICK_REFERENCE.md** - Quick lookup guide for common tasks
- **DOCUMENTATION_INDEX.md** - Index of all documentation

---

## 📊 Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts, authentication, profiles |
| `events` | Event information and details |
| `registrations` | Student event registrations |
| `merchandise` | Product catalog |
| `merchandise_orders` | Merchandise purchase orders and status |
| `tickets` | Event tickets and verification |
| `certificates` | Generated certificates for attendees |
| `notifications` | Message history |
| `event_likes` | Event likes/favorites |
| `event_comments` | Event comments |
| `email_logs` | Email sending history |

---

## 🔐 Security Features

- ✅ Role-based access control (Student, Organizer, Admin)
- ✅ Session-based authentication
- ✅ SQL prepared statements (prevents SQL injection)
- ✅ Password hashing
- ✅ File upload validation
- ✅ CSRF protection

---

## 🛠️ Technologies Used

- **Backend**: PHP 8.0.30
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (jQuery)
- **Email**: PHPMailer (Composer)
- **Server**: XAMPP (Apache 2.4)

---

## 📝 Recent Updates (April 2026)

✅ **Merchandise Order Tracking** - Added full order tracking system for both students and organizers
✅ **Email Configuration** - Fixed PHPMailer integration and SMTP constant definitions
✅ **Documentation** - Updated all documentation files to reflect new features

---

## 🤝 Support & Issues

For issues, bugs, or feature requests, please check the `problems/` folder or create a new issue in the project.

---

## 📄 License

CampusBuzz: Collage Event Management System © 2026. All rights reserved.
