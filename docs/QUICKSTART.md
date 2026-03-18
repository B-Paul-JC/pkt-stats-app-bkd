# Quick Start Guide - EXINSAB

A quick reference for getting up and running with EXINSAB.

## TL;DR - 5 Minute Setup

```bash
# 1. Navigate to project
cd c:\laragon\www\exinsab

# 2. Setup environment
cp .env.example .env
# Edit .env with your DB credentials

# 3. Install dependencies
pip install pandas matplotlib fpdf2 openpyxl

# 4. Create databases (MySQL)
mysql -u root -p << EOF
CREATE DATABASE student_ui_portal;
CREATE DATABASE oauth;
EOF

# 5. Start server (Laragon automatically does this)
# Or manually: php -S localhost:8000

# 6. Test API
curl http://localhost:8000/api.php

# Done! ✓
```

## First Steps

### 1. Understand the Project

Read [README.md](README.md) for:

- What the project does
- Tech stack overview
- Features overview

### 2. Setup Your Environment

Follow [DEVELOPMENT.md#Development Environment Setup](DEVELOPMENT.md#development-environment-setup)

### 3. Explore the API

Check [API.md](API.md) for:

- Available endpoints
- Request/response examples
- Authentication details

### 4. Understand the Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for:

- System design
- Component breakdown
- Data flow diagrams

## Common Tasks

### Login and Get Data

```bash
# 1. Login to get token
curl -X POST http://localhost:8000/auth/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'

# Response gives you: "token": "eyJhbGc..."
TOKEN="your_token_here"

# 2. Get students
curl -X GET "http://localhost:8000/api.php?action=get_students&gender=M" \
  -H "Authorization: Bearer $TOKEN"

# 3. Create chart
curl -X POST http://localhost:8000/api.php?action=create_chart \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "chart_type": "bar",
    "breakdown_by": "gender"
  }'

# 4. Export as PDF
curl -X GET "http://localhost:8000/api.php?action=export&format=pdf&gender=M" \
  -H "Authorization: Bearer $TOKEN" \
  -o students.pdf
```

### Make Code Changes

1. **Modify PHP file**: Edit `api.php` or files in `backend/`
   - PHP reloads automatically in development
   - Check browser/terminal for errors

2. **Modify Python script**: Edit `*.py` files
   - Restart PHP server to reload Python changes
   - Or test script directly: `python3 render_charts.py '{"breakdown_by":"gender"}'`

3. **Modify database schema**: Create migration file
   - See [DEVELOPMENT.md#Database Migration](DEVELOPMENT.md#database-migration)

### Test Your Changes

```bash
# Quick test
curl http://localhost:8000/api.php?action=health

# With authentication token
curl -H "Authorization: Bearer $TOKEN" http://localhost:8000/api.php

# Check logs for errors
# Development: Check console output and browser DevTools
# Production: tail -f /var/log/nginx/exinsab_error.log
```

### Deploy to Production

See [DEPLOYMENT.md](DEPLOYMENT.md) for:

- VPS setup instructions
- Docker deployment
- Zero-downtime deployment
- Production checklist

## File Structure at a Glance

```
exinsab/
├── README.md               👈 Start here
├── API.md                 📚 API endpoints
├── ARCHITECTURE.md        🏗️  System design
├── DEVELOPMENT.md         👨‍💻 Developer guide
├── DEPLOYMENT.md          🚀 Production setup
├── CHANGELOG.md           📝 Version history
│
├── api.php                🎯 Main API entry point
├── reports.php            📊 Report endpoints
│
├── backend/
│   ├── Database.php       🗄️  DB abstraction
│   ├── chart_generator.py 📈 Chart generation
│   ├── db-worker/
│   └── oauth/
│       ├── AuthService.php  🔐 Authentication
│       ├── Mailer.php      📧 Email service
│       └── cors_helper.php  🛡️  CORS config
│
├── render_charts.py       📉 Generate simple charts (matplotlib)
├── generate_batch_report.py 📄 Generate PDF reports (charts + tables)
├── universal_export.py    💾 Export data (CSV/XLSX/PDF)
├── console_batch_report.py 🔄 Batch job processing
├── render_reports.py      📋 Basic report templates
├── tomolu.py              🛠️ Utility functions
│
├── logs/                  📋 Application logs
├── charts/                🖼️  Generated images
├── exports/               📂 Export files
│
└── docs/
    └── (this documentation)
```

## Key Concepts

### Authentication

- Email + password login → JWT token
- Use token in `Authorization: Bearer {token}` header
- Password reset via OTP sent to email

### Data Flow

1. User sends request with token
2. API validates token
3. Query/process data
4. Return JSON response

### Charts

- 5 types: bar, line, pie, area, scatter
- 6 breakdown options: gender, level, state, department, faculty, hall
- Output: PNG image (base64 or file)

### Exports

- CSV: Spreadsheet format
- XLSX: Excel format
- PDF: Document format
- Filtered by: gender, level, department, state

### Database

- Two MySQL databases:
  - `student_ui_portal`: Student data (profiles, departments, faculties)
  - `oauth`: Authentication (users, sessions, OTP logs)

## Help & Troubleshooting

### "Can't connect to database"

```bash
# Check MySQL is running
mysql -u root -p -e "SHOW DATABASES;"

# Check credentials in .env or Database.php
cat .env | grep DB_

# Verify databases exist
mysql -u root -p -e "SHOW DATABASES;" | grep -E 'student_ui_portal|oauth'
```

### "Python script not working"

```bash
# Check Python is installed
python3 --version

# Check packages installed
pip3 list | grep -E 'pandas|matplotlib|fpdf2'

# Test script directly
python3 render_charts.py '{"breakdown_by":"gender"}'
```

### "Login not working"

```bash
# Check oauth database and users table
mysql oauth -u root -p -e "SELECT * FROM users;"

# Check SMTP for password reset
# In development, check Mailpit: http://localhost:8025

# Check password reset email configuration
grep -i "SMTP" backend/oauth/Mailer.php
```

### "Port 8000 already in use"

```bash
# Find process using port 8000
lsof -i :8000
# or on Windows:
netstat -ano | findstr :8000

# Use different port
php -S localhost:8001
```

## Important Files to Know

| File                             | Purpose                               |
| -------------------------------- | ------------------------------------- |
| `api.php`                        | Main API router, handles all requests |
| `backend/db-worker/Database.php` | Database connection and queries       |
| `backend/oauth/AuthService.php`  | User authentication logic             |
| `backend/oauth/cors_helper.php`  | CORS configuration (security)         |
| `backend/oauth/Mailer.php`       | Email sending (OTP, notifications)    |
| `render_charts.py`               | Generate charts via matplotlib        |
| `universal_export.py`            | Export to CSV/XLSX/PDF                |
| `.env`                           | Configuration file (never commit)     |

## Common Errors & Fixes

| Error                                            | Fix                                               |
| ------------------------------------------------ | ------------------------------------------------- |
| `SQLSTATE[HY000]: General error: 1030 Got error` | Check disk space, or add indexes                  |
| `Class not found: PDO`                           | PHP compiled without PDO support                  |
| `ModuleNotFoundError: No module named 'pandas'`  | Run `pip3 install pandas`                         |
| `Connection refused`                             | MySQL not running, check `systemctl status mysql` |
| `Permission denied`                              | File permissions wrong, fix with `chmod`          |
| `CORS error`                                     | Frontend origin not in cors_helper.php whitelist  |
| `Invalid OTP`                                    | OTP expired or wrong, request new one             |
| `Chart generation failed`                        | Check Python path, verify matplotlib installed    |

## Next Steps

1. **Read the full docs**:
   - [API.md](API.md) - Complete API reference
   - [ARCHITECTURE.md](ARCHITECTURE.md) - How it's structured
   - [DEVELOPMENT.md](DEVELOPMENT.md) - Development best practices

2. **Set up your environment**:
   - Follow [DEVELOPMENT.md setup guide](DEVELOPMENT.md#development-environment-setup)
   - Configure `.env` file
   - Test basic endpoints

3. **Make your first change**:
   - Edit `api.php` or a Python script
   - Test locally
   - Commit to git: `git add . && git commit -m "Your message"`

4. **Deploy when ready**:
   - Review [DEPLOYMENT.md](DEPLOYMENT.md)
   - Follow the deployment checklist
   - Deploy to staging first

## Quick Links

- 📖 **README.md** - Project overview
- 🔌 **API.md** - API endpoints
- 🏗️ **ARCHITECTURE.md** - System design
- 👨‍💻 **DEVELOPMENT.md** - Development guide
- 🚀 **DEPLOYMENT.md** - Production deployment
- 📝 **CHANGELOG.md** - Version history

## Questions?

1. Check the relevant documentation file above
2. Review [DEVELOPMENT.md#Troubleshooting](DEVELOPMENT.md#troubleshooting)
3. Check API examples in [API.md](API.md#integration-examples)
4. Look at [ARCHITECTURE.md](ARCHITECTURE.md) for how components work

---

**Last Updated**: March 18, 2024
**Version**: 1.0.0
**Status**: ✅ Production Ready
