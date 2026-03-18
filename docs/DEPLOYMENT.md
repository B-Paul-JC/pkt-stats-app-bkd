# Deployment Guide - EXINSAB

Complete guide for deploying EXINSAB to various environments (staging, production) with best practices and checklists.

## Pre-Deployment Checklist

### Code Quality

- [ ] All tests passing (if test suite exists)
- [ ] No debug code left in production
- [ ] No console.log or print statements in release code
- [ ] All TODOs and FIXMEs resolved
- [ ] Code review approved by at least one team member
- [ ] No hardcoded credentials in code
- [ ] All dependencies documented in requirements.txt

### Security

- [ ] Database credentials moved to environment variables
- [ ] CORS whitelist updated with production domain
- [ ] HTTPS certificate obtained and configured
- [ ] SMTP credentials configured for production email service
- [ ] API rate limiting enabled
- [ ] Request logging and monitoring configured
- [ ] Error tracking service (Sentry) configured
- [ ] Backup strategy in place
- [ ] Security headers configured (HSTS, X-Frame-Options, CSP)

### Performance

- [ ] Database indexes created for filtered queries
- [ ] Chart caching implemented and enabled
- [ ] Static assets compression enabled (gzip)
- [ ] Database connection pooling configured
- [ ] Load testing completed
- [ ] Query performance optimized (EXPLAIN analyzed)

### Documentation

- [ ] API documentation updated
- [ ] Environmental differences documented
- [ ] Deployment runbook created
- [ ] Database schema backed up
- [ ] Troubleshooting guide completed

---

## Environment Setup

### 1. Shared Hosting (cPanel/Plesk)

**Advantages**: Easy setup, cheap, includes SSL
**Disadvantages**: Limited control, shared resources

#### Setup Steps

```bash
# 1. Upload files via FTP/SFTP
sftp> cd public_html/exinsab
sftp> put -r .

# 2. Create .env file
touch .env
# Edit with: FTP file manager or terminal

# 3. Set file permissions
chmod 755 .
chmod 644 *.php
chmod 755 backend/
chmod 755 backend/oauth/
chmod 700 logs/
chmod 700 exports/
chmod 700 charts/

# 4. Create MySQL databases
# Via cPanel > MySQL Databases

# 5. Import initial schema
mysql -u username -p dbname < schema/student_ui_portal.sql
mysql -u username -p oauth_db < schema/oauth.sql

# 6. Configure .htaccess for routing
cat > .htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /exinsab/
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ api.php?request=$1 [QSA,L]
</IfModule>
EOF

# 7. Test installation
curl https://yourdomain.com/exinsab/api.php
```

#### Environment File (`.env`)

```
DB_HOST=localhost
DB_USER=cpanel_user_dbuser
DB_PASS=your_password_here
DB_NAME=cpanel_user_uiportal

AUTH_DB_HOST=localhost
AUTH_DB_USER=cpanel_user_authuser
AUTH_DB_PASS=your_password_here
AUTH_DB_NAME=cpanel_user_oauth

SMTP_HOST=mail.yourdomain.com
SMTP_PORT=587
SMTP_USER=noreply@yourdomain.com
SMTP_PASS=email_password
SMTP_FROM=noreply@yourdomain.com

APP_ENV=production
APP_DEBUG=false
```

---

### 2. VPS/Dedicated Server

**Advantages**: Full control, scalable, better performance
**Disadvantages**: More setup, requires Linux knowledge

#### Setup Steps

```bash
# 1. Provision VPS (DigitalOcean, Linode, AWS)
# - Ubuntu 20.04 LTS recommended
# - 2GB+ RAM, 20GB+ storage

# 2. SSH into server
ssh root@your_server_ip

# 3. Update system
apt update && apt upgrade -y

# 4. Install PHP and extensions
apt install -y php7.4 php7.4-mysql php7.4-xml php7.4-curl php7.4-mbstring php7.4-json php7.4-gd

# 5. Install MySQL Server
apt install -y mysql-server
mysql_secure_installation

# 6. Install Nginx (or Apache)
apt install -y nginx
systemctl start nginx
systemctl enable nginx

# 7. Configure Nginx
cat > /etc/nginx/sites-available/exinsab << 'EOF'
server {
    listen 443 ssl http2;
    server_name api.exinsab.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/api.exinsab.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.exinsab.yourdomain.com/privkey.pem;

    root /var/www/exinsab;
    index api.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index api.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PHP_VALUE "display_errors=0";
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /api.php?$query_string;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';" always;

    # Logging
    access_log /var/log/nginx/exinsab_access.log;
    error_log /var/log/nginx/exinsab_error.log;
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.exinsab.yourdomain.com;
    return 301 https://$server_name$request_uri;
}
EOF

# 8. Enable site
ln -s /etc/nginx/sites-available/exinsab /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx

# 9. Configure SSL (Let's Encrypt)
apt install -y certbot python3-certbot-nginx
certbot certonly --nginx -d api.exinsab.yourdomain.com

# 10. Clone repository
cd /var/www
git clone https://github.com/yourusername/exinsab.git
cd exinsab

# 11. Set permissions
chown -R www-data:www-data /var/www/exinsab
chmod -R 755 /var/www/exinsab
chmod -R 700 /var/www/exinsab/logs
chmod -R 700 /var/www/exinsab/exports
chmod -R 700 /var/www/exinsab/charts

# 12. Configure environment
cp .env.example .env
nano .env
# Edit with production values

# 13. Create databases
mysql -u root -p << EOF
CREATE DATABASE student_ui_portal CHARACTER SET utf8mb4;
CREATE DATABASE oauth CHARACTER SET utf8mb4;
CREATE USER 'exinsab'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON student_ui_portal.* TO 'exinsab'@'localhost';
GRANT ALL PRIVILEGES ON oauth.* TO 'exinsab'@'localhost';
FLUSH PRIVILEGES;
EOF

# 14. Import schema
mysql -u exinsab -p student_ui_portal < schema/student_ui_portal.sql
mysql -u exinsab -p oauth < schema/oauth.sql

# 15. Install Python and dependencies
apt install -y python3 python3-pip
pip3 install pandas matplotlib fpdf2 openpyxl

# 16. Test deployment
curl https://api.exinsab.yourdomain.com/api.php

# 17. Setup cron for maintenance (if needed)
crontab -e
# Add: 0 2 * * * /var/www/exinsab/maintenance/cleanup.sh
```

#### VPS Security Hardening

```bash
# 1. Configure firewall
ufw enable
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw allow 3306/tcp from 127.0.0.1  # MySQL localhost only

# 2. Setup fail2ban (brute force protection)
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# 3. Disable root SSH login
sed -i 's/#PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl restart sshd

# 4. Automatic security updates
apt install -y unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades

# 5. Monitor logs
apt install -y logwatch
echo "0 6 * * * /usr/sbin/logwatch --output mail --format html --detail high" | crontab -
```

---

### 3. Docker Containerization

**Advantages**: Portable, consistent, scalable
**Disadvantages**: Learning curve, container orchestration needed

#### Dockerfile

```dockerfile
FROM php:7.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    gd \
    zip \
    xml \
    json \
    curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Python
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    && pip3 install pandas matplotlib fpdf2 openpyxl

# Copy application
COPY . /var/www/exinsab

# Set working directory
WORKDIR /var/www/exinsab

# Permissions
RUN chown -R www-data:www-data /var/www/exinsab && \
    chmod -R 755 /var/www/exinsab && \
    chmod -R 700 logs exports charts

# Expose port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
```

#### docker-compose.yml

```yaml
version: "3.8"

services:
  php:
    build: .
    ports:
      - "9000:9000"
    volumes:
      - .:/var/www/exinsab
    environment:
      - DB_HOST=mysql
      - AUTH_DB_HOST=mysql
      - SMTP_HOST=mailpit
    depends_on:
      - mysql
      - mailpit

  mysql:
    image: mysql:5.7
    ports:
      - "3306:3306"
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: student_ui_portal
    volumes:
      - mysql_data:/var/lib/mysql
      - ./schema:/docker-entrypoint-initdb.d

  nginx:
    image: nginx:latest
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - .:/var/www/exinsab
      - ./nginx.conf:/etc/nginx/nginx.conf
    depends_on:
      - php

  mailpit:
    image: axllent/mailpit:latest
    ports:
      - "8025:8025"
      - "1025:1025"

volumes:
  mysql_data:
```

#### Deploy with Docker

```bash
# Build and start
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f php

# Stop
docker-compose down

# Deploy to production (with proper registry)
docker build -t youregistry.azurecr.io/exinsab:1.0.0 .
docker push youregistry.azurecr.io/exinsab:1.0.0
# Then deploy to Kubernetes, ECS, etc.
```

---

## Database Deployment

### Initial Database Setup

```bash
# 1. Create databases
mysql -u root -p << EOF
CREATE DATABASE student_ui_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE oauth CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

# 2. Import schema
mysql -u root -p student_ui_portal < schema/student_ui_portal.sql
mysql -u root -p oauth < schema/oauth.sql

# 3. Verify tables
mysql -u root -p -e "USE student_ui_portal; SHOW TABLES;"
mysql -u root -p -e "USE oauth; SHOW TABLES;"

# 4. Create indexes
mysql -u root -p student_ui_portal < schema/indexes.sql
```

### Database Backup & Recovery

```bash
# Backup (before deployment)
mysqldump -u root -p student_ui_portal > backup_student_ui_portal_$(date +%Y%m%d).sql
mysqldump -u root -p oauth > backup_oauth_$(date +%Y%m%d).sql

# Backup to file with compression
mysqldump -u root -p --all-databases | gzip > backup_all_$(date +%Y%m%d_%H%M%S).sql.gz

# Restore from backup
mysql -u root -p < backup_student_ui_portal_20240118.sql

# Remote backup (to another server)
mysqldump -u root -p database_name | ssh user@backup-server "cat > /backups/backup_$(date +%Y%m%d).sql"

# Automated daily backups via cron
0 2 * * * /usr/bin/mysqldump -u root -pPASSWORD student_ui_portal | gzip > /backups/student_ui_portal_$(date +\%Y\%m\%d).sql.gz && find /backups -name "*.sql.gz" -mtime +30 -delete
```

### Database Migration (Schema Changes)

```bash
# 1. Plan migration (create migration file)
# migrations/002_add_gpa_column.sql

# 2. Test on backup
mysql -u root -p student_ui_portal_test < migrations/002_add_gpa_column.sql

# 3. Execute migration
mysql -u root -p student_ui_portal < migrations/002_add_gpa_column.sql

# 4. Verify
mysql -u root -p -e "DESC student_ui_portal.profiles;"

# 5. Record migration
mysql -u root -p -e "INSERT INTO migrations (name) VALUES ('002_add_gpa_column');"
```

---

## Deployment Procedures

### Zero-Downtime Deployment

Strategy: Use separate directories and symlink switching

```bash
# 1. Backup current production
mkdir -p /var/www/backup
cp -r /var/www/exinsab /var/www/backup/exinsab_$(date +%Y%m%d_%H%M%S)

# 2. Prepare new version in separate directory
mkdir -p /var/www/exinsab_new
cd /var/www/exinsab_new
git clone --depth 1 https://github.com/yourrepo/exinsab.git .
# OR: cp -r /var/www/exinsab_staging/* .

# 3. Install dependencies
pip3 install -r requirements.txt

# 4. Copy environment file from current
cp /var/www/exinsab/.env /var/www/exinsab_new/.env

# 5. Run tests
# ./vendor/bin/phpunit
# python3 -m pytest

# 6. Switch symlink (atomic operation)
cd /var/www
ln -sfn exinsab_new exinsab

# 7. Reload application
systemctl reload php-fpm
systemctl reload nginx

# 8. Monitor for errors
tail -f /var/log/nginx/exinsab_error.log
sleep 60
# If all good...

# 9. Cleanup old versions (keep last 3)
ls -dt /var/www/backup/exinsab_* | tail -n +4 | xargs rm -rf
```

### Rollback Procedure

```bash
# If deployment goes wrong, quick rollback:
cd /var/www
ln -sfn backup/exinsab_20240118_120000 exinsab
systemctl reload php-fpm
systemctl reload nginx

# Verify
curl https://api.exinsab.yourdomain.com/api.php
```

---

## Monitoring & Health Checks

### Application Health Endpoint

Add to `api.php`:

```php
if ($_GET['action'] === 'health') {
    $checks = [
        'database' => checkDatabase(),
        'python' => checkPythonEnvironment(),
        'disk_space' => checkDiskSpace(),
        'memory' => checkMemoryUsage(),
    ];

    $status = 200;
    foreach ($checks as $check => $result) {
        if (!$result['ok']) {
            $status = 500;
            break;
        }
    }

    http_response_code($status);
    echo json_encode([
        'status' => $status === 200 ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => date('c'),
    ]);
    exit;
}
```

### Monitoring Tools

```bash
# Setup uptime monitoring
# 1. Add to cron (check every 5 minutes)
*/5 * * * * curl -fsS https://api.exinsab.yourdomain.com/api.php?action=health || \
  /usr/bin/mail -s "EXINSAB Health Check Failed" admin@example.com

# 2. Use monitoring service (Pingdom, Uptime Robot, DataDog)
# - Set monitor URL: https://api.exinsab.yourdomain.com/api.php?action=health
# - Expected response: 200 (or JSON with "healthy")
# - Check interval: 5-10 minutes
# - Alert if fails 2+ times

# 3. Log monitoring
# - Watch error logs: tail -f /var/log/nginx/exinsab_error.log
# - Setup log aggregation: ELK Stack, Splunk, CloudWatch
```

### Error Tracking

Setup Sentry for error reporting:

```php
// Add to a top-level initialization file
require_once __DIR__ . '/vendor/autoload.php';

\Sentry\init(['
    'dsn' => $_ENV['SENTRY_DSN'] ?? '',
    'environment' => $_ENV['APP_ENV'] ?? 'production',
    'traces_sample_rate' => 0.1,
]);

// Errors now automatically tracked
```

### Performance Monitoring

```bash
# Monitor system resources
htop

# Monitor MySQL performance
mysqladmin -u root -p -i 1 processlist

# Check slow queries
tail -f /var/log/mysql/slow-query.log

# Database statistics
mysql -u root -p -e "SHOW STATUS LIKE 'Threads%'; SHOW STATUS LIKE 'Questions';"
```

---

## Scaling Strategies

### Horizontal Scaling (Load Balancing)

```
Load Balancer (HAProxy/nginx)
    ├─ Server 1 (php-fpm, app code)
    ├─ Server 2 (php-fpm, app code)
    └─ Server 3 (php-fpm, app code)
         └─ All read/write to shared MySQL
```

Setup HAProxy:

```bash
apt install -y haproxy

# /etc/haproxy/haproxy.cfg
global
    log stdout  local0
    log stdout  local1 notice

frontend webserver
    bind *:80
    mode http
    default_backend app_servers

backend app_servers
    balance roundrobin
    option httpchk GET /api.php?action=health
    server app1 192.168.1.10:80 check
    server app2 192.168.1.11:80 check
    server app3 192.168.1.12:80 check
```

### Vertical Scaling (Bigger Servers)

Simply upgrade server resources:

- More RAM for caching and buffer pools
- Faster CPU for query processing
- SSD storage for I/O performance
- Better network bandwidth

### Database Scaling

#### Master-Slave Replication

```sql
-- On master server
GRANT REPLICATION SLAVE ON *.* TO 'repl_user'@'slave_ip' IDENTIFIED BY 'password';
SHOW MASTER STATUS;

-- On slave server
CHANGE MASTER TO
  MASTER_HOST='master_ip',
  MASTER_USER='repl_user',
  MASTER_PASSWORD='password',
  MASTER_LOG_FILE='mysql-bin.000001',
  MASTER_LOG_POS=154;
START SLAVE;
SHOW SLAVE STATUS\G
```

---

## Maintenance

### Regular Maintenance Tasks

```bash
# Weekly
- Monitor error logs
- Check disk space
- Verify backups completed

# Monthly
- Review performance metrics
- Update security patches
- Clean up old logs

# Quarterly
- Database optimization (OPTIMIZE TABLE, analyze)
- Security audit
- Capacity planning review

# Yearly
- Complete disaster recovery drill
- Major version upgrades
- Security penetration testing
```

### Database Optimization

```sql
-- Analyze tables (update statistics)
ANALYZE TABLE profiles;
ANALYZE TABLE departments;
ANALYZE TABLE users;

-- Optimize tables (defragment)
OPTIMIZE TABLE profiles;
OPTIMIZE TABLE departments;

-- Check table integrity
CHECK TABLE profiles;
REPAIR TABLE profiles;

-- View index usage
SELECT * FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'student_ui_portal'
ORDER BY TABLE_NAME, SEQ_IN_INDEX;
```

---

## Production Checklist

### Before Going Live

- [ ] SSL certificate installed and valid
- [ ] Database backups automated and tested
- [ ] Error tracking (Sentry) configured
- [ ] Performance monitoring active
- [ ] Health check endpoint working
- [ ] All credentials in environment variables
- [ ] Logging enabled and monitored
- [ ] CORS whitelist updated
- [ ] Rate limiting enabled
- [ ] Database indexes created
- [ ] Cache warming script ready
- [ ] Disaster recovery plan documented
- [ ] Runbook created for common issues
- [ ] Team trained on deployment procedures
- [ ] Load testing completed
- [ ] Security audit completed

### After Deployment

- [ ] Verify all endpoints responding
- [ ] Check error logs for issues
- [ ] Monitor resource usage (CPU, memory, disk)
- [ ] Verify database replication (if used)
- [ ] Test backup/restore procedure
- [ ] Confirm email notifications working
- [ ] Monitor chart generation performance
- [ ] Track export file sizes and timings
- [ ] Check database query performance

---

## Troubleshooting Deployment Issues

### Application won't start

```bash
# 1. Check PHP syntax
php -l /var/www/exinsab/api.php

# 2. Check permissions
ls -la /var/www/exinsab/
# Should be drwxr-xr-x with www-data owner

# 3. Check error logs
tail -f /var/log/php-fpm/error.log
tail -f /var/log/nginx/error.log

# 4. Test PHP directly
php -r "include '/var/www/exinsab/api.php';"
```

### Database connection errors

```bash
# 1. Verify MySQL running
systemctl status mysql

# 2. Test connection
mysql -u exinsab -p -h 127.0.0.1 student_ui_portal -e "SELECT 1;"

# 3. Check database exists
mysql -u root -p -e "SHOW DATABASES;"

# 4. Check user permissions
mysql -u root -p -e "SHOW GRANTS FOR 'exinsab'@'localhost';"
```

### Python script execution failures

```bash
# 1. Verify Python installed
which python3
python3 --version

# 2. Check package installation
python3 -c "import pandas; print(pandas.__version__)"

# 3. Test script directly
python3 /var/www/exinsab/render_charts.py "{\"breakdown_by\": \"gender\"}"

# 4. Check PHP can execute
php -r "echo shell_exec('python3 --version');"
```

---

## Disaster Recovery

### Complete System Restore

```bash
# 1. Restore from backup
mysqldump -u root -p student_ui_portal < backup_20240118.sql
mysqldump -u root -p oauth < backup_oauth_20240118.sql

# 2. Restore application code
git clone https://github.com/yourrepo/exinsab.git /var/www/exinsab
cd /var/www/exinsab
git checkout v1.0.0  # Checkout specific version

# 3. Restore configuration
cp backup/.env /var/www/exinsab/.env

# 4. Restore file uploads (if any)
rsync -av backup/uploads/ /var/www/exinsab/uploads/

# 5. Verify system integrity
php -l /var/www/exinsab/api.php
curl https://api.exinsab.yourdomain.com/api.php?action=health

# 6. Verify data integrity
mysql -u root -p -e "SELECT COUNT(*) FROM student_ui_portal.profiles;"
```

---

**Last Updated**: March 18, 2024
**Current Recommended Deployment**: VPS with Nginx + PHP-FPM
