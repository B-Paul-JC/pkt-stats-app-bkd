# Development Guide - EXINSAB

Guidelines, setup instructions, best practices, and troubleshooting for developers working on this project.

## Development Environment Setup

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Python 3.7 or higher
- Composer (for PHP dependency management)
- Git
- Code editor (VS Code recommended)
- Laragon (for local development on Windows)

### Step-by-Step Setup

#### 1. Clone Repository

```bash
cd c:\laragon\www\exinsab
# or navigate to your project directory
```

#### 2. Install PHP Dependencies (if using Composer)

```bash
composer install
```

#### 3. Install Python Dependencies

```bash
pip install pandas matplotlib fpdf2 openpyxl
```

Or use requirements file (create if missing):

```bash
# Create requirements.txt
echo pandas==1.3.0 >> requirements.txt
echo matplotlib==3.5.0 >> requirements.txt
echo fpdf2==2.7.0 >> requirements.txt
echo openpyxl==3.7.0 >> requirements.txt

pip install -r requirements.txt
```

#### 4. Verify PHP Installation

```bash
php --version
# Output: PHP 7.4.x (or higher)
```

#### 5. Database Configuration

Edit `/backend/Database.php` and update credentials:

```php
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';  // Enter your MySQL password
const DB_NAME = 'student_ui_portal';

const AUTH_DB_HOST = 'localhost';
const AUTH_DB_USER = 'root';
const AUTH_DB_PASS = '';
const AUTH_DB_NAME = 'oauth';
```

Or better, use environment variables:

```bash
cp .env.example .env
# Edit .env with your credentials
```

#### 6. Create MySQL Databases

```sql
-- Connect to MySQL
mysql -u root -p

-- Create databases
CREATE DATABASE IF NOT EXISTS student_ui_portal DEFAULT CHARSET utf8mb4;
CREATE DATABASE IF NOT EXISTS oauth DEFAULT CHARSET utf8mb4;

-- Import schema (if available)
mysql -u root -p student_ui_portal < schema/student_ui_portal.sql
mysql -u root -p oauth < schema/oauth.sql

-- Verify
SHOW DATABASES;
```

#### 7. Setup SMTP Server (Local Development)

Install and run Mailpit for testing emails:

**Windows**:

```bash
# Download from https://github.com/axllent/mailpit/releases
mailpit
# Runs on localhost:1025
```

**Docker**:

```bash
docker run --rm -it -p 1025:1025 -p 8025:8025 axllent/mailpit
```

Update SMTP config in `/backend/oauth/Mailer.php`:

```php
const SMTP_HOST = 'localhost';
const SMTP_PORT = 1025;
const SMTP_USER = '';      // Mailpit doesn't require auth
const SMTP_PASS = '';
```

#### 8. Start Development Server

```bash
# PHP built-in server
php -S localhost:8000

# Or use Laragon (automatic)
# Start Laragon application
```

Test the API:

```bash
curl http://localhost:8000/api.php
```

## Project Structure Details

```
exinsab/
├── api.php                           # Main API entry point
├── reports.php                       # Report endpoints
├── tomolu.py                         # Utility script
├── console_batch_report.py           # Batch processing script
├── render_charts.py                  # Chart generation script
├── render_reports.py                 # Report generation script
├── universal_export.py               # Export functionality
│
├── backend/
│   ├── chart_generator.py            # Chart generation (may be called by api.php)
│   │
│   ├── db-worker/
│   │   └── Database.php              # Database abstraction class
│   │
│   └── oauth/
│       ├── auth.php                  # Auth endpoints
│       ├── AuthService.php           # Authentication logic
│       ├── cors_helper.php           # CORS configuration
│       ├── Mailer.php               # Email service
│       ├── debug_query.sql           # Debug queries (dev only)
│       └── otp_log.txt               # OTP log (dev only)
│
├── vendor/                           # Composer dependencies (if used)
├── logs/                             # Application logs
├── charts/                           # Generated chart images
├── exports/                          # Generated export files
│
├── .env                              # Environment variables (git-ignored)
├── .env.example                      # Example env file
├── .gitignore                        # Git ignore rules
│
└── docs/
    ├── README.md                     # Project overview
    ├── API.md                        # API documentation
    ├── ARCHITECTURE.md               # System design
    ├── DEVELOPMENT.md                # This file
    └── CHANGELOG.md                  # Version history
```

## Code Style & Standards

### PHP Standards (PSR-12)

#### Class Definition

```php
<?php

namespace ExinsabBackend;

use PDO;
use Exception;

class StudentService
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieve students with optional filters
     *
     * @param array $filters Filter conditions
     * @return array List of students
     * @throws Exception
     */
    public function getStudents(array $filters = []): array
    {
        // Implementation
    }
}
```

#### Naming Conventions

- **Classes**: PascalCase (`StudentService`, `AuthService`)
- **Methods**: camelCase (`getStudents`, `validateToken`)
- **Constants**: UPPER_SNAKE_CASE (`DB_HOST`, `MAX_RETRY_ATTEMPTS`)
- **Variables**: camelCase (`$studentId`, `$filterValue`)
- **Private properties**: `$_private` or `private $property`

#### Indentation & Formatting

- Use 4 spaces (not tabs)
- Opening braces on same line
- Max line length: 120 characters
- Blank line between methods

#### Comments & Documentation

```php
/**
 * Short description
 *
 * Longer description if needed.
 *
 * @param string $email User email
 * @param string $password User password
 * @return array User data with token
 * @throws InvalidArgumentException If email is invalid
 * @throws AuthenticationException If password is incorrect
 */
public function login(string $email, string $password): array
{
    // Implementation
}
```

### Python Standards (PEP 8)

#### Code Format

```python
"""Module docstring describing purpose."""

import pandas as pd
import matplotlib.pyplot as plt
from typing import Dict, List, Tuple

class ChartGenerator:
    """Generate charts from student data."""

    def __init__(self, db_connection):
        """Initialize chart generator with database connection."""
        self.db = db_connection

    def generate_bar_chart(
        self,
        breakdown_by: str,
        filters: Dict = None
    ) -> Tuple[str, Dict]:
        """
        Generate a bar chart.

        Args:
            breakdown_by: Column to group data by
            filters: Optional filter criteria

        Returns:
            Tuple of (image_path, data_summary)

        Raises:
            ValueError: If breakdown_by is invalid
        """
        # Implementation
        pass
```

#### Naming Conventions

- **Classes**: PascalCase (`ChartGenerator`, `DatabaseConnection`)
- **Functions/Methods**: snake_case (`generate_bar_chart`, `get_students`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_RETRY_COUNT`, `CHART_TIMEOUT`)
- **Variables**: snake_case (`student_data`, `filter_value`)
- **Private**: `_private_method`, `_private_var`

#### Formatting

- Use 4 spaces for indentation
- Max line length: 88 characters (Black formatter standard)
- Use type hints where possible
- Docstrings for modules, classes, and functions

### General Code Guidelines

#### Functions Should Be Small

- Aim for < 50 lines per function
- Single responsibility principle
- Extract complex logic to separate functions

#### Use Meaningful Names

```php
// GOOD ✓
$profilesWithGenderFilter = $this->getStudentsByGender($gender);

// AVOID ✗
$data = $this->getStudents($g);
$arr1 = $this->process($x);
```

#### Error Handling

```php
// DO THIS
try {
    $result = $this->db->query($sql, $params);
} catch (PDOException $e) {
    error_log("Database query failed: " . $e->getMessage());
    throw new Exception("Unable to fetch data", 500);
}

// NOT THIS
$result = $this->db->query($sql, $params);
// or
// @$this->db->query($sql, $params);
```

#### Validation

```php
// Validate input early
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new InvalidArgumentException('Invalid email format');
}

if (!in_array($gender, ['M', 'F'])) {
    throw new InvalidArgumentException('Gender must be M or F');
}
```

## Common Development Tasks

### Adding a New API Endpoint

1. **Create handler function** in `api.php`:

```php
function handleGetDepartments() {
    $db = new Database();
    $departments = $db->query(
        "SELECT * FROM departments ORDER BY dept_name"
    );
    return json_response('success', $departments);
}
```

2. **Add route** to API router:

```php
$action = $_GET['action'] ?? '';
$routes = [
    'get_students' => 'handleGetStudents',
    'get_departments' => 'handleGetDepartments',
    'create_chart' => 'handleCreateChart',
    // ... other routes
];

if (isset($routes[$action])) {
    $response = call_user_func($routes[$action]);
    // ...
}
```

3. **Test the endpoint**:

```bash
curl http://localhost:8000/api.php?action=get_departments \
  -H "Authorization: Bearer {token}"
```

4. **Document in API.md**:
   Add endpoint details, parameters, and examples.

### Adding Python Script

#### Simple Script Example

1. **Create script** in project root:

```python
# my_task.py
"""Description of what this script does."""

import pandas as pd
import sys
import json

def main(filters: dict) -> dict:
    """Execute task with filters."""
    # Implementation
    return {'success': True, 'data': {...}}

if __name__ == '__main__':
    filters = json.loads(sys.argv[1]) if len(sys.argv) > 1 else {}
    result = main(filters)
    print(json.dumps(result))
```

#### Complex Script: See `generate_batch_report.py` Example

For more complex scenarios (like PDF report generation with charts), see the existing `generate_batch_report.py` script which demonstrates:

- Reading JSON from stdin
- Processing multiple content types (charts, tables)
- Generating binary output (PDF)
- Proper error handling and encoding
- High-quality output (300 DPI charts)

2. **Call from PHP**:

```php
$filters = json_encode(['gender' => 'M', 'level' => 200]);
$cmd = "python my_task.py '" . escapeshellarg($filters) . "'";
$output = shell_exec($cmd);
$result = json_decode($output, true);
```

3. **Error handling**:

```php
$process = proc_open(
    $cmd,
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes
);
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
$returnCode = proc_close($process);

if ($returnCode !== 0) {
    throw new Exception("Python script failed: " . $error);
}
```

### Database Migration

1. **Create migration file**:

```sql
-- migrations/001_add_new_column.sql
ALTER TABLE profiles ADD COLUMN gpa DECIMAL(3,2) DEFAULT NULL;
ALTER TABLE profiles ADD INDEX idx_gpa (gpa);
```

2. **Track applied migrations**:

```sql
CREATE TABLE IF NOT EXISTS migrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO migrations (name) VALUES ('001_add_new_column');
```

3. **Create migration script** (optional):

```php
php run_migrations.php
```

## Testing

### Manual API Testing

#### Using curl

```bash
# Login
TOKEN=$(curl -s -X POST http://localhost:8000/auth/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }' | jq -r '.data.token')

# Test endpoint
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api.php?action=get_students&gender=M"
```

#### Using Postman

1. Import `API.postman_collection.json` (if available)
2. Set environment variables for token and base URL
3. Run requests
4. Check response status and body

#### Using Python requests

```python
import requests
import json

BASE_URL = "http://localhost:8000"

# Login
response = requests.post(
    f"{BASE_URL}/auth/api/login",
    json={
        "email": "user@example.com",
        "password": "password"
    }
)
token = response.json()['data']['token']

# Get students
response = requests.get(
    f"{BASE_URL}/api.php?action=get_students",
    headers={'Authorization': f'Bearer {token}'},
    params={'gender': 'M', 'level': 200}
)
print(json.dumps(response.json(), indent=2))
```

### Unit Testing

#### PHPUnit (if implemented)

```bash
./vendor/bin/phpunit tests/
./vendor/bin/phpunit tests/AuthServiceTest.php
./vendor/bin/phpunit tests/AuthServiceTest.php::testLogin
```

#### pytest (Python scripts)

```bash
pytest backend/tests/
pytest backend/tests/test_chart_generator.py
pytest backend/tests/test_chart_generator.py::test_bar_chart
```

### Test Data

Create test data insertion scripts:

```sql
-- test_data.sql
INSERT INTO users (email, password_hash) VALUES
  ('test1@test.com', '$2y$12$...'),
  ('test2@test.com', '$2y$12$...');

INSERT INTO profiles (matric_no, first_name, last_name, gender, level, department_id) VALUES
  ('102000001', 'Test', 'User1', 'M', 100, 1),
  ('102000002', 'Test', 'User2', 'F', 200, 2);
```

## Debugging

### PHP Debugging

#### Enable Error Reporting

```php
// Add to top of files during development
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

#### Log to Console

```php
error_log("Debug message: " . json_encode($data));
// Check logs with: tail -f /var/log/apache2/error.log
```

#### VSCode Xdebug Setup

1. Install Xdebug PHP extension
2. Install VSCode Xdebug extension
3. Configure `.vscode/launch.json`:

```json
{
  "version": "0.2.0",
  "configurations": [
    {
      "name": "Xdebug",
      "type": "php",
      "request": "launch",
      "port": 9003,
      "pathMapping": {
        "/var/www/html": "${workspaceRoot}"
      }
    }
  ]
}
```

### Python Debugging

#### Print Debugging

```python
print(f"Debug: {variable}", file=sys.stderr)
import json
print(json.dumps(data, indent=2), file=sys.stderr)
```

#### Using pdb

```python
import pdb

def complex_function():
    pdb.set_trace()  # Debugger will pause here
    result = perform_calculation()
    return result
```

#### VSCode Python Debugging

```json
{
  "name": "Python: Chart Generator",
  "type": "python",
  "request": "launch",
  "program": "${workspaceFolder}/render_charts.py",
  "args": ["argument1", "argument2"],
  "console": "integratedTerminal"
}
```

### Database Debugging

#### Show Query Being Executed

```php
// In Database.php
public function query($sql, $params = []) {
    error_log("SQL: " . $sql . " | Params: " . json_encode($params));
    // ...
}
```

#### Check SQL Syntax

```sql
EXPLAIN SELECT * FROM profiles WHERE gender = 'M' AND level = 200;
-- Shows query plan and indexes used
```

#### Profile Queries

```sql
SET PROFILING = 1;
SELECT * FROM profiles WHERE gender = 'M';
SHOW PROFILES;
SHOW PROFILE FOR QUERY 1;
```

## Security Guidelines

### ✅ DO

- Use prepared statements for ALL database queries
- Hash passwords with Bcrypt (minimum 10 rounds)
- Validate and sanitize all inputs
- Use HTTPS in production
- Implement rate limiting on login/OTP endpoints
- Log security events (login attempts, password changes)
- Use environment variables for sensitive config
- Implement CSRF token for state-changing operations
- Set HTTPOnly flag on authentication cookies
- Validate file uploads (type, size, content)

### ❌ DON'T

- Concatenate user input into SQL queries
- Store passwords in plaintext or weak hashing
- Trust client-side validation alone
- Use md5() or sha1() for password hashing
- Log sensitive data (passwords, tokens, SSN)
- Disable CORS protection entirely
- Commit `.env` file to git
- Allow directory listing
- Write debug files to web-accessible directories
- Use unvalidated file uploads

### Input Validation Example

```php
function validateStudentFilter($key, $value) {
    switch ($key) {
        case 'gender':
            if (!in_array($value, ['M', 'F'])) {
                throw new InvalidArgumentException('Invalid gender');
            }
            break;
        case 'level':
            if (!is_numeric($value) || $value < 100 || $value > 700) {
                throw new InvalidArgumentException('Invalid level');
            }
            break;
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email');
            }
            break;
        default:
            throw new InvalidArgumentException('Unknown filter');
    }
    return true;
}
```

## Troubleshooting

### Common Issues & Solutions

#### "Database connection failed"

```bash
# Check MySQL is running
mysql -u root -p -e "SHOW DATABASES;"

# Check credentials in Database.php
# Verify databases exist:
mysql -u root -p -e "SHOW DATABASES;" | grep -E 'student_ui_portal|oauth'

# Test PDO connection with:
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost', 'root', '');
    echo 'Connection OK';
} catch (PDOException \$e) {
    echo 'Connection failed: ' . \$e-getMessage();
}
"
```

#### "Python script timeout"

```bash
# Check Python is installed and accessible by PHP
which python3
php -r "echo shell_exec('python3 --version');"

# Test script execution
python3 render_charts.py '{"breakdown_by": "gender"}'

# Check file permissions
ls -la render_charts.py
chmod +x render_charts.py
```

#### "Email not sending"

```bash
# Check Mailpit is running
curl http://localhost:8025

# Test SMTP connection from PHP
php -r "
\$fp = fsockopen('localhost', 1025, \$errno, \$errstr, 5);
if (\$fp) {
    echo 'Connected to Mailpit';
    fclose(\$fp);
} else {
    echo 'Connection failed: ' . \$errstr;
}
"

# Check Mailer.php config
grep -i "const SMTP" backend/oauth/Mailer.php
```

#### "CORS errors"

```javascript
// Browser console error:
// "Access to XMLHttpRequest... blocked by CORS policy"

// Solution: Check cors_helper.php
// Add frontend origin to allowed list:
// $allowed_origins[] = 'http://localhost:3000';

// Verify headers being sent from browser
curl -H "Origin: http://localhost:3000" http://localhost:8000/api.php
```

#### "OTP not working"

```bash
# Check otp_log table
mysql oauth -u root -p -e "SELECT * FROM otp_log ORDER BY created_at DESC LIMIT 5;"

# Check OTP hasn't expired
mysql oauth -u root -p -e "SELECT *, (NOW() < expires_at) as is_valid FROM otp_log ORDER BY created_at DESC LIMIT 1;"

# Check email configuration
php -r "include 'backend/oauth/Mailer.php'; // Test Mailer setup"
```

## Development Workflow

### Creating a Feature Branch

```bash
git checkout -b feature/add-new-report-type
# Make changes
git add .
git commit -m "Add new milestone-based report"
git push origin feature/add-new-report-type
# Create Pull Request on GitHub
```

### Committing Code

```bash
# Stage changes
git add src/file.php

# Commit with descriptive message
git commit -m "Fix: Handle missing department in student profiles

- Add null check for department_id
- Return default value when department not found"

# Push to remote
git push origin feature-branch
```

### Code Review Checklist

- [ ] Follows code style guidelines
- [ ] No SQL injections (uses prepared statements)
- [ ] Input validation implemented
- [ ] Error handling in place
- [ ] No hardcoded credentials
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No debug code left behind
- [ ] No debug files/logs committed

## Git Workflow

### Branch Naming

- Feature: `feature/description` (e.g., `feature/add-export-xlsx`)
- Bugfix: `fix/description` (e.g., `fix/otp-expiration`)
- Hotfix: `hotfix/description` (e.g., `hotfix/critical-auth-bug`)
- Release: `release/version` (e.g., `release/1.0.0`)

### Commit Message Format

```
[Type]: Brief description (50 chars or less)

Longer explanation if needed (wrap at 72 chars).
Explain the why, not the what.

- Bullet point 1
- Bullet point 2

Fixes #123
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`

## Tools & Extensions

### VS Code Extensions

- **PHP Intelephense** - PHP code intelligence
- **Python** - Python language support
- **Pylint** - Python linting
- **SQLTools** - Database management
- **REST Client** - Test API endpoints (in editor)
- **Thunder Client** - Alternative API testing
- **Prettier** - Code formatting
- **Git Lens** - Git blame and history

### Useful Commands

```bash
# Format PHP code
vendor/bin/phpcbf .

# Check PHP syntax
php -l api.php
find . -name "*.php" -exec php -l {} \;

# Format Python code
python -m black *.py

# Lint Python code
python -m pylint *.py

# Database schema dump
mysqldump -u root -p student_ui_portal --no-data > schema.sql

# SSH into server (for production)
ssh user@exinsab.example.com
```

## Performance Profiling

### PHP Profiling

```php
// Add to beginning of script
$start = microtime(true);

// Your code here
$this->getStudents();

$duration = microtime(true) - $start;
error_log("Execution time: " . round($duration * 1000, 2) . "ms");
```

### Query Performance

```php
// In Database.php, track slow queries
if ($duration > 1.0) { // > 1 second
    error_log("SLOW QUERY: $sql (${duration}s)");
}
```

### Python Profiling

```python
import cProfile
import pstats

pr = cProfile.Profile()
pr.enable()

# Your code here
generate_chart(...)

pr.disable()
ps = pstats.Stats(pr)
ps.sort_stats('cumulative').print_stats(10)
```
