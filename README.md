# Aptech Student Attendance Portal
### PHP + MySQL Web Application — Setup Guide

---

## Requirements
- **XAMPP** (or any Apache + PHP 8.0+ + MySQL 5.7+/MariaDB 10.4+ stack)
- A modern web browser

---

## Installation Steps

### 1. Copy project files
Place the entire `aptech_portal` folder inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\aptech_portal\       (Windows)
/Applications/XAMPP/htdocs/aptech_portal/  (macOS)
/opt/lampp/htdocs/aptech_portal/     (Linux)
```

### 2. Start XAMPP services
Open the XAMPP Control Panel and start:
- ✅ Apache
- ✅ MySQL

### 3. Create the database
**Option A — phpMyAdmin (easiest)**
1. Open your browser and go to: `http://localhost/phpmyadmin`
2. Click **Import** in the top menu
3. Choose the file: `aptech_portal/database.sql`
4. Click **Go** — the database and all tables will be created automatically

**Option B — Command line**
```bash
mysql -u root -p < database.sql
```

### 4. Configure database connection
Open `includes/config.php` and update if your MySQL credentials differ:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // your MySQL username
define('DB_PASS', '');          // your MySQL password (blank by default in XAMPP)
define('DB_NAME', 'aptech_portal');
define('BASE_URL', 'http://localhost/aptech_portal');
```

### 5. Open the portal
Visit: **http://localhost/aptech_portal**

---

## Demo Login Credentials

| Role     | Email                     | Password        |
|----------|---------------------------|-----------------|
| Admin    | admin@aptech.edu.ng       | Admin@1234      |
| Lecturer | obi@aptech.edu.ng         | Lecturer@1234   |
| Lecturer | grace@aptech.edu.ng       | Lecturer@1234   |
| Student  | chidi@aptech.edu.ng       | Student@1234    |
| Student  | adaeze@aptech.edu.ng      | Student@1234    |
| Student  | kemi@aptech.edu.ng        | Student@1234    |

---

## Features

### 🛡 Admin
- Full dashboard with live stats, weekly trend chart, at-risk students
- Mark attendance for any module/session
- Manage all students (add, deactivate, view detail)
- Manage all modules (add, edit)
- Full user management — add/deactivate/reset passwords for all roles
- Reports: generate weekly/monthly/term summaries with real DB data
- Alerts: view at-risk students, send individual or bulk notifications

### 📋 Lecturer
- Dashboard scoped to their own modules
- Mark attendance for their assigned modules only
- View students enrolled in their modules
- Generate reports for their modules

### 🎓 Student
- Personal overview with attendance gauge and module breakdown
- Full session history with date, module, status
- Module cards with threshold warnings
- Notifications inbox for at-risk alerts

---

## Security Features
- ✅ bcrypt password hashing (cost factor 12)
- ✅ CSRF tokens on every POST form
- ✅ Session timeout (1 hour)
- ✅ Session ID regeneration (every 30 minutes)
- ✅ HttpOnly, SameSite cookies
- ✅ Role-based access control (admin / lecturer / student)
- ✅ PDO prepared statements — SQL injection protected
- ✅ htmlspecialchars output escaping — XSS protected
- ✅ Lecturers can only access their own modules

---

## Project Structure
```
aptech_portal/
├── index.php                  — Login page
├── logout.php                 — Session destroy + redirect
├── database.sql               — Full schema + seed data
├── README.md                  — This file
├── includes/
│   ├── config.php             — DB config + PDO connection
│   ├── auth.php               — Login, logout, CSRF, guards
│   ├── header.php             — Topbar + sidebar HTML
│   └── footer.php             — Closing tags + scripts
├── assets/
│   ├── css/style.css          — Master stylesheet
│   └── js/app.js              — Client-side JS
└── pages/
    ├── dashboard.php          — Admin/Lecturer dashboard
    ├── mark_attendance.php    — Mark attendance (saves to DB)
    ├── students.php           — Student list + add/deactivate
    ├── student_detail.php     — Single student breakdown
    ├── modules.php            — Module cards + add module
    ├── reports.php            — Report generation
    ├── alerts.php             — At-risk alerts + notifications
    ├── users.php              — User management (admin only)
    ├── student_overview.php   — Student dashboard + gauge
    ├── student_history.php    — Student session log
    ├── student_modules.php    — Student enrolled modules
    ├── notifications.php      — Notification inbox
    └── profile.php            — Profile + change password
```

---

## Troubleshooting

**"Database Connection Failed"**
→ Make sure MySQL is running in XAMPP and `includes/config.php` credentials match.

**"Class not found" / blank page**
→ Make sure PHP 8.0+ is running. Check `php.ini` — `pdo_mysql` extension must be enabled.

**Passwords not working**
→ The seed data uses pre-hashed passwords. If you re-import `database.sql`, all passwords reset to the defaults shown above.

**Session issues / keeps logging out**
→ Increase `SESSION_TIMEOUT` in `config.php` or ensure PHP session storage is writable.
