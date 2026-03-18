# API Documentation - EXINSAB

Complete reference for all API endpoints, parameters, request/response formats, and examples.

## Base URL

```
http://localhost:8000
```

Production: `https://exinsab.yourdomain.com`

## Authentication

All endpoints (except `/auth/api/login` and `/auth/api/request-otp`) require authentication via:

### Option 1: Bearer Token
```
Authorization: Bearer {access_token}
```

### Option 2: Session Cookie
Set automatically on login; include credentials flag:
```bash
curl -X GET ... -u username:password
```

## Response Format

All responses return JSON with the following structure:

### Success Response (200)
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed successfully"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Error code",
  "message": "Human-readable error message",
  "status": 400
}
```

## Common Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 500 | Server Error |

---

## Authentication Endpoints

### 1. Login

**Endpoint**: `POST /auth/api/login`

**Description**: Authenticate user with email and password

**Request Headers**:
```
Content-Type: application/json
```

**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "securepassword"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGc...",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "name": "John Doe"
    }
  },
  "message": "Login successful"
}
```

**Error Cases**:
- `INVALID_CREDENTIALS`: Email/password combination incorrect
- `USER_NOT_FOUND`: User doesn't exist
- `ACCOUNT_DISABLED`: Account is disabled

**Example**:
```bash
curl -X POST http://localhost:8000/auth/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

---

### 2. Request OTP (Password Reset)

**Endpoint**: `POST /auth/api/request-otp`

**Description**: Request OTP for password reset via email

**Request Body**:
```json
{
  "email": "user@example.com"
}
```

**Response**:
```json
{
  "success": true,
  "message": "OTP sent to your email",
  "data": {
    "email": "user@example.com"
  }
}
```

**Error Cases**:
- `USER_NOT_FOUND`: Email doesn't exist in system
- `EMAIL_SEND_FAILED`: SMTP error occurred
- `RATE_LIMITED`: Too many requests (max 3 per hour)

**Example**:
```bash
curl -X POST http://localhost:8000/auth/api/request-otp \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
```

---

### 3. Verify OTP and Reset Password

**Endpoint**: `POST /auth/api/reset-password`

**Description**: Verify OTP and reset password

**Request Body**:
```json
{
  "email": "user@example.com",
  "otp": "123456",
  "new_password": "newSecurePassword123"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Password reset successful"
}
```

**Error Cases**:
- `INVALID_OTP`: OTP is incorrect or expired
- `OTP_EXPIRED`: OTP expired (valid for 30 minutes)
- `WEAK_PASSWORD`: Password doesn't meet requirements

---

### 4. Logout

**Endpoint**: `POST /auth/api/logout`

**Description**: Logout current user (invalidate session)

**Headers**: Requires valid authentication token

**Response**:
```json
{
  "success": true,
  "message": "Logout successful"
}
```

---

## Data Endpoints

### 5. Get Students List

**Endpoint**: `GET /api.php?action=get_students`

**Description**: Retrieve filtered list of students with their profiles

**Query Parameters** (all optional, use for filtering):

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `gender` | string | Filter by gender (M/F) | `gender=M` |
| `level` | integer | Filter by academic level | `level=100` |
| `state` | string | Filter by state of origin | `state=Lagos` |
| `department` | string | Filter by department code | `department=CSC` |
| `faculty` | string | Filter by faculty name | `faculty=Science` |
| `hall` | string | Filter by residential hall | `hall=Tedder` |
| `limit` | integer | Results per page (default: 100, max: 1000) | `limit=50` |
| `offset` | integer | Pagination offset (default: 0) | `offset=100` |

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 350,
    "limit": 100,
    "offset": 0,
    "students": [
      {
        "profile_id": 1,
        "matric_no": "102000001",
        "first_name": "John",
        "last_name": "Doe",
        "gender": "M",
        "level": 100,
        "department": {
          "dept_id": 1,
          "dept_code": "CSC",
          "dept_name": "Computer Science"
        },
        "state_of_origin": "Lagos",
        "hall": "Tedder Hall"
      }
    ]
  },
  "message": "Students retrieved successfully"
}
```

**Example**:
```bash
# Get all male students in level 200
curl -X GET "http://localhost:8000/api.php?action=get_students&gender=M&level=200" \
  -H "Authorization: Bearer {token}"

# Get students from Computer Science dept with pagination
curl -X GET "http://localhost:8000/api.php?action=get_students&department=CSC&limit=50&offset=100" \
  -H "Authorization: Bearer {token}"
```

---

## Chart Generation Endpoints

### 6. Create Chart

**Endpoint**: `POST /api.php?action=create_chart`

**Description**: Generate chart visualization from student data

**Request Headers**:
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Request Body**:
```json
{
  "chart_type": "bar",
  "breakdown_by": "gender",
  "filter_by": "level",
  "filter_value": "200",
  "title": "Gender Distribution in Level 200"
}
```

**Parameters**:

| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `chart_type` | string | Yes | bar, line, pie, area, scatter | Type of chart |
| `breakdown_by` | string | Yes | gender, level, state, department, faculty | What to group data by |
| `filter_by` | string | No | gender, level, state, department | Filter applied to entire dataset |
| `filter_value` | string | No | varies by filter | Value for filter (e.g., "200", "M", "CSC") |
| `title` | string | No | - | Chart title |

**Supported Breakdowns**:
- `gender` - Male/Female distribution
- `level` - By academic level (100-700)
- `state` - By state of origin
- `department` - By department code
- `faculty` - By faculty
- `hall` - By residential hall

**Response**:
```json
{
  "success": true,
  "data": {
    "chart_id": "chart_12345",
    "image_url": "/charts/chart_12345.png",
    "image_base64": "iVBORw0KGgo...",
    "breakdown_data": {
      "M": 1542,
      "F": 1238
    },
    "total_count": 2780
  },
  "message": "Chart generated successfully"
}
```

**Example**:
```bash
curl -X POST http://localhost:8000/api.php?action=create_chart \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "chart_type": "pie",
    "breakdown_by": "gender",
    "title": "Student Gender Distribution"
  }'
```

---

## Export Endpoints

### 7. Export Student Data

**Endpoint**: `GET /api.php?action=export`

**Description**: Export filtered student data in various formats

**Query Parameters**:

| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `format` | string | Yes | csv, xlsx, pdf | Export format |
| `gender` | string | No | M/F | Filter by gender |
| `level` | integer | No | 100-700 | Filter by level |
| `department` | string | No | - | Filter by dept code |
| `state` | string | No | - | Filter by state |

**Response** (File Download):
- Content-Type: `application/csv`, `application/xlsx`, or `application/pdf`
- Filename: `students_export_{timestamp}.{ext}`

**Example**:
```bash
# Export all male students as CSV
curl -X GET "http://localhost:8000/api.php?action=export&format=csv&gender=M" \
  -H "Authorization: Bearer {token}" \
  -o students_male.csv

# Export level 300 students as PDF
curl -X GET "http://localhost:8000/api.php?action=export&format=pdf&level=300" \
  -H "Authorization: Bearer {token}" \
  -o level300_students.pdf
```

---

### 8. Batch Report Generation

**Endpoint**: `POST /api.php?action=generate_batch_report`

**Description**: Generate multiple reports at once (useful for batch processing)

**Request Body**:
```json
{
  "reports": [
    {
      "name": "CSC_Gender_Report",
      "export_format": "pdf",
      "filters": {
        "department": "CSC"
      },
      "include_chart": true
    },
    {
      "name": "Level_200_Export",
      "export_format": "xlsx",
      "filters": {
        "level": 200
      },
      "include_chart": false
    }
  ],
  "job_id": "batch_123"
}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "job_id": "batch_123",
    "status": "processing",
    "total_reports": 2,
    "completed": 0,
    "files": []
  },
  "message": "Batch job queued"
}
```

**Check Status**: `GET /api.php?action=batch_status&job_id=batch_123`

---

## Reporting Endpoints

### 9. Get Report Metadata

**Endpoint**: `GET /reports.php?action=list`

**Description**: Get list of available reports

**Response**:
```json
{
  "success": true,
  "data": {
    "reports": [
      {
        "id": 1,
        "name": "Monthly Enrollment Report",
        "description": "...",
        "created_at": "2024-01-15T10:30:00Z",
        "filters": ["level", "gender", "department"]
      }
    ]
  }
}
```

---

## Error Handling

### Error Codes Reference

| Error Code | Status | Description |
|-----------|--------|-------------|
| `AUTH_REQUIRED` | 401 | Missing or invalid authentication |
| `INVALID_CREDENTIALS` | 401 | Wrong email/password |
| `PERMISSION_DENIED` | 403 | User lacks required permissions |
| `RESOURCE_NOT_FOUND` | 404 | Requested resource doesn't exist |
| `INVALID_PARAMS` | 400 | Invalid query parameters |
| `VALIDATION_ERROR` | 400 | Request validation failed |
| `DB_ERROR` | 500 | Database operation failed |
| `PROCESS_ERROR` | 500 | Report/chart generation failed |
| `RATE_LIMITED` | 429 | Too many requests |

### Example Error Response

```json
{
  "success": false,
  "error": "INVALID_PARAMS",
  "message": "Parameter 'chart_type' is required",
  "details": {
    "missing_params": ["chart_type"]
  },
  "status": 400
}
```

---

## Rate Limiting

- **General endpoints**: 100 requests per minute per IP
- **Export endpoints**: 10 requests per minute per user
- **Batch operations**: 5 concurrent jobs per user
- **Response header**: `X-RateLimit-Remaining`, `X-RateLimit-Reset`

---

## Pagination

Large result sets are paginated using:
- `limit` - Items per page (default: 100, max: 1000)
- `offset` - Starting position (default: 0)

Response includes:
```json
{
  "data": {
    "total": 5000,
    "limit": 100,
    "offset": 200,
    "items": [...]
  }
}
```

---

## Filtering Examples

### Single Filter
```
GET /api.php?action=get_students&gender=M
```

### Multiple Filters
```
GET /api.php?action=get_students?gender=M&level=200&department=CSC
```

### Export with Filters
```
GET /api.php?action=export&format=pdf&gender=F&state=Lagos&department=CSC
```

---

## Integration Examples

### JavaScript/Fetch

```javascript
// Login
const loginResponse = await fetch('http://localhost:8000/auth/api/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});
const { data } = await loginResponse.json();
const token = data.token;

// Get students
const studentsResponse = await fetch(
  'http://localhost:8000/api.php?action=get_students&gender=M',
  {
    headers: { 'Authorization': `Bearer ${token}` }
  }
);
const students = await studentsResponse.json();
```

### Python

```python
import requests

# Login
login_response = requests.post(
    'http://localhost:8000/auth/api/login',
    json={
        'email': 'user@example.com',
        'password': 'password123'
    }
)
token = login_response.json()['data']['token']

# Get students
students_response = requests.get(
    'http://localhost:8000/api.php?action=get_students&gender=M',
    headers={'Authorization': f'Bearer {token}'}
)
students = students_response.json()
```

### cURL

```bash
# Login and save token
TOKEN=$(curl -s -X POST http://localhost:8000/auth/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }' | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# Use token
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api.php?action=get_students&gender=M"
```

---

## Changelog

### v1.0.0 (Initial Release)
- Core authentication endpoints
- Student data queries with filtering
- Chart generation (5 types, 6 breakdown options)
- CSV/XLSX/PDF export
- Batch report generation

See [CHANGELOG.md](CHANGELOG.md) for version history.
