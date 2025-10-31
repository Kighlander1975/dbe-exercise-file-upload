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
     * Prüft, ob ein Pfad Teil des aktuellen Pfades ist
     */
    private static function isInPath(string $dirPath, string $currentPath): bool
    {
        $dirPath = rtrim(str_replace('\\', '/', $dirPath), '/');
        $currentPath = rtrim(str_replace('\\', '/', $currentPath), '/');

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
        $parentPath = rtrim(str_replace('\\', '/', $parentPath), '/') . '/';
        $childPath = rtrim(str_replace('\\', '/', $childPath), '/') . '/';

        // Prüfen, ob der Kindpfad mit dem Elternpfad beginnt
        return $parentPath !== $childPath && strpos($childPath, $parentPath) === 0;
    }

    /**
     * Neue Hilfsmethode: Prüft, ob ein Pfad einer der Zielpfade ist oder ein Elternteil davon
     * HINZUGEFÜGT: Unterstützt die Prüfung gegen mehrere Zielpfade
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

    public static function buildDirectoryTree(string $basePath, string $currentPath = '', int $maxDepth = 10, int $currentDepth = 0): array
    {
        if ($currentPath === '') {
            $currentPath = $basePath;
        }

        // Pfad zum Upload-Verzeichnis
        $uploadPath = realpath(UPLOAD_PATH);
        $currentRealPath = realpath($currentPath);

        // Pfade, die expandiert werden sollen
        $pathsToExpand = [];

        // Wenn kein Pfad angegeben wurde (direkter Aufruf) oder der aktuelle Pfad das Upload-Verzeichnis ist,
        // dann den Pfad zum Upload-Verzeichnis expandieren
        $isDirectAccess = !isset($_GET['path']) || trim($_GET['path']) === '';

        if ($isDirectAccess) {
            // Bei direktem Aufruf: Pfad zum Upload-Verzeichnis expandieren
            $tempPath = $uploadPath;
            while ($tempPath && $tempPath !== dirname($tempPath)) {
                $pathsToExpand[] = $tempPath;
                $tempPath = dirname($tempPath);
            }
            // Umkehren, damit wir von der Wurzel nach unten gehen
            $pathsToExpand = array_reverse($pathsToExpand);
        } else {
            // Bei Auswahl eines anderen Pfades: Nur diesen Pfad expandieren
            $tempPath = $currentRealPath;
            while ($tempPath && $tempPath !== dirname($tempPath)) {
                $pathsToExpand[] = $tempPath;
                $tempPath = dirname($tempPath);
            }
            // Umkehren, damit wir von der Wurzel nach unten gehen
            $pathsToExpand = array_reverse($pathsToExpand);
        }

        // Rest der Methode bleibt gleich...
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

        // Prüfen, ob dieses Verzeichnis Teil eines der Pfade ist, die wir expandieren wollen
        $isInExpandPath = self::isPathOrParentOf($basePath, $pathsToExpand);

        // Wenn wir dieses Verzeichnis bereits gescannt haben und es nicht der aktuelle Pfad ist
        // und es nicht Teil eines der Pfade ist, die wir expandieren wollen
        if (
            isset(self::$directoryCache[$cacheKey]) &&
            !self::isInPath($basePath, $currentPath) &&
            !$isInExpandPath
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

                // Prüfen, ob dieses Verzeichnis Teil des aktuellen Pfades ist
                $isInCurrentPath = self::isInPath($itemPath, $currentPath);
                $isCurrentPath = rtrim($itemPath, '/\\') === rtrim($currentPath, '/\\');

                // Prüfen, ob dieses Verzeichnis Teil eines der Pfade ist, die wir expandieren wollen
                $isInExpandPath = self::isPathOrParentOf($itemPath, $pathsToExpand);

                // Unterverzeichnisse laden, wenn:
                // - dieses Verzeichnis Teil des aktuellen Pfades ist ODER
                // - dieses Verzeichnis Teil eines der Pfade ist, die wir expandieren wollen
                $children = [];
                if ($isInCurrentPath || $isInExpandPath) {
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

    public static function renderDirectoryTree(array $tree, string $currentPath): string
    {
        // Pfad zum Upload-Verzeichnis
        $uploadPath = realpath(UPLOAD_PATH);
        $currentRealPath = realpath($currentPath);

        // Wenn kein Pfad angegeben wurde (direkter Aufruf) oder der aktuelle Pfad das Upload-Verzeichnis ist,
        // dann den Pfad zum Upload-Verzeichnis expandieren
        $isDirectAccess = !isset($_GET['path']) || trim($_GET['path']) === '';
        $isNavigatingUp = isset($_GET['navigate_up']) && $_GET['navigate_up'] === '1';

        // Pfade, die expandiert werden sollen
        $pathsToExpand = [];

        if ($isDirectAccess) {
            // Bei direktem Aufruf: Pfad zum Upload-Verzeichnis expandieren
            $pathsToExpand[] = $uploadPath;
        } else {
            // Bei Auswahl eines anderen Pfades: Nur diesen Pfad expandieren
            $pathsToExpand[] = $currentRealPath ?: $currentPath;
        }

        $html = '<ul class="directory-tree">';

        foreach ($tree as $dir) {
            // Prüfen, ob dieser Knoten dem aktuellen Pfad entspricht
            $isActive = false;
            
            if ($isNavigatingUp) {
                // Bei Navigation nach oben: Prüfen, ob dieser Knoten dem aktuellen Pfad entspricht
                $normalizedDirPath = rtrim(str_replace('\\', '/', $dir['path']), '/');
                $normalizedCurrentPath = rtrim(str_replace('\\', '/', $currentPath), '/');
                $isActive = ($normalizedDirPath === $normalizedCurrentPath);
            } else {
                // Normale Aktivitätsprüfung
                $isActive = $dir['isActive'] ?? false;
            }
            
            $hasChildren = $dir['hasChildren'] ?? false;
            $isInPath = self::isInPath($dir['path'], $currentPath);

            // Prüfen, ob dieses Verzeichnis Teil eines der Pfade ist, die wir expandieren wollen
            $isInExpandPath = self::isPathOrParentOf($dir['path'], $pathsToExpand);

            $html .= '<li>';
            $html .= '<div class="tree-item' . ($isActive ? ' tree-item-active' : '') . '">';
            $html .= '<i class="fas fa-folder' . (($isActive || $isInPath || $isInExpandPath) ? '-open' : '') . ' folder-icon"></i>';
            $html .= '<a href="' . htmlspecialchars(BASE_URL . '/?path=' . urlencode($dir['path'])) . '">' .
                htmlspecialchars($dir['name']) . '</a>';
            $html .= '</div>';

            if ($hasChildren) {
                if (!empty($dir['children'])) {
                    $html .= self::renderDirectoryTree($dir['children'], $currentPath);
                } else if ($isActive || $isInPath || $isInExpandPath) {
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
     * Stellt sicher, dass der Pfad zum Upload-Verzeichnis expandiert wird
     * und als aktiv markiert wird, wenn es der aktuelle Pfad ist
     */
    public static function ensureUploadPathExpanded(array &$tree): void
    {
        $uploadPath = realpath(UPLOAD_PATH);
        if (!$uploadPath) {
            return;
        }

        // Prüfen, ob es ein direkter Aufruf ist oder ob wir über ".." navigieren
        $isDirectAccess = !isset($_GET['path']) || trim($_GET['path']) === '';
        $isNavigatingUp = isset($_GET['navigate_up']) && $_GET['navigate_up'] === '1';
        
        // Aktuellen Pfad ermitteln
        $currentPath = isset($_GET['path']) && trim($_GET['path']) !== '' ? $_GET['path'] : UPLOAD_PATH;
        
        // Pfad in Segmente aufteilen
        $pathSegments = explode(DIRECTORY_SEPARATOR, $uploadPath);

        // Für Windows den Laufwerksbuchstaben entfernen
        if (PHP_OS_FAMILY === 'Windows' && isset($pathSegments[0]) && strpos($pathSegments[0], ':') !== false) {
            $drive = $pathSegments[0];
            array_shift($pathSegments);
        }

        // Leere Segmente entfernen
        $pathSegments = array_filter($pathSegments);

        // Aktuellen Pfad aufbauen
        $currentPathBase = PHP_OS_FAMILY === 'Windows' ? $drive . '\\' : '/';

        // Durch den Baum navigieren und den Pfad zum Upload-Verzeichnis expandieren
        self::expandPathInTree($tree, $pathSegments, $currentPathBase, $isDirectAccess);
        
        // Wenn wir über ".." navigieren, müssen wir den aktuellen Pfad im Baum als aktiv markieren
        if ($isNavigatingUp) {
            self::markCurrentPathAsActive($tree, $currentPath);
        }
    }

    /**
     * Rekursive Hilfsmethode, um den Pfad in einem Verzeichnisbaum zu expandieren
     * und als aktiv zu markieren, wenn es der aktuelle Pfad ist
     */
    private static function expandPathInTree(array &$tree, array $segments, string $currentPath, bool $markAsActive = false): bool
    {
        if (empty($segments)) {
            return true;
        }

        $nextSegment = array_shift($segments);
        $found = false;

        foreach ($tree as &$node) {
            $nodeName = basename($node['path']);

            if (strcasecmp($nodeName, $nextSegment) === 0) {
                // Gefunden! Diesen Knoten expandieren
                $found = true;

                // Wenn es das letzte Segment ist und markAsActive true ist, als aktiv markieren
                if (empty($segments) && $markAsActive) {
                    $node['isActive'] = true;
                }

                // Wenn es Kinder gibt, in diesen weitersuchen
                if (!empty($node['children'])) {
                    if (self::expandPathInTree($node['children'], $segments, $node['path'], $markAsActive)) {
                        return true;
                    }
                }
                // Wenn keine Kinder vorhanden sind, aber es sollte weitere Segmente geben
                else if (!empty($segments)) {
                    // Kinder laden
                    $node['children'] = self::buildDirectoryTree($node['path'], $node['path']);
                    $node['hasChildren'] = !empty($node['children']);

                    // Weiter expandieren
                    if ($node['hasChildren']) {
                        if (self::expandPathInTree($node['children'], $segments, $node['path'], $markAsActive)) {
                            return true;
                        }
                    }
                }

                break;
            }
        }

        return $found;
    }

    /**
     * Markiert den aktuellen Pfad im Verzeichnisbaum als aktiv
     */
    private static function markCurrentPathAsActive(array &$tree, string $currentPath): void
    {
        // Normalisieren des Pfads für Vergleich
        $normalizedCurrentPath = rtrim(str_replace('\\', '/', $currentPath), '/');
        
        foreach ($tree as &$node) {
            $normalizedNodePath = rtrim(str_replace('\\', '/', $node['path']), '/');
            
            // Wenn dieser Knoten dem aktuellen Pfad entspricht, als aktiv markieren
            if ($normalizedNodePath === $normalizedCurrentPath) {
                $node['isActive'] = true;
                return;
            }
            
            // Rekursiv in Unterverzeichnissen suchen
            if (!empty($node['children'])) {
                self::markCurrentPathAsActive($node['children'], $currentPath);
            }
        }
    }
}