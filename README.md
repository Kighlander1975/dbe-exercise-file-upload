# PHP File System Explorer

A comprehensive, secure PHP file system explorer with an intuitive interface for browsing directories, uploading, viewing, and downloading files, including detailed file information and EXIF data for images.

## Features

- **File System Navigation**: Browse through your entire file system with an interactive directory tree
- **Secure File Upload**: Validates file types, sizes, and sanitizes filenames
- **File Browser**: Lists all files with size, type, and modification date
- **Preview Support**: Direct viewing of images, PDFs, videos, and other supported file types
- **Download Option**: Easy downloading of any file
- **Folder Organization**: Optional subfolder support for better file organization
- **File Details**: View detailed information about files including EXIF data for images
- **Flash Messages**: User-friendly feedback after operations
- **Drive Selection**: Quick navigation between available drives on Windows systems
- **Breadcrumb Navigation**: Easy path tracking and navigation
- **Responsive Design**: Clean, modern interface that works on various screen sizes
- **Security Measures**: 
  - Prevents PHP execution in the uploads directory
  - Restricts uploads to allowed file types only
  - Sanitizes filenames and paths
  - Path traversal prevention

## Requirements

- PHP 7.4 or higher
- Web server with URL rewriting capability (Apache with mod_rewrite or Nginx)
- Write permissions for the uploads directory
- PHP EXIF extension (optional, for image metadata)

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
│   └── App.php                # Application configuration
├── public/
│   ├── assets/
│   │   ├── styles.css         # CSS styles
│   │   └── scripts.js         # JavaScript functions
│   ├── index.php              # Main interface
│   ├── upload.php             # Upload handler
│   ├── file.php               # File streaming handler
│   ├── get_exif.php           # EXIF data retrieval
│   └── .htaccess              # Public directory rules
├── src/
│   ├── FileSystemExplorer.php # File system navigation
│   ├── Utils.php              # Utility classes
│   └── FlashMessage.php       # Flash message system
├── uploads/                   # File storage directory
│   └── .htaccess              # Security rules for uploads
└── .htaccess                  # Main URL rewriting rules
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

// Path to upload directory
define('UPLOAD_PATH', __DIR__ . '/../uploads');
```

## Usage

1. **Browsing Files and Directories**:
   - Use the directory tree on the left to navigate through folders
   - Click on folders in the main view to navigate into them
   - Use the breadcrumb navigation for quick path traversal
   - Select drives from the dropdown (on Windows systems)

2. **Uploading Files**:
   - Select a file using the file input
   - Optionally specify a subfolder
   - Click "Upload"
   - Note: Uploads are restricted to the designated upload directory

3. **Viewing and Managing Files**:
   - The main page displays all files in the current directory
   - Click the eye icon to view supported file types in the browser
   - Click the download icon to download any file
   - Click the info icon to view detailed information about the file

4. **File Details**:
   - Basic information: name, path, size, type, modification date
   - For images: preview thumbnail and EXIF metadata (if available)

## Security Considerations

This project implements several security measures:

- File type validation through extension whitelist
- File size limits
- Prevention of PHP execution in the uploads directory
- Path traversal prevention
- Filename sanitization
- Unique filenames to prevent overwriting
- Restricted upload capabilities to designated directories

## Customization

- **Adding File Types**: Edit the `UPLOAD_ALLOWED_EXT` array in `config/App.php`
- **Changing Upload Size**: Modify `UPLOAD_MAX_BYTES` in `config/App.php`
- **Styling**: Adjust the CSS in `public/assets/styles.css`
- **Behavior**: Modify JavaScript in `public/assets/scripts.js`

## License

This project is available under the MIT License. Feel free to use, modify, and distribute it as needed.

## Credits

Developed as a demonstration project for secure file handling and file system navigation in PHP.