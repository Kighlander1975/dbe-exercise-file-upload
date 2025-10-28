<?php
// public/file.php

// Alle Fehler anzeigen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/App.php';
require __DIR__ . '/../src/Utils.php';

// Beispiel: /file.php?name=avatars/ab12cd.jpg&download=1
$name = isset($_GET['name']) ? (string)$_GET['name'] : '';
$asAttachment = isset($_GET['download']) && $_GET['download'] === '1';

$fs = new FileService(UPLOAD_PATH, UPLOAD_MAX_BYTES, UPLOAD_ALLOWED_EXT);
$fs->streamFile($name, $asAttachment || DOWNLOAD_FORCE_ATTACHMENT);
