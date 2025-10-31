<?php
// public/file.php

// Alle Fehler anzeigen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../config/App.php';
require __DIR__ . '/../src/Utils.php';
require __DIR__ . '/../src/FileSystemExplorer.php';

// Entweder name (relativ zum Upload-Verzeichnis) oder path (absoluter Pfad) verwenden
$name = isset($_GET['name']) ? (string)$_GET['name'] : '';
$path = isset($_GET['path']) ? (string)$_GET['path'] : '';
$asAttachment = isset($_GET['download']) && $_GET['download'] === '1';

// Vollständigen Pfad ermitteln
$filePath = '';
if (!empty($name)) {
    // Sicherheitscheck: Keine Pfadtraversierung erlauben
    $name = str_replace('\\', '/', $name);
    $name = preg_replace('/\.{2,}/', '', $name); // Entfernt .. aus dem Pfad
    $filePath = UPLOAD_PATH . '/' . $name;
} elseif (!empty($path)) {
    // Direkter Pfad - hier sollten wir prüfen, ob der Benutzer Zugriff haben darf
    // In einer realen Anwendung würde man hier weitere Sicherheitsprüfungen durchführen
    $filePath = $path;
} else {
    http_response_code(400);
    echo "Keine Datei angegeben.";
    exit;
}

// Prüfen, ob die Datei existiert und lesbar ist
if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    echo "Datei nicht gefunden oder nicht lesbar.";
    exit;
}

// Dateigröße ermitteln
$fileSize = filesize($filePath);
if ($fileSize === false) {
    http_response_code(500);
    echo "Fehler beim Ermitteln der Dateigröße.";
    exit;
}

// MIME-Typ ermitteln
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath) ?: 'application/octet-stream';
finfo_close($finfo);

// Dateiname für Download extrahieren
$fileName = basename($filePath);

// HTTP-Header setzen
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=86400'); // 1 Tag Cache

if ($asAttachment || DOWNLOAD_FORCE_ATTACHMENT) {
    // Als Anhang zum Download anbieten
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
} else {
    // Im Browser anzeigen
    header('Content-Disposition: inline; filename="' . $fileName . '"');
}

// Datei ausgeben
readfile($filePath);
exit;