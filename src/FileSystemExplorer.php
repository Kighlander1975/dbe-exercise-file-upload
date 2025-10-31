<?php
// src/FileSystemExplorer.php

/**
 * Hilfsfunktionen für den Dateisystem-Explorer
 */
class FileSystemExplorer
{
    // Cache für Verzeichnisinhalte
    private static $directoryCache = [];

    /**
     * Prüft, ob ein Pfad innerhalb des Upload-Verzeichnisses liegt
     */
    public static function isPathWithinUploadDir(string $path): bool
    {
        $uploadRealPath = realpath(UPLOAD_PATH);
        $targetRealPath = realpath($path);

        // Wenn der Pfad nicht existiert oder nicht innerhalb des Upload-Verzeichnisses liegt
        if ($targetRealPath === false) {
            return false;
        }

        return strpos($targetRealPath, $uploadRealPath) === 0;
    }

    /**
     * Ermittelt verfügbare Laufwerke (Windows) oder Root-Verzeichnisse (Linux/Mac)
     */
    public static function getAvailableDrives(): array
    {
        static $drives = null;

        // Cache für Laufwerke
        if ($drives !== null) {
            return $drives;
        }

        $drives = [];

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows-Laufwerke ermitteln
            foreach (range('A', 'Z') as $letter) {
                $drive = $letter . ':\\';
                if (is_dir($drive)) {
                    $drives[] = [
                        'path' => $drive,
                        'label' => $drive
                    ];
                }
            }
        } else {
            // Unix/Linux/Mac - Root-Verzeichnis
            $drives[] = [
                'path' => '/',
                'label' => 'Root /'
            ];

            // Optional: Häufig verwendete Verzeichnisse hinzufügen
            if (is_dir('/home')) {
                $drives[] = [
                    'path' => '/home',
                    'label' => 'Home'
                ];
            }
        }

        return $drives;
    }

    /**
     * Prüft, ob eine Datei oder ein Verzeichnis versteckt ist (optimiert)
     */
    private static function isHidden(string $path): bool
    {
        $basename = basename($path);

        // Whitelist für versteckte Dateien prüfen
        if (defined('SHOW_HIDDEN_FILES') && is_array(SHOW_HIDDEN_FILES) && in_array($basename, SHOW_HIDDEN_FILES)) {
            return false; // Datei ist in der Whitelist, also nicht versteckt
        }

        // In Unix/Linux beginnen versteckte Dateien mit einem Punkt
        if ($basename[0] === '.') {
            return true;
        }

        // Windows: Versteckte Systemordner und spezielle Ordner
        if (PHP_OS_FAMILY === 'Windows') {
            // Ordner, die mit $ beginnen, sind in der Regel Systemordner
            if ($basename[0] === '$') {
                return true;
            }

            // Bekannte versteckte Systemordner
            $hiddenFolders = [
                'System Volume Information',
                '$RECYCLE.BIN',
                'Config.Msi',
                'Recovery',
                'ProgramData',
                'AppData',
                'WindowsApps',
                '$Windows.~BT',
                '$Windows.~WS',
                '$SysReset',
                'Documents and Settings',
                'System32',
                'WinSxS',
                'Boot',
                'Temp',
                'Recycler',
                'MSOCache'
            ];

            foreach ($hiddenFolders as $folder) {
                if (stripos($basename, $folder) !== false) {
                    return true;
                }
            }

            // Prüfen auf versteckte Systemattribute nur für wichtige Verzeichnisse
            // Dies ist ein Kompromiss zwischen Genauigkeit und Geschwindigkeit
            if (function_exists('exec')) {
                // Nur für bestimmte Verzeichnisse das Attribut prüfen
                if (
                    strlen($basename) <= 5 || // Kurze Namen wie "FOUND.000"
                    strpos($basename, '~') !== false || // Temporäre Dateien wie "~$"
                    preg_match('/^[A-Z]+\d+(\.\d+)?$/', $basename)
                ) { // Muster wie "FOUND.000"

                    // Prüfen, ob das Verzeichnis das versteckte Attribut hat
                    if (PHP_OS_FAMILY === 'Windows') {
                        $output = [];
                        @exec('attrib "' . $path . '"', $output);

                        if (!empty($output)) {
                            // H für versteckt, S für System
                            if (strpos($output[0], 'H') !== false || strpos($output[0], 'S') !== false) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Normalisiert einen Pfad für Vergleiche
     */
    private static function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * Prüft, ob ein Pfad Teil des aktuellen Pfades ist
     */
    private static function isInPath(string $dirPath, string $currentPath): bool
    {
        $dirPath = self::normalizePath($dirPath);
        $currentPath = self::normalizePath($currentPath);

        // Prüfen, ob der aktuelle Pfad mit dem Verzeichnispfad beginnt
        return strpos($currentPath . '/', $dirPath . '/') === 0;
    }

    /**
     * Prüft, ob ein Pfad ein Elternteil des aktuellen Pfades ist
     */
    private static function isParentOfPath(string $parentPath, string $childPath): bool
    {
        if (!$parentPath || !$childPath) {
            return false;
        }

        // Normalisieren der Pfade für Vergleich
        $parentPath = self::normalizePath($parentPath) . '/';
        $childPath = self::normalizePath($childPath) . '/';

        // Prüfen, ob der Kindpfad mit dem Elternpfad beginnt
        return $parentPath !== $childPath && strpos($childPath, $parentPath) === 0;
    }

    /**
     * Prüft, ob ein Pfad einer der Zielpfade ist oder ein Elternteil davon
     */
    private static function isPathOrParentOf(string $path, array $targetPaths): bool
    {
        foreach ($targetPaths as $targetPath) {
            if ($path === $targetPath || self::isParentOfPath($path, $targetPath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ermittelt den Pfad zum Upload-Ordner vom Root aus
     */
    private static function getPathToUploadDir(): array
    {
        $uploadPath = realpath(UPLOAD_PATH);
        if (!$uploadPath) {
            return [];
        }

        $pathSegments = [];

        // Für Windows
        if (PHP_OS_FAMILY === 'Windows') {
            // Laufwerksbuchstaben extrahieren
            $driveLetter = substr($uploadPath, 0, 2); // z.B. "C:"
            $pathWithoutDrive = substr($uploadPath, 3); // Pfad ohne "C:\"

            // Pfadsegmente ermitteln
            $segments = explode('\\', $pathWithoutDrive);
            $currentPath = $driveLetter . '\\';

            foreach ($segments as $segment) {
                if (empty($segment)) continue;
                $currentPath .= $segment . '\\';
                $pathSegments[] = $currentPath;
            }
        } else {
            // Für Unix/Linux/Mac
            $segments = explode('/', $uploadPath);
            $currentPath = '';

            foreach ($segments as $segment) {
                if (empty($segment)) continue;
                $currentPath .= '/' . $segment;
                $pathSegments[] = $currentPath;
            }
        }

        return $pathSegments;
    }

    /**
     * Markiert den aktuellen Pfad im Verzeichnisbaum als aktiv
     * Zentrale Methode für die konsistente Markierung des aktuellen Pfades
     */
    public static function markCurrentPathActive(array &$tree, string $currentPath): void
    {
        // Normalisieren des Pfads für Vergleich
        $normalizedCurrentPath = self::normalizePath($currentPath);

        foreach ($tree as &$node) {
            $normalizedNodePath = self::normalizePath($node['path']);

            // Wenn dieser Knoten dem aktuellen Pfad entspricht, als aktiv markieren
            if ($normalizedNodePath === $normalizedCurrentPath) {
                $node['isActive'] = true;
            } else {
                // Sicherstellen, dass dieser Knoten nicht aktiv ist
                $node['isActive'] = false;
            }

            // Rekursiv in Unterverzeichnissen suchen
            if (!empty($node['children'])) {
                self::markCurrentPathActive($node['children'], $currentPath);
            }
        }
    }

    /**
     * Stellt sicher, dass der Pfad im Verzeichnisbaum expandiert ist
     */
    public static function ensurePathExpanded(array &$tree, string $targetPath): void
    {
        // Wenn der Zielpfad leer ist, nichts tun
        if (empty($targetPath)) {
            return;
        }

        // Normalisieren des Zielpfads
        $normalizedTargetPath = self::normalizePath($targetPath);

        // Pfad in Segmente aufteilen
        $pathSegments = [];

        // Für Windows
        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]:)(.*)$/', $targetPath, $matches)) {
            $driveLetter = $matches[1];
            $pathWithoutDrive = $matches[2];

            // Laufwerksbuchstaben als Basis-Pfad verwenden
            $currentPath = $driveLetter . '\\';
            $pathSegments[] = $currentPath;

            // Pfad ohne Laufwerksbuchstaben in Segmente aufteilen
            $segments = array_filter(explode('\\', $pathWithoutDrive), 'strlen');

            foreach ($segments as $segment) {
                $currentPath .= $segment . '\\';
                $pathSegments[] = rtrim($currentPath, '\\');
            }
        } else {
            // Unix/Linux/Mac
            $segments = array_filter(explode('/', $targetPath), 'strlen');
            $currentPath = '/';
            $pathSegments[] = $currentPath;

            foreach ($segments as $segment) {
                $currentPath .= $segment . '/';
                $pathSegments[] = rtrim($currentPath, '/');
            }
        }

        // Für jeden Pfad in den Segmenten sicherstellen, dass er expandiert ist
        foreach ($pathSegments as $path) {
            self::expandNodeInTree($tree, $path);
        }

        // Zum Schluss den Zielpfad als aktiv markieren
        self::markCurrentPathActive($tree, $targetPath);
    }

    /**
     * Expandiert einen bestimmten Knoten im Verzeichnisbaum
     */
    private static function expandNodeInTree(array &$tree, string $targetPath): bool
    {
        $normalizedTargetPath = self::normalizePath($targetPath);
        $found = false;

        foreach ($tree as &$node) {
            $normalizedNodePath = self::normalizePath($node['path']);

            // Wenn dieser Knoten dem Zielpfad entspricht
            if ($normalizedNodePath === $normalizedTargetPath) {
                // Wenn der Knoten Kinder hat, aber sie noch nicht geladen wurden
                if ($node['hasChildren'] && empty($node['children'])) {
                    // Kinder laden
                    $node['children'] = self::buildDirectoryTree($node['path'], $targetPath);
                }
                return true;
            }

            // Wenn dieser Knoten ein Elternteil des Zielpfads ist
            if (self::isParentOfPath($normalizedNodePath, $normalizedTargetPath)) {
                // Wenn keine Kinder vorhanden sind, aber es sollten welche geben
                if (empty($node['children']) && $node['hasChildren']) {
                    // Kinder laden
                    $node['children'] = self::buildDirectoryTree($node['path'], $targetPath);
                }

                // Wenn Kinder vorhanden sind, in ihnen weitersuchen
                if (!empty($node['children'])) {
                    if (self::expandNodeInTree($node['children'], $targetPath)) {
                        return true;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Baut den Verzeichnisbaum auf
     */
    public static function buildDirectoryTree(string $basePath, string $currentPath = '', int $maxDepth = 10, int $currentDepth = 0): array
    {
        if ($currentPath === '') {
            $currentPath = $basePath;
        }

        // Tiefenbegrenzung für bessere Performance
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        $result = [];

        if (!is_dir($basePath) || !is_readable($basePath)) {
            return $result;
        }

        // Cache-Key für dieses Verzeichnis
        $cacheKey = $basePath . '|' . $currentDepth;

        // Normalisierte Pfade für Vergleiche
        $normalizedBasePath = self::normalizePath($basePath);
        $normalizedCurrentPath = self::normalizePath($currentPath);

        // Prüfen, ob dieses Verzeichnis Teil des aktuellen Pfades ist
        $isInCurrentPath = strpos($normalizedCurrentPath . '/', $normalizedBasePath . '/') === 0;
        $isCurrentPath = $normalizedBasePath === $normalizedCurrentPath;

        // Wenn wir dieses Verzeichnis bereits gescannt haben und es nicht der aktuelle Pfad ist
        // und es nicht Teil des aktuellen Pfades ist
        if (
            isset(self::$directoryCache[$cacheKey]) &&
            !$isCurrentPath &&
            !$isInCurrentPath
        ) {
            return self::$directoryCache[$cacheKey];
        }

        try {
            // Verzeichnisinhalt scannen (nur einmal)
            $items = @scandir($basePath);
            if ($items === false) {
                return [];
            }

            // Nur Verzeichnisse sammeln, keine Dateien
            $directories = [];
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $basePath . DIRECTORY_SEPARATOR . $item;

                // Schneller Check, ob es ein Verzeichnis ist
                if (!is_dir($itemPath)) continue;

                // Versteckte Verzeichnisse überspringen
                if (self::isHidden($itemPath)) continue;

                $directories[] = [
                    'name' => $item,
                    'path' => $itemPath
                ];
            }

            // Verzeichnisse alphabetisch sortieren
            usort($directories, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            // Verarbeite jedes Verzeichnis
            foreach ($directories as $dir) {
                $itemPath = $dir['path'];
                $item = $dir['name'];

                // Normalisierter Pfad für Vergleiche
                $normalizedItemPath = self::normalizePath($itemPath);

                // Prüfen, ob dieses Verzeichnis Teil des aktuellen Pfades ist
                $isInCurrentPath = strpos($normalizedCurrentPath . '/', $normalizedItemPath . '/') === 0;
                $isCurrentPath = $normalizedItemPath === $normalizedCurrentPath;

                // Unterverzeichnisse laden, wenn dieses Verzeichnis Teil des aktuellen Pfades ist
                $children = [];
                if ($isInCurrentPath) {
                    $children = self::buildDirectoryTree($itemPath, $currentPath, $maxDepth, $currentDepth + 1);
                }

                // Prüfen, ob das Verzeichnis Unterverzeichnisse hat (ohne sie alle zu laden)
                $hasChildren = !empty($children);
                if (!$hasChildren && is_readable($itemPath)) {
                    // Schneller Check, ob es Unterverzeichnisse gibt
                    $subItems = @scandir($itemPath);
                    if ($subItems !== false) {
                        foreach ($subItems as $subItem) {
                            if ($subItem === '.' || $subItem === '..') continue;
                            $subItemPath = $itemPath . DIRECTORY_SEPARATOR . $subItem;
                            if (is_dir($subItemPath) && !self::isHidden($subItemPath)) {
                                $hasChildren = true;
                                break;
                            }
                        }
                    }
                }

                $result[] = [
                    'name' => $item,
                    'path' => $itemPath,
                    'children' => $children,
                    'hasChildren' => $hasChildren,
                    'isActive' => $isCurrentPath
                ];
            }

            // Ergebnis cachen
            self::$directoryCache[$cacheKey] = $result;
        } catch (\Exception $e) {
            // Bei Fehlern leeres Array zurückgeben
            return [];
        }

        return $result;
    }

    /**
     * Lädt Dateien und Verzeichnisse für den aktuellen Ordner (optimiert)
     */
    public static function getDirectoryContents(string $path): array
    {
        $result = [];

        if (!is_dir($path) || !is_readable($path)) {
            return $result;
        }

        try {
            // Verzeichnisinhalte auslesen
            $items = @scandir($path);
            if ($items === false) {
                return $result;
            }

            // Spezielle Einträge für aktuelles und übergeordnetes Verzeichnis
            $result[] = [
                'name' => '.',
                'path' => $path,
                'isDir' => true,
                'isSpecial' => true,
                'mtime' => @filemtime($path) ?: time(),
                'size' => 0,
                'ext' => ''
            ];

            $parentPath = dirname($path);
            $result[] = [
                'name' => '..',
                'path' => $parentPath,
                'isDir' => true,
                'isSpecial' => true,
                'mtime' => @filemtime($parentPath) ?: time(),
                'size' => 0,
                'ext' => ''
            ];

            // Sammle Verzeichnisse und Dateien getrennt
            $directories = [];
            $files = [];

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;

                $itemPath = $path . DIRECTORY_SEPARATOR . $item;

                if (!is_readable($itemPath)) continue;

                // Versteckte Dateien/Verzeichnisse überspringen
                if (self::isHidden($itemPath)) continue;

                $isDir = is_dir($itemPath);

                $entry = [
                    'name' => $item,
                    'path' => $itemPath,
                    'isDir' => $isDir,
                    'isSpecial' => false,
                    'mtime' => @filemtime($itemPath) ?: time(),
                ];

                if (!$isDir) {
                    $entry['size'] = @filesize($itemPath) ?: 0;
                    $entry['ext'] = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    $files[] = $entry;
                } else {
                    $entry['size'] = 0;
                    $entry['ext'] = '';
                    $directories[] = $entry;
                }
            }

            // Verzeichnisse und Dateien alphabetisch sortieren
            usort($directories, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            usort($files, function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });

            // Ergebnis zusammenführen: Spezielle Einträge, dann Verzeichnisse, dann Dateien
            $result = array_merge($result, $directories, $files);
        } catch (\Exception $e) {
            // Bei Fehlern leeres Array zurückgeben
        }

        return $result;
    }

    /**
     * Rendert den Verzeichnisbaum als HTML
     */
    public static function renderDirectoryTree(array $tree, string $currentPath): string
    {
        // Normalisierter aktueller Pfad für Vergleiche
        $normalizedCurrentPath = self::normalizePath($currentPath);

        $html = '<ul class="directory-tree">';

        foreach ($tree as $dir) {
            // Normalisierter Verzeichnispfad für Vergleiche
            $normalizedDirPath = self::normalizePath($dir['path']);

            // Prüfen, ob dieser Knoten dem aktuellen Pfad entspricht
            $isActive = $dir['isActive'] ?? false;

            // Prüfen, ob dieses Verzeichnis Teil des aktuellen Pfades ist
            $isInPath = strpos($normalizedCurrentPath . '/', $normalizedDirPath . '/') === 0;

            // Prüfen, ob das Verzeichnis Kinder hat
            $hasChildren = $dir['hasChildren'] ?? false;

            $html .= '<li>';
            $html .= '<div class="tree-item' . ($isActive ? ' tree-item-active' : '') . '">';
            $html .= '<i class="fas fa-folder' . (($isActive || $isInPath) ? '-open' : '') . ' folder-icon"></i>';
            $html .= '<a href="' . htmlspecialchars(BASE_URL . '/?path=' . urlencode($dir['path'])) . '">' .
                htmlspecialchars($dir['name']) . '</a>';
            $html .= '</div>';

            if ($hasChildren) {
                if (!empty($dir['children'])) {
                    $html .= self::renderDirectoryTree($dir['children'], $currentPath);
                } else if ($isActive || $isInPath) {
                    // Wenn das aktuelle Verzeichnis ausgewählt ist oder Teil des Pfades ist
                    // und potentiell Kinder hat, aber sie noch nicht geladen wurden
                    $html .= '<ul><li><div class="tree-item tree-item-placeholder"><i class="fas fa-ellipsis-h"></i> ...</div></li></ul>';
                }
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    /**
     * Entscheidet, ob eine Datei inline angezeigt werden soll
     */
    public static function isInlineView(string $ext): bool
    {
        static $inlineExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'webm', 'ogg', 'mp3', 'wav', 'txt'];
        return in_array($ext, $inlineExt, true);
    }

    /**
     * Formatiert Bytes in lesbare Größenangaben
     */
    public static function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $size = $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return sprintf('%.1f %s', $size, $units[$i]);
    }

    /**
     * Cache für Verzeichnisse leeren
     */
    public static function clearDirectoryCache(): void
    {
        self::$directoryCache = [];
    }

    /**
     * Generiert Breadcrumb-Navigation
     */
    public static function generateBreadcrumb(string $path): string
    {
        // Spezialfall: Upload-Verzeichnis
        if ($path === UPLOAD_PATH) {
            return '<a href="' . htmlspecialchars(BASE_URL . '/?path=' . urlencode(UPLOAD_PATH)) . '">' .
                '<i class="fas fa-upload"></i> Upload-Verzeichnis</a>';
        }

        $html = '';

        // Startpunkt der Breadcrumb-Navigation
        if (strpos($path, UPLOAD_PATH) === 0) {
            // Wenn wir uns innerhalb des Upload-Verzeichnisses befinden
            $html = '<a href="' . htmlspecialchars(BASE_URL . '/?path=' . urlencode(UPLOAD_PATH)) . '">' .
                '<i class="fas fa-upload"></i> Upload-Verzeichnis</a>';
        } else {
            // Wenn wir uns außerhalb des Upload-Verzeichnisses befinden
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows: Laufwerk als Startpunkt
                $drive = substr($path, 0, 2); // z.B. "C:"
                $html = '<a href="' . htmlspecialchars(BASE_URL . '/?path=' . urlencode($drive . '\\')) . '">' .
                    '<i class="fas fa-hdd"></i> ' . htmlspecialchars($drive) . '</a>';

                // Pfad ohne Laufwerk für die weiteren Teile
                $pathWithoutDrive = substr($path, 2);

                // Wenn der Pfad nur aus dem Laufwerk besteht, sind wir fertig
                if (strlen(trim($pathWithoutDrive, '\\')) === 0) {
                    return $html;
                }
            } else {
                // Unix/Linux/Mac: Root-Verzeichnis als Startpunkt
                $html = '<a href="' . htmlspecialchars(BASE_URL . '/?path=/') . '">' .
                    '<i class="fas fa-hdd"></i> /</a>';

                // Wenn der Pfad nur aus dem Root-Verzeichnis besteht, sind wir fertig
                if ($path === '/') {
                    return $html;
                }
            }
        }

        // Pfadteile für die Breadcrumb-Navigation ermitteln
        $segments = [];
        $currentPath = '';

        // Für Windows den Laufwerksbuchstaben entfernen
        if (PHP_OS_FAMILY === 'Windows' && preg_match('/^([A-Za-z]:)(.*)$/', $path, $matches)) {
            $driveLetter = $matches[1];
            $pathWithoutDrive = $matches[2];

            // Laufwerksbuchstaben als Basis-Pfad verwenden
            $currentPath = $driveLetter . '\\';

            // Pfad ohne Laufwerksbuchstaben in Segmente aufteilen
            $pathParts = array_filter(explode('\\', $pathWithoutDrive), 'strlen');

            foreach ($pathParts as $part) {
                $currentPath .= $part . '\\';
                $segments[] = [
                    'name' => $part,
                    'path' => rtrim($currentPath, '\\')
                ];
            }
        } else {
            // Unix/Linux/Mac: Pfad in Segmente aufteilen
            $pathParts = array_filter(explode('/', $path), 'strlen');
            $currentPath = '/';

            foreach ($pathParts as $part) {
                $currentPath .= $part . '/';
                $segments[] = [
                    'name' => $part,
                    'path' => rtrim($currentPath, '/')
                ];
            }
        }

        // Breadcrumb-Segmente ausgeben
        foreach ($segments as $segment) {
            $html .= '<span class="breadcrumb-separator">/</span>';
            $html .= '<a href="' . htmlspecialchars(BASE_URL . '/?path=' . urlencode($segment['path'])) . '">' .
                htmlspecialchars($segment['name']) . '</a>';
        }

        return $html;
    }

    /**
     * Streamt eine Datei zum Browser
     */
    public static function streamFile(string $relativePath, bool $asAttachment = false): void
    {
        // Sicherheitscheck: Keine Pfadtraversierung erlauben
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = preg_replace('/\.{2,}/', '', $relativePath); // Entfernt .. aus dem Pfad

        // Vollständigen Pfad erstellen
        $filePath = UPLOAD_PATH . '/' . $relativePath;

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

        if ($asAttachment) {
            // Als Anhang zum Download anbieten
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
        } else {
            // Im Browser anzeigen
            header('Content-Disposition: inline; filename="' . $fileName . '"');
        }

        // Datei ausgeben
        readfile($filePath);
        exit;
    }
}
