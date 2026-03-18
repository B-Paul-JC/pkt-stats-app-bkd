# EXINSAB - Student Analytics & Reporting Backend API

A comprehensive backend API for generating statistical reports, charts, and data exports for the University of Ibadan student management system.

## Overview

EXINSAB is a PHP and Python-based platform that provides advanced student analytics capabilities including:

- **Dynamic Report Generation** - Filter student data by gender, level, department, location, and more
- **Data Visualization** - Create interactive charts (bar, line, pie, area, scatter) with multiple breakdown options
- **Multi-format Exports** - Export data as CSV, XLSX, and PDF documents
- **Batch Processing** - Generate reports in bulk with configurable parameters
- **Secure Authentication** - Email/password authentication with OTP-based password reset

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL (dual database setup)
- **Data Processing**: Python 3.x (pandas, matplotlib, fpdf2)
- **Authentication**: Bcrypt hashing, HTTPOnly cookies
- **Email**: SMTP (Mailpit for development)

## Project Structure

```
exinsab/
├── api.php                    # Main API router and entry point
├── reports.php                # Report generation endpoints
│
├── Python Scripts (Report & Chart Generation):
├── generate_batch_report.py   # Advanced PDF reports with charts & tables
├── render_charts.py           # Chart generation with matplotlib
├── render_reports.py          # Basic report templates
├── universal_export.py        # Multi-format data export (CSV/XLSX/PDF)
├── console_batch_report.py    # Batch job queue and processing
├── tomolu.py                  # Utility functions
│
├── backend/
│   ├── chart_generator.py     # Legacy chart generation (may call render_charts.py)
│   ├── Database.php           # Database abstraction layer
│   ├── db-worker/             # Database worker scripts
│   │   └── Database.php       # PDO database wrapper
│   └── oauth/
│       ├── auth.php           # Authentication endpoints
│       ├── AuthService.php    # Authentication service logic
│       ├── Mailer.php         # Email/SMTP service
│       └── cors_helper.php    # CORS configuration
├── render_charts.py           # Simple chart generation (matplotlib)
├── render_reports.py          # Basic report generation
├── generate_batch_report.py   # Advanced PDF report with charts & tables
├── universal_export.py        # Data export (CSV/XLSX/PDF)
├── console_batch_report.py    # Batch job processing
├── tomolu.py                  # Utility script
└── README.md                  # This file
```

## Quick Start

### Prerequisites

- PHP 7.4+
- MySQL 5.7+
- Python 3.x
- Composer (for PHP dependencies)
- Node.js/npm (optional, for frontend integration)

### Installation

1. **Clone and setup repository**

   ```bash
   cd c:\laragon\www\exinsab
   ```

2. **Install PHP dependencies** (if using Composer)

   ```bash
   composer install
   ```

3. **Install Python dependencies**

   ```bash
   pip install pandas matplotlib fpdf2
   ```

4. **Configure databases**
   - Update `Database.php` with your MySQL credentials:
     - `student_ui_portal` - Student data database
     - `oauth` - Authentication database

   Example configuration:

   ```php
   const DB_HOST = 'localhost';
   const DB_USER = 'root';
   const DB_PASS = '';
   const DB_NAME = 'student_ui_portal';
   ```

5. **Setup SMTP** (for password reset emails)
   - Configure Mailpit or your SMTP server in `Mailer.php`
   - Development: Use Mailpit (runs on localhost:1025)

6. **Start servers**

   ```bash
   # PHP (if not using built-in server)
   php -S localhost:8000

   # Mailpit for email testing (development)
   mailpit
   ```

## API Usage

### Authentication

Login with credentials to receive an authentication token:

```bash
curl -X POST http://localhost:8000/auth/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

### Generate Report

Retrieve filtered student data:

```bash
curl -X GET "http://localhost:8000/api.php?action=get_students&gender=M&level=100" \
  -H "Authorization: Bearer {token}"
```

### Create Chart

Generate a visualization:

```bash
curl -X POST http://localhost:8000/api.php?action=create_chart \
  -H "Content-Type: application/json" \
  -d '{
    "chart_type":"bar",
    "breakdown_by":"gender",
    "filter_by":"level",
    "filter_value":"100"
  }'
```

### Export Data

Export student list as PDF/CSV/XLSX:

```bash
curl -X GET "http://localhost:8000/api.php?action=export&format=pdf&filters=gender:M" \
  -H "Authorization: Bearer {token}"
```

See [API.md](API.md) for complete endpoint documentation.

## Database Schema

### Core Tables

**profiles** - Student profile data

- `profile_id` (PK)
- `matric_no` (unique)
- `first_name`, `last_name`
- `gender`, `level`
- `department_id` (FK)
- `state_of_origin`

**departments** - Department information

- `department_id` (PK)
- `dept_code`
- `dept_name`
- `faculty_id` (FK)

**faculties** - Faculty information

- `faculty_id` (PK)
- `faculty_name`

**halls** - Residential halls

- `hall_id` (PK)
- `hall_name`

**users** (oauth DB) - Authentication accounts

- `user_id` (PK)
- `email` (unique)
- `password_hash` (bcrypt)
- `created_at`

See [ARCHITECTURE.md](ARCHITECTURE.md) for full schema details.

## Configuration

### Environment Variables

Create a `.env` file (recommended for sensitive data):

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=student_ui_portal

AUTH_DB_HOST=localhost
AUTH_DB_USER=root
AUTH_DB_PASS=password
AUTH_DB_NAME=oauth

SMTP_HOST=localhost
SMTP_PORT=1025
SMTP_USER=user
SMTP_PASS=pass

CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8080
```

### CORS Configuration

Modify `cors_helper.php` to set allowed origins and methods:

```php
$allowed_origins = ['http://localhost:3000', 'https://yourdomain.com'];
```

## Development

### Running Tests

```bash
# PHPUnit tests (if available)
./vendor/bin/phpunit

# Python script tests
python -m pytest
```

### Code Standards

- **PHP**: PSR-12 style guide
- **Python**: PEP 8 style guide
- **Comments**: Document complex logic with inline comments
- **Functions**: Keep functions focused and under 50 lines where possible

See [DEVELOPMENT.md](DEVELOPMENT.md) for detailed guidelines.

## Troubleshooting

### Common Issues

**"Database connection failed"**

- Check MySQL is running
- Verify credentials in `Database.php`
- Ensure both databases exist

**"Python script execution timeout"**

- Check Python path in PHP
- Verify Python packages are installed
- Check system process limits

**"Email not sending"**

- Verify SMTP configuration in `Mailer.php`
- For development, ensure Mailpit is running
- Check firewall/network access to SMTP server

**"CORS errors"**

- Review `cors_helper.php` settings
- Verify frontend origin is in whitelist
- Check Content-Type headers

See [DEVELOPMENT.md](DEVELOPMENT.md) for more troubleshooting.

## Security Considerations

### ✅ Implemented

- Bcrypt password hashing (strength: 12 rounds)
- Prepared statements for all database queries
- HTTPOnly cookie flags
- Session regeneration on login
- OTP-based password reset

### ⚠️ Known Issues

- Database credentials currently hardcoded in `Database.php` (move to `.env`)
- CORS policy is broad (restrict to specific domains)
- Debug/log files may be written to disk (ensure logs/ is not web-accessible)

### Recommendations

- [ ] Implement rate limiting on auth endpoints
- [ ] Add CSRF token validation
- [ ] Enable HTTPS in production
- [ ] Implement API key rotation
- [ ] Add request/response logging with sensitive data redaction
- [ ] Regular security audits of SQL queries

See [DEVELOPMENT.md](DEVELOPMENT.md#security) for security guidelines.

## Performance

### Optimization Tips

- **Database**: Add indexes on frequently filtered columns (gender, level, department_id)
- **Charts**: Cache generated images for repeated requests
- **Exports**: Implement pagination for large datasets
- **Python**: Use multiprocessing for batch operations

### Load Testing

```bash
# Using Apache Bench
ab -n 1000 -c 100 http://localhost:8000/api.php?action=get_students

# Using wrk
wrk -t4 -c100 -d30s http://localhost:8000/api.php?action=get_students
```

## Contributing

1. Create a feature branch from `main`
2. Make changes following code standards
3. Test thoroughly
4. Submit pull request with description
5. Code review required before merge

See [DEVELOPMENT.md](DEVELOPMENT.md) for detailed guidelines.

## Deployment

### Production Checklist

- [ ] Set environment variables in `.env`
- [ ] Configure production database credentials
- [ ] Set up HTTPS
- [ ] Configure production SMTP server
- [ ] Update CORS allowed origins
- [ ] Enable query logging and monitoring
- [ ] Set up error tracking (e.g., Sentry)
- [ ] Configure backups

### Deployment Steps

```bash
# 1. Pull latest code
git pull origin main

# 2. Install/update dependencies
composer install --no-dev
pip install -r requirements.txt

# 3. Run migrations (if applicable)
# php artisan migrate (if using Laravel) or custom script

# 4. Clear caches
# php artisan cache:clear (if using Laravel)

# 5. Restart services
systemctl restart php-fpm
systemctl restart nginx  # or apache2
```

## Support & Contact

For issues, questions, or suggestions:

- Check [DEVELOPMENT.md](DEVELOPMENT.md) for troubleshooting
- Review [API.md](API.md) for endpoint details
- Check [ARCHITECTURE.md](ARCHITECTURE.md) for system design

## License

[Add your license here]

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and updates.
