<?php
// src/FileExplorer.php

/**
 * Hilfsfunktionen für den Dateisystem-Explorer
 */
// Prüfen, ob die Klasse bereits definiert wurde
if (!class_exists('FileExplorer')) {
    class FileExplorer {
        /**
         * Prüft, ob ein Pfad innerhalb des Upload-Verzeichnisses liegt
         */
        public static function isPathWithinUploadDir(string $path): bool {
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
        public static function getAvailableDrives(): array {
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
         * Erstellt einen Verzeichnisbaum für einen bestimmten Pfad (begrenzte Tiefe)
         */
        public static function buildDirectoryTree(string $basePath, int $maxDepth = 2, int $currentDepth = 0): array {
            if ($currentDepth >= $maxDepth) {
                return [];
            }
            
            $result = [];
            
            if (!is_dir($basePath) || !is_readable($basePath)) {
                return $result;
            }
            
            try {
                $items = scandir($basePath);
                
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    
                    $itemPath = $basePath . DIRECTORY_SEPARATOR . $item;
                    
                    if (is_dir($itemPath) && is_readable($itemPath)) {
                        $children = ($currentDepth < $maxDepth - 1) ? 
                            self::buildDirectoryTree($itemPath, $maxDepth, $currentDepth + 1) : [];
                        
                        $result[] = [
                            'name' => $item,
                            'path' => $itemPath,
                            'children' => $children,
                            'hasChildren' => !empty($children) || (is_dir($itemPath) && count(array_diff(scandir($itemPath), ['.', '..'])) > 0)
                        ];
                    }
                }
            } catch (Exception $e) {
                // Bei Zugriffsproblemen leeres Array zurückgeben
            }
            
            // Sortieren nach Name
            usort($result, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            
            return $result;
        }

        /**
         * Lädt Dateien und Verzeichnisse für den aktuellen Ordner
         */
        public static function getDirectoryContents(string $path): array {
            $result = [];
            
            if (!is_dir($path) || !is_readable($path)) {
                return $result;
            }
            
            try {
                // Verzeichnisinhalte auslesen
                $items = scandir($path);
                
                // Spezielle Einträge für aktuelles und übergeordnetes Verzeichnis
                $result[] = [
                    'name' => '.',
                    'path' => $path,
                    'isDir' => true,
                    'isSpecial' => true,
                    'mtime' => filemtime($path),
                    'size' => 0,
                    'ext' => ''
                ];
                
                $parentPath = dirname($path);
                $result[] = [
                    'name' => '..',
                    'path' => $parentPath,
                    'isDir' => true,
                    'isSpecial' => true,
                    'mtime' => filemtime($parentPath),
                    'size' => 0,
                    'ext' => ''
                ];
                
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    
                    $itemPath = $path . DIRECTORY_SEPARATOR . $item;
                    
                    if (!is_readable($itemPath)) continue;
                    
                    $isDir = is_dir($itemPath);
                    
                    $entry = [
                        'name' => $item,
                        'path' => $itemPath,
                        'isDir' => $isDir,
                        'isSpecial' => false,
                        'mtime' => filemtime($itemPath),
                    ];
                    
                    if (!$isDir) {
                        $entry['size'] = filesize($itemPath);
                        $entry['ext'] = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                    } else {
                        $entry['size'] = 0;
                        $entry['ext'] = '';
                    }
                    
                    $result[] = $entry;
                }
            } catch (Exception $e) {
                // Bei Zugriffsproblemen leeres Array zurückgeben
            }
            
            // Sortieren: Spezielle Einträge zuerst, dann Verzeichnisse, dann Dateien
            usort($result, function($a, $b) {
                // Spezielle Einträge (. und ..) ganz oben
                if ($a['isSpecial'] && !$b['isSpecial']) return -1;
                if (!$a['isSpecial'] && $b['isSpecial']) return 1;
                
                // Dann Verzeichnisse vor Dateien
                if ($a['isDir'] && !$b['isDir']) return -1;
                if (!$a['isDir'] && $b['isDir']) return 1;
                
                // Sonst alphabetisch nach Name sortieren
                return strcasecmp($a['name'], $b['name']);
            });
            
            return $result;
        }

        /**
         * Erstellt rekursiv den HTML-Code für den Verzeichnisbaum
         */
        public static function renderDirectoryTree(array $tree, string $currentPath): string {
            $html = '<ul class="directory-tree">';
            
            foreach ($tree as $dir) {
                $isActive = (rtrim($dir['path'], '/\\') === rtrim($currentPath, '/\\'));
                $hasChildren = !empty($dir['children']) || $dir['hasChildren'];
                
                $html .= '<li class="' . ($isActive ? 'active' : '') . '">';
                $html .= '<div class="tree-item' . ($isActive ? ' tree-item-active' : '') . '">';
                $html .= '<i class="fas fa-folder' . ($isActive ? '-open' : '') . ' folder-icon"></i>';
                $html .= '<a href="' . htmlspecialchars(BASE_URL . '/public/index.php?path=' . urlencode($dir['path'])) . '">' . 
                        htmlspecialchars($dir['name']) . '</a>';
                $html .= '</div>';
                
                if ($hasChildren) {
                    if (!empty($dir['children'])) {
                        $html .= self::renderDirectoryTree($dir['children'], $currentPath);
                    } else if ($isActive) {
                        // Wenn der aktuelle Ordner ausgewählt ist und potentiell Kinder hat,
                        // aber sie noch nicht geladen wurden, zeigen wir einen Ladehinweis
                        $html .= '<ul><li><i class="fas fa-ellipsis-h"></i> ...</li></ul>';
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
        public static function isInlineView(string $ext): bool {
            $inlineExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'webm', 'ogg', 'mp3', 'wav', 'txt'];
            return in_array($ext, $inlineExt, true);
        }

        /**
         * Formatiert Bytes in lesbare Größenangaben
         */
        public static function humanSize(int $bytes): string {
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
         * Generiert Breadcrumb-Navigation
         */
        public static function generateBreadcrumb(string $path): string {
            // Spezialfall: Upload-Verzeichnis
            if ($path === UPLOAD_PATH) {
                return '<a href="' . htmlspecialchars(BASE_URL . '/public/index.php?path=' . urlencode(UPLOAD_PATH)) . '">' .
                       '<i class="fas fa-upload"></i> Upload-Verzeichnis</a>';
            }
            
            $html = '';
            
            // Startpunkt der Breadcrumb-Navigation
            if (strpos($path, UPLOAD_PATH) === 0) {
                // Wenn wir uns innerhalb des Upload-Verzeichnisses befinden
                $html = '<a href="' . htmlspecialchars(BASE_URL . '/public/index.php?path=' . urlencode(UPLOAD_PATH)) . '">' .
                        '<i class="fas fa-upload"></i> Upload-Verzeichnis</a>';
            } else {
                // Wenn wir uns außerhalb des Upload-Verzeichnisses befinden
                if (PHP_OS_FAMILY === 'Windows') {
                    // Windows: Laufwerk als Startpunkt
                    $drive = substr($path, 0, 2); // z.B. "C:"
                    $html = '<a href="' . htmlspecialchars(BASE_URL . '/public/index.php?path=' . urlencode($drive . '\\')) . '">' .
                            '<i class="fas fa-hdd"></i> ' . htmlspecialchars($drive) . '</a>';
                    
                    // Pfad ohne Laufwerk für die weiteren Teile
                    $pathWithoutDrive = substr($path, 2);
                    
                    // Wenn der Pfad nur aus dem Laufwerk besteht, sind wir fertig
                    if (strlen(trim($pathWithoutDrive, '\\')) === 0) {
                        return $html;
                    }
                } else {
                    // Unix/Linux/Mac: Root-Verzeichnis als Startpunkt
                    $html = '<a href="' . htmlspecialchars(BASE_URL . '/public/index.php?path=/') . '">' .
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
                $html .= '<a href="' . htmlspecialchars(BASE_URL . '/public/index.php?path=' . urlencode($segment['path'])) . '">' . 
                         htmlspecialchars($segment['name']) . '</a>';
            }
            
            return $html;
        }
    }
}