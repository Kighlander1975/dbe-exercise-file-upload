<?php
// public/get_exif.php
// Fehlerberichterstattung für Debugging aktivieren
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Sicherheitscheck: Prüfen, ob die Anfrage gültig ist
if (!isset($_GET['path']) || empty($_GET['path'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No path specified'
    ]);
    exit;
}

$path = $_GET['path'];

// Sicherheitscheck: Prüfen, ob die Datei existiert und lesbar ist
if (!file_exists($path) || !is_readable($path)) {
    echo json_encode([
        'success' => false,
        'message' => 'File not found or not readable'
    ]);
    exit;
}

// Vereinfachte Version: Nur prüfen, ob EXIF-Daten verfügbar sind
$exifData = [];

if (function_exists('exif_read_data')) {
    try {
        $exif = @exif_read_data($path, 'ANY_TAG', true);
        
        if ($exif !== false) {
            // Einfache Extraktion einiger gängiger EXIF-Daten
            if (isset($exif['IFD0'])) {
                foreach ($exif['IFD0'] as $key => $value) {
                    if (is_string($value)) {
                        $exifData[$key] = $value;
                    }
                }
            }
            
            if (isset($exif['EXIF'])) {
                foreach ($exif['EXIF'] as $key => $value) {
                    if (is_string($value)) {
                        $exifData[$key] = $value;
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fehler beim Lesen der EXIF-Daten ignorieren
    }
}

// Antwort senden
echo json_encode([
    'success' => true,
    'exif' => $exifData
]);