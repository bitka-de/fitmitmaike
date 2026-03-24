<?php
declare(strict_types=1);

const KURS_DATA_DIR = __DIR__ . '/inside/kurs_data';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizeKursSlug(string $slug): string
{
    $slug = mb_strtolower(trim($slug), 'UTF-8');
    $slug = strtr($slug, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    $slug = preg_replace('/[^a-z0-9\-]/', '', $slug) ?? '';
    return trim($slug, '-');
}

function getKursFilePath(string $slug): string
{
    return KURS_DATA_DIR . '/' . $slug . '.json';
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
        if (is_array($data) && !empty($data['slug'])) {
            $kurse[] = $data;
        }
    }

    usort(
        $kurse,
        static fn(array $a, array $b): int => strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01')
    );

    return $kurse;
}

function resolveKursImageUrl(string $imagePath): string
{
    $imagePath = trim($imagePath);
    if ($imagePath === '') {
        return '';
    }

    if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
        return $imagePath;
    }

    $imagePath = ltrim($imagePath, '/');

    if (str_starts_with($imagePath, 'inside/uploads/')) {
        return $imagePath;
    }

    if (str_starts_with($imagePath, 'uploads/')) {
        return 'inside/' . $imagePath;
    }

    return '';
}

function formatKursDate(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if ($dt !== false && $dt->format('Y-m-d') === $date) {
        return $dt->format('d.m.Y');
    }

    return $date;
}

function weekdayFromDate(string $date): string
{
    $dt = DateTime::createFromFormat('Y-m-d', trim($date));
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

function formatZeitSlot(array $slot): string
{
    $datumRaw = trim((string) ($slot['datum'] ?? ''));
    $datum = formatKursDate($datumRaw);
    $tag = weekdayFromDate($datumRaw);
    $von = trim((string) ($slot['von'] ?? ''));
    $bis = trim((string) ($slot['bis'] ?? ''));

    if ($datum === '' || $von === '' || $bis === '') {
        return '';
    }

    $prefix = trim($tag . ', ' . $datum);
    return $prefix . ' · ' . $von . ' bis ' . $bis . ' Uhr';
}

$slug = isset($_GET['slug']) ? normalizeKursSlug((string) $_GET['slug']) : '';
$kurs = $slug !== '' ? loadKurs($slug) : null;
$allKurse = loadAllKurse();

$pageTitle = $slug !== '' && $kurs !== null
    ? e($kurs['name']) . ' - Kursanmeldung | Fit mit Maike'
    : 'Kurse | Fit mit Maike';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="./css/styles.css">
    <link href="https://api.fontshare.com/v2/css?f[]=switzer@400,500,600,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --card-border: color-mix(in oklab, var(--text-color) 10%, transparent);
            --card-shadow: 0 16px 40px color-mix(in srgb, black 8%, transparent);
        }

        body {
            background:
                radial-gradient(circle at 8% 0%, color-mix(in oklab, var(--brand-color) 16%, var(--background-color)) 0%, transparent 36%),
                radial-gradient(circle at 96% 4%, color-mix(in oklab, var(--accent-color) 14%, var(--background-color)) 0%, transparent 28%),
                var(--background-color);
            min-height: 100dvh;
        }

        .kurs-page {
            max-width: 1080px;
            margin: 0 auto;
            padding: clamp(1.25rem, 3.5vw, 2.25rem) 1rem clamp(2rem, 6vw, 4rem);
        }

        .page-intro {
            margin-bottom: clamp(1.5rem, 4vw, 2.5rem);
            text-align: center;
        }

        .page-intro .eyebrow {
            display: inline-block;
            margin-bottom: 0.5rem;
            color: var(--brand-color);
        }

        .page-intro h1 {
            margin: 0;
            font-size: clamp(1.7rem, 4vw, 2.6rem);
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .page-intro p {
            margin: 0.75rem auto 0;
            max-width: 58ch;
            color: var(--text-muted);
        }

        .hero-shell {
            background: linear-gradient(145deg, color-mix(in oklab, var(--brand-color) 88%, black), var(--brand-color));
            border-radius: calc(var(--border-radius) * 1.4);
            color: #fff;
            padding: clamp(1.2rem, 3vw, 2.3rem);
            box-shadow: 0 26px 52px color-mix(in srgb, black 14%, transparent);
            margin-bottom: clamp(1.2rem, 3vw, 2.1rem);
            position: relative;
            overflow: hidden;
        }

        .hero-shell::after {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            right: -90px;
            top: -130px;
            background: color-mix(in oklab, white 20%, transparent);
            opacity: 0.36;
            pointer-events: none;
        }

        .hero-shell .eyebrow,
        .hero-shell h1,
        .hero-shell p {
            color: #fff;
            position: relative;
            z-index: 1;
        }

        .hero-shell .eyebrow {
            opacity: 0.85;
        }

        .hero-shell p {
            max-width: 62ch;
            opacity: 0.9;
        }

        .hero-metrics {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.55rem;
            position: relative;
            z-index: 1;
        }

        .hero-metric {
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            padding: 0.62rem 0.72rem;
            backdrop-filter: blur(2px);
        }

        .hero-metric .label {
            margin: 0;
            font-size: 0.67rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            opacity: 0.82;
        }

        .hero-metric .value {
            margin: 0.22rem 0 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(285px, 1fr));
            gap: 1.15rem;
        }

        .course-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.22s ease, box-shadow 0.24s ease;
        }

        .course-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 22px 40px color-mix(in srgb, black 12%, transparent);
        }

        .course-image {
            width: 100%;
            aspect-ratio: 16/10;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid var(--card-border);
            background: color-mix(in oklab, var(--text-color) 5%, white);
        }

        .course-card-head {
            padding: 1rem 1.1rem 0.85rem;
            background: linear-gradient(140deg, color-mix(in oklab, var(--brand-color) 75%, black), var(--brand-color));
            color: #fff;
        }

        .course-card-head .niveau {
            display: inline-block;
            margin-bottom: 0.5rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
        }

        .course-card-head h2 {
            margin: 0;
            font-size: 1.22rem;
            line-height: 1.35;
            color: #fff;
        }

        .course-card-body {
            padding: 1rem 1.1rem 1.2rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            flex: 1;
        }

        .course-card-body p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.55;
            font-size: 0.93rem;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.65rem;
            border-radius: 999px;
            font-size: 0.78rem;
            background: color-mix(in oklab, var(--brand-color) 8%, white);
            border: 1px solid color-mix(in oklab, var(--brand-color) 23%, white);
            color: color-mix(in oklab, var(--brand-color) 80%, black);
        }

        .course-card-footer {
            border-top: 1px solid var(--card-border);
            padding: 0.9rem 1.1rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .course-price {
            font-weight: 700;
            color: color-mix(in oklab, var(--brand-color) 80%, black);
            font-size: 0.95rem;
        }

        .not-found,
        .empty-state {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            padding: 1.8rem 1.1rem;
            text-align: center;
        }

        .not-found p,
        .empty-state p {
            color: var(--text-muted);
            margin: 0.75rem 0 1.1rem;
        }

        .detail-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 1.2rem;
            align-items: start;
        }

        @media (max-width: 860px) {
            .detail-layout {
                grid-template-columns: 1fr;
            }
        }

        .detail-main,
        .detail-side {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .detail-intro {
            margin-bottom: 1.1rem;
            text-align: left;
        }

        .detail-intro .eyebrow {
            margin-bottom: 0.45rem;
        }

        .detail-back {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: var(--brand-color);
            text-decoration: none;
            font-size: 0.86rem;
            font-weight: 600;
            margin-bottom: 0.9rem;
        }

        .detail-back:hover {
            opacity: 0.8;
        }

        .detail-back svg {
            width: 15px;
            height: 15px;
        }

        .detail-image {
            width: 100%;
            aspect-ratio: 16/8;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid var(--card-border);
            background: color-mix(in oklab, var(--text-color) 5%, white);
        }

        .detail-image-wrap {
            position: relative;
        }

        .detail-image-overlay {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 1.2rem 1.2rem 0.85rem;
            background: linear-gradient(180deg, transparent 10%, rgba(15, 23, 42, 0.82) 100%);
            color: #fff;
        }

        .detail-image-overlay .title-mini {
            margin: 0;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.85;
        }

        .detail-image-overlay .subtitle-mini {
            margin: 0.3rem 0 0;
            font-size: 1.02rem;
            font-weight: 600;
            line-height: 1.35;
        }

        .detail-head {
            padding: 1.4rem 1.25rem 1rem;
            background: linear-gradient(140deg, color-mix(in oklab, var(--brand-color) 75%, black), var(--brand-color));
            color: #fff;
        }

        .detail-head .niveau {
            display: inline-block;
            margin-bottom: 0.55rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
        }

        .detail-head h1 {
            margin: 0;
            color: #fff;
            font-size: clamp(1.35rem, 3.5vw, 2rem);
            line-height: 1.2;
        }

        .detail-head p {
            margin: 0.45rem 0 0;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.92rem;
        }

        .detail-body {
            padding: 1.2rem 1.25rem 1.4rem;
        }

        .detail-top-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.55rem;
            margin: 0 0 0.8rem;
        }

        .top-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.34rem;
            font-size: 0.76rem;
            line-height: 1;
            border-radius: 999px;
            padding: 0.4rem 0.62rem;
            background: color-mix(in oklab, var(--brand-color) 8%, #fff);
            border: 1px solid color-mix(in oklab, var(--brand-color) 20%, #fff);
            color: color-mix(in oklab, var(--brand-color) 80%, black);
            font-weight: 600;
        }

        .detail-meta {
            margin: 0 0 0.8rem;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .detail-section-title {
            margin: 0 0 0.65rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: var(--text-muted);
        }

        .detail-desc {
            margin: 1rem 0 0;
            color: var(--text-muted);
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .fact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.65rem;
            margin-top: 0.35rem;
        }

        .fact-card {
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 0.72rem 0.78rem;
            background: linear-gradient(180deg, #fff, #f8fafc);
        }

        .fact-card .fact-label {
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            margin: 0 0 0.25rem;
        }

        .fact-card .fact-value {
            font-size: 0.92rem;
            color: var(--text-color);
            font-weight: 600;
            margin: 0;
        }

        .schedule-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.45rem;
        }

        .schedule-list li {
            font-size: 0.84rem;
            color: var(--text-color);
            line-height: 1.4;
            border: 1px dashed color-mix(in oklab, var(--brand-color) 25%, white);
            border-radius: 10px;
            padding: 0.4rem 0.5rem;
            background: color-mix(in oklab, var(--brand-color) 6%, white);
        }

        .side-head {
            padding: 1rem 1rem 0.9rem;
            background: linear-gradient(140deg, color-mix(in oklab, var(--brand-color) 75%, black), var(--brand-color));
            color: #fff;
        }

        .side-head h3 {
            margin: 0;
            font-size: 1rem;
            color: #fff;
        }

        .side-head p {
            margin: 0.25rem 0 0;
            font-size: 0.83rem;
            opacity: 0.88;
            color: #fff;
        }

        .side-body {
            padding: 1rem;
        }

        .detail-side {
            position: sticky;
            top: 0.8rem;
        }

        @media (max-width: 860px) {
            .detail-side {
                position: static;
            }
        }

        .side-points {
            margin: 0.1rem 0 0.9rem;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.35rem;
        }

        .side-points li {
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .price-box {
            margin-bottom: 0.95rem;
            padding: 0.7rem 0.8rem;
            border-radius: var(--border-radius);
            background: color-mix(in oklab, var(--brand-color) 9%, white);
            border: 1px solid color-mix(in oklab, var(--brand-color) 24%, white);
        }

        .price-label {
            margin: 0;
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--brand-color);
        }

        .price-value {
            margin: 0.2rem 0 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: color-mix(in oklab, var(--brand-color) 80%, black);
        }

        .field {
            margin-bottom: 0.8rem;
        }

        .field label {
            display: block;
            margin-bottom: 0.28rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .field input {
            width: 100%;
            padding: 0.72rem 0.78rem;
            border-radius: var(--border-radius);
            border: 1.5px solid color-mix(in oklab, var(--text-color) 16%, transparent);
            background: var(--background-color);
            color: var(--text-color);
            font: inherit;
            box-sizing: border-box;
        }

        .field input:focus {
            outline: none;
            border-color: var(--brand-color);
            background: #fff;
        }

        .field input::placeholder {
            color: color-mix(in oklab, var(--text-muted) 88%, white);
        }

        .submit-btn {
            width: 100%;
            border: 0;
            border-radius: var(--border-radius);
            padding: 0.86rem;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            color: #fff;
            background: var(--brand-color);
            transition: transform 0.12s ease, background 0.2s ease;
            letter-spacing: 0.01em;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            background: color-mix(in oklab, var(--brand-color) 82%, black);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .form-msg {
            margin-top: 0.8rem;
            border-radius: var(--border-radius);
            padding: 0.68rem 0.78rem;
            display: none;
            font-size: 0.88rem;
        }

        .form-msg.ok {
            display: block;
            color: #166534;
            background: #dcfce7;
        }

        .form-msg.err {
            display: block;
            color: #991b1b;
            background: #fee2e2;
        }
    </style>
</head>
<body>
    <main class="kurs-page">
        <?php if ($slug !== '' && $kurs !== null): ?>
            <div class="page-intro detail-intro">
                <a href="kurse.php" class="detail-back">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Zur Kursübersicht
                </a>
                <span class="eyebrow">Kursanmeldung</span>
                <h1><?= e($kurs['name'] ?? '') ?></h1>
                <p>Fülle das kurze Formular aus und ich melde mich bei dir mit allen weiteren Infos.</p>
            </div>

            <section class="detail-layout">
                <article class="detail-main">
                    <?php $detailImageUrl = resolveKursImageUrl((string) ($kurs['bild'] ?? '')); ?>
                    <?php if ($detailImageUrl !== ''): ?>
                        <div class="detail-image-wrap">
                            <img class="detail-image" src="<?= e($detailImageUrl) ?>" alt="Kursbild <?= e($kurs['name'] ?? '') ?>" loading="lazy">
                            <div class="detail-image-overlay">
                                <p class="title-mini">Personal Training Kurs</p>
                                <p class="subtitle-mini">Bewegung mit klarer Struktur und persönlicher Begleitung</p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="detail-head">
                        <?php if (!empty($kurs['niveau'])): ?>
                            <span class="niveau"><?= e($kurs['niveau']) ?></span>
                        <?php endif; ?>
                        <h1><?= e($kurs['name'] ?? '') ?></h1>
                        <p>Gezielte Betreuung, klare Strukturen, echte Fortschritte.</p>
                    </div>
                    <div class="detail-body">
                        <?php if (!empty($kurs['created_at'])): ?>
                            <p class="detail-meta">Veröffentlicht am <?= e($kurs['created_at']) ?></p>
                        <?php endif; ?>

                        <div class="detail-top-row">
                            <?php
                            $zeitSlots = array_values(array_filter(array_map(
                                static fn($slot) => is_array($slot) ? formatZeitSlot($slot) : '',
                                (array) ($kurs['termine'] ?? [])
                            )));

                            if (empty($zeitSlots)) {
                                $zeitSlots = array_values(array_filter(array_map(
                                    static fn($slot) => is_array($slot) ? formatZeitSlot($slot) : '',
                                    (array) ($kurs['zeiten'] ?? [])
                                )));
                            }
                            ?>
                            <?php if (!empty($zeitSlots)): ?>
                                <span class="top-pill">🗓 <?= e($zeitSlots[0]) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($kurs['niveau'])): ?><span class="top-pill">📊 <?= e($kurs['niveau']) ?></span><?php endif; ?>
                            <?php if (!empty($kurs['preis'])): ?><span class="top-pill">💶 <?= e($kurs['preis']) ?></span><?php endif; ?>
                            <?php if (!empty($kurs['max_teilnehmer'])): ?><span class="top-pill">👥 Max. <?= e((string) $kurs['max_teilnehmer']) ?></span><?php endif; ?>
                        </div>

                        <h2 class="detail-section-title">Kursinfos</h2>
                        <div class="fact-grid">
                            <?php if (!empty($zeitSlots)): ?>
                                    <div class="fact-card">
                                        <p class="fact-label">Zeiten</p>
                                        <ul class="schedule-list">
                                            <?php foreach ($zeitSlots as $slot): ?>
                                                <li>🗓 <?= e($slot) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                            <?php endif; ?>
                            <?php if (!empty($kurs['ort'])): ?>
                                <div class="fact-card">
                                    <p class="fact-label">Ort</p>
                                    <p class="fact-value">📍 <?= e($kurs['ort']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($kurs['beschreibung'])): ?>
                            <p class="detail-desc"><?= nl2br(e($kurs['beschreibung'])) ?></p>
                        <?php endif; ?>
                    </div>
                </article>

                <aside class="detail-side">
                    <div class="side-head">
                        <h3>Jetzt anmelden</h3>
                        <p>In 30 Sekunden abgeschlossen.</p>
                    </div>
                    <div class="side-body">
                        <?php if (!empty($kurs['preis'])): ?>
                            <div class="price-box">
                                <p class="price-label">Preis</p>
                                <p class="price-value">💶 <?= e($kurs['preis']) ?></p>
                            </div>
                        <?php endif; ?>

                        <ul class="side-points">
                            <li>Persönliche Rückmeldung per E-Mail</li>
                            <li>Keine automatische Newsletter-Anmeldung</li>
                            <li>Unverbindliche Anfrage</li>
                        </ul>

                        <form id="anmeldeForm">
                            <input type="hidden" name="kurs" value="<?= e($kurs['name'] ?? '') ?>">

                            <div class="field">
                                <label for="name">Name</label>
                                <input id="name" type="text" name="name" required placeholder="Dein vollständiger Name">
                            </div>

                            <div class="field">
                                <label for="email">E-Mail</label>
                                <input id="email" type="email" name="email" required placeholder="deine@email.de">
                            </div>

                            <button class="submit-btn" type="submit">Anmeldung absenden</button>
                        </form>

                        <div id="formMsg" class="form-msg"></div>
                    </div>
                </aside>
            </section>

        <?php elseif ($slug !== '' && $kurs === null): ?>
            <section class="not-found">
                <h1>Kurs nicht gefunden</h1>
                <p>Der angefragte Kurs existiert nicht oder wurde entfernt.</p>
                <a href="kurse.php" class="button primary">Zur Kursübersicht</a>
            </section>

        <?php else: ?>
            <section class="hero-shell">
                <div class="page-intro" style="margin:0;text-align:left;">
                    <span class="eyebrow">Angebot</span>
                    <h1>Unsere Kurse</h1>
                    <p>Wähle deinen Kurs, sichere dir deinen Platz und starte mit einem klaren Plan.</p>
                </div>
                <div class="hero-metrics">
                    <div class="hero-metric">
                        <p class="label">Verfügbare Kurse</p>
                        <p class="value"><?= count($allKurse) ?></p>
                    </div>
                    <div class="hero-metric">
                        <p class="label">Betreuung</p>
                        <p class="value">Persönlich</p>
                    </div>
                    <div class="hero-metric">
                        <p class="label">Anmeldung</p>
                        <p class="value">Direkt online</p>
                    </div>
                </div>
            </section>

            <?php if (empty($allKurse)): ?>
                <section class="empty-state">
                    <h2>Aktuell keine Kurse online</h2>
                    <p>Sobald neue Kurse verfügbar sind, erscheinen sie hier.</p>
                </section>
            <?php else: ?>
                <section class="grid">
                    <?php foreach ($allKurse as $entry): ?>
                        <article class="course-card">
                            <?php $listImageUrl = resolveKursImageUrl((string) ($entry['bild'] ?? '')); ?>
                            <?php if ($listImageUrl !== ''): ?>
                                <img class="course-image" src="<?= e($listImageUrl) ?>" alt="Kursbild <?= e($entry['name'] ?? '') ?>" loading="lazy">
                            <?php endif; ?>

                            <div class="course-card-head">
                                <?php if (!empty($entry['niveau'])): ?>
                                    <span class="niveau"><?= e($entry['niveau']) ?></span>
                                <?php endif; ?>
                                <h2><?= e($entry['name'] ?? '') ?></h2>
                            </div>

                            <div class="course-card-body">
                                <?php if (!empty($entry['beschreibung'])): ?>
                                    <p><?= e(mb_substr($entry['beschreibung'], 0, 120)) ?><?= mb_strlen((string) $entry['beschreibung']) > 120 ? ' ...' : '' ?></p>
                                <?php endif; ?>

                                <div class="chips">
                                    <?php
                                    $entryZeiten = array_values(array_filter(array_map(
                                        static fn($slot) => is_array($slot) ? formatZeitSlot($slot) : '',
                                        (array) ($entry['termine'] ?? [])
                                    )));
                                    if (empty($entryZeiten)) {
                                        $entryZeiten = array_values(array_filter(array_map(
                                        static fn($slot) => is_array($slot) ? formatZeitSlot($slot) : '',
                                        (array) ($entry['zeiten'] ?? [])
                                        )));
                                    }
                                    if (!empty($entryZeiten)) {
                                        $previewZeiten = array_slice($entryZeiten, 0, 2);
                                        $zeitenText = implode(', ', $previewZeiten);
                                        if (count($entryZeiten) > 2) {
                                            $zeitenText .= ' +'. (count($entryZeiten) - 2);
                                        }
                                        echo '<span class="chip">🗓 ' . e($zeitenText) . '</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="course-card-footer">
                                <?php if (!empty($entry['preis'])): ?>
                                    <span class="course-price">💶 <?= e($entry['preis']) ?></span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>

                                <a href="kurse.php?slug=<?= e($entry['slug'] ?? '') ?>" class="button primary">Zur Anmeldung</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
        (function () {
            const form = document.getElementById('anmeldeForm');
            const msg = document.getElementById('formMsg');
            if (!form || !msg) {
                return;
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const btn = form.querySelector('.submit-btn');

                msg.className = 'form-msg';
                msg.textContent = '';

                btn.disabled = true;
                btn.textContent = 'Wird gesendet ...';

                try {
                    const response = await fetch('kurs-anmeldung.php', {
                        method: 'POST',
                        body: new FormData(form)
                    });

                    const data = await response.json();
                    msg.textContent = data.message || 'Unbekannte Antwort vom Server.';
                    msg.classList.add(data.success ? 'ok' : 'err');

                    if (data.success) {
                        form.reset();
                    }
                } catch (error) {
                    msg.textContent = 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
                    msg.classList.add('err');
                } finally {
                    btn.disabled = false;
                    btn.textContent = 'Anmeldung absenden';
                }
            });
        }());
    </script>
</body>
</html>
