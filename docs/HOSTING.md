# Hosting Guide

## Requirements
- PHP 7.4 or higher
- MySQL/MariaDB database
- Apache web server
- SSL certificate (for secure WebSocket connections)

## Hosting Options

### 1. Shared Hosting (Recommended for Beginners)
Popular providers: Hostinger, NameCheap, or InMotion Hosting

#### Steps:
1. **Purchase a Hosting Plan**
   - Choose a PHP-supported hosting plan
   - Get a domain name
   - Ensure SSL is included

2. **Upload Files**
   - Access cPanel or hosting control panel
   - Use File Manager or FTP (FileZilla)
   - Upload all project files to `public_html` or `www` directory
   - Set file permissions:
     - Directories: 755
     - Files: 644
     - Configuration files: 600

3. **Database Setup**
   ```sql
   -- Create database and user
   CREATE DATABASE your_chat_db;
   CREATE USER 'your_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON your_chat_db.* TO 'your_user'@'localhost';
   FLUSH PRIVILEGES;
   
   -- Import database schema
   mysql -u your_user -p your_chat_db < schema.sql
   ```

4. **Configure Application**
   ```php
   // config/database.php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_user');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'your_chat_db');
   ```

5. **Set Up SSL**
   - Install SSL certificate through hosting panel
   - Enable HTTPS redirection
   - Update application URLs to use HTTPS

### 2. VPS Hosting (Advanced)
Providers: DigitalOcean, Linode, or Vultr

#### Steps:
1. **Server Setup**
   ```bash
   # Update system
   sudo apt update && sudo apt upgrade -y

   # Install LAMP stack
   sudo apt install apache2 mysql-server php php-mysql php-mbstring php-zip php-gd php-json php-curl

   # Enable Apache modules
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   ```

2. **Configure Apache**
   ```apache
   # /etc/apache2/sites-available/chat-app.conf
   <VirtualHost *:80>
       ServerName yourdomain.com
       DocumentRoot /var/www/chat-app
       
       <Directory /var/www/chat-app>
           Options Indexes FollowSymLinks
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/error.log
       CustomLog ${APACHE_LOG_DIR}/access.log combined
   </VirtualHost>
   ```

3. **Deploy Application**
   ```bash
   # Clone repository
   cd /var/www
   git clone https://github.com/yourusername/chat-app.git

   # Set permissions
   sudo chown -R www-data:www-data chat-app
   sudo chmod -R 755 chat-app
   ```

4. **Install SSL**
   ```bash
   # Install Certbot
   sudo apt install certbot python3-certbot-apache

   # Get SSL certificate
   sudo certbot --apache -d yourdomain.com
   ```

### 3. Cloud Platform (Scalable)
Platform: AWS, Google Cloud, or Azure

#### Steps:
1. **Setup Cloud Environment**
   - Create a cloud account
   - Launch a compute instance
   - Configure networking and security groups

2. **Deploy Using Docker**
   ```dockerfile
   # Dockerfile
   FROM php:7.4-apache
   
   # Install dependencies
   RUN apt-get update && apt-get install -y \
       libpng-dev \
       libjpeg-dev \
       libfreetype6-dev \
       zip \
       unzip
   
   # Configure PHP extensions
   RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
       && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql
   
   # Copy application
   COPY . /var/www/html/
   
   # Set permissions
   RUN chown -R www-data:www-data /var/www/html \
       && chmod -R 755 /var/www/html
   ```

   ```yaml
   # docker-compose.yml
   version: '3'
   services:
     web:
       build: .
       ports:
         - "80:80"
         - "443:443"
       volumes:
         - ./:/var/www/html
       depends_on:
         - db
     db:
       image: mysql:5.7
       environment:
         MYSQL_ROOT_PASSWORD: rootpassword
         MYSQL_DATABASE: chat_db
         MYSQL_USER: chatuser
         MYSQL_PASSWORD: chatpass
   ```

## Performance Optimization

1. **Enable Caching**
   ```php
   // Add to config/app.php
   define('CACHE_ENABLED', true);
   define('CACHE_DURATION', 3600); // 1 hour
   ```

2. **Configure OPcache**
   ```ini
   # php.ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.interned_strings_buffer=8
   opcache.max_accelerated_files=4000
   opcache.revalidate_freq=60
   ```

3. **Setup CDN**
   - Configure Cloudflare
   - Enable caching for static assets
   - Enable minification

## Security Considerations

1. **Configure Firewall**
   ```bash
   # Allow only necessary ports
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   sudo ufw enable
   ```

2. **Secure Database**
   ```sql
   -- Remove test database and anonymous users
   DROP DATABASE IF EXISTS test;
   DELETE FROM mysql.user WHERE User='';
   FLUSH PRIVILEGES;
   ```

3. **Set Security Headers**
   ```apache
   # .htaccess
   Header set X-Content-Type-Options "nosniff"
   Header set X-Frame-Options "SAMEORIGIN"
   Header set X-XSS-Protection "1; mode=block"
   Header set Strict-Transport-Security "max-age=31536000"
   ```

## Monitoring

1. **Setup Error Logging**
   ```php
   // config/app.php
   ini_set('error_reporting', E_ALL);
   ini_set('display_errors', 0);
   ini_set('log_errors', 1);
   ini_set('error_log', '/path/to/error.log');
   ```

2. **Configure Backup**
   ```bash
   # Automated backup script
   #!/bin/bash
   mysqldump -u user -p database > backup_$(date +%Y%m%d).sql
   tar -czf backup_$(date +%Y%m%d).tar.gz /var/www/chat-app
   ```

## Troubleshooting

Common issues and solutions:
1. **Database Connection Issues**
   - Verify database credentials
   - Check database server status
   - Confirm firewall settings

2. **Permission Problems**
   ```bash
   # Fix permissions
   sudo find /var/www/chat-app -type f -exec chmod 644 {} \;
   sudo find /var/www/chat-app -type d -exec chmod 755 {} \;
   sudo chown -R www-data:www-data /var/www/chat-app
   ```

3. **SSL Certificate Issues**
   - Renew SSL certificate
   - Check certificate configuration
   - Verify domain DNS settings
