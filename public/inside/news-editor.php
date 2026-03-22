<?php

declare(strict_types=1);


error_reporting(E_ALL);
ini_set('display_errors', '1');

const DATA_DIR   = __DIR__ . '/news_data';
const UPLOAD_DIR = __DIR__ . '/uploads';

if (!is_dir(DATA_DIR)) {
  mkdir(DATA_DIR, 0775, true);
}

if (!is_dir(UPLOAD_DIR)) {
  mkdir(UPLOAD_DIR, 0775, true);
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizeSlug(string $slug): string
{
  $slug = mb_strtolower(trim($slug), 'UTF-8');
  $slug = strtr($slug, [
    'ä' => 'ae',
    'ö' => 'oe',
    'ü' => 'ue',
    'ß' => 'ss',
  ]);
  $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? '';
  $slug = trim($slug, '-');

  return $slug !== '' ? $slug : 'news-' . time();
}

function sanitizeHtml(string $html): string
{
  $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote><img>';
  $html = trim($html);
  $html = strip_tags($html, $allowedTags);

  $html = preg_replace('/\son\w+="[^"]*"/i', '', $html) ?? '';
  $html = preg_replace("/\son\w+='[^']*'/i", '', $html) ?? '';
  $html = preg_replace('/\sjavascript:/i', '', $html) ?? '';

  return $html;
}

function getPostFilePath(string $slug): string
{
  return DATA_DIR . '/' . $slug . '.json';
}

function savePost(array $post): bool
{
  $file = getPostFilePath($post['slug']);
  $json = json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  if ($json === false) {
    return false;
  }

  return file_put_contents($file, $json) !== false;
}

function loadPost(string $slug): ?array
{
  $file = getPostFilePath($slug);

  if (!is_file($file)) {
    return null;
  }

  $content = file_get_contents($file);
  if ($content === false) {
    return null;
  }

  $data = json_decode($content, true);
  return is_array($data) ? $data : null;
}

function loadAllPosts(): array
{
  $files = glob(DATA_DIR . '/*.json') ?: [];
  $posts = [];

  foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
      continue;
    }

    $data = json_decode($content, true);
    if (is_array($data)) {
      $posts[] = $data;
    }
  }

  usort($posts, static function (array $a, array $b): int {
    return strtotime($b['created_at'] ?? '1970-01-01 00:00:00')
      <=> strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
  });

  return $posts;
}

function deletePost(string $slug): bool
{
  $file = getPostFilePath($slug);
  return is_file($file) ? unlink($file) : false;
}

function getUploadImagesRecursive(): array
{
  $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'];
  $images = [];

  if (!is_dir(UPLOAD_DIR)) {
    return [];
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
      continue;
    }

    $ext = strtolower($fileInfo->getExtension());
    if (!in_array($ext, $allowedExtensions, true)) {
      continue;
    }

    $fullPath = $fileInfo->getPathname();
    $relativePath = str_replace('\\', '/', substr($fullPath, strlen(UPLOAD_DIR) + 1));

    $images[] = [
      'path' => $relativePath,
      'name' => $fileInfo->getFilename(),
      'dir'  => str_replace('\\', '/', dirname($relativePath)),
    ];
  }

  usort($images, static function (array $a, array $b): int {
    return strcmp($a['path'], $b['path']);
  });

  return $images;
}

function imageExistsInList(string $selectedPath, array $images): bool
{
  foreach ($images as $img) {
    if ($img['path'] === $selectedPath) {
      return true;
    }
  }
  return false;
}

function buildImageUrl(string $relativePath): string
{
  $parts = explode('/', str_replace('\\', '/', $relativePath));
  $parts = array_map('rawurlencode', $parts);
  return 'uploads/' . implode('/', $parts);
}

function redirect(string $url): void
{
  header('Location: ' . $url);
  exit;
}

$action  = $_GET['action'] ?? '';
$slugGet = isset($_GET['slug']) ? normalizeSlug((string) $_GET['slug']) : '';

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $originalSlug = normalizeSlug($_POST['original_slug'] ?? '');
  $headline     = trim((string) ($_POST['headline'] ?? ''));
  $preview      = trim((string) ($_POST['preview'] ?? ''));
  $slug         = normalizeSlug((string) ($_POST['slug'] ?? ''));
  $image        = trim((string) ($_POST['image'] ?? ''));
  $content      = sanitizeHtml((string) ($_POST['content'] ?? ''));

  if ($headline === '') {
    $error = 'Bitte eine Headline eingeben.';
  } elseif ($slug === '') {
    $error = 'Bitte einen gültigen Slug eingeben.';
  } else {
    $images = getUploadImagesRecursive();

    if ($image !== '' && !imageExistsInList($image, $images)) {
      $image = '';
    }

    $existing = loadPost($slug);

    if ($originalSlug !== '' && $originalSlug !== $slug && $existing !== null) {
      $error = 'Der neue Slug existiert bereits.';
    }

    if ($originalSlug === '' && $existing !== null) {
      $error = 'Dieser Slug existiert bereits.';
    }

    if ($error === '') {
      $oldPost = $originalSlug !== '' ? loadPost($originalSlug) : null;

      $post = [
        'headline'   => $headline,
        'preview'    => $preview,
        'slug'       => $slug,
        'image'      => $image,
        'content'    => $content,
        'created_at' => $oldPost['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
      ];

      if (savePost($post)) {
        if ($originalSlug !== '' && $originalSlug !== $slug) {
          $oldFile = getPostFilePath($originalSlug);
          if (is_file($oldFile)) {
            unlink($oldFile);
          }
        }

        redirect('news-editor.php?saved=1');
      } else {
        $error = 'Beitrag konnte nicht gespeichert werden.';
      }
    }
  }
}

if ($action === 'delete' && $slugGet !== '') {
  if (deletePost($slugGet)) {
    redirect('news-editor.php?deleted=1');
  } else {
    $error = 'Beitrag konnte nicht gelöscht werden.';
  }
}

if (isset($_GET['saved'])) {
  $message = 'Beitrag wurde gespeichert.';
}

if (isset($_GET['deleted'])) {
  $message = 'Beitrag wurde gelöscht.';
}

$formData = [
  'headline' => '',
  'preview'  => '',
  'slug'     => '',
  'image'    => '',
  'content'  => '<p>Hier steht dein Inhalt ...</p>',
];

if ($action === 'edit' && $slugGet !== '') {
  $post = loadPost($slugGet);
  if ($post !== null) {
    $formData = $post;
  } else {
    $error = 'Beitrag nicht gefunden.';
  }
}

$images   = getUploadImagesRecursive();
$allPosts = loadAllPosts();
?>
<!DOCTYPE html>
<html lang="de">

<head>
  <meta charset="UTF-8">
  <title>News Editor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f3f4f6;
      color: #222;
    }

    .wrap {
      max-width: 1200px;
      margin: 30px auto;
      padding: 20px;
    }

    .card {
      background: #fff;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 6px 25px rgba(0, 0, 0, 0.08);
      margin-bottom: 20px;
    }

    .topnav {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }

    .btn {
      display: inline-block;
      padding: 10px 14px;
      border-radius: 8px;
      text-decoration: none;
      border: none;
      cursor: pointer;
      background: #111827;
      color: #fff;
      font-size: 14px;
    }

    .btn.secondary {
      background: #4b5563;
    }

    .btn.danger {
      background: #b91c1c;
    }

    .btn.light {
      background: #e5e7eb;
      color: #111827;
    }

    .msg {
      padding: 12px 14px;
      border-radius: 8px;
      margin-bottom: 15px;
    }

    .msg.ok {
      background: #dcfce7;
      color: #166534;
    }

    .msg.err {
      background: #fee2e2;
      color: #991b1b;
    }

    label {
      display: block;
      font-weight: bold;
      margin-bottom: 8px;
    }

    input[type="text"],
    textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      margin-bottom: 15px;
      font-size: 14px;
      background: #fff;
    }

    textarea {
      min-height: 110px;
      resize: vertical;
    }

    .grid {
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 20px;
    }

    @media (max-width: 980px) {
      .grid {
        grid-template-columns: 1fr;
      }
    }

    .post-list {
      display: grid;
      gap: 18px;
    }

    .post-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      overflow: hidden;
      background: #fff;
    }

    .post-item img {
      width: 100%;
      max-height: 240px;
      object-fit: cover;
      display: block;
    }

    .post-body {
      padding: 16px;
    }

    .meta {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 8px;
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .editor-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 10px;
    }

    .editor-toolbar button {
      padding: 8px 10px;
      border: 1px solid #d1d5db;
      background: #f9fafb;
      border-radius: 8px;
      cursor: pointer;
    }

    .html-editor {
      min-height: 300px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 12px;
      background: #fff;
      margin-bottom: 15px;
      outline: none;
    }

    .small {
      font-size: 12px;
      color: #666;
    }

    .slug-box {
      background: #f9fafb;
      border: 1px dashed #d1d5db;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
    }

    .image-search-input {
      margin-bottom: 12px;
    }

    .image-picker {
      border: 1px solid #d1d5db;
      border-radius: 10px;
      padding: 10px;
      max-height: 520px;
      overflow-y: auto;
      background: #fafafa;
    }

    .image-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }

    .image-option {
      border: 1px solid #d1d5db;
      border-radius: 10px;
      background: #fff;
      padding: 8px;
      cursor: pointer;
      transition: 0.15s ease;
    }

    .image-option:hover {
      border-color: #9ca3af;
      transform: translateY(-1px);
    }

    .image-option.active {
      border: 2px solid #111827;
    }

    .image-option img {
      width: 100%;
      height: 110px;
      object-fit: cover;
      border-radius: 8px;
      display: block;
      margin-bottom: 8px;
      background: #f3f4f6;
    }

    .image-name {
      font-size: 12px;
      font-weight: bold;
      word-break: break-word;
      margin-bottom: 4px;
    }

    .image-path {
      font-size: 11px;
      color: #6b7280;
      word-break: break-word;
    }

    .selected-image-preview img {
      width: 100%;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      display: block;
    }

    .hidden {
      display: none !important;
    }
  </style>
</head>

<body>

  <div class="wrap">

    <div class="topnav">
      <a class="btn" href="news-editor.php">Admin Übersicht</a>
      <a class="btn secondary" href="news-editor.php?action=create">Neuen Beitrag erstellen</a>
      <a class="btn light" href="../news.php">Frontend ansehen</a>
    </div>

    <?php if ($message !== ''): ?>
      <div class="msg ok"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="msg err"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($action === 'create' || $action === 'edit'): ?>
      <div class="grid">
        <div class="card">
          <h1><?= $action === 'edit' ? 'Beitrag bearbeiten' : 'Neuen Beitrag erstellen' ?></h1>

          <form method="post" action="news-editor.php">
            <input type="hidden" name="original_slug" value="<?= e($action === 'edit' ? ($formData['slug'] ?? '') : '') ?>">
            <input type="hidden" name="image" id="selectedImageInput" value="<?= e($formData['image'] ?? '') ?>">

            <label for="headline">Headline</label>
            <input type="text" id="headline" name="headline" value="<?= e($formData['headline'] ?? '') ?>" required>

            <label for="preview">Preview-Text</label>
            <textarea id="preview" name="preview"><?= e($formData['preview'] ?? '') ?></textarea>

            <label for="slug">Slug</label>
            <input type="text" id="slug" name="slug" value="<?= e($formData['slug'] ?? '') ?>" required>
            <div class="slug-box small">
              Aufrufbar über:
              <strong>news.php?slug=<?= e($formData['slug'] ?? 'dein-slug') ?></strong>
            </div>

            <label for="imageSearch">Bild auswählen</label>
            <input type="text" id="imageSearch" class="image-search-input" placeholder="Bild suchen nach Dateiname oder Ordner ..." autocomplete="off">

            <div class="image-picker">
              <div style="margin-bottom:10px;">
                <button type="button" class="btn light" id="clearImageBtn">Kein Bild auswählen</button>
              </div>

              <div class="image-grid" id="imageGrid">
                <?php foreach ($images as $img): ?>
                  <?php
                  $path = $img['path'];
                  $url  = buildImageUrl($path);
                  $isActive = ($formData['image'] ?? '') === $path;
                  ?>
                  <div
                    class="image-option <?= $isActive ? 'active' : '' ?>"
                    data-path="<?= e($path) ?>"
                    data-search="<?= e(mb_strtolower($img['name'] . ' ' . $img['dir'] . ' ' . $img['path'], 'UTF-8')) ?>">
                    <img src="<?= e($url) ?>" alt="<?= e($img['name']) ?>">
                    <div class="image-name"><?= e($img['name']) ?></div>
                    <div class="image-path"><?= e($path) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <label style="margin-top:15px;">Text / HTML-Editor</label>
            <div class="editor-toolbar">
              <button type="button" onclick="formatCmd('bold')"><strong>B</strong></button>
              <button type="button" onclick="formatCmd('italic')"><em>I</em></button>
              <button type="button" onclick="formatCmd('underline')"><u>U</u></button>
              <button type="button" onclick="formatCmd('insertUnorderedList')">Liste</button>
              <button type="button" onclick="formatCmd('insertOrderedList')">Nummeriert</button>
              <button type="button" onclick="formatCmd('formatBlock','<h2>')">H2</button>
              <button type="button" onclick="formatCmd('formatBlock','<h3>')">H3</button>
              <button type="button" onclick="insertLink()">Link</button>
              <button type="button" onclick="formatCmd('removeFormat')">Format löschen</button>
            </div>

            <div id="editor" class="html-editor" contenteditable="true"><?= $formData['content'] ?? '' ?></div>
            <textarea id="content" name="content" style="display:none;"><?= e($formData['content'] ?? '') ?></textarea>

            <div class="actions">
              <button class="btn" type="submit">Speichern</button>
              <a class="btn secondary" href="news-editor.php">Zurück</a>
            </div>
          </form>
        </div>

        <div class="card">
          <h2>Ausgewähltes Bild</h2>
          <div class="selected-image-preview" id="selectedImagePreview">
            <?php if (!empty($formData['image'])): ?>
              <img src="<?= e(buildImageUrl($formData['image'])) ?>" alt="">
              <p class="small" style="margin-top:10px;"><?= e($formData['image']) ?></p>
            <?php else: ?>
              <div class="small">Kein Bild ausgewählt.</div>
            <?php endif; ?>
          </div>

          <hr style="margin:20px 0; border:none; border-top:1px solid #e5e7eb;">

          <h3>Speicherung</h3>
          <p class="small">
            Jeder Beitrag wird als <strong>JSON-Datei</strong> in
            <strong>inside/news_data/</strong> gespeichert.
          </p>
        </div>
      </div>
    <?php else: ?>
      <div class="card">
        <h1>Admin Übersicht</h1>
        <p>Hier kannst du Beiträge erstellen, bearbeiten und löschen.</p>
      </div>

      <div class="card">
        <h2>Vorhandene Beiträge</h2>

        <?php if (empty($allPosts)): ?>
          <p>Noch keine Beiträge vorhanden.</p>
        <?php else: ?>
          <div class="post-list">
            <?php foreach ($allPosts as $post): ?>
              <div class="post-item">
                <?php if (!empty($post['image'])): ?>
                  <img src="<?= e(buildImageUrl($post['image'])) ?>" alt="">
                <?php endif; ?>

                <div class="post-body">
                  <div class="meta">
                    Slug: <strong><?= e($post['slug'] ?? '') ?></strong><br>
                    Erstellt: <?= e($post['created_at'] ?? '') ?><br>
                    Aktualisiert: <?= e($post['updated_at'] ?? '') ?>
                  </div>

                  <h3><?= e($post['headline'] ?? '') ?></h3>
                  <p><?= nl2br(e($post['preview'] ?? '')) ?></p>

                  <div class="actions">
                    <a class="btn" href="news.php?slug=<?= e($post['slug'] ?? '') ?>" target="_blank">Ansehen</a>
                    <a class="btn secondary" href="news-editor.php?action=edit&slug=<?= e($post['slug'] ?? '') ?>">Bearbeiten</a>
                    <a class="btn danger" href="news-editor.php?action=delete&slug=<?= e($post['slug'] ?? '') ?>" onclick="return confirm('Beitrag wirklich löschen?');">Löschen</a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

  <script>
    const editor = document.getElementById('editor');
    const hiddenContent = document.getElementById('content');
    const headlineInput = document.getElementById('headline');
    const slugInput = document.getElementById('slug');

    const imageSearch = document.getElementById('imageSearch');
    const imageInput = document.getElementById('selectedImageInput');
    const selectedImagePreview = document.getElementById('selectedImagePreview');
    const clearImageBtn = document.getElementById('clearImageBtn');

    function syncEditor() {
      if (editor && hiddenContent) {
        hiddenContent.value = editor.innerHTML;
      }
    }

    function formatCmd(command, value = null) {
      document.execCommand(command, false, value);
      syncEditor();
    }

    function insertLink() {
      const url = prompt('Bitte URL eingeben:', 'https://');
      if (url) {
        document.execCommand('createLink', false, url);
        syncEditor();
      }
    }

    function escapeHtml(str) {
      return str
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function setActiveImage(path, imgSrc) {
      if (!imageInput) return;

      imageInput.value = path;

      document.querySelectorAll('.image-option').forEach(el => {
        el.classList.toggle('active', el.dataset.path === path);
      });

      if (!selectedImagePreview) return;

      if (!path) {
        selectedImagePreview.innerHTML = '<div class="small">Kein Bild ausgewählt.</div>';
        return;
      }

      selectedImagePreview.innerHTML = `
            <img src="${imgSrc}" alt="">
            <p class="small" style="margin-top:10px;">${escapeHtml(path)}</p>
        `;
    }

    if (editor) {
      editor.addEventListener('input', syncEditor);
    }

    const form = document.querySelector('form');
    if (form) {
      form.addEventListener('submit', syncEditor);
    }

    document.querySelectorAll('.image-option').forEach(el => {
      el.addEventListener('click', function() {
        const path = this.dataset.path;
        const img = this.querySelector('img');
        setActiveImage(path, img ? img.src : '');
      });
    });

    if (clearImageBtn) {
      clearImageBtn.addEventListener('click', function() {
        setActiveImage('', '');
      });
    }

    if (imageSearch) {
      imageSearch.addEventListener('input', function() {
        const term = this.value.trim().toLowerCase();

        document.querySelectorAll('.image-option').forEach(el => {
          const haystack = (el.dataset.search || '').toLowerCase();
          const match = term === '' || haystack.includes(term);
          el.classList.toggle('hidden', !match);
        });
      });
    }

    if (headlineInput && slugInput) {
      headlineInput.addEventListener('blur', function() {
        if (slugInput.value.trim() !== '') return;

        let slug = this.value.toLowerCase()
          .replace(/ä/g, 'ae')
          .replace(/ö/g, 'oe')
          .replace(/ü/g, 'ue')
          .replace(/ß/g, 'ss')
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');

        slugInput.value = slug;
      });
    }

    syncEditor();
  </script>
</body>

</html>