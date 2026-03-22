<?php
require_once 'auth.php';
requireLogin();

$uploadDir = __DIR__ . '/uploads';
$uploadUrl = 'uploads';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$message = '';
$error = '';

$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
$videoExtensions = ['mp4', 'webm', 'ogg'];
$allowedExtensions = array_merge($imageExtensions, $videoExtensions);

$currentFolder = $_GET['folder'] ?? '';
$currentFolder = trim($currentFolder);
$currentFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $currentFolder);

$filter = $_GET['filter'] ?? 'all';
$validFilters = ['all', 'image', 'video'];
if (!in_array($filter, $validFilters, true)) {
    $filter = 'all';
}

function detectType(string $filename, array $imageExtensions, array $videoExtensions): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $imageExtensions, true)) {
        return 'image';
    }

    if (in_array($ext, $videoExtensions, true)) {
        return 'video';
    }

    return null;
}

function createSafeFileName(string $originalName, string $targetDir): string
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = pathinfo($originalName, PATHINFO_FILENAME);

    $base = preg_replace('/[^a-zA-Z0-9_-]/', '-', $base);
    $base = trim($base, '-_');

    if ($base === '') {
        $base = 'datei';
    }

    $candidate = $base . '.' . $ext;
    $i = 1;

    while (file_exists($targetDir . '/' . $candidate)) {
        $candidate = $base . '-' . $i . '.' . $ext;
        $i++;
    }

    return $candidate;
}

function createSafeFolderName(string $name, string $rootDir): string
{
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '-', trim($name));
    $name = trim($name, '-_');

    if ($name === '') {
        $name = 'ordner';
    }

    $candidate = $name;
    $i = 1;

    while (is_dir($rootDir . '/' . $candidate)) {
        $candidate = $name . '-' . $i;
        $i++;
    }

    return $candidate;
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

function folderPath(string $uploadDir, string $folder): string
{
    return $folder === '' ? $uploadDir : $uploadDir . '/' . $folder;
}

function folderUrl(string $uploadUrl, string $folder, string $file): string
{
    if ($folder === '') {
        return $uploadUrl . '/' . rawurlencode($file);
    }

    return $uploadUrl . '/' . rawurlencode($folder) . '/' . rawurlencode($file);
}

function redirectToCurrent(string $folder, string $filter, string $msg = ''): void
{
    $url = 'media.php?filter=' . urlencode($filter) . '&folder=' . urlencode($folder);
    if ($msg !== '') {
        $url .= '&msg=' . urlencode($msg);
    }
    header('Location: ' . $url);
    exit;
}

function deleteDirectoryRecursive(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }

    $items = array_diff(scandir($dir), ['.', '..']);

    foreach ($items as $item) {
        $path = $dir . '/' . $item;

        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

$allFolders = [];
foreach (array_diff(scandir($uploadDir), ['.', '..']) as $entry) {
    if (is_dir($uploadDir . '/' . $entry) && preg_match('/^[a-zA-Z0-9_-]+$/', $entry)) {
        $allFolders[] = $entry;
    }
}
sort($allFolders, SORT_NATURAL | SORT_FLAG_CASE);

if ($currentFolder !== '' && !in_array($currentFolder, $allFolders, true)) {
    $currentFolder = '';
}

$currentDir = folderPath($uploadDir, $currentFolder);

/* Ordner anlegen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $folderName = trim($_POST['folder_name'] ?? '');

    if ($folderName === '') {
        $error = 'Bitte einen Ordnernamen angeben.';
    } else {
        $safeFolder = createSafeFolderName($folderName, $uploadDir);
        $newFolderPath = $uploadDir . '/' . $safeFolder;

        if (mkdir($newFolderPath, 0777, true)) {
            redirectToCurrent($safeFolder, $filter, 'Ordner erstellt.');
        } else {
            $error = 'Ordner konnte nicht erstellt werden.';
        }
    }
}

/* Ordner löschen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_folder_name'], $_POST['delete_folder_mode'])) {
    $folderName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['delete_folder_name']);
    $mode = $_POST['delete_folder_mode'];

    $folderDir = $uploadDir . '/' . $folderName;

    if ($folderName === '' || !is_dir($folderDir)) {
        $error = 'Ordner nicht gefunden.';
    } else {
        if ($mode === 'delete_all') {
            if (deleteDirectoryRecursive($folderDir)) {
                redirectToCurrent('', $filter, 'Ordner und Inhalte gelöscht.');
            } else {
                $error = 'Ordner konnte nicht gelöscht werden.';
            }
        } elseif ($mode === 'move_to_root') {
            $entries = array_diff(scandir($folderDir), ['.', '..']);

            foreach ($entries as $entry) {
                $source = $folderDir . '/' . $entry;

                if (is_file($source)) {
                    $newName = createSafeFileName($entry, $uploadDir);
                    rename($source, $uploadDir . '/' . $newName);
                }
            }

            if (rmdir($folderDir)) {
                redirectToCurrent('', $filter, 'Ordner gelöscht, Inhalte in den Hauptordner verschoben.');
            } else {
                $error = 'Ordner konnte nicht gelöscht werden.';
            }
        }
    }
}

/* Upload */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['media'])) {
    $uploadedCount = 0;
    $failedCount = 0;

    if (is_array($_FILES['media']['name'])) {
        $total = count($_FILES['media']['name']);

        for ($i = 0; $i < $total; $i++) {
            $name = $_FILES['media']['name'][$i] ?? '';
            $tmpName = $_FILES['media']['tmp_name'][$i] ?? '';
            $errorCode = $_FILES['media']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

            if ($errorCode !== UPLOAD_ERR_OK) {
                $failedCount++;
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExtensions, true)) {
                $failedCount++;
                continue;
            }

            $safeName = createSafeFileName(basename($name), $currentDir);
            $targetFile = $currentDir . '/' . $safeName;

            if (move_uploaded_file($tmpName, $targetFile)) {
                $uploadedCount++;
            } else {
                $failedCount++;
            }
        }

        if ($uploadedCount > 0 && $failedCount === 0) {
            redirectToCurrent($currentFolder, $filter, $uploadedCount . ' Datei(en) erfolgreich hochgeladen.');
        }

        if ($uploadedCount > 0 || $failedCount > 0) {
            $message = $uploadedCount . ' Datei(en) hochgeladen.';
            if ($failedCount > 0) {
                $error = $failedCount . ' Datei(en) konnten nicht hochgeladen werden.';
            }
        }
    }
}

/* Umbenennen */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_old'], $_POST['rename_new'])) {
    $oldName = basename($_POST['rename_old']);
    $newBase = trim($_POST['rename_new']);
    $oldPath = $currentDir . '/' . $oldName;

    if (!is_file($oldPath)) {
        $error = 'Datei zum Umbenennen nicht gefunden.';
    } else {
        $ext = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));
        $newBase = preg_replace('/[^a-zA-Z0-9_-]/', '-', $newBase);
        $newBase = trim($newBase, '-_');

        if ($newBase === '') {
            $error = 'Bitte einen gültigen Dateinamen angeben.';
        } else {
            $newName = $newBase . '.' . $ext;
            $newPath = $currentDir . '/' . $newName;

            if ($newName === $oldName) {
                $message = 'Dateiname unverändert.';
            } elseif (file_exists($newPath)) {
                $error = 'Eine Datei mit diesem Namen existiert bereits.';
            } elseif (rename($oldPath, $newPath)) {
                redirectToCurrent($currentFolder, $filter, 'Datei erfolgreich umbenannt.');
            } else {
                $error = 'Datei konnte nicht umbenannt werden.';
            }
        }
    }
}

/* Einzelnes Medium verschieben */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_file'], $_POST['move_target_folder'])) {
    $fileName = basename($_POST['move_file']);
    $targetFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['move_target_folder']);
    $sourcePath = $currentDir . '/' . $fileName;

    if (!is_file($sourcePath)) {
        $error = 'Datei nicht gefunden.';
    } else {
        $targetDir = folderPath($uploadDir, $targetFolder);

        if (!is_dir($targetDir)) {
            $error = 'Zielordner nicht gefunden.';
        } else {
            $newName = createSafeFileName($fileName, $targetDir);

            if (rename($sourcePath, $targetDir . '/' . $newName)) {
                redirectToCurrent($currentFolder, $filter, 'Datei verschoben.');
            } else {
                $error = 'Datei konnte nicht verschoben werden.';
            }
        }
    }
}

/* Bulk Move */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_move'], $_POST['selected_files'], $_POST['bulk_target_folder']) && is_array($_POST['selected_files'])) {
    $targetFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['bulk_target_folder']);
    $targetDir = folderPath($uploadDir, $targetFolder);
    $movedCount = 0;

    if (!is_dir($targetDir)) {
        $error = 'Zielordner nicht gefunden.';
    } else {
        foreach ($_POST['selected_files'] as $selectedFile) {
            $file = basename($selectedFile);
            $sourcePath = $currentDir . '/' . $file;

            if (is_file($sourcePath)) {
                $newName = createSafeFileName($file, $targetDir);
                if (rename($sourcePath, $targetDir . '/' . $newName)) {
                    $movedCount++;
                }
            }
        }

        redirectToCurrent($currentFolder, $filter, $movedCount . ' Datei(en) verschoben.');
    }
}

/* Drag & Drop Move */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drag_move'], $_POST['move_file'], $_POST['move_target_folder'])) {
    $fileName = basename($_POST['move_file']);
    $targetFolder = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['move_target_folder']);
    $sourcePath = $currentDir . '/' . $fileName;

    if (!is_file($sourcePath)) {
        $error = 'Datei nicht gefunden.';
    } else {
        $targetDir = folderPath($uploadDir, $targetFolder);

        if (!is_dir($targetDir)) {
            $error = 'Zielordner nicht gefunden.';
        } else {
            $newName = createSafeFileName($fileName, $targetDir);

            if (rename($sourcePath, $targetDir . '/' . $newName)) {
                redirectToCurrent($currentFolder, $filter, 'Datei per Drag & Drop verschoben.');
            } else {
                $error = 'Datei konnte nicht verschoben werden.';
            }
        }
    }
}

/* Bulk Delete */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'], $_POST['selected_files']) && is_array($_POST['selected_files'])) {
    $deletedCount = 0;

    foreach ($_POST['selected_files'] as $selectedFile) {
        $file = basename($selectedFile);
        $filePath = $currentDir . '/' . $file;

        if (is_file($filePath) && unlink($filePath)) {
            $deletedCount++;
        }
    }

    redirectToCurrent($currentFolder, $filter, $deletedCount . ' Datei(en) gelöscht.');
}

/* Einzeln löschen */
if (isset($_GET['delete'])) {
    $file = basename($_GET['delete']);
    $filePath = $currentDir . '/' . $file;

    if (is_file($filePath)) {
        unlink($filePath);
    }

    redirectToCurrent($currentFolder, $filter, 'Datei gelöscht.');
}

if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = $_GET['msg'];
}

/* Dateien lesen */
$items = [];
$entries = array_diff(scandir($currentDir), ['.', '..']);

foreach ($entries as $entry) {
    $path = $currentDir . '/' . $entry;

    if (!is_file($path)) {
        continue;
    }

    $type = detectType($entry, $imageExtensions, $videoExtensions);

    if ($type === null) {
        continue;
    }

    if ($filter !== 'all' && $filter !== $type) {
        continue;
    }

    $items[] = [
        'name' => $entry,
        'type' => $type,
        'url' => folderUrl($uploadUrl, $currentFolder, $entry),
        'base' => pathinfo($entry, PATHINFO_FILENAME),
        'mtime' => filemtime($path),
        'size' => filesize($path),
    ];
}

usort($items, function ($a, $b) {
    return $b['mtime'] <=> $a['mtime'];
});

function activeFilterClass(string $current, string $expected): string
{
    return $current === $expected ? 'chip is-active' : 'chip';
}

function activeFolderClass(string $current, string $expected): string
{
    return $current === $expected ? 'folder-link is-active' : 'folder-link';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medienverwaltung</title>
    <link rel="stylesheet" href="./../css/media.css">
    <style>
        .upload-progress {
            display: none;
            margin-top: 12px;
            width: 100%;
        }

        .upload-progress.is-visible {
            display: block;
        }

        .upload-progress-bar {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            overflow: hidden;
            background: #e5e7eb;
            margin-top: 8px;
        }

        .upload-progress-fill {
            height: 100%;
            width: 0%;
            border-radius: 999px;
            background: #2563eb;
            transition: width 0.2s ease;
        }

        .upload-progress-text {
            font-size: 14px;
            color: #374151;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="title-block">
            <h1>Medienverwaltung</h1>
            <p>Mit Ordnern, Verschieben, Bulk-Aktionen und Drag & Drop.</p>
        </div>

        <a href="logout.php" class="button button-secondary">Logout</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success panel"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error panel"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="layout">
        <aside class="sidebar panel">
            <h3>Ordner</h3>

            <div class="folder-list">
                <a
                    class="<?= activeFolderClass($currentFolder, '') ?>"
                    href="?folder=&filter=<?= urlencode($filter) ?>"
                    data-folder=""
                >
                    <span>Hauptordner</span>
                </a>

                <?php foreach ($allFolders as $folder): ?>
                    <a
                        class="<?= activeFolderClass($currentFolder, $folder) ?>"
                        href="?folder=<?= urlencode($folder) ?>&filter=<?= urlencode($filter) ?>"
                        data-folder="<?= htmlspecialchars($folder) ?>"
                    >
                        <span><?= htmlspecialchars($folder) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="post" class="folder-inline">
                <input type="hidden" name="create_folder" value="1">
                <input type="text" name="folder_name" placeholder="Neuer Ordner">
                <button type="submit" class="button button-primary">Anlegen</button>
            </form>

            <?php if ($currentFolder !== ''): ?>
                <div class="folder-actions">
                    <button type="button" class="button button-danger" onclick="openDeleteFolderModal('<?= htmlspecialchars($currentFolder) ?>')">
                        Ordner löschen
                    </button>
                </div>
            <?php endif; ?>
        </aside>

        <main class="content">
            <div class="folder-header panel">
                <div>
                    <h2><?= $currentFolder === '' ? 'Hauptordner' : 'Ordner: ' . htmlspecialchars($currentFolder) ?></h2>
                    <p>Uploads landen immer im aktuell geöffneten Ordner.</p>
                </div>
            </div>

            <div class="toolbar panel">
                <div class="toolbar-left">
                    <form id="uploadForm" method="post" enctype="multipart/form-data">
                        <label class="upload-label">
                            Medien auswählen
                            <input id="mediaInput" type="file" name="media[]" accept="image/*,video/*" multiple>
                        </label>

                        <div class="upload-progress" id="uploadProgress">
                            <div class="upload-progress-text" id="uploadProgressText">Upload startet ...</div>
                            <div class="upload-progress-bar">
                                <div class="upload-progress-fill" id="uploadProgressFill"></div>
                            </div>
                        </div>
                    </form>

                    <div class="chips">
                        <a class="<?= activeFilterClass($filter, 'all') ?>" href="?folder=<?= urlencode($currentFolder) ?>&filter=all">Alle</a>
                        <a class="<?= activeFilterClass($filter, 'image') ?>" href="?folder=<?= urlencode($currentFolder) ?>&filter=image">Bilder</a>
                        <a class="<?= activeFilterClass($filter, 'video') ?>" href="?folder=<?= urlencode($currentFolder) ?>&filter=video">Videos</a>
                    </div>
                </div>

                <div class="toolbar-right">
                    <button type="button" class="button button-secondary" id="selectAllBtn">Alle markieren</button>
                    <button type="button" class="button button-secondary" id="clearSelectionBtn">Auswahl aufheben</button>
                </div>
            </div>

            <form method="post" id="bulkActionForm">
                <div class="bulkbar panel" id="bulkbar">
                    <div>
                        <strong id="selectedCount">0</strong> ausgewählt
                        <div class="bulk-meta">Mehrere Dateien gleichzeitig verschieben oder löschen.</div>
                    </div>

                    <div class="bulk-controls">
                        <select name="bulk_target_folder" id="bulkTargetFolder">
                            <option value="">In Hauptordner verschieben</option>
                            <?php foreach ($allFolders as $folder): ?>
                                <?php if ($folder !== $currentFolder): ?>
                                    <option value="<?= htmlspecialchars($folder) ?>"><?= htmlspecialchars($folder) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" name="bulk_move" value="1" class="button button-secondary">Auswahl verschieben</button>
                        <button type="button" class="button button-danger" id="bulkDeleteBtn">Ausgewählte löschen</button>
                    </div>
                </div>

                <?php if (empty($items)): ?>
                    <div class="panel empty">Keine Medien vorhanden.</div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($items as $index => $item): ?>
                            <div
                                class="media-card panel"
                                draggable="true"
                                data-file-name="<?= htmlspecialchars($item['name']) ?>"
                            >
                                <input
                                    class="select-box"
                                    type="checkbox"
                                    name="selected_files[]"
                                    value="<?= htmlspecialchars($item['name']) ?>"
                                >

                                <div class="type-badge">
                                    <?= $item['type'] === 'image' ? 'Bild' : 'Video' ?>
                                </div>

                                <div class="preview-wrap">
                                    <?php if ($item['type'] === 'image'): ?>
                                        <img
                                            class="preview"
                                            src="<?= htmlspecialchars($item['url']) ?>"
                                            alt="<?= htmlspecialchars($item['name']) ?>"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <video class="preview" controls preload="metadata">
                                            <source src="<?= htmlspecialchars($item['url']) ?>">
                                        </video>
                                    <?php endif; ?>
                                </div>

                                <div class="media-body">
                                    <p class="file-name"><?= htmlspecialchars($item['name']) ?></p>

                                    <div class="file-meta">
                                        <span><?= formatBytes((int)$item['size']) ?></span>
                                        <span><?= date('d.m.Y H:i', $item['mtime']) ?></span>
                                    </div>

                                    <div class="actions">
                                        <a class="icon-btn" href="<?= htmlspecialchars($item['url']) ?>" target="_blank" title="Öffnen">
                                            ↗
                                        </a>

                                        <button
                                            type="button"
                                            class="icon-btn"
                                            title="Umbenennen"
                                            data-toggle-box="renameBox<?= $index ?>"
                                            data-group-class="rename-box"
                                        >✎</button>

                                        <button
                                            type="button"
                                            class="icon-btn"
                                            title="Verschieben"
                                            data-toggle-box="moveBox<?= $index ?>"
                                            data-group-class="move-box"
                                        >⇄</button>

                                        <a
                                            class="icon-btn"
                                            href="?folder=<?= urlencode($currentFolder) ?>&filter=<?= urlencode($filter) ?>&delete=<?= rawurlencode($item['name']) ?>"
                                            title="Löschen"
                                            onclick="return confirm('Diese Datei wirklich löschen?')"
                                        >🗑</a>
                                    </div>

                                    <form method="post" class="rename-box" id="renameBox<?= $index ?>">
                                        <input type="hidden" name="rename_old" value="<?= htmlspecialchars($item['name']) ?>">
                                        <input type="text" name="rename_new" value="<?= htmlspecialchars($item['base']) ?>" placeholder="Neuer Dateiname">
                                        <button type="submit" class="button button-primary">Speichern</button>
                                    </form>

                                    <form method="post" class="move-box" id="moveBox<?= $index ?>">
                                        <input type="hidden" name="move_file" value="<?= htmlspecialchars($item['name']) ?>">
                                        <select name="move_target_folder" required>
                                            <option value="">Hauptordner</option>
                                            <?php foreach ($allFolders as $folder): ?>
                                                <?php if ($folder !== $currentFolder): ?>
                                                    <option value="<?= htmlspecialchars($folder) ?>"><?= htmlspecialchars($folder) ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="button button-secondary">Verschieben</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>

            <form method="post" id="dragMoveForm" class="hidden-form">
                <input type="hidden" name="drag_move" value="1">
                <input type="hidden" name="move_file" id="dragMoveFile" value="">
                <input type="hidden" name="move_target_folder" id="dragMoveTargetFolder" value="">
            </form>
        </main>
    </div>
</div>

<div class="modal-backdrop" id="deleteFolderModal">
    <div class="modal panel">
        <h3>Ordner löschen?</h3>
        <p>
            Soll der Ordner samt Medien gelöscht werden?<br>
            <strong>Ja</strong> = alles löschen<br>
            <strong>Nein</strong> = Medien in den Hauptordner verschieben
        </p>

        <form method="post" id="deleteFolderForm">
            <input type="hidden" name="delete_folder_name" id="deleteFolderName" value="">
            <input type="hidden" name="delete_folder_mode" id="deleteFolderMode" value="">

            <div class="modal-actions">
                <button type="button" class="button button-danger" data-folder-delete-mode="delete_all">Ja, alles löschen</button>
                <button type="button" class="button button-secondary" data-folder-delete-mode="move_to_root">Nein, verschieben</button>
                <button type="button" class="button button-secondary" data-close-folder-modal="1">Abbrechen</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.MEDIA_APP = {
        currentFolder: <?= json_encode($currentFolder, JSON_UNESCAPED_UNICODE) ?>
    };
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const uploadForm = document.getElementById('uploadForm');
    const mediaInput = document.getElementById('mediaInput');
    const progressBox = document.getElementById('uploadProgress');
    const progressFill = document.getElementById('uploadProgressFill');
    const progressText = document.getElementById('uploadProgressText');

    if (!uploadForm || !mediaInput || !progressBox || !progressFill || !progressText) {
        return;
    }

    let isUploading = false;

    mediaInput.addEventListener('change', function () {
        if (!mediaInput.files || mediaInput.files.length === 0 || isUploading) {
            return;
        }

        const formData = new FormData(uploadForm);
        const xhr = new XMLHttpRequest();

        isUploading = true;
        progressBox.classList.add('is-visible');
        progressFill.style.width = '0%';
        progressText.textContent = 'Upload startet ...';

        xhr.open('POST', window.location.href, true);

        xhr.upload.addEventListener('progress', function (event) {
            if (!event.lengthComputable) {
                progressText.textContent = 'Dateien werden hochgeladen ...';
                return;
            }

            const percent = Math.round((event.loaded / event.total) * 100);
            progressFill.style.width = percent + '%';
            progressText.textContent = 'Upload läuft: ' + percent + '%';
        });

        xhr.addEventListener('load', function () {
            isUploading = false;

            if (xhr.status >= 200 && xhr.status < 300) {
                progressFill.style.width = '100%';
                progressText.textContent = 'Upload abgeschlossen.';
                window.location.reload();
            } else {
                progressText.textContent = 'Upload fehlgeschlagen.';
            }
        });

        xhr.addEventListener('error', function () {
            isUploading = false;
            progressText.textContent = 'Upload fehlgeschlagen.';
        });

        xhr.addEventListener('abort', function () {
            isUploading = false;
            progressText.textContent = 'Upload abgebrochen.';
        });

        xhr.send(formData);
    });
});
</script>

<script src="./../js/media.js"></script>
</body>
</html>