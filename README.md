# OpenBookManager

A lightweight, self-developed web tool for managing a large book collection with PHP & MariaDB.

## Features

- 📚 Book management with categories and tags
- 👤 Author management (n:m relationship)
- 🗂️ Category system (main and subcategories)
- 💭 Wishlist for desired books
- 🏷️ Physical tag system (e.g., "WR PH 0042")
- 🔍 Search and filter functions
- 📊 Statistics and reports
- 📤 Import/Export (CSV, JSON, PDF)
- 🖨️ Label printing for Zebra thermal printers (planned)

## Technology Stack

- **Backend**: PHP 8.x with PDO
- **Frontend**: HTML5, CSS3, Alpine.js
- **Database**: MariaDB/MySQL
- **Deployment**: Shared hosting compatible OR Docker containers

## Quick Start with Docker (Recommended for Local Use)

The easiest way to run OpenBookManager locally is using Docker. This provides a complete LAMP stack without manual configuration.

### Requirements

- Docker Desktop (Windows/macOS) or Docker Engine (Linux)
- Docker Compose (included with Docker Desktop)

### Step 1: Clone Repository

```bash
git clone https://github.com/thomasbutzbach/openbookmanager.git
cd openbookmanager
```

### Step 2: Start the Application

**Linux/macOS:**
```bash
./start-bookmanager.sh
```

**Windows:**
Double-click `start-bookmanager.bat` or run in Command Prompt:
```cmd
start-bookmanager.bat
```

That's it! The script will:
- Check for Docker installation
- Set up the configuration automatically
- Build and start all containers (Web, Database, phpMyAdmin)
- Open the application in your browser

### Step 3: First Login

The application will open at `http://localhost:8000`

Default credentials:
- **Username**: `admin`
- **Password**: `admin123`

⚠️ **IMPORTANT**: Change the password after first login!

### Services

When running with Docker, you have access to:
- **OpenBookManager**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080 (for database management)
- **MariaDB**: `localhost:3307` (for external tools)

### Stopping the Application

**Linux/macOS:**
```bash
./stop-bookmanager.sh
```

**Windows:**
```cmd
stop-bookmanager.bat
```

### Desktop Integration (Linux)

To create a desktop launcher:

1. Create a `.desktop` file:
   ```bash
   nano ~/.local/share/applications/openbookmanager.desktop
   ```

2. Add this content (adjust paths):
   ```ini
   [Desktop Entry]
   Type=Application
   Name=OpenBookManager
   Comment=Book Collection Manager
   Exec=/path/to/openbookmanager/start-bookmanager.sh
   Icon=/path/to/openbookmanager/public/images/icon.png
   Terminal=true
   Categories=Utility;Database;
   ```

3. Make it executable and refresh:
   ```bash
   chmod +x ~/.local/share/applications/openbookmanager.desktop
   update-desktop-database ~/.local/share/applications
   ```

The application will now appear in your application menu!

---

## Production Installation (Web Server)

For production deployment on a web server (shared hosting, VPS, etc.), follow these instructions instead.

### Requirements

- PHP 8.0 or higher
- MariaDB/MySQL 10.x or higher
- Web server (Apache/Nginx)
- mod_rewrite enabled (for Apache)

### Step 1: Clone Repository

```bash
git clone https://github.com/thomasbutzbach/openbookmanager.git
cd openbookmanager
```

### Step 2: Configuration

1. Copy the example configuration:
   ```bash
   cp config/config.example.php config/config.php
   ```

2. Edit `config/config.php` and adjust database credentials:
   ```php
   'database' => [
       'host' => 'localhost',
       'database' => 'openbookmanager',
       'username' => 'your_db_user',
       'password' => 'your_db_password',
   ],
   ```

### Step 3: Create Database

1. Create a new database:
   ```sql
   CREATE DATABASE openbookmanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. Import the schema:
   ```bash
   mysql -u username -p openbookmanager < database/schema.sql
   ```

### Step 4: Web Server Configuration

#### Apache

Create a `.htaccess` file in the `public/` directory:

```apache
RewriteEngine On

# Redirect to HTTPS (optional)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Front Controller
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

Configure your VirtualHost:

```apache
<VirtualHost *:80>
    ServerName openbookmanager.local
    DocumentRoot /path/to/openbookmanager/public

    <Directory /path/to/openbookmanager/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name openbookmanager.local;
    root /path/to/openbookmanager/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Step 5: Set Permissions

```bash
chmod -R 755 .
chmod -R 775 public/uploads
chown -R www-data:www-data public/uploads  # Adjust to your web server user
```

### Step 6: First Login

1. Open the application in your browser
2. Default credentials:
   - **Username**: `admin`
   - **Password**: `admin123`

⚠️ **IMPORTANT**: Change the password immediately after first login!

## Change Password

To generate a new password, use this PHP snippet:

```php
<?php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
?>
```

Then update the database:

```sql
UPDATE users SET password = 'paste_generated_hash_here' WHERE username = 'admin';
```

## Project Structure

```
openbookmanager/
├── config/              # Configuration files
│   ├── config.example.php
│   └── config.php (not committed)
├── database/            # Database schemas
│   └── schema.sql
├── public/              # Public directory (Document Root)
│   ├── css/
│   ├── js/
│   ├── images/
│   ├── uploads/
│   ├── index.php
│   ├── login.php
│   └── logout.php
├── src/
│   ├── Controllers/     # Controller classes (future)
│   ├── Models/          # Model classes (future)
│   ├── Views/           # View templates
│   │   └── layout/
│   ├── bootstrap.php    # Application bootstrap
│   └── helpers.php      # Helper functions
└── README.md
```

## Data Model

### Tag System

Each book receives a unique tag in the format:

```
AA BB 0001
```

- `AA`: Main category code (e.g., "WR" for Scientific/Research)
- `BB`: Category code (e.g., "PH" for Physics)
- `0001`: Sequential number within category

Example: **WR PH 0042** = Scientific/Research > Physics > Book No. 42

### Database Tables

- `maincategories` - Main categories
- `categories` - Subcategories
- `authors` - Authors
- `books` - Books
- `book_author` - Pivot table for book-author relationships
- `wishlist` - Wishlist
- `users` - Users
- `changelog` - Change log (optional)

## Development

### Planned Features

- ✅ Basic authentication
- ✅ Dashboard with statistics
- ⏳ CRUD for books
- ⏳ CRUD for authors
- ⏳ CRUD for categories
- ⏳ Wishlist management
- ⏳ Advanced search and filter functions
- ⏳ CSV/JSON/PDF export
- ⏳ ISBN API integration (Google Books)
- ⏳ Zebra label printer integration
- ⏳ Duplicate detection
- ⏳ Installer/update system

### Contributing

As this is a private learning project, pull requests are welcome!

## License

MIT License - see [LICENSE](LICENSE) file

## Support

For questions or issues, please create an issue in the GitHub repository.

---

**Version**: 1.0.0
**Author**: Thomas Butzbach
