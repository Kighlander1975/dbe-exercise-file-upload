# PHP File Upload System

A lightweight, secure PHP file upload system with a clean interface for uploading, viewing, and downloading files.

## Features

- **Secure File Upload**: Validates file types, sizes, and sanitizes filenames
- **File Browser**: Lists all uploaded files with size and modification date
- **Preview Support**: Direct viewing of images, PDFs, videos, and other supported file types
- **Download Option**: Easy downloading of any uploaded file
- **Folder Organization**: Optional subfolder support for better file organization
- **Flash Messages**: User-friendly feedback after operations
- **Security Measures**: 
  - Prevents PHP execution in the uploads directory
  - Restricts access to allowed file types only
  - Sanitizes filenames and paths

## Requirements

- PHP 7.4 or higher
- Web server with URL rewriting capability (Apache with mod_rewrite or Nginx)
- Write permissions for the uploads directory

## Installation

1. Clone or download this repository to your web server
2. Ensure the `uploads` directory is writable by the web server
3. Configure your web server to point to the project directory
4. Access the application through your web browser

### Apache Configuration

The project includes `.htaccess` files for Apache. Make sure `mod_rewrite` is enabled:

```apache
# Enable mod_rewrite
a2enmod rewrite

# Restart Apache
service apache2 restart
```

## Directory Structure

```
dbe-php-file-upload/
├── config/
│   └── App.php             # Application configuration
├── public/
│   ├── index.php           # Main interface
│   ├── upload.php          # Upload handler
│   └── file.php            # File streaming handler
├── src/
│   ├── Utils.php           # Utility classes
│   └── FlashMessage.php    # Flash message system
├── uploads/                # File storage directory
│   └── .htaccess           # Security rules for uploads
└── .htaccess               # Main URL rewriting rules
```

## Configuration

Edit `config/App.php` to adjust settings:

```php
// Base URL of your application
define('BASE_URL', '/your-path/dbe-php-file-upload');

// Maximum upload size (in bytes)
define('UPLOAD_MAX_BYTES', 20 * 1024 * 1024); // 20 MB

// Allowed file extensions
define('UPLOAD_ALLOWED_EXT', ['jpg','jpeg','png','gif','webp','pdf','txt','md','mp3','mp4']);

// Force download instead of inline viewing
define('DOWNLOAD_FORCE_ATTACHMENT', false);
```

## Usage

1. **Uploading Files**:
   - Select a file using the file input
   - Optionally specify a subfolder
   - Click "Upload"

2. **Viewing Files**:
   - The main page displays all uploaded files
   - Click "Open" to view supported file types in the browser
   - Click "Download" to download any file

3. **Organization**:
   - Use the subfolder option to organize files into categories
   - Files are automatically sorted by upload date (newest first)

## Security Considerations

This project implements several security measures:

- File type validation through extension whitelist
- File size limits
- Prevention of PHP execution in the uploads directory
- Path traversal prevention
- Filename sanitization
- Unique filenames to prevent overwriting

## Customization

- **Adding File Types**: Edit the `UPLOAD_ALLOWED_EXT` array in `config/App.php`
- **Changing Upload Size**: Modify `UPLOAD_MAX_BYTES` in `config/App.php`
- **Styling**: Adjust the CSS in `public/index.php`

## License

This project is available under the MIT License. Feel free to use, modify, and distribute it as needed.

## Credits

Developed as a demonstration project for secure file handling in PHP.