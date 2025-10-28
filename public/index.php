<?php
// public/index.php
require __DIR__ . '/../config/App.php';
require __DIR__ . '/../src/Utils.php';
require __DIR__ . '/../src/FlashMessage.php';

// Flash-Messages initialisieren
FlashMessage::init();
$flashMessages = FlashMessage::getAll();

// Hilfsfunktion: Dateien rekursiv einsammeln
function listFilesRecursive(string $baseDir): array
{
    $result = [];
    if (!is_dir($baseDir)) return $result;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $baseLen = strlen(rtrim($baseDir, DIRECTORY_SEPARATOR)) + 1;

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile()) {
            $absPath = $fileInfo->getPathname();
            $relative = substr($absPath, $baseLen); // relativ zu UPLOAD_PATH
            // Normalisiere auf forward slashes für die URL
            $relativeUrl = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $result[] = [
                'relative' => $relativeUrl,
                'name'     => $fileInfo->getFilename(),
                'size'     => $fileInfo->getSize(),
                'mtime'    => $fileInfo->getMTime(),
                'ext'      => strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION)),
            ];
        }
    }
    // Neueste zuerst
    usort($result, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $result;
}

// Logik: Liste laden
$files = listFilesRecursive(UPLOAD_PATH);

// Hilfslogik: Entscheide, ob inline angezeigt werden soll
function isInlineView(string $ext): bool
{
    $inlineExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'webm', 'ogg', 'mp3', 'wav', 'txt'];
    return in_array($ext, $inlineExt, true);
}

// Hilfsformatierung
function humanSize(int $bytes): string
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
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Uploads verwalten</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            margin: 2rem;
        }

        h1 {
            margin-top: 0;
        }

        form.upload {
            display: grid;
            gap: .5rem;
            max-width: 480px;
            padding: 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        .row {
            display: flex;
            gap: .5rem;
            align-items: center;
        }

        input[type="text"],
        input[type="file"] {
            width: 100%;
        }

        button {
            padding: .6rem 1rem;
            cursor: pointer;
        }

        .files {
            margin-top: 2rem;
        }

        .files table {
            width: 100%;
            border-collapse: collapse;
        }

        .files th,
        .files td {
            padding: .5rem .6rem;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }

        .badge {
            font-size: .75rem;
            padding: .2rem .4rem;
            border-radius: .4rem;
            background: #eee;
        }

        .actions a {
            margin-right: .5rem;
        }

        .empty {
            color: #666;
            font-style: italic;
        }

        .flash {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .flash-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .flash-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <?php if (!empty($flashMessages)): ?>
        <?php foreach ($flashMessages as $flash): ?>
            <div class="flash flash-<?php echo $flash['type']; ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h1>Uploads</h1>

    <section>
        <h2>Datei hochladen</h2>
        <form class="upload" action="<?php echo htmlspecialchars(BASE_URL . '/public/upload.php'); ?>" method="post" enctype="multipart/form-data">
            <div class="row">
                <input type="file" name="file" required>
            </div>
            <div class="row">
                <input type="text" name="folder" placeholder="optional: Unterordner, z. B. avatars">
            </div>
            <div class="row">
                <button type="submit">Hochladen</button>
            </div>
            <small>Erlaubte Typen gemäß Konfiguration. Maximale Größe: <?php echo humanSize(UPLOAD_MAX_BYTES); ?>.</small>
        </form>
    </section>

    <section class="files">
        <h2>Gespeicherte Dateien</h2>
        <?php if (empty($files)): ?>
            <p class="empty">Noch keine Dateien vorhanden.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Datei</th>
                        <th>Größe</th>
                        <th>Geändert</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $f):
                        $name = $f['name'];
                        $rel  = $f['relative'];
                        $ext  = $f['ext'];
                        $viewUrl = BASE_URL . '/public/file.php?name=' . rawurlencode($rel);
                        $downloadUrl = $viewUrl . '&download=1';
                        $inline = isInlineView($ext);
                    ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($rel); ?>
                                <span class="badge"><?php echo strtoupper($ext ?: ''); ?></span>
                            </td>
                            <td><?php echo humanSize((int)$f['size']); ?></td>
                            <td><?php echo date('Y-m-d H:i', (int)$f['mtime']); ?></td>
                            <td class="actions">
                                <?php if ($inline): ?>
                                    <a href="<?php echo htmlspecialchars($viewUrl); ?>" target="_blank" rel="noopener">Öffnen</a>
                                <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($downloadUrl); ?>">Download</a>
                                <?php endif; ?>
                                <!-- Optional: immer auch Download anbieten -->
                                <?php if ($inline): ?>
                                    <a href="<?php echo htmlspecialchars($downloadUrl); ?>">Download</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</body>

</html>