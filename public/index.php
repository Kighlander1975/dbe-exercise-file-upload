<?php
// public/index.php
require __DIR__ . '/../config/App.php';
require __DIR__ . '/../src/Utils.php';
require __DIR__ . '/../src/FlashMessage.php';
require __DIR__ . '/../src/FileSystemExplorer.php';

// Flash-Messages initialisieren
FlashMessage::init();
$flashMessages = FlashMessage::getAll();

// Aktuellen Pfad bestimmen - vereinfachte Logik
if (isset($_GET['path']) && trim($_GET['path']) !== '') {
    // Wenn ein Pfad angegeben ist, diesen verwenden
    $currentPath = $_GET['path'];

    // Sicherheitscheck: Prüfen, ob der Pfad existiert und lesbar ist
    if (!is_dir($currentPath) || !is_readable($currentPath)) {
        $currentPath = UPLOAD_PATH;
    }
} else {
    // Bei direktem Aufruf: Upload-Verzeichnis verwenden
    $currentPath = UPLOAD_PATH;
}

// Verfügbare Laufwerke ermitteln
$drives = FileSystemExplorer::getAvailableDrives();

// Verzeichnisbaum für das aktuelle Laufwerk/Root erstellen
$rootPath = '';
if (PHP_OS_FAMILY === 'Windows') {
    // Für Windows das aktuelle Laufwerk ermitteln
    $rootPath = substr($currentPath, 0, 3); // z.B. "C:\"
} else {
    // Für Unix/Linux/Mac das Root-Verzeichnis
    $rootPath = '/';
}

// Cache immer leeren, um sicherzustellen, dass wir einen frischen Verzeichnisbaum haben
FileSystemExplorer::clearDirectoryCache();

// Verzeichnisbaum mit aktuellem Pfad erstellen
$directoryTree = FileSystemExplorer::buildDirectoryTree($rootPath, $currentPath);

// Den aktuellen Pfad im Verzeichnisbaum expandieren
FileSystemExplorer::ensurePathExpanded($directoryTree, $currentPath);

$currentContents = FileSystemExplorer::getDirectoryContents($currentPath);

// Prüfen, ob der aktuelle Pfad innerhalb des Upload-Verzeichnisses liegt
$isWithinUploadDir = FileSystemExplorer::isPathWithinUploadDir($currentPath);

// Aktuelles Laufwerk für das Select-Feld ermitteln
$currentDrive = '';
$currentDriveLabel = '';
foreach ($drives as $drive) {
    if (strpos($currentPath, $drive['path']) === 0) {
        $currentDrive = $drive['path'];
        $currentDriveLabel = $drive['label'];
        break;
    }
}

// URL-Basis für Links bestimmen - IMMER die Basis-URL verwenden, nie /public hinzufügen
$urlBase = rtrim(BASE_URL, '/');
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Dateisystem-Browser</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($urlBase); ?>/assets/styles.css">
</head>

<body>
    <div class="container">
        <?php if (!empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $flash): ?>
                <div class="flash flash-<?php echo $flash['type']; ?>">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h1><a href="<?php echo htmlspecialchars($urlBase); ?>/" style="text-decoration: none; color:black;">Dateisystem-Browser</a></h1>

        <section>
            <h2>Datei hochladen</h2>
            <form class="upload" action="<?php echo htmlspecialchars($urlBase . '/upload.php'); ?>" method="post" enctype="multipart/form-data">
                <div class="upload-row">
                    <div class="file-input-container">
                        <input type="file" name="file" required>
                    </div>
                    <div class="folder-input-container">
                        <?php
                        // Relativen Pfad zum Upload-Verzeichnis berechnen
                        $relativePath = '';
                        if ($isWithinUploadDir) {
                            $relativePath = str_replace(UPLOAD_PATH, '', $currentPath);
                            $relativePath = trim($relativePath, '/\\');
                        }
                        ?>
                        <input type="text" name="folder" value="<?php echo htmlspecialchars($relativePath); ?>" placeholder="optional: Unterordner, z. B. avatars">
                    </div>
                    <div class="button-container">
                        <button type="submit" <?php echo !$isWithinUploadDir ? 'disabled' : ''; ?>>Hochladen</button>
                    </div>
                </div>

                <div class="upload-info-row">
                    <div>
                        <small>Erlaubte Typen gemäß Konfiguration. Maximale Größe: <?php echo FileSystemExplorer::humanSize(UPLOAD_MAX_BYTES); ?>.</small>
                    </div>
                    <?php if (!$isWithinUploadDir): ?>
                        <div class="upload-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Uploads sind nur im Upload-Verzeichnis möglich.
                            <a href="<?php echo htmlspecialchars($urlBase); ?>/" class="upload-dir-link">
                                <i class="fas fa-upload"></i> Zum Upload-Verzeichnis wechseln
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="explorer">
            <!-- Linke Seite: Verzeichnisbaum -->
            <div class="explorer-sidebar">
                <div class="drive-selector">
                    <select id="drive-select">
                        <!-- Dummy-Option, die immer ausgewählt ist -->
                        <option value="dummy" selected>Laufwerk wählen...</option>

                        <!-- Optionen für alle verfügbaren Laufwerke -->
                        <?php foreach ($drives as $drive): ?>
                            <option value="<?php echo htmlspecialchars($drive['path']); ?>">
                                <?php echo htmlspecialchars($drive['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Verzeichnisbaum -->
                <?php echo FileSystemExplorer::renderDirectoryTree($directoryTree, $currentPath); ?>
            </div>

            <!-- Rechte Seite: Dateiliste des aktuellen Ordners -->
            <div class="explorer-content">
                <div class="content-header">
                    <h3>
                        <?php echo basename($currentPath); ?>
                        <span class="content-path">
                            <?php echo $currentPath; ?>
                        </span>
                    </h3>
                </div>

                <!-- Breadcrumb Navigation -->
                <div class="breadcrumb">
                    <?php echo FileSystemExplorer::generateBreadcrumb($currentPath); ?>
                </div>

                <div class="files">
                    <?php if (empty($currentContents)): ?>
                        <p class="empty">Dieses Verzeichnis ist leer oder nicht lesbar.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Größe</th>
                                    <th>Typ</th>
                                    <th>Geändert</th>
                                    <th>Aktion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($currentContents as $f):
                                    $name = $f['name'];
                                    $path = $f['path'];
                                    $isDir = $f['isDir'];
                                    $isSpecial = $f['isSpecial'] ?? false;

                                    // Icon basierend auf Typ bestimmen
                                    $iconClass = 'fas ';
                                    if ($isDir) {
                                        if ($name === '.') {
                                            $iconClass .= 'fa-dot-circle';
                                        } else if ($name === '..') {
                                            $iconClass .= 'fa-level-up-alt';
                                        } else {
                                            $iconClass .= 'fa-folder folder-icon';
                                        }
                                    } else {
                                        $ext = $f['ext'];
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                                            $iconClass .= 'fa-file-image file-icon-image';
                                        } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'md'])) {
                                            $iconClass .= 'fa-file-alt file-icon-document';
                                        } elseif (in_array($ext, ['mp3', 'wav', 'ogg'])) {
                                            $iconClass .= 'fa-file-audio file-icon-audio';
                                        } elseif (in_array($ext, ['mp4', 'webm'])) {
                                            $iconClass .= 'fa-file-video file-icon-video';
                                        } else {
                                            $iconClass .= 'fa-file file-icon-generic';
                                        }
                                    }

                                    // Prüfen, ob die Datei im Upload-Verzeichnis liegt (für Vorschau/Download)
                                    $isInUploadDir = FileSystemExplorer::isPathWithinUploadDir($path);
                                    $relativePath = '';
                                    if ($isInUploadDir && !$isDir) {
                                        $relativePath = str_replace(UPLOAD_PATH . DIRECTORY_SEPARATOR, '', $path);
                                        $relativePath = str_replace('\\', '/', $relativePath);
                                    }
                                ?>
                                    <tr>
                                        <td title="<?php echo htmlspecialchars($name); ?>">
                                            <i class="<?php echo $iconClass; ?> file-icon"></i>
                                            <?php if ($isDir): ?>
                                                <a href="<?php echo htmlspecialchars($urlBase . '/?path=' . urlencode($path)); ?>" class="file-name" title="<?php echo htmlspecialchars($name); ?>">
                                                    <?php echo htmlspecialchars($name); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="file-name" title="<?php echo htmlspecialchars($name); ?>">
                                                    <?php echo htmlspecialchars($name); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $isDir ? '-' : FileSystemExplorer::humanSize((int)$f['size']); ?>
                                        </td>
                                        <td>
                                            <?php if ($isDir): ?>
                                                <span class="badge badge-dir">DIR</span>
                                            <?php else:
                                                $ext = strtolower($f['ext'] ?: 'BIN');
                                                $badgeClass = 'badge-other'; // Standard-Klasse

                                                // Dateityp-Kategorien bestimmen
                                                if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'md', 'rtf', 'odt', 'pages'])) {
                                                    $badgeClass = 'badge-doc';
                                                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'ico'])) {
                                                    $badgeClass = 'badge-image';
                                                } elseif (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma'])) {
                                                    $badgeClass = 'badge-audio';
                                                } elseif (in_array($ext, ['mp4', 'webm', 'avi', 'mov', 'wmv', 'flv', 'mkv'])) {
                                                    $badgeClass = 'badge-video';
                                                } elseif (in_array($ext, ['html', 'htm', 'css', 'js', 'php', 'py', 'java', 'c', 'cpp', 'cs', 'rb', 'go', 'ts', 'jsx', 'xml', 'json'])) {
                                                    $badgeClass = 'badge-code';
                                                } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'iso'])) {
                                                    $badgeClass = 'badge-archive';
                                                } elseif (in_array($ext, ['exe', 'msi', 'bat', 'sh', 'app', 'dmg', 'deb', 'rpm'])) {
                                                    $badgeClass = 'badge-executable';
                                                }
                                            ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper($ext); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('Y-m-d H:i', (int)$f['mtime']); ?>
                                        </td>
                                        <td class="actions">
                                            <?php
                                            // Für Dateien (nicht Verzeichnisse)
                                            if (!$isDir):
                                                // Prüfen, ob die Datei anzeigbar ist
                                                $inline = FileSystemExplorer::isInlineView($f['ext'] ?? '');

                                                // Wenn die Datei im Upload-Verzeichnis liegt, können wir die relativen Pfade verwenden
                                                if ($isInUploadDir) {
                                                    $viewUrl = $urlBase . '/file.php?name=' . rawurlencode($relativePath);
                                                    $downloadUrl = $viewUrl . '&download=1';
                                                } else {
                                                    // Für Dateien außerhalb des Upload-Verzeichnisses den vollständigen Pfad verwenden
                                                    $viewUrl = $urlBase . '/file.php?path=' . urlencode($path);
                                                    $downloadUrl = $viewUrl . '&download=1';
                                                }

                                                // Ansehen-Button für anzeigbare Dateien
                                                if ($inline):
                                            ?>
                                                    <a href="<?php echo htmlspecialchars($viewUrl); ?>" target="_blank" rel="noopener" title="Öffnen">
                                                        <i class="fas fa-eye action-icon"></i>
                                                    </a>
                                                <?php
                                                endif;

                                                // Download-Button für alle Dateien
                                                ?>
                                                <a href="<?php echo htmlspecialchars($downloadUrl); ?>" title="Herunterladen">
                                                    <i class="fas fa-download action-icon"></i>
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$isSpecial): ?>
                                                <!-- Info-Button mit Modal-Aufruf statt Alert -->
                                                <a href="#" title="Details" class="info-button"
                                                    data-path="<?php echo htmlspecialchars($path); ?>"
                                                    data-name="<?php echo htmlspecialchars($name); ?>"
                                                    data-is-dir="<?php echo $isDir ? '1' : '0'; ?>"
                                                    data-size="<?php echo $isDir ? '0' : (int)$f['size']; ?>"
                                                    data-type="<?php echo $isDir ? 'DIR' : strtoupper($f['ext'] ?: 'BIN'); ?>"
                                                    data-mtime="<?php echo date('Y-m-d H:i', (int)$f['mtime']); ?>">
                                                    <i class="fas fa-info-circle action-icon"></i>
                                                </a>
                                            <?php else: ?>
                                                <!-- Sicherstellen, dass die Zelle nicht leer ist -->
                                                <span style="display: inline-block; width: 16px;">&nbsp;</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal für Dateidetails -->
    <div id="file-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Dateidetails</h2>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="file-basic-info">
                    <table class="details-table">
                        <tr>
                            <th>Name:</th>
                            <td id="detail-name"></td>
                        </tr>
                        <tr>
                            <th>Pfad:</th>
                            <td id="detail-path"></td>
                        </tr>
                        <tr id="detail-size-row">
                            <th>Größe:</th>
                            <td id="detail-size"></td>
                        </tr>
                        <tr>
                            <th>Typ:</th>
                            <td id="detail-type"></td>
                        </tr>
                        <tr>
                            <th>Geändert:</th>
                            <td id="detail-mtime"></td>
                        </tr>
                    </table>
                </div>

                <!-- Bereich für Bild-Vorschau und EXIF-Daten -->
                <div id="image-preview-container" style="display: none;">
                    <h3>Vorschau</h3>
                    <div class="image-preview">
                        <img id="image-preview" src="" alt="Vorschau">
                    </div>
                </div>

                <div id="exif-data-container" style="display: none;">
                    <h3>EXIF-Daten</h3>
                    <div id="exif-loading">Lade EXIF-Daten...</div>
                    <div id="exif-content" style="display: none;">
                        <table class="details-table" id="exif-table">
                            <!-- EXIF-Daten werden hier per JavaScript eingefügt -->
                        </table>
                    </div>
                    <div id="exif-error" style="display: none;">
                        Keine EXIF-Informationen verfügbar.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="modal-close-btn" class="btn">Schließen</button>
            </div>
        </div>
    </div>
    <script>
        const baseUrl = '<?php echo htmlspecialchars($urlBase); ?>';
    </script>
    <script src="<?php echo htmlspecialchars($urlBase); ?>/assets/scripts.js"></script>
</body>

</html>