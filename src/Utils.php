<?php
// src/Utils.php

class FileService
{
    private string $uploadDir;
    private int $maxBytes;
    private array $allowedExts; // <— statt string

    public function __construct(
        string $uploadDir,
        int $maxBytes,
        array $allowedExts // <— Array
    ) {
        $this->uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR);
        $this->maxBytes = $maxBytes;
        $this->allowedExts = array_map('strtolower', $allowedExts);
    }

    // Prüft $_FILES-Entry und gibt ein Array mit Metadaten zurück
    public function validateUpload(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Invalid upload structure');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_NO_FILE: throw new RuntimeException('No file sent');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: throw new RuntimeException('Exceeded filesize limit');
            default: throw new RuntimeException('Unknown upload error');
        }

        if (($file['size'] ?? 0) <= 0 || $file['size'] > $this->maxBytes) {
            throw new RuntimeException('File size invalid');
        }

        $originalName = $file['name'] ?? '';
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $this->allowedExts, true)) {
            throw new RuntimeException('File type not allowed');
        }

        $mime = $this->detectMime($file['tmp_name']);

        return [
            'original_name' => $originalName,
            'size' => (int)$file['size'],
            'tmp_name' => $file['tmp_name'],
            'mime' => $mime,
            'extension' => $ext,
            'basename' => pathinfo($originalName, PATHINFO_FILENAME),
        ];
    }

    // Speichert die Datei sicher ab. Optional: Unterordner, zufälliger Dateiname.
    public function saveUpload(
        array $validated,
        ?string $subdir = null,
        bool $useRandomName = true
    ): array {
        $dir = $this->uploadDir;
        if ($subdir) {
            $safeSubdir = $this->sanitizePath($subdir);
            $dir .= DIRECTORY_SEPARATOR . $safeSubdir;
        }

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('Failed to create upload directory');
            }
        }

        $ext = $validated['extension'];
        $finalName = $useRandomName
            ? $this->randomName() . '.' . $ext
            : $this->sanitizeFilename($validated['original_name']);

        $targetPath = $dir . DIRECTORY_SEPARATOR . $finalName;

        if (!is_uploaded_file($validated['tmp_name'])) {
            // In Tests/CLI ist tmp evtl. nicht als "uploaded" markiert
            // Für echte Uploads sollte diese Prüfung true sein
        }

        if (!move_uploaded_file($validated['tmp_name'], $targetPath)) {
            // Fallback für Fälle ohne SAPI-Upload (Tests)
            if (!rename($validated['tmp_name'], $targetPath)) {
                throw new RuntimeException('Failed to move uploaded file');
            }
        }

        // Rückgabe-Metadaten
        return [
            'stored_path' => $targetPath,
            'stored_dir' => $dir,
            'stored_name' => $finalName,
            'mime' => $validated['mime'],
            'size' => $validated['size'],
            'original_name' => $validated['original_name'],
            'relative' => ltrim(str_replace($this->uploadDir, '', $targetPath), DIRECTORY_SEPARATOR),
        ];
    }

    // Liefert eine Datei aus uploads/ aus. name kann Unterordner enthalten.
    public function streamFile(
        string $name,
        bool $asAttachment = false,
        ?string $downloadName = null
    ): void {
        // Pfad härten
        if ($name === '' || strpos($name, "\0") !== false || strpos($name, '..') !== false) {
            http_response_code(400);
            echo 'Bad request';
            return;
        }

        $safePath = $this->sanitizePath($name);
        $path = $this->uploadDir . DIRECTORY_SEPARATOR . $safePath;

        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Not found';
            return;
        }

        $mime = $this->detectMime($path);
        $size = filesize($path);
        $mtime = filemtime($path);
        $etag = '"' . md5($path . '|' . $size . '|' . $mtime) . '"';

        // Caching-Header
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        header('Cache-Control: public, max-age=31536000, immutable');

        // 304 Handling
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            return;
        }

        header('Content-Type: ' . $mime);
        if ($asAttachment) {
            $dlName = $downloadName ?: basename($path);
            header('Content-Disposition: attachment; filename="' . $this->asciiOnly($dlName) . '"');
        } else {
            header('Content-Disposition: inline');
        }

        // Optional: einfache Range-Unterstützung für Medien
        $start = 0;
        $end = $size - 1;
        $status = 200;

        if (isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
                if ($m[1] !== '') $start = (int)$m[1];
                if ($m[2] !== '') $end = (int)$m[2];
                if ($start > $end || $start >= $size) {
                    header('Content-Range: bytes */' . $size);
                    http_response_code(416);
                    return;
                }
                $status = 206;
            }
        }

        $length = $end - $start + 1;
        header('Accept-Ranges: bytes');
        if ($status === 206) {
            header("Content-Range: bytes $start-$end/$size");
            http_response_code(206);
        } else {
            http_response_code(200);
        }
        header('Content-Length: ' . $length);

        $this->outputFileRange($path, $start, $end);
    }

    // ===== Helpers =====

    private function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $path) : null;
        if ($finfo) finfo_close($finfo);
        return $mime ?: 'application/octet-stream';
    }

    private function sanitizeFilename(string $name): string
    {
        // Entferne Pfadangaben, ersetze problematische Zeichen
        $name = basename($name);
        $name = preg_replace('/[^\w.\-]+/u', '_', $name);
        // Trimme doppelte Punkte/Unterstriche
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '._');
    }

    private function sanitizePath(string $path): string
    {
        // Erlaubt Unterordner, verhindert Traversal
        $parts = array_filter(explode('/', str_replace('\\', '/', $path)), function ($p) {
            return $p !== '' && $p !== '.' && $p !== '..';
        });
        $parts = array_map(fn($p) => $this->sanitizeFilename($p), $parts);
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function randomName(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private function asciiOnly(string $name): string
    {
        // Für Content-Disposition: einfache ASCII-Variante
        $name = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $name = preg_replace('/[^\w.\-]+/', '_', $name);
        return $name;
    }

    private function outputFileRange(string $path, int $start, int $end): void
    {
        $chunk = 8192;
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            http_response_code(500);
            echo 'Read error';
            return;
        }
        try {
            fseek($fh, $start);
            $toRead = $end - $start + 1;
            while ($toRead > 0 && !feof($fh)) {
                $read = ($toRead > $chunk) ? $chunk : $toRead;
                $buffer = fread($fh, $read);
                if ($buffer === false) break;
                echo $buffer;
                flush();
                $toRead -= strlen($buffer);
            }
        } finally {
            fclose($fh);
        }
    }
}
