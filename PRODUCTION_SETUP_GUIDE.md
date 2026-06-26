# Production Setup Guide - Awan ERP

## Environment Configuration

### Required Environment Variables

Create a `.env` file in your project root with the following configuration:

```env
APP_NAME="Awan ERP"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY_HERE
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_LEVEL=info

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=awan_erp
DB_USERNAME=awan_user
DB_PASSWORD=your_secure_password

# BROADCAST_DRIVER=log
# CACHE_DRIVER=file
# FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Redis Configuration (Recommended for Production)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@your-domain.com"
MAIL_FROM_NAME="${APP_NAME}"

# SMS Configuration (Optional)
SMS_PROVIDER=twilio
SMS_TWILIO_SID=your_twilio_sid
SMS_TWILIO_TOKEN=your_twilio_token
SMS_TWILIO_FROM=your_twilio_phone_number

# Push Notification Configuration (Optional)
PUSH_PROVIDER=fcm
PUSH_FCM_SERVER_KEY=your_fcm_server_key

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=your-domain.com

# CORS Configuration
CORS_ALLOWED_ORIGINS=https://your-domain.com,https://app.your-domain.com
```

## Database Setup

### 1. Create Database
```sql
CREATE DATABASE awan_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'awan_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON awan_erp.* TO 'awan_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Run Migrations
```bash
php artisan migrate --force
```

### 3. Seed Database
```bash
php artisan db:seed --force
```

## Security Configuration

### 1. Generate Application Key
```bash
php artisan key:generate
```

### 2. Set Proper Permissions
```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

### 3. Optimize for Production
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Queue Configuration

### 1. Configure Queue Worker
```bash
php artisan queue:work --tries=3 --timeout=90
```

### 2. Install Supervisor (Recommended)
```bash
# Install Supervisor
sudo apt-get install supervisor

# Create Supervisor Configuration
sudo nano /etc/supervisor/conf.d/awan-queue.conf
```

**Supervisor Configuration:**
```ini
[program:awan-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=3 --timeout=90
autostart=true
autorestart=true
user=your-user
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/queue-worker.log
stopwaitsecs=3600
```

### 3. Start Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start awan-queue:*
```

## Web Server Configuration

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /path/to/your/project/public;

    ssl_certificate /path/to/ssl/certificate.crt;
    ssl_certificate_key /path/to/ssl/private.key;

    index index.php index.html;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Apache Configuration

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /path/to/your/project/public

    SSLEngine on
    SSLCertificateFile /path/to/ssl/certificate.crt
    SSLCertificateKeyFile /path/to/ssl/private.key

    <Directory /path/to/your/project/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/awan-erp-error.log
    CustomLog ${APACHE_LOG_DIR}/awan-erp-access.log combined
</VirtualHost>
```

## SSL/TLS Configuration

### 1. Install Certbot (Let's Encrypt)
```bash
sudo apt-get install certbot python3-certbot-nginx
```

### 2. Generate SSL Certificate
```bash
sudo certbot --nginx -d your-domain.com
```

### 3. Auto-renewal
```bash
sudo certbot renew --dry-run
```

## Email Configuration

### SMTP Configuration
- **Mailtrap**: For development/testing
- **SendGrid**: For production
- **Mailgun**: For production
- **AWS SES**: For high-volume

### Example SendGrid Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.YOUR_SENDGRID_API_KEY
MAIL_ENCRYPTION=tls
```

## SMS Configuration

### Twilio Setup
```env
SMS_PROVIDER=twilio
SMS_TWILIO_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SMS_TWILIO_TOKEN=your_auth_token
SMS_TWILIO_FROM=+1234567890
```

## Push Notification Configuration

### Firebase Cloud Messaging (FCM)
```env
PUSH_PROVIDER=fcm
PUSH_FCM_SERVER_KEY=your_fcm_server_key
```

## Backup Strategy

### 1. Database Backup
```bash
# Create backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u awan_user -p awan_erp > /backups/awan_erp_$DATE.sql
```

### 2. File Backup
```bash
# Backup storage and uploads
tar -czf /backups/awan_files_$DATE.tar.gz /path/to/your/project/storage
```

### 3. Automated Backups (Cron)
```bash
# Add to crontab
0 2 * * * /path/to/backup-script.sh
```

## Monitoring

### 1. Application Monitoring
- **Laravel Telescope**: For development
- **Sentry**: For error tracking
- **New Relic**: For performance monitoring

### 2. Server Monitoring
- **Uptime Robot**: For uptime monitoring
- **Datadog**: For infrastructure monitoring
- **Prometheus + Grafana**: For metrics

## Performance Optimization

### 1. Enable OPcache
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### 2. Configure Redis
```bash
# Install Redis
sudo apt-get install redis-server

# Start Redis
sudo systemctl start redis
sudo systemctl enable redis
```

### 3. Enable Gzip Compression
```nginx
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json;
```

## Maintenance Mode

### Enable Maintenance Mode
```bash
php artisan down
```

### Allow Specific IPs During Maintenance
```bash
php artisan down --allow=127.0.0.1 --allow=192.168.1.1
```

### Disable Maintenance Mode
```bash
php artisan up
```

## Troubleshooting

### Common Issues

1. **Permission Denied**
   ```bash
   chmod -R 755 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```

2. **Queue Not Processing**
   ```bash
   php artisan queue:restart
   php artisan queue:work --tries=3 --timeout=90
   ```

3. **Cache Issues**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **Database Connection Issues**
   ```bash
   # Check MySQL service
   sudo systemctl status mysql
   sudo systemctl start mysql
   ```

## Post-Deployment Checklist

- [ ] Set APP_ENV=production
- [ ] Set APP_DEBUG=false
- [ ] Generate and set APP_KEY
- [ ] Configure database credentials
- [ ] Run migrations with --force flag
- [ ] Seed database with --force flag
- [ ] Configure email provider
- [ ] Configure SMS provider (if needed)
- [ ] Configure push notifications (if needed)
- [ ] Set up queue workers
- [ ] Configure Supervisor
- [ ] Set up SSL/TLS certificates
- [ ] Configure web server (Nginx/Apache)
- [ ] Set up automated backups
- [ ] Configure monitoring
- [ ] Test all critical API endpoints
- [ ] Test notification system
- [ ] Test workflow automation
- [ ] Set up log rotation
- [ ] Configure CORS settings
- [ ] Optimize PHP settings
- [ ] Enable OPcache
- [ ] Configure Redis (if using)
- [ ] Set up cron jobs for scheduled tasks
- [ ] Test maintenance mode
- [ ] Document deployment process

## Scheduled Tasks

Add to crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Support

For issues or questions:
- Check Laravel documentation: https://laravel.com/docs
- Review error logs: `storage/logs/laravel.log`
- Check queue logs: `storage/logs/queue-worker.log`
