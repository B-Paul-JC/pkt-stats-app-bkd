# Architecture & Design - EXINSAB

High-level system architecture, component interactions, data flows, and technical design decisions.

## System Architecture Overview

```
┌──────────────────────────────────────────────────────────────────┐
│                        Frontend Layer                             │
│  (Web/Mobile App - Not part of this repo)                        │
└─────────────────────────┬──────────────────────────────────────┘
                          │ HTTP/JSON
                          ▼
┌──────────────────────────────────────────────────────────────────┐
│                    API Gateway Layer                              │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ CORS Middleware │ Auth Middleware │ Rate Limiter          │ │
│  │ cors_helper.php │ Session mgmt    │ Request throttle      │ │
│  └─────────────────────────────────────────────────────────────┘ │
└────────────────┬────────────────────────────────────┬─────────────┘
                 │                                    │
                 ▼                                    ▼
        ┌────────────────────┐          ┌────────────────────────┐
        │  api.php (Router)  │          │  reports.php (Router)  │
        │  Endpoint routing  │          │  Report endpoints      │
        │  Request dispatch  │          └────────────────────────┘
        └────────────────────┘
                 │
        ┌────────┴──────────┬──────────────┬──────────────┐
        ▼                   ▼              ▼              ▼
   ┌─────────┐      ┌─────────────┐  ┌──────────┐  ┌──────────────┐
   │ Student │      │ Chart       │  │ Export   │  │ Batch        │
   │ Service │      │ Generation  │  │ Service  │  │ Processing   │
   │         │      │             │  │          │  │              │
   └────┬────┘      └──────┬──────┘  └────┬─────┘  └──────────────┘
        │                  │              │
        │ (Spawns)       │              │ (Spawns)     │ (Spawns)
        ▼                ▼              ▼              ▼
   ┌────────────────────────────────────────────────────────────┐
   │           Python Processing Layer                          │
   │  ┌──────────────────┐ ┌──────────────┐ ┌──────────────┐   │
   │  │ render_charts.py │ │ universal_   │ │generate_batch│   │
   │  │                  │ │ export.py    │ │_report.py    │   │
   │  │ Uses:            │ │              │ │              │   │
   │  │ - matplotlib     │ │ Uses:        │ │ Uses:        │   │
   │  │ - pandas         │ │ - pandas     │ │ - fpdf2      │   │
   │  │ - numpy          │ │ - openpyxl   │ │ - matplotlib │   │
   │  │                  │ │              │ │ - requests   │   │
   │  └──────────────────┘ └──────────────┘ └──────────────┘   │
   └─────────────────────────────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        ▼                         ▼
   ┌─────────────────┐    ┌──────────────────────┐
   │ Chart Images    │    │ Export Files         │
   │ (PNG/SVG)       │    │ (CSV/XLSX/PDF)       │
   └─────────────────┘    └──────────────────────┘
        │                         │
        ▼                         ▼
   ┌──────────────────────────────────────────────┐
   │      Data Access Layer (Database.php)        │
   │  PDO Database Abstraction                    │
   └────────────┬──────────────────────────────────┘
                │
        ┌───────┴────────────┐
        ▼                    ▼
   ┌──────────────────┐  ┌────────────────────┐
   │ MySQL: student_  │  │ MySQL: oauth       │
   │ ui_portal DB     │  │ Authentication DB  │
   │                  │  │                    │
   │ • profiles       │  │ • users            │
   │ • departments    │  │ • otp_logs         │
   │ • faculties      │  │ • sessions         │
   │ • halls          │  │                    │
   └──────────────────┘  └────────────────────┘
```

## Component Breakdown

### 1. API Layer (`api.php`, `reports.php`)

**Purpose**: Route incoming HTTP requests to appropriate handlers

**Responsibilities**:

- Parse query parameters and request body
- Validate request format and parameters
- Determine which action/handler to invoke
- Format and return JSON responses

**Key Routes**:

- `GET /api.php?action=get_students` - Fetch student data
- `POST /api.php?action=create_chart` - Generate chart
- `GET /api.php?action=export` - Export data
- `POST /reports.php?action=...` - Report operations

### 2. Authentication Service (`AuthService.php`)

**Purpose**: Handle user authentication and session management

**Responsibilities**:

- Validate email/password credentials
- Generate authentication tokens
- Manage sessions (create, validate, destroy)
- Hash passwords with Bcrypt (cost: 12 rounds)
- Generate and validate OTP for password reset

**Key Methods**:

```
login(email, password) → token
validateToken(token) → user_data
generateOTP(email) → otp_code
validateOTP(email, otp) → boolean
resetPassword(email, new_password) → success
```

**Database**: Uses `oauth` database

### 3. CORS Helper (`cors_helper.php`)

**Purpose**: Handle Cross-Origin Resource Sharing

**Configuration**:

- Whitelist allowed origins
- Set allowed methods (GET, POST, PUT, DELETE)
- Define allowed headers
- Handle preflight requests (OPTIONS)

**Security**:

- Restrict origins to trusted domains
- Avoid using wildcard (\*) in production
- Validate origin header

### 4. Database Abstraction (`Database.php`)

**Purpose**: Provide PDO-based database access layer

**Responsibilities**:

- Establish database connections
- Execute prepared statements (prevents SQL injection)
- Handle connection pooling
- Provide error handling and logging

**Key Methods**:

```
prepare(sql) → PDOStatement
execute(sql, params) → result
query(sql) → array
findById(table, id) → object
insert(table, data) → insert_id
update(table, id, data) → affected_rows
delete(table, id) → affected_rows
```

**Connection Strategy**:

- Separate connections for `student_ui_portal` and `oauth` databases
- Connection reuse within request lifecycle
- Automatic reconnection on connection loss

### 5. Student Service

**Purpose**: Handle student data queries and filtering

**Responsibilities**:

- Query student profiles from database
- Apply filters (gender, level, department, etc.)
- Join with related tables (departments, faculties, halls)
- Return formatted student data

**Filtering Logic**:

```
Query students with applied filters:
  SELECT p.*, d.dept_code, d.dept_name, f.faculty_name, h.hall_name
  FROM profiles p
  LEFT JOIN departments d ON p.department_id = d.department_id
  LEFT JOIN faculties f ON d.faculty_id = f.faculty_id
  LEFT JOIN halls h ON p.hall_id = h.hall_id
  WHERE (gender = ? OR ? IS NULL)
    AND (level = ? OR ? IS NULL)
    AND (state_of_origin = ? OR ? IS NULL)
    AND (department_id IN (SELECT ...dept_id WHERE dept_code = ?) OR ? IS NULL)
```

### 6. Chart Generation

#### `render_charts.py` - Simple Charts

**Purpose**: Generate basic statistical visualizations
**Responsibilities**:

- Fetch aggregated data from database
- Create visualizations using matplotlib
- Support multiple chart types
- Save charts as PNG/SVG images

#### `generate_batch_report.py` - Advanced PDF Reports

**Purpose**: Generate comprehensive PDF reports with embedded charts and tables
**Responsibilities**:

- Create multi-page PDF documents
- Embed charts (bar, line, pie, area, scatter) as PNG images
- Render data tables with automatic column sizing
- Add headers, footers, metadata, school logos
- Handle pagination and styling
- Combine charts and tables into single PDF

**Supported Chart Types**:

1. **Bar Chart** - Discrete categories
2. **Line Chart** - Trends over categories
3. **Pie Chart** - Proportional distribution
4. **Area Chart** - Stacked areas over time/categories
5. **Scatter Plot** - Relationship between variables

**Breakdown Types**:

- By gender (M/F)
- By academic level (100-700)
- By state of origin
- By department
- By faculty
- By residential hall

**Technology Stack**:

- `matplotlib` - Chart rendering
- `pandas` - Data aggregation
- `numpy` - Numerical operations

**Output**: PNG images saved to `/charts/` directory

### 7. PDF Report Generator (`generate_batch_report.py`)

**Purpose**: Generate comprehensive multi-page PDF reports with embedded content

**Responsibilities**:

- Create professional PDF documents with headers, footers, metadata
- Embed chart images (bar, line, pie, area, scatter charts)
- Render data tables with automatic column sizing
- Handle pagination and page breaks
- Add school information (name, address, logo)
- Support mixed content (charts and tables in single PDF)

**Input Format**: JSON with metadata and items array

```json
{
  "meta": {
    "school_name": "University of Ibadan",
    "address": "Nigeria",
    "school_logo": "https://url.to/logo.png"
  },
  "items": [
    {"type": "chart", "title": "Distribution", "data": [...], "config": {...}},
    {"type": "table", "title": "Student List", "data": [...]}
  ]
}
```

**Output**: Binary PDF file

**Technology Stack**:

- `fpdf2` - PDF generation
- `matplotlib` - Chart rendering to PNG
- `pandas` - Data manipulation
- `requests` - Fetch remote logos

---

### 8. Export Service (`universal_export.py`)

**Purpose**: Generate exportable documents

**Responsibilities**:

- Query student data with filters
- Format data for different export types
- Generate CSV/XLSX/PDF files
- Stream files to client

**Supported Formats**:

1. **CSV** - Comma-separated values
2. **XLSX** - Excel format (openpyxl)
3. **PDF** - Portable document format (fpdf2)

**Technology Stack**:

- `pandas` - Data manipulation
- `openpyxl` - Excel generation
- `fpdf2` - PDF generation

### 9. Batch Processing (`console_batch_report.py`)

**Purpose**: Generate multiple reports in batch

**Responsibilities**:

- Queue batch jobs
- Execute reports in sequence/parallel
- Aggregate results
- Store output files

**Features**:

- Job status tracking
- Progress monitoring
- Error handling and retry logic
- Scheduled execution

## Data Flow Diagrams

### Login Flow

```
User Request (email, password)
    ↓
[AuthService] Validate credentials
    ↓
Is password correct? --NO-→ Return 401 Error
    ↓ YES
Generate JWT token or session
    ↓
Store session in oauth DB
    ↓
Return token/session to client
    ↓
Client stores token in localStorage/Cookie
```

### Student Report Generation Flow

```
User Request (charter_type, breakdown_by, filters)
    ↓
[api.php] Validate parameters
    ↓
[StudentService] Query student data with filters
    ↓
[render_charts.py] Process data & generate chart
    ↓
[matplotlib] Render visualization
    ↓
Cache image in /charts/
    ↓
Return image URL/base64 to client
```

### Export Flow

```
User Request (format, filters)
    ↓
[api.php] Validate request
    ↓
[StudentService] Fetch filtered data
    ↓
[universal_export.py] Format for export type
    ↓
Generate file (CSV/XLSX/PDF)
    ↓
Set response headers (Content-Type, Content-Disposition)
    ↓
Stream file to client
```

## Database Schema

### Tables in `student_ui_portal` Database

#### profiles (Student Master Data)

```sql
┌─────────────────────────────────────────┐
│            profiles                     │
├─────────────────────────────────────────┤
│ profile_id (PK)          INT PRIMARY KEY│
│ matric_no (UNIQUE)       VARCHAR(20)    │
│ first_name               VARCHAR(100)   │
│ last_name                VARCHAR(100)   │
│ middle_name              VARCHAR(100)   │
│ gender                   CHAR(1) M/F    │
│ level                    INT (100-700)  │
│ department_id (FK)       INT            │ ──→ departments
│ faculty_id (FK)          INT            │ ──→ faculties
│ hall_id (FK)             INT            │ ──→ halls
│ state_of_origin          VARCHAR(50)    │
│ date_of_birth            DATE           │
│ phone_number             VARCHAR(20)    │
│ email                    VARCHAR(100)   │
│ created_at               TIMESTAMP      │
│ updated_at               TIMESTAMP      │
└─────────────────────────────────────────┘
```

#### departments

```sql
┌──────────────────────────────────┐
│       departments                │
├──────────────────────────────────┤
│ department_id (PK)     INT PRIMARY│
│ dept_code (UNIQUE)     VARCHAR(10)│
│ dept_name              VARCHAR(100)
│ faculty_id (FK)        INT        │ ──→ faculties
│ created_at             TIMESTAMP  │
└──────────────────────────────────┘
```

#### faculties

```sql
┌──────────────────────────────────┐
│       faculties                  │
├──────────────────────────────────┤
│ faculty_id (PK)      INT PRIMARY  │
│ faculty_name         VARCHAR(100) │
│ created_at           TIMESTAMP    │
└──────────────────────────────────┘
```

#### halls (Residential Halls)

```sql
┌──────────────────────────────────┐
│       halls                      │
├──────────────────────────────────┤
│ hall_id (PK)       INT PRIMARY    │
│ hall_name          VARCHAR(100)   │
│ capacity           INT            │
│ occupancy          INT            │
│ created_at         TIMESTAMP      │
└──────────────────────────────────┘
```

### Tables in `oauth` Database

#### users (Authentication)

```sql
┌────────────────────────────────────┐
│       users                        │
├────────────────────────────────────┤
│ user_id (PK)         INT PRIMARY   │
│ email (UNIQUE)       VARCHAR(100)  │
│ password_hash        VARCHAR(255)  │
│ profile_id (FK)      INT           │
│ last_login           DATETIME      │
│ is_active            BOOLEAN       │
│ created_at           TIMESTAMP     │
│ updated_at           TIMESTAMP     │
└────────────────────────────────────┘
```

#### otp_log (OTP Tracking)

```sql
┌────────────────────────────────────┐
│       otp_log                      │
├────────────────────────────────────┤
│ id (PK)              INT PRIMARY    │
│ email                VARCHAR(100)   │
│ otp_code             CHAR(6)        │
│ is_used              BOOLEAN        │
│ created_at           TIMESTAMP      │
│ expires_at           TIMESTAMP      │
└────────────────────────────────────┘
```

#### sessions (Session Management)

```sql
┌────────────────────────────────────┐
│       sessions                     │
├────────────────────────────────────┤
│ session_id (PK)      VARCHAR(255)   │
│ user_id (FK)         INT            │
│ ip_address           VARCHAR(45)    │
│ user_agent           TEXT           │
│ created_at           TIMESTAMP      │
│ last_activity        TIMESTAMP      │
│ expires_at           TIMESTAMP      │
└────────────────────────────────────┘
```

## Security Architecture

### Authentication & Authorization

1. **Password Storage**
   - Bcrypt hashing with cost factor of 12
   - Salts generated per password
   - Never store plaintext passwords

2. **Session Management**
   - HTTPOnly cookies (prevent XSS access)
   - Secure flag in HTTPS
   - Session expiration (default: 24 hours)
   - Session regeneration on login

3. **Token-Based Auth**
   - JWT tokens for API endpoints
   - Token expiration (configurable)
   - Token refresh mechanism

4. **OTP Password Reset**
   - 6-digit OTP codes
   - Sent via email (Mailpit/SMTP)
   - Expires after 30 minutes
   - One-time use only

### SQL Injection Prevention

All queries use **prepared statements**:

```php
// SAFE ✓
$stmt = $db->prepare("SELECT * FROM profiles WHERE gender = ? AND level = ?");
$stmt->execute([$gender, $level]);

// UNSAFE ✗ (Vulnerable to SQL injection)
$query = "SELECT * FROM profiles WHERE gender = '$gender'";
```

### CORS Security

- Whitelist allowed origins (avoid wildcard `*`)
- Validate Origin header on all requests
- Control allowed methods (GET, POST, etc.)
- Control allowed headers
- Handle preflight requests properly

### Password Reset Security

- OTP sent via email (not SMS or insecure channels)
- OTP expires (30 minutes)
- Invalidated after use
- Email rate limiting (max 3 requests/hour)
- No user enumeration (same response if email exists/not)

## Performance Considerations

### Database Optimization

1. **Indexes**

   ```sql
   CREATE INDEX idx_gender ON profiles(gender);
   CREATE INDEX idx_level ON profiles(level);
   CREATE INDEX idx_department ON profiles(department_id);
   CREATE INDEX idx_matric ON profiles(matric_no);
   ```

2. **Query Optimization**
   - Use EXPLAIN to analyze slow queries
   - Avoid SELECT \* (specify needed columns)
   - Use JOINs instead of multiple queries
   - Limit result sets with pagination

3. **Connection Pooling**
   - Reuse database connections
   - Set appropriate connection timeout
   - Monitor connection usage

### Caching Strategy

1. **Chart Caching** - Cache generated charts for repeated requests
   - Cache key: `{breakdown_by}_{filter_by}_{filter_value}`
   - TTL: 1 hour (or configurable)

2. **Data Caching** - Cache expensive aggregations
   - Cache student count by gender/level/etc.
   - Invalidate on data updates

3. **Query Caching**
   - Cache department/faculty lists
   - Cache static reference data

### Scalability Patterns

1. **Horizontal Scaling**
   - Stateless API design (no session in memory)
   - Database as single source of truth
   - Load balancer distributes requests

2. **Batch Processing**
   - Queue large export jobs
   - Process asynchronously
   - Store results for download

3. **Python Worker Pool**
   - Chart generation in separate processes
   - Limit concurrent Python processes
   - Monitor resource usage

## Deployment Architecture

### Local Development

```
Laragon (PHP 7.4+, MySQL, Apache)
  └─ exinsab/
      ├─ api.php (localhost:8000)
      └─ backend/
```

### Production

```
Load Balancer (HAProxy/nginx)
  ├─ Web Server 1 (Apache/nginx + PHP)
  ├─ Web Server 2 (Apache/nginx + PHP)
  └─ Web Server 3 (Apache/nginx + PHP)
      └─ All connect to
         MySQL Database Cluster (Master-Slave Replication)
         Worker Nodes (Python batch processing)
```

### Configuration Management

- Environment variables in `.env` file
- Separate configs for dev/staging/production
- Secrets stored in secure vault (not in git)

## Error Handling Strategy

### PHP Layer

```php
try {
    // Perform operation
    $result = $service->process();
    return json_response('success', $result);
} catch (ValidationException $e) {
    return json_response('error', null, $e->getMessage(), 400);
} catch (DatabaseException $e) {
    return json_response('error', null, 'Database error', 500);
} catch (Exception $e) {
    log_error($e);
    return json_response('error', null, 'Unexpected error', 500);
}
```

### Python Layer

- Write errors to log files
- Return non-zero exit codes on failure
- Parent PHP process detects failure via return code

### Logging

- Log all authentication attempts
- Log database errors
- Log process execution failures
- Rotate logs to prevent disk space issues
- Filter sensitive data from logs

## Technology Choices

| Component        | Technology          | Why                                                          |
| ---------------- | ------------------- | ------------------------------------------------------------ |
| Backend API      | PHP 7.4+            | Simplicity, shared hosting friendly, rapid development       |
| Database         | MySQL 5.7+          | Reliable, good support for joins, free, widely supported     |
| Chart Generation | Python + Matplotlib | Rich visualization library, good performance, easy to extend |
| Export           | Python + pandas     | Powerful data manipulation, supports multiple formats        |
| Authentication   | Bcrypt + JWT        | Industry standard, secure, stateless                         |
| File Format      | JSON                | Universal standard, human readable, web native               |

## Future Enhancements

1. **API Versioning** - Support multiple API versions (v1/, v2/)
2. **Advanced Analytics** - Statistical summaries, forecasting
3. **Real-time Updates** - WebSockets for live charts
4. **Caching Layer** - Redis for session and data caching
5. **API Documentation** - Swagger/OpenAPI specification
6. **Microservices** - Split into separate services (Auth, Reports, Data)
7. **Event-Driven** - Message queue for asynchronous operations
8. **Monitoring** - APM integration (New Relic, Datadog)
9. **GraphQL** - Support GraphQL queries as alternative to REST
10. **Mobile API** - Optimized endpoints for mobile clients
