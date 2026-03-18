# Backend API Project Analysis: University of Ibadan Student Portal

## 1. Project Purpose & Overview

**Primary Purpose**: A comprehensive student data analytics and reporting system for the University of Ibadan, designed to generate statistical reports, charts, and export student information with advanced filtering and visualization capabilities.

**Key Use Cases**:
- Generate statistical reports about student demographics (gender, level, location, etc.)
- Create multi-series charts for data visualization
- Export student lists with advanced filtering
- Generate PDF reports with metadata and branding
- Manage user authentication with OTP-based password resets
- Support batch operations for multiple report types

**Institution Context**: University of Ibadan (UI) - a Nigerian university with focus on generating statistics about student distribution across:
- Academic levels
- Departments
- Faculties
- Geographic zones/states
- Halls of residence
- Demographic attributes

---

## 2. Technology Stack

### Backend Languages & Frameworks
| Technology | Purpose | Version | Usage |
|------------|---------|---------|-------|
| **PHP** | Main API backend, request routing, database queries | ~7.4+ | Core business logic |
| **Python** | Report/chart generation, data processing | ~3.x | PDF generation, matplotlib charts |
| **MySQL** | Primary database | - | Student data, auth/oauth data |
| **PDO** | Database abstraction layer | PHP native | Database connectivity |

### Key Libraries & Dependencies

**PHP Libraries**:
- **PDO** (PHP Data Objects) - Database connectivity with prepared statements
- **Native PHP** - Session management, file operations
- **Built-in PHP functions** - JSON encoding/decoding, header management

**Python Libraries**:
- **fpdf2** - PDF document generation with text, images, tables
- **matplotlib** - Chart generation (bar, line, area, pie, scatter)
- **pandas** - Data manipulation and DataFrame operations
- **numpy** - Numerical operations
- **requests** - HTTP requests (for fetching logos)
- **faker** - Dummy data generation (for testing)

**Services**:
- **Mailpit** - Local SMTP server (localhost:1025) for OTP email delivery
- **CORS** - Security headers for cross-origin requests

**Build Environment**:
- **Laragon** - Local development server (Windows-based)
- **MySQL** - Database server at 192.168.3.83 (local network)

---

## 3. Architecture & Component Breakdown

### 3.1 Entry Points & Core Files

#### **api.php** - Main API Endpoint
**Role**: Primary request router handling all API actions
**Size**: ~1000+ lines
**Key Responsibilities**:
- CORS header configuration
- PDO database connection management
- Request routing based on `action` parameter
- Direct MySQL queries for reporting data
- Integration with Python scripts for PDF/chart generation

**Database**: Connects to `student_ui_portal` (MySQL at 192.168.3.83)
**Credentials**: root/password (hardcoded - development only)

---

#### **reports.php** - PDF Report Generator
**Role**: Dedicated endpoint for generating PDF reports
**Size**: ~50 lines
**Key Responsibilities**:
- Accepts JSON input from frontend
- Spawns Python process running `render_reports.py`
- Streams PDF output back to client
- Error handling with stderr capture

**Technology**: PHP process spawning with pipe communication
**Output**: PDF binary stream with appropriate headers

---

### 3.2 Backend Components (PHP)

#### **Database.php** - Database Connection Wrapper
**Location**: `backend/db-worker/Database.php`
**Purpose**: Centralized database connection management
**Features**:
```
- Default config: 192.168.3.83 / student_ui_portal / root:password
- Accepts config overrides via constructor
- PDO error mode exception handling
- UTF-8 character support
- Can switch databases (e.g., 'oauth' database)
```
**Usage Pattern**:
```php
$db = new Database(['db_name' => 'oauth']);
$conn = $db->getConnection();
```

---

#### **AuthService.php** - Authentication & Session Management
**Location**: `backend/oauth/AuthService.php`
**Purpose**: Handle user authentication, registration, and password reset
**Database**: Uses 'oauth' database (separate from student_ui_portal)

**Key Methods**:
1. **login($email, $password)**
   - Validates credentials using bcrypt hash verification
   - Creates secure session with regenerated ID
   - Stores user info: id, uid, email, role, profile_id, department_id, faculty_id
   - Returns JSON response with user details

2. **logout()**
   - Clears session data
   - Destroys session cookie
   - Returns success response

3. **checkSession()**
   - Verifies active session
   - Returns authentication status and user info
   - Used for persistent login checks

4. **register($email, $password, $profileId)**
   - Creates new user with auto-generated UID (format: U-[8-char-hex])
   - Bcrypt password hashing
   - Optional profile_id linking
   - Duplicate email detection with PDO exception

5. **requestReset($email)** (Partial - not fully shown)
   - Initiates OTP-based password reset flow
   - Validates email existence

**Security Features**:
- Session cookie params: HTTPOnly, SameSite=Lax, 24-hour lifetime
- Bcrypt password hashing (PASSWORD_DEFAULT)
- Session ID regeneration on login
- Prepared statements (SQL injection prevention)

**OTP Management**:
- Stores OTPs in `$_SESSION['otp_store']`
- 5-minute expiration (from Mailer.php)
- Sent via Mailpit SMTP

---

#### **Mailer.php** - Email Service
**Location**: `backend/oauth/Mailer.php`
**Purpose**: Send OTP verification emails for password resets

**Configuration**:
```
SMTP Host: localhost
SMTP Port: 1025 (Mailpit)
Sender: noreply@ui.edu.ng
Subject: "Password Reset Verification Code"
```

**Methods**:
- **sendOtp($toEmail, $otp)** - Sends HTML-formatted OTP email
- HTML email template with school branding
- Uses SMTP protocol for delivery
- Includes 5-minute expiration notice

---

#### **cors_helper.php** - CORS Configuration
**Location**: `backend/oauth/cors_helper.php`
**Purpose**: Handle cross-origin resource sharing

**Configuration**:
```php
Access-Control-Allow-Origin: http://localhost:5173 (or parent domain)
Access-Control-Allow-Credentials: true
Access-Control-Allow-Methods: GET, POST, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
Access-Control-Max-Age: 86400 (1 day)
```

---

### 3.3 Python Components

#### **render_reports.py** - PDF Report Generation
**Purpose**: Generate formatted PDF reports with student data
**Triggers**: Called by `reports.php` via process spawning

**Key Features**:
- Custom `ReportPDF` class extending fpdf2.FPDF
- Dynamic header with school logo (fetched via HTTP)
- Metadata support: school name, address, disclaimer
- Active filters display in PDF
- Page numbering and footer with generation info
- Supports student data tables
- Can include multiple pages

**Input Format** (JSON via STDIN):
```json
{
  "print_meta": {
    "school_logo": "URL",
    "school_name": "University of Ibadan",
    "address": "1 Knowledge Drive...",
    "generated_by": "User ID",
    "generated_at": "Date",
    "active_filters": {"key": "value"}
  },
  "data": [{"fields": "values"}],
  "charts": {}
}
```

---

#### **backend/chart_generator.py** - Advanced Chart Generation
**Location**: `backend/chart_generator.py`
**Purpose**: Generate interactive/static charts and tables with metadata

**Supported Chart Types**:
1. **bar** - Vertical/horizontal bar charts
2. **pie** - Pie charts with percentages
3. **line** - Line charts with trends
4. **area** - Area charts with fill
5. **scatter** - Scatter plots
6. **table** - Data tables with pagination

**Key Features**:
- Multi-series support (e.g., gender breakdown by level)
- Automatic table pagination (30 rows/page)
- Footer metadata: total counts, generation info
- Color configuration from input
- PDF output with matplotlib PdfPages
- First-page PNG export for preview
- Responsive title wrapping

**Chart Customization**:
- Custom titles, descriptions
- Key column specification for indexing
- Configurable colors and styling
- UIDs and descriptions in metadata

---

#### **console_batch_report.py** - Batch Report Generator
**Purpose**: Generate multiple reports in a single operation
**Features**:
- Generates dummy Nigerian student data
- 550+ records with realistic demographics
- Output: SQL INSERT statements
- Columns: JAMB number, state of origin, level, department, etc.

---

#### **render_charts.py** - Multi-Chart PDF Reports
**Purpose**: Complex PDF generation with multiple visualizations
**Features**:
- Bar, line, area, pie, scatter chart support
- Color palette with 60+ shade variations
- Watermark support (University of Ibadan)
- Metrics calculation (diversity, dominance, trends)
- Professional styling with grid backgrounds

---

#### **universal_export.py** - Multi-Format Export
**Location**: `universal_export.py`
**Purpose**: Export data in multiple formats via CLI

**Supported Formats**:
- **CSV** - Comma-separated values
- **XLSX** - Excel spreadsheets
- **PDF** - Formatted documents

**Command Line Interface**:
```bash
python universal_export.py --format csv|xlsx|pdf
```
**Input**: JSON via STDIN

---

#### **tomolu.py** - Data Generation Utility
**Purpose**: Generate dummy student data for testing
**Features**:
- Uses Faker library with Nigerian locale
- Generates 550+ realistic records
- Fields: JAMB number, state of origin, religion, language, etc.
- Outputs SQL INSERT statements
- Nigerian context: States, languages, disabilities

---

### 3.4 Database Structure

#### **Database 1: student_ui_portal**
**Primary database** for student and institutional data

**Key Tables** (inferred from queries):
| Table | Purpose | Key Fields |
|-------|---------|-----------|
| **profiles** | Student personal/academic data | user_id, gender, level_id, department_id, faculty_id, state_of_origin, hall_id, dob, marital_status, religion, mode_of_admission, lga |
| **departments** | Department master data | id, name, faculty_id |
| **faculties** | Faculty/college master data | id, name |
| **halls** | Residential halls master data | id, name |
| *(implicit)* users | User accounts | id, uid, email |

**Query Features**:
- Strict SQL mode disabled for complex GROUP BY
- LEFT JOINs for filtering by related entities
- COUNT(DISTINCT user_id) to avoid duplicate counts when joining

---

#### **Database 2: oauth**
**Separate database** for authentication/authorization

**Tables** (inferred from AuthService):
| Table | Purpose | Fields |
|-------|---------|--------|
| **users** | OAuth users | id, uid, email, password_hash, role, profile_id, department_id, faculty_id |

**Connection**: AuthService connects to 'oauth' database for auth operations

**Session Storage**: In-memory PHP `$_SESSION` array (not database-persistent)

---

### 3.5 Data Flow Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     FRONTEND (React/Vue)                     │
│              (localhost:5173 / 192.168.3.83:5173)           │
└────────────────┬────────────────────────────────────────────┘
                 │ JSON POST/GET
                 │
┌────────────────▼────────────────────────────────────────────┐
│          API ENTRY POINTS (CORS Enabled)                    │
│  api.php, reports.php (CORS headers)                        │
└────────────────┬────────────────────────────────────────────┘
                 │
        ┌────────▼────────┐
        │ Action Routing  │
        └────────┬────────┘
        │
    ┌───┴─────────────────────────────────┐
    │                                     │
┌───▼──────────────────┐  ┌──────────────▼──────────────┐
│ Direct MySQL Queries │  │ Python Process Spawning     │
│ (PHP/PDO)             │  │ (for charts/reports)       │
│                       │  │                            │
│ - Student List        │  │ Processes:                 │
│ - Report Data         │  │ - render_reports.py        │
│ - Chart Data          │  │ - generate_chart.py        │
│ - Batch Operations    │  │ - universal_export.py      │
│                       │  │ - generate_batch_report    │
└───┬──────────────────┘  └──────────────┬──────────────┘
    │                                    │
    └────────┬───────────────┬───────────┘
             │               │
    ┌────────▼──┐   ┌───────▼──────┐
    │   MySQL   │   │ Python: PDF/ │
    │ Database  │   │ Matplotlib   │
    │           │   │     +        │
    │ student_  │   │   fpdf2      │
    │ ui_portal │   │              │
    │ oauth     │   │ Returns:     │
    └───────────┘   │ Binary/JSON  │
                    └──────────────┘
                              │
                    ┌─────────▼────────┐
                    │ Response Headers │
                    │ + Output Stream  │
                    └──────────────────┘
                          │
                    ┌─────▼──────────┐
                    │   FRONTEND     │
                    │   Display/DL   │
                    └────────────────┘
```

---

## 4. API Endpoints & Functionality

### Core Actions in api.php

#### **1. download_table_export** - Multi-format Export
**Purpose**: Export filtered student data in CSV/XLSX/PDF
**HTTP Method**: POST
**Parameters**:
```json
{
  "action": "download_table_export",
  "format": "csv|xlsx|pdf",
  "uid": "user_id",
  "data": {
    "data": [array of student records],
    "title": "Export Title",
    "print_meta": {...}
  }
}
```
**Process**:
1. Routes to `universal_export.py --format [format]`
2. Passes payload via STDIN
3. Returns binary stream (file download)

---

#### **2. download_batch_pdf** - Multiple Report Assembly
**Purpose**: Generate batch PDF combining multiple report types
**Parameters**:
```json
{
  "action": "download_batch_pdf",
  "items": [array of report items]
}
```
**Process**:
1. Spawns `generate_batch_report.py`
2. Returns base64-encoded PDF
3. Filename pattern: `batch_report_{userId}_{timestamp}.pdf`

---

#### **3. generate_student_list** - Filtered Student Directory
**Purpose**: Generate student list with multiple filter options
**Parameters**:
```json
{
  "action": "generate_student_list",
  "zone": "South West|South East|...",
  "state": "Lagos|Ogun|...",
  "gender": "M|F|Other",
  "level": "100|200|300|...",
  "department": "dept_id",
  "faculty": "faculty_id",
  "hall": "hall_id"
}
```
**Filters** (all optional, combinable):
- **Geographic**: Zone, State, LGA
- **Academic**: Department, Faculty, Level
- **Residential**: Hall of residence
- **Demographic**: Gender

**Output**: Formatted student directory with names and IDs
**Title Generation**: Dynamic, e.g., "300 Level Female Students in Dept Computer Science from Lagos"

---

#### **4. generate_report / download_pdf** - Chart Generation
**Purpose**: Generate statistical charts from student data
**Parameters**:
```json
{
  "action": "generate_report|download_pdf",
  "breakdown": "gender|state_of_origin|geopolitical_zone|level_category|religion|marital_status|mode_of_admission|lga|hall|department",
  "type": "bar|pie|line|area|scatter",
  "title": "Chart Title",
  "filter_gender": "M|F",
  "filter_level": "100|200|...",
  "filter_department": "dept_id",
  "filter_faculty": "faculty_id",
  "filter_zone": "South West|...",
  "filter_state": "state_name",
  "filter_hall": "hall_id"
}
```

**Breakdown Types**:
1. **gender** - Student count by gender
2. **state_of_origin** - Count by home state
3. **geopolitical_zone** - Count by Nigerian zone
4. **level_category** - Grouping by level ranges (100-200, 300-400, 500+)
5. **religion** - Distribution by religion
6. **marital_status** - Count by marital status
7. **mode_of_admission** - UTME vs Direct Entry
8. **lga** - Local government area
9. **hall** - Residential hall distribution
10. **department** - Department-wise breakdown

**Chart Types**:
- **bar** - Vertical bar chart
- **pie** - Pie chart with percentages
- **line** - Line chart for trends
- **area** - Filled area chart
- **scatter** - Scatter plot

**Multi-Series Logic**:
- If filter_level set but not filter_gender → Series by Gender
- If filter_gender set but not filter_level → Series by Level
- If both/neither set → Series by Level (default)
- Pie charts are single-series only

**Output**:
- **generate_report**: JSON payload with chart config
- **download_pdf**: Binary PDF via `generate_chart.py` or `render_charts.py`

---

#### **5. GET Request - Dashboard Summary**
**Purpose**: Retrieve basic dashboard statistics
**Parameters**: None required
**Response**:
```json
{
  "user_role": "admin",
  "summary_stats": {
    "total_students": <number>
  },
  "widgets": []
}
```

---

### reports.php Endpoint

**Simple PDF Generator**
- Accepts JSON input
- Calls `render_reports.py`
- Returns PDF binary stream
- Minimal error handling with stderr capture

---

## 5. Key Modules & Their Interactions

### Module Dependency Graph

```
┌─────────────────┐
│   api.php       │◄─────────────────┐
│ (Main Router)   │                  │
└────────┬────────┘                  │
         │                           │
    ┌────┴─────────────────────────┐ │
    │                              │ │
┌───▼──────┐         ┌────────────▼───┐
│ Database │         │  reports.php   │
│   .php   │         │ (PDF wrapper)  │
│          │         └────────┬────────┘
└───┬──────┘                  │
    │                         │
    │      ┌──────────────────┴──┐
    │      │                     │
    │  ┌───▼──────────────┐      │
    │  │ MySQL Database   │      │
    │  │ student_ui_portal│      │
    │  │ oauth database   │      │
    │  └──────────────────┘      │
    │                            │
┌───▼────────────────────────┐  │
│  AuthService.php           │  │
│ (if auth endpoint exists)  │  │
│  Uses: Database.php        │  │
│         Mailer.php         │  │
└────────────────────────────┘  │
                                │
            ┌───────────────────┴──────────────────┐
            │                                      │
      ┌─────▼────────────┐   ┌──────────────────▼──┐
      │  render_reports  │   │  generate_chart.py  │
      │  .py             │   │  render_charts.py   │
      │  (via proc_open) │   │  universal_export   │
      │                  │   │  .py                │
      │  Returns: PDF    │   │                     │
      │  Binary          │   │  Returns: PDF/JSON  │
      └──────────────────┘   │  or CSV/XLSX        │
                             └─────────────────────┘
```

---

## 6. Security Considerations

### Current Implementations

1. **SQL Injection Prevention**
   - Prepared statements with parameter binding (placeholders)
   - Validated table names for dynamic queries (whitelist approach)

2. **Password Security**
   - Bcrypt hashing (PASSWORD_DEFAULT)
   - Constant-time comparison via password_verify()

3. **Session Management**
   - HTTPOnly cookies (prevents JS access)
   - SameSite=Lax (CSRF protection)
   - Session ID regeneration on login
   - Secure logout with cookie deletion

4. **CORS Configuration**
   - Origin validation (though broad in current setup)
   - Preflight OPTIONS handling
   - Credential support for cross-origin requests

5. **OTP Security**
   - Time-based expiration (5 minutes)
   - Email delivery via secure SMTP

### Potential Vulnerabilities

1. **Hardcoded Credentials** (Development)
   - Database credentials in plaintext in api.php
   - Should use environment variables (.env file)

2. **Broad CORS Policy**
   - Currently accepts all origins in api.php
   - Should restrict to specific frontend domains

3. **Debug File Creation**
   - `debug_query.sql` and `debug_query.log` files written to disk
   - Should be disabled in production

4. **Process Spawning**
   - Python processes spawned via proc_open
   - Should validate command-line arguments carefully
   - Currently uses escapeshellarg() in some places

5. **Error Messages**
   - Detailed error responses may leak system information
   - Should sanitize in production

---

## 7. Data Model & Relationships

### Key Entities

```
profiles (student_ui_portal)
├── user_id → users.id
├── department_id → departments.id
├── faculty_id (via departments.faculty_id)
├── level_id (Academic level: 100, 200, 300, 400, 500+)
├── hall_id → halls.id
├── gender (M/F/Other)
├── state_of_origin (Nigerian state)
├── lga (Local Government Area)
├── dob (Date of Birth)
├── marital_status
├── religion
├── mode_of_admission (UTME/Direct Entry)
└── [other demographic fields]

departments (student_ui_portal)
├── id
├── name
└── faculty_id → faculties.id

faculties (student_ui_portal)
├── id
└── name

halls (student_ui_portal)
├── id
└── name

users (oauth)
├── id
├── uid (Unique identifier: U-[hex])
├── email
├── password_hash
├── role
├── profile_id → profiles.user_id
├── department_id
└── faculty_id
```

### Data Access Patterns

**Single Metric Query** (e.g., student count by gender):
```sql
SELECT p.gender as name, COUNT(DISTINCT p.user_id) as value
FROM profiles p
WHERE [filters]
GROUP BY p.gender
```

**Multi-Series Query** (e.g., gender by level):
```sql
SELECT p.gender as name, p.level_id as series_key, COUNT(DISTINCT p.user_id) as value
FROM profiles p
WHERE [filters]
GROUP BY p.gender, p.level_id
```

**With Joins** (e.g., department names, not IDs):
```sql
SELECT d.name as name, COUNT(DISTINCT p.user_id) as value
FROM profiles p
LEFT JOIN departments d ON p.department_id = d.id
WHERE [filters]
GROUP BY d.name
```

---

## 8. Dependencies & Requirements

### System Requirements
- **OS**: Windows (Laragon), Linux/Mac compatible
- **Web Server**: Apache/PHP-FPM (via Laragon)
- **PHP**: 7.4 or higher
- **MySQL**: 5.7+ or MariaDB
- **Python**: 3.6+

### Python Package Dependencies
```
fpdf2          # PDF generation
matplotlib     # Chart creation
pandas         # Data manipulation
numpy          # Numerical operations
requests       # HTTP client
faker          # Dummy data (testing)
```

### External Services
- **Mailpit** (localhost:1025) - SMTP server for OTP emails
- **MySQL Database** (192.168.3.83) - Primary data store

### Network Configuration
- Frontend: http://localhost:5173 or http://192.168.3.83:5173
- Database: 192.168.3.83:3306
- CORS headers configured for these endpoints

---

## 9. Workflow Examples

### Scenario 1: Generate Student List Report

```
User Interface
    ↓
POST /api.php {
  action: "generate_student_list",
  level: "300",
  gender: "F",
  state: "Lagos"
}
    ↓
api.php: Parse filters → Build WHERE clause
    ↓
Query: SELECT u.uid, p.state_of_origin, etc. FROM profiles...
    ↓
Format: Build list title "300 Level Female Students from Lagos"
    ↓
Response: JSON with student records
    ↓
Frontend: Display in table/list format
```

---

### Scenario 2: Generate Chart & Export PDF

```
User Interface
    ↓
POST /api.php {
  action: "download_pdf",
  breakdown: "state_of_origin",
  type: "bar",
  title: "Student Distribution by State"
}
    ↓
api.php: Execute query → Aggregate by state
    ↓
Build payload with:
  - Chart type: bar
  - Data: [{name: "Lagos", value: 250}, ...]
  - Colors: Generated color palette
  - Config: Bar chart configuration
    ↓
proc_open("python generate_chart.py")
    ↓
Python process:
  - Read JSON from STDIN
  - Create matplotlib Figure
  - Generate PDF with PdfPages
  - Write binary to STDOUT
    ↓
PHP captures output → Send to browser
    ↓
Headers: Content-Type: application/pdf
Content-Disposition: attachment; filename="chart.pdf"
    ↓
Frontend: Browser downloads PDF
```

---

### Scenario 3: User Authentication

```
Client
    ↓
POST /api.php {
  action: "login",
  email: "user@ui.edu.ng",
  password: "password123"
}
    ↓
AuthService.php:
  1. Query oauth.users by email
  2. password_verify(input_password, db_hash)
  3. If match: Regenerate session ID
  4. Store in $_SESSION: user_id, role, profile_id, etc.
    ↓
Response: {
  success: true,
  user: {id, uid, role, department_id, ...}
}
    ↓
Client: Session cookie set (HTTPOnly, SameSite=Lax)
Client: Stores user info in frontend state
```

---

### Scenario 4: Password Reset via OTP

```
Client: "Forgot Password" click
    ↓
POST /api.php {
  action: "requestReset",
  email: "user@ui.edu.ng"
}
    ↓
AuthService.requestReset():
  1. Check email in oauth.users
  2. Generate random OTP (typically 6 digits)
  3. Store in $_SESSION['otp_store'][email]
  4. Call Mailer.sendOtp(email, otp)
    ↓
Mailer.sendOtp():
  - Build HTML email template
  - Connect to Mailpit SMTP (localhost:1025)
  - Send email with OTP
    ↓
Response: {success: true, message: "OTP sent"}
    ↓
Client: Shows OTP input form
    ↓
User receives email with OTP (max 5 minutes)
Client: Submits OTP + new password
    ↓
Server: Verifies OTP → Updates password_hash → Clears OTP
```

---

## 10. File Structure Summary

```
c:\laragon\www\exinsab\
├── api.php                           # Main API router
├── reports.php                       # PDF report endpoint
├── render_charts.py                  # Chart generation (complex)
├── render_reports.py                 # Report PDF generation
├── console_batch_report.py           # Batch report utility
├── universal_export.py               # Multi-format export (CSV/XLSX/PDF)
├── tomolu.py                         # Dummy data generator
│
├── backend/
│   ├── chart_generator.py            # Advanced chart generation
│   │
│   ├── db-worker/
│   │   └── Database.php              # PDO database wrapper
│   │
│   └── oauth/
│       ├── auth.php                  # (likely auth routes)
│       ├── AuthService.php           # Authentication logic
│       ├── Mailer.php                # Email/OTP service
│       ├── cors_helper.php           # CORS configuration
│       ├── debug_query.sql           # Debug file (generated)
│       └── otp_log.txt               # OTP log (generated)
│
├── .git/                             # Git repository
├── .gitignore                        # Git ignore rules
└── .vscode/                          # VS Code settings
```

---

## 11. Performance & Scalability Notes

### Current Bottlenecks
1. **Process Spawning** - Python processes spawned per request (overhead)
2. **Large Query Results** - No pagination in some queries
3. **LEFT JOINs** - Multiple joins on each query can be slow with large datasets
4. **Single Database Connection** - No connection pooling

### Optimization Opportunities
1. Implement query caching (Redis)
2. Add pagination to student list queries
3. Use database views for common aggregations
4. Implement async Python task queue (Celery)
5. Add indexes on frequently filtered columns (gender, level_id, state_of_origin)
6. Consider materialized views for pre-computed statistics

### Scalability
**Current Design**: Single-machine, vertical scaling
- Suitable for: 10K-100K students
- Bottleneck at: Database (MySQL) and chart generation (matplotlib)
- Would need: Horizontal scaling at 1M+ records

---

## 12. Configuration & Setup

### Database Setup
```sql
CREATE DATABASE student_ui_portal;
CREATE DATABASE oauth;

-- student_ui_portal tables would include:
-- profiles, departments, faculties, halls, users, etc.

-- oauth database tables:
-- users (id, uid, email, password_hash, role, profile_id, etc.)
```

### Environment Setup Needed
1. MySQL server at 192.168.3.83
2. Python 3.6+ with required packages
3. Mailpit SMTP server on localhost:1025
4. Apache/PHP server (Laragon)
5. Frontend at port 5173

### Configuration Changes for Production
1. Move credentials to .env file
2. Change CORS to specific origins
3. Disable debug file creation
4. Enable HTTPS
5. Add proper error logging
6. Implement rate limiting
7. Add request validation/sanitization

---

## Summary Table

| Aspect | Details |
|--------|---------|
| **Primary Purpose** | University student analytics & reporting system |
| **Backend Languages** | PHP, Python |
| **Databases** | MySQL (student_ui_portal + oauth) |
| **Main Entry Points** | api.php, reports.php |
| **Key Modules** | AuthService, Database, Mailer, Chart Generators |
| **Chart Types** | Bar, Line, Area, Pie, Scatter, Table |
| **Export Formats** | CSV, XLSX, PDF |
| **Filters** | Gender, Level, Department, Faculty, State, Zone, Hall, Religion, etc. |
| **Authentication** | Email/Password with Bcrypt + OTP password reset |
| **SMTP Service** | Mailpit (localhost:1025) |
| **Data Flow** | Frontend → PHP API → MySQL / Python Processes → PDF/JSON/CSV |
| **Security** | Prepared statements, Bcrypt, Session tokens, OTP, CORS |

