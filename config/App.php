<?php
// config/app.php

// Projekt-Basis: eine Ebene über /public
define('BASE_PATH', realpath(__DIR__ . '/..'));               // .../dbe-php-file-upload
define('PUBLIC_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'public');
define('UPLOAD_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');

// Basis-URL deiner Anwendung (ohne /public am Ende)
define('BASE_URL', '/DBE-exercises/dbe-php-file-upload');

// Sicherheitsrelevantes
define('UPLOAD_MAX_BYTES', 20 * 1024 * 1024); // 20 MB
// Whitelist der erlaubten Endungen (regex, case-insensitive)
define('UPLOAD_ALLOWED_EXT', ['jpg','jpeg','png','gif','webp','pdf','txt','md','mp3','mp4','doc','docx','xls','xlsx','ppt','pptx']);

// Optional: ob beim Download der "originale" Dateiname erzwungen wird
define('DOWNLOAD_FORCE_ATTACHMENT', false); // true = Content-Disposition: attachment
