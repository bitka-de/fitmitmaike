<?php

declare(strict_types=1);

require_once 'auth.php';

if (!isSecure()) {
  header('Location: index.php');
  exit;
}

const KURS_DATA_DIR = __DIR__ . '/kurs_data';

if (!is_dir(KURS_DATA_DIR)) {
  mkdir(KURS_DATA_DIR, 0775, true);
}

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizeKursSlug(string $slug): string
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

  return $slug !== '' ? $slug : 'kurs-' . time();
}

function getKursFilePath(string $slug): string
{
  return KURS_DATA_DIR . '/' . $slug . '.json';
}

function saveKurs(array $kurs): bool
{
  $file = getKursFilePath($kurs['slug']);
  $json = json_encode($kurs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  if ($json === false) {
    return false;
  }

  return file_put_contents($file, $json) !== false;
}

function loadKurs(string $slug): ?array
{
  $file = getKursFilePath($slug);

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

function loadAllKurse(): array
{
  $files = glob(KURS_DATA_DIR . '/*.json') ?: [];
  $kurse = [];

  foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
      continue;
    }

    $data = json_decode($content, true);
    if (is_array($data)) {
      $kurse[] = $data;
    }
  }

  usort($kurse, static function (array $a, array $b): int {
    return strtotime($b['created_at'] ?? '1970-01-01 00:00:00')
      <=> strtotime($a['created_at'] ?? '1970-01-01 00:00:00');
  });

  return $kurse;
}

function deleteKurs(string $slug): bool
{
  $file = getKursFilePath($slug);
  return is_file($file) ? unlink($file) : false;
}

function listAvailableMediaImages(): array
{
  $uploadDir = __DIR__ . '/uploads';
  if (!is_dir($uploadDir)) {
    return [];
  }

  $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
  $images = [];

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
      continue;
    }

    $ext = strtolower($fileInfo->getExtension());
    if (!in_array($ext, $allowedExt, true)) {
      continue;
    }

    $fullPath = $fileInfo->getPathname();
    $relativePath = str_replace('\\', '/', substr($fullPath, strlen(__DIR__) + 1));
    $displayPath = str_replace('uploads/', '', $relativePath);
    $images[$relativePath] = $displayPath;
  }

  asort($images, SORT_NATURAL | SORT_FLAG_CASE);

  return $images;
}

function isValidTimeInput(string $time): bool
{
  return (bool) preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time);
}

function isValidDateInput(string $date): bool
{
  $dt = DateTime::createFromFormat('Y-m-d', $date);
  return $dt !== false && $dt->format('Y-m-d') === $date;
}

function weekdayNameFromDate(string $date): string
{
  $dt = DateTime::createFromFormat('Y-m-d', $date);
  if ($dt === false) {
    return '';
  }

  $weekdays = [
    1 => 'Montag',
    2 => 'Dienstag',
    3 => 'Mittwoch',
    4 => 'Donnerstag',
    5 => 'Freitag',
    6 => 'Samstag',
    7 => 'Sonntag',
  ];

  return $weekdays[(int) $dt->format('N')] ?? '';
}

function redirect(string $url): void
{
  header('Location: ' . $url);
  exit;
}

$action  = $_GET['action'] ?? '';
$slugGet = isset($_GET['slug']) ? normalizeKursSlug((string) $_GET['slug']) : '';

$availableMediaImages = listAvailableMediaImages();

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $originalSlug   = normalizeKursSlug($_POST['original_slug'] ?? '');
  $name           = trim((string) ($_POST['name'] ?? ''));
  $beschreibung   = trim((string) ($_POST['beschreibung'] ?? ''));
  $slug           = normalizeKursSlug((string) ($_POST['slug'] ?? ''));
  $termineInput   = (array) ($_POST['termine'] ?? []);
  $termineDatum   = array_map('trim', (array) ($termineInput['datum'] ?? []));
  $termineVon     = array_map('trim', (array) ($termineInput['von'] ?? []));
  $termineBis     = array_map('trim', (array) ($termineInput['bis'] ?? []));
  $niveau         = trim((string) ($_POST['niveau'] ?? ''));
  $max_teilnehmer = trim((string) ($_POST['max_teilnehmer'] ?? ''));
  $ort            = trim((string) ($_POST['ort'] ?? ''));
  $preis          = trim((string) ($_POST['preis'] ?? ''));
  $bild           = trim((string) ($_POST['bild'] ?? ''));

  $allowedNiveaus = ['Alle Levels', 'Anfänger', 'Fortgeschrittene', 'Experten'];
  $termine = [];

  $rowCount = max(count($termineDatum), count($termineVon), count($termineBis));
  for ($i = 0; $i < $rowCount; $i++) {
    $datum = $termineDatum[$i] ?? '';
    $von = $termineVon[$i] ?? '';
    $bis = $termineBis[$i] ?? '';

    if ($datum === '' && $von === '' && $bis === '') {
      continue;
    }

    if (!isValidDateInput($datum) || !isValidTimeInput($von) || !isValidTimeInput($bis) || $von >= $bis) {
      continue;
    }

    $termine[] = [
      'datum' => $datum,
      'von' => $von,
      'bis' => $bis,
    ];
  }

  usort($termine, static fn(array $a, array $b): int => strcmp((string) $a['datum'], (string) $b['datum']));

  $wochentage = array_values(array_unique(array_filter(array_map(
    static fn(array $t): string => weekdayNameFromDate((string) ($t['datum'] ?? '')),
    $termine
  ))));

  if ($bild !== '' && !array_key_exists($bild, $availableMediaImages)) {
    $bild = '';
  }

  if (!in_array($niveau, $allowedNiveaus, true)) {
    $niveau = '';
  }

  if ($name === '') {
    $error = 'Bitte einen Kursnamen eingeben.';
  } elseif ($slug === '') {
    $error = 'Bitte einen gültigen Slug eingeben.';
  } elseif (empty($termine)) {
    $error = 'Bitte mindestens ein Datum mit von/bis-Zeit angeben.';
  } elseif ($preis === '') {
    $error = 'Bitte einen Preis angeben.';
  } elseif ($max_teilnehmer === '' || filter_var($max_teilnehmer, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
    $error = 'Bitte eine gültige maximale Kursgröße angeben.';
  } else {
    $existing = loadKurs($slug);

    if ($originalSlug !== '' && $originalSlug !== $slug && $existing !== null) {
      $error = 'Der neue Slug existiert bereits.';
    }

    if ($originalSlug === '' && $existing !== null) {
      $error = 'Dieser Slug existiert bereits.';
    }

    if ($error === '') {
      $oldKurs = $originalSlug !== '' ? loadKurs($originalSlug) : null;

      $kurs = [
        'name'           => $name,
        'beschreibung'   => $beschreibung,
        'slug'           => $slug,
        'wochentage'     => $wochentage,
        'termine'        => $termine,
        'niveau'         => $niveau,
        'max_teilnehmer' => $max_teilnehmer,
        'ort'            => $ort,
        'preis'          => $preis,
        'bild'           => $bild,
        'created_at'     => $oldKurs['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at'     => date('Y-m-d H:i:s'),
      ];

      if (saveKurs($kurs)) {
        if ($originalSlug !== '' && $originalSlug !== $slug) {
          $oldFile = getKursFilePath($originalSlug);
          if (is_file($oldFile)) {
            unlink($oldFile);
          }
        }

        redirect('kurs-editor.php?saved=1');
      } else {
        $error = 'Kurs konnte nicht gespeichert werden.';
      }
    }
  }
}

if ($action === 'delete' && $slugGet !== '') {
  if (deleteKurs($slugGet)) {
    redirect('kurs-editor.php?deleted=1');
  } else {
    $error = 'Kurs konnte nicht gelöscht werden.';
  }
}

if (isset($_GET['saved'])) {
  $message = 'Kurs wurde gespeichert.';
}

if (isset($_GET['deleted'])) {
  $message = 'Kurs wurde gelöscht.';
}

$formData = [
  'name'           => '',
  'beschreibung'   => '',
  'slug'           => '',
  'wochentage'     => [],
  'termine'        => [],
  'niveau'         => '',
  'max_teilnehmer' => '',
  'ort'            => '',
  'preis'          => '',
  'bild'           => '',
];

if ($action === 'edit' && $slugGet !== '') {
  $kurs = loadKurs($slugGet);
  if ($kurs !== null) {
    if (!isset($kurs['termine']) || !is_array($kurs['termine'])) {
      $kurs['termine'] = [];
    }
    $formData = $kurs;
  } else {
    $error = 'Kurs nicht gefunden.';
  }
}

$allKurse = loadAllKurse();
?>
<!DOCTYPE html>
<html lang="de">

<head>
  <meta charset="UTF-8">
  <title>Kurs-Editor</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://api.fontshare.com/v2/css?f[]=switzer@400,500,600,700&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
    }

    :root {
      --bg: #f1f5f9;
      --card-bg: #ffffff;
      --text: #0f172a;
      --muted: #64748b;
      --line: #dbe3ee;
      --brand: #0f172a;
      --brand-soft: #1e293b;
      --accent: #3b82f6;
      --success: #166534;
      --success-bg: #dcfce7;
      --danger: #991b1b;
      --danger-bg: #fee2e2;
      --radius: 14px;
      --shadow: 0 18px 48px rgba(15, 23, 42, 0.08);
    }

    body {
      margin: 0;
      font-family: "Switzer", Inter, Arial, sans-serif;
      background:
        radial-gradient(circle at 8% 2%, rgba(59, 130, 246, 0.12), transparent 35%),
        radial-gradient(circle at 92% 0%, rgba(15, 23, 42, 0.08), transparent 30%),
        var(--bg);
      color: var(--text);
    }

    .wrap {
      max-width: 1060px;
      margin: 34px auto;
      padding: 20px;
    }

    .card {
      background: var(--card-bg);
      border-radius: var(--radius);
      border: 1px solid var(--line);
      padding: 22px;
      box-shadow: var(--shadow);
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
      border-radius: 10px;
      text-decoration: none;
      border: none;
      cursor: pointer;
      background: linear-gradient(180deg, #1f2937, #111827);
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      letter-spacing: 0.01em;
      transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    .btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 20px rgba(17, 24, 39, 0.18);
    }

    .btn.secondary {
      background: linear-gradient(180deg, #475569, #334155);
    }

    .btn.danger {
      background: linear-gradient(180deg, #dc2626, #b91c1c);
    }

    .btn.light {
      background: #f8fafc;
      color: #0f172a;
      border: 1px solid var(--line);
      box-shadow: none;
    }

    .msg {
      padding: 12px 14px;
      border-radius: 10px;
      margin-bottom: 15px;
      font-weight: 500;
    }

    .msg.ok {
      background: var(--success-bg);
      color: var(--success);
    }

    .msg.err {
      background: var(--danger-bg);
      color: var(--danger);
    }

    label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      color: var(--text);
    }

    input[type="text"],
    input[type="time"],
    input[type="number"],
    select,
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

    input[type="text"]:focus,
    input[type="time"]:focus,
    input[type="number"]:focus,
    select:focus,
    textarea:focus {
      outline: none;
      border-color: #94a3b8;
      box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.22);
    }

    .field-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    @media (max-width: 600px) {
      .field-row { grid-template-columns: 1fr; }
    }

    .checkbox-group {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 15px;
    }

    .checkbox-group label {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: normal;
      font-size: 14px;
      cursor: pointer;
      background: #f3f4f6;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: 8px 12px;
      margin: 0;
    }

    .checkbox-group label:hover {
      border-color: #94a3b8;
      background: #fff;
    }

    .checkbox-group input[type="checkbox"] {
      width: auto;
      margin: 0;
      padding: 0;
    }

    .zeiten-list {
      display: grid;
      gap: 8px;
      margin-bottom: 10px;
    }

    .zeiten-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr auto;
      gap: 8px;
      align-items: center;
    }

    .zeiten-row select,
    .zeiten-row input {
      margin-bottom: 0;
    }

    .btn.small {
      padding: 9px 11px;
      font-size: 12px;
      border-radius: 9px;
    }

    .media-picker-head {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
      margin-bottom: 10px;
    }

    .media-picker-head input[type="text"] {
      margin: 0;
      flex: 1;
      min-width: 220px;
    }

    .media-picker-actions {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .media-filter-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 10px;
    }

    .media-filter-chip {
      border: 1px solid var(--line);
      background: #fff;
      color: #334155;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      line-height: 1;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .media-filter-chip:hover {
      border-color: #94a3b8;
    }

    .media-filter-chip.is-active {
      background: #111827;
      color: #fff;
      border-color: #111827;
    }

    .media-current {
      border: 1px solid #d1d5db;
      border-radius: 10px;
      background: #f9fafb;
      padding: 10px;
      display: grid;
      grid-template-columns: 112px 1fr;
      gap: 10px;
      margin-bottom: 12px;
      align-items: center;
    }

    .media-current img {
      width: 112px;
      height: 74px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      background: #fff;
    }

    .media-current .hint {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 4px;
    }

    .media-current .name {
      font-size: 13px;
      color: #111827;
      line-height: 1.35;
      word-break: break-word;
    }

    .media-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(156px, 1fr));
      gap: 10px;
      margin-bottom: 15px;
      max-height: 360px;
      overflow: auto;
      padding: 2px;
    }

    .media-option {
      border: 1px solid #d1d5db;
      border-radius: 10px;
      background: #fff;
      cursor: pointer;
      text-align: left;
      padding: 7px;
      transition: border-color 0.2s, box-shadow 0.2s, transform 0.1s;
    }

    .media-option:hover {
      border-color: #9ca3af;
      box-shadow: 0 5px 14px rgba(0, 0, 0, 0.06);
    }

    .media-option:active {
      transform: translateY(1px);
    }

    .media-option.is-selected {
      border-color: #111827;
      box-shadow: 0 0 0 2px rgba(17, 24, 39, 0.15), 0 12px 22px rgba(17, 24, 39, 0.08);
      background: #f8fafc;
    }

    .media-option img {
      width: 100%;
      aspect-ratio: 16/10;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      display: block;
      background: #f3f4f6;
      margin-bottom: 6px;
    }

    .media-option .media-label {
      display: block;
      font-size: 12px;
      color: #374151;
      line-height: 1.3;
      word-break: break-word;
    }

    .media-option .selected-tag {
      display: none;
      margin-top: 4px;
      font-size: 11px;
      color: #0f172a;
      font-weight: 600;
    }

    .media-option.is-selected .selected-tag {
      display: block;
    }

    .media-option.none {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 96px;
    }

    .media-empty {
      display: none;
      border: 1px dashed #d1d5db;
      border-radius: 10px;
      padding: 12px;
      font-size: 13px;
      color: #6b7280;
      margin-bottom: 15px;
      background: #f9fafb;
    }

    .section-title {
      font-size: 13px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      border-top: 1px solid #e2e8f0;
      padding-top: 16px;
      margin: 20px 0 14px;
    }

    .slug-box {
      background: #f9fafb;
      border: 1px dashed #d1d5db;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 15px;
      font-size: 12px;
      color: #666;
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .kurs-list {
      display: grid;
      gap: 14px;
    }

    .kurs-item {
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      padding: 16px;
    }

    .meta {
      font-size: 12px;
      color: #64748b;
      margin-bottom: 8px;
    }

    .kurs-item h3 {
      margin: 0 0 8px;
      font-size: 18px;
    }

    .kurs-item p {
      margin: 0 0 12px;
      color: #475569;
      font-size: 14px;
    }

    .kurs-thumb {
      width: 160px;
      max-width: 100%;
      aspect-ratio: 16/10;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      display: block;
      margin: 0 0 10px;
      background: #f3f4f6;
    }

    h1,
    h2,
    h3 {
      letter-spacing: -0.01em;
    }

    .card > h1 {
      margin-top: 0;
      margin-bottom: 14px;
      font-size: 26px;
    }

    .card > h2 {
      margin-top: 0;
    }
  </style>
</head>

<body>

  <div class="wrap">

    <div class="topnav">
      <a class="btn" href="kurs-editor.php">Kurs Übersicht</a>
      <a class="btn secondary" href="kurs-editor.php?action=create">Neuen Kurs erstellen</a>
      <a class="btn light" href="../kurse.php">Frontend ansehen</a>
      <a class="btn light" href="dashboard.php">Dashboard</a>
    </div>

    <?php if ($message !== ''): ?>
      <div class="msg ok"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="msg err"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($action === 'create' || $action === 'edit'): ?>
      <div class="card">
        <h1><?= $action === 'edit' ? 'Kurs bearbeiten' : 'Neuen Kurs erstellen' ?></h1>

        <form method="post" action="kurs-editor.php">
          <input type="hidden" name="original_slug" value="<?= e($action === 'edit' ? ($formData['slug'] ?? '') : '') ?>">

          <label for="name">Kursname</label>
          <input type="text" id="name" name="name" value="<?= e($formData['name'] ?? '') ?>" required>

          <label for="beschreibung">Kurze Beschreibung</label>
          <textarea id="beschreibung" name="beschreibung"><?= e($formData['beschreibung'] ?? '') ?></textarea>

          <div class="section-title">Kursdetails</div>

          <div class="field-row">
            <div>
              <label for="niveau">Niveau</label>
              <select id="niveau" name="niveau">
                <?php
                $niveaus = ['', 'Alle Levels', 'Anfänger', 'Fortgeschrittene', 'Experten'];
                $selectedNiveau = $formData['niveau'] ?? '';
                foreach ($niveaus as $n):
                ?>
                  <option value="<?= e($n) ?>" <?= $selectedNiveau === $n ? 'selected' : '' ?>>
                    <?= $n === '' ? '– bitte wählen –' : e($n) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="preis">Preis</label>
              <input type="text" id="preis" name="preis" value="<?= e($formData['preis'] ?? '') ?>" placeholder="z. B. 15 € / Einheit" required>
            </div>
          </div>

          <label>Termine mit Uhrzeit (Datum, von, bis)</label>
          <div class="zeiten-list" id="zeitenList">
            <?php
            $zeitenRows = (array) ($formData['termine'] ?? []);
            if (empty($zeitenRows)) {
              $zeitenRows = [[
                'datum' => '',
                'von' => '',
                'bis' => '',
              ]];
            }
            foreach ($zeitenRows as $z):
              $rowDatum = (string) ($z['datum'] ?? '');
              $rowVon = (string) ($z['von'] ?? '');
              $rowBis = (string) ($z['bis'] ?? '');
            ?>
              <div class="zeiten-row">
                <input type="date" name="termine[datum][]" value="<?= e($rowDatum) ?>">
                <input type="time" name="termine[von][]" value="<?= e($rowVon) ?>">
                <input type="time" name="termine[bis][]" value="<?= e($rowBis) ?>">
                <button type="button" class="btn light small remove-date">Entfernen</button>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="actions" style="margin-top:0;">
            <button type="button" class="btn light small" id="addZeitBtn">+ Termin hinzufügen</button>
          </div>

          <div class="field-row">
            <div>
              <label for="max_teilnehmer">Max. Teilnehmer</label>
              <input type="number" id="max_teilnehmer" name="max_teilnehmer" min="1" value="<?= e($formData['max_teilnehmer'] ?? '') ?>" placeholder="z. B. 12" required>
            </div>
            <div>
              <label for="ort">Ort / Raum</label>
              <input type="text" id="ort" name="ort" value="<?= e($formData['ort'] ?? '') ?>" placeholder="z. B. Studio 1">
            </div>
          </div>

          <label for="bildFilter">Kursbild aus Medien</label>
          <input type="hidden" id="bild" name="bild" value="<?= e($formData['bild'] ?? '') ?>">

          <div class="media-picker-head">
            <input type="text" id="bildFilter" placeholder="Bilder filtern (z. B. Hero, training, jpg)">
            <div class="media-picker-actions">
              <a class="btn light" href="media.php?filter=image" target="_blank" rel="noopener noreferrer">Medien öffnen</a>
            </div>
          </div>

          <div class="media-filter-chips" id="mediaFilterChips"></div>

          <div class="media-current" id="mediaCurrent">
            <img id="bildPreview" src="" alt="Vorschau Kursbild">
            <div>
              <div class="hint">Aktuelle Auswahl</div>
              <div class="name" id="bildCurrentName">Kein Bild ausgewählt</div>
            </div>
          </div>

          <div class="media-grid" id="mediaGrid">
            <button type="button" class="media-option none" data-media-value="" data-media-label="Kein Bild auswählen">
              <span class="media-label">Kein Bild auswählen</span>
            </button>
            <?php foreach ($availableMediaImages as $path => $label): ?>
              <button
                type="button"
                class="media-option"
                data-media-value="<?= e($path) ?>"
                data-media-label="<?= e($label) ?>"
              >
                <img src="<?= e($path) ?>" alt="<?= e($label) ?>" loading="lazy">
                <span class="media-label"><?= e($label) ?></span>
                <span class="selected-tag">Ausgewählt</span>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="media-empty" id="mediaEmpty">Keine Bilder zu diesem Filter gefunden.</div>

          <div class="section-title">URL</div>

          <label for="slug">Slug</label>
          <input type="text" id="slug" name="slug" value="<?= e($formData['slug'] ?? '') ?>" required>
          <div class="slug-box">
            Aufrufbar über: <strong>kurse.php?slug=<?= e($formData['slug'] ?? 'dein-slug') ?></strong>
          </div>

          <div class="actions">
            <button class="btn" type="submit">Speichern</button>
            <a class="btn secondary" href="kurs-editor.php">Zurück</a>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="card">
        <h1>Kurs-Übersicht</h1>
        <p>Hier kannst du Kurse erstellen, bearbeiten und löschen.</p>
      </div>

      <div class="card">
        <h2>Vorhandene Kurse</h2>

        <?php if (empty($allKurse)): ?>
          <p>Noch keine Kurse vorhanden.</p>
        <?php else: ?>
          <div class="kurs-list">
            <?php foreach ($allKurse as $kurs): ?>
              <div class="kurs-item">
                <div class="meta">
                  Slug: <strong><?= e($kurs['slug'] ?? '') ?></strong> &nbsp;|&nbsp;
                  Erstellt: <?= e($kurs['created_at'] ?? '') ?> &nbsp;|&nbsp;
                  Aktualisiert: <?= e($kurs['updated_at'] ?? '') ?>
                </div>

                <?php if (!empty($kurs['bild'])): ?>
                  <img class="kurs-thumb" src="<?= e((string) $kurs['bild']) ?>" alt="Kursbild">
                <?php endif; ?>

                <h3><?= e($kurs['name'] ?? '') ?></h3>
                <p><?= nl2br(e($kurs['beschreibung'] ?? '')) ?></p>

                <?php
                $infos = [];
                if (!empty($kurs['termine']) && is_array($kurs['termine'])) {
                  $zeitenText = [];
                  foreach ((array) $kurs['termine'] as $z) {
                    $datumRaw = (string) ($z['datum'] ?? '');
                    $datum = htmlspecialchars($datumRaw, ENT_QUOTES, 'UTF-8');
                    $wochentag = htmlspecialchars(weekdayNameFromDate($datumRaw), ENT_QUOTES, 'UTF-8');
                    $von = htmlspecialchars((string) ($z['von'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $bis = htmlspecialchars((string) ($z['bis'] ?? ''), ENT_QUOTES, 'UTF-8');
                    if ($datum !== '' && $von !== '' && $bis !== '') {
                      $zeitenText[] = trim($wochentag . ', ' . $datum . ' · ' . $von . ' bis ' . $bis . ' Uhr');
                    }
                  }
                  if (!empty($zeitenText)) {
                    $infos[] = '🗓 ' . implode(', ', $zeitenText);
                  }
                }
                if (!empty($kurs['niveau']))     $infos[] = '📊 ' . e($kurs['niveau']);
                if (!empty($kurs['max_teilnehmer'])) $infos[] = '👥 max. ' . e($kurs['max_teilnehmer']);
                if (!empty($kurs['ort']))        $infos[] = '📍 ' . e($kurs['ort']);
                if (!empty($kurs['preis']))      $infos[] = '💶 ' . e($kurs['preis']);
                if (!empty($infos)):
                ?>
                  <p style="font-size:13px;color:#374151;"><?= implode(' &nbsp;·&nbsp; ', $infos) ?></p>
                <?php endif; ?>

                <div class="actions">
                  <a class="btn light" href="../kurse.php?slug=<?= e($kurs['slug'] ?? '') ?>" target="_blank">Ansehen</a>
                  <a class="btn secondary" href="kurs-editor.php?action=edit&slug=<?= e($kurs['slug'] ?? '') ?>">Bearbeiten</a>
                  <a class="btn danger" href="kurs-editor.php?action=delete&slug=<?= e($kurs['slug'] ?? '') ?>" onclick="return confirm('Kurs wirklich löschen?');">Löschen</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  </div>

  <script>
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const bildInput = document.getElementById('bild');
    const zeitenList = document.getElementById('zeitenList');
    const addZeitBtn = document.getElementById('addZeitBtn');
    const bildFilter = document.getElementById('bildFilter');
    const mediaFilterChips = document.getElementById('mediaFilterChips');
    const mediaGrid = document.getElementById('mediaGrid');
    const mediaEmpty = document.getElementById('mediaEmpty');
    const mediaCurrent = document.getElementById('mediaCurrent');
    const bildPreview = document.getElementById('bildPreview');
    const bildCurrentName = document.getElementById('bildCurrentName');

    if (nameInput && slugInput) {
      nameInput.addEventListener('blur', function () {
        if (slugInput.value.trim() !== '') return;

        let slug = this.value.toLowerCase()
          .replace(/ä/g, 'ae')
          .replace(/ö/g, 'oe')
          .replace(/ü/g, 'ue')
          .replace(/ß/g, 'ss')
          .replace(/[^a-z0-9\s\-]/g, '')
          .trim()
          .replace(/\s+/g, '-');

        slugInput.value = slug;
      });
    }

    if (zeitenList && addZeitBtn) {
      const createZeitRow = (datum = '', von = '', bis = '') => {
        const row = document.createElement('div');
        row.className = 'zeiten-row';

        const datumInput = document.createElement('input');
        datumInput.type = 'date';
        datumInput.name = 'termine[datum][]';
        datumInput.value = datum;

        const vonInput = document.createElement('input');
        vonInput.type = 'time';
        vonInput.name = 'termine[von][]';
        vonInput.value = von;

        const bisInput = document.createElement('input');
        bisInput.type = 'time';
        bisInput.name = 'termine[bis][]';
        bisInput.value = bis;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn light small remove-date';
        removeBtn.textContent = 'Entfernen';

        row.appendChild(datumInput);
        row.appendChild(vonInput);
        row.appendChild(bisInput);
        row.appendChild(removeBtn);
        zeitenList.appendChild(row);
      };

      addZeitBtn.addEventListener('click', function () {
        createZeitRow();
      });

      zeitenList.addEventListener('click', function (event) {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.classList.contains('remove-date')) {
          return;
        }

        const rows = zeitenList.querySelectorAll('.zeiten-row');
        if (rows.length <= 1) {
          const first = rows[0];
          if (first) {
            const datum = first.querySelector('input[name="termine[datum][]"]');
            const von = first.querySelector('input[name="termine[von][]"]');
            const bis = first.querySelector('input[name="termine[bis][]"]');
            if (datum) datum.value = '';
            if (von) von.value = '';
            if (bis) bis.value = '';
          }
          return;
        }

        const row = target.closest('.zeiten-row');
        if (row) {
          row.remove();
        }
      });
    }

    if (bildInput && mediaGrid && bildPreview && bildCurrentName && mediaCurrent) {
      const mediaOptions = Array.from(mediaGrid.querySelectorAll('.media-option'));

      const getFolderFromValue = (value) => {
        if (!value || value === '') return '';
        const parts = value.split('/');
        if (parts.length < 3) return 'Andere';
        return parts[1] || 'Andere';
      };

      const setActiveFolderChip = (folder) => {
        if (!mediaFilterChips) return;
        const chips = Array.from(mediaFilterChips.querySelectorAll('.media-filter-chip'));
        chips.forEach((chip) => {
          chip.classList.toggle('is-active', chip.dataset.folder === folder);
        });
      };

      const applySelection = (value, label) => {
        bildInput.value = value;

        mediaOptions.forEach((option) => {
          option.classList.toggle('is-selected', option.dataset.mediaValue === value);
        });

        if (value === '') {
          bildPreview.style.opacity = '0.35';
          bildPreview.removeAttribute('src');
          bildCurrentName.textContent = 'Kein Bild ausgewählt';
        } else {
          bildPreview.style.opacity = '1';
          bildPreview.src = value;
          bildCurrentName.textContent = label;
        }
      };

      const refreshFilterVisibility = () => {
        const query = (bildFilter?.value || '').trim().toLowerCase();
        const activeChip = mediaFilterChips?.querySelector('.media-filter-chip.is-active');
        const activeFolder = activeChip ? (activeChip.dataset.folder || 'all') : 'all';
        let visibleCount = 0;

        mediaOptions.forEach((option) => {
          const label = (option.dataset.mediaLabel || '').toLowerCase();
          const value = (option.dataset.mediaValue || '').toLowerCase();
          const folder = getFolderFromValue(option.dataset.mediaValue || '');

          const queryPass = query === '' || label.includes(query) || value.includes(query);
          const folderPass = activeFolder === 'all' || folder === activeFolder || option.dataset.mediaValue === '';
          const visible = queryPass && folderPass;

          option.style.display = visible ? '' : 'none';
          if (visible) {
            visibleCount += 1;
          }
        });

        mediaEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
      };

      if (mediaFilterChips) {
        const folders = new Set();
        folders.add('all');
        mediaOptions.forEach((option) => {
          const value = option.dataset.mediaValue || '';
          if (value !== '') {
            folders.add(getFolderFromValue(value));
          }
        });

        const sortedFolders = Array.from(folders).sort((a, b) => {
          if (a === 'all') return -1;
          if (b === 'all') return 1;
          return a.localeCompare(b, 'de', { sensitivity: 'base' });
        });

        sortedFolders.forEach((folder) => {
          const chip = document.createElement('button');
          chip.type = 'button';
          chip.className = 'media-filter-chip' + (folder === 'all' ? ' is-active' : '');
          chip.dataset.folder = folder;
          chip.textContent = folder === 'all' ? 'Alle Ordner' : folder;
          chip.addEventListener('click', () => {
            setActiveFolderChip(folder);
            refreshFilterVisibility();
          });
          mediaFilterChips.appendChild(chip);
        });
      }

      mediaOptions.forEach((option) => {
        option.addEventListener('click', function () {
          applySelection(this.dataset.mediaValue || '', this.dataset.mediaLabel || 'Bild');
        });
      });

      const selectedStart = (bildInput.value || '').trim();
      const selectedOption = mediaOptions.find((option) => option.dataset.mediaValue === selectedStart);
      if (selectedOption) {
        applySelection(selectedOption.dataset.mediaValue || '', selectedOption.dataset.mediaLabel || 'Bild');
      } else {
        applySelection('', 'Kein Bild ausgewählt');
      }

      if (bildFilter && mediaEmpty) {
        bildFilter.addEventListener('input', function () {
          refreshFilterVisibility();
        });
      }

      refreshFilterVisibility();
    }
  </script>

</body>
</html>
