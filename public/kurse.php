<?php
declare(strict_types=1);

require_once __DIR__ . '/inside/auth.php';

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

function renderMarkdown(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    // Escape HTML first
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // Convert markdown to HTML
    // Bold: **text** -> <strong>text</strong>
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);

    // Italic: *text* -> <em>text</em> (but not ** or ***)
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);

    // Headings: ### text -> <h3>text</h3>
    $text = preg_replace('/^### (.+?)$/m', '<h3 style="margin: 16px 0 8px; font-size: 1.1rem; color: var(--text-color);">$1</h3>', $text);
    $text = preg_replace('/^## (.+?)$/m', '<h2 style="margin: 18px 0 10px; font-size: 1.25rem; color: var(--text-color);">$1</h2>', $text);

    // Lists: - item -> <li>item</li> wrapped in <ul>
    $lines = explode("\n", $text);
    $inList = false;
    $result = [];

    foreach ($lines as $line) {
        if (preg_match('/^- (.+)$/', $line, $matches)) {
            if (!$inList) {
                $result[] = '<ul style="margin: 12px 0; padding-left: 24px; line-height: 1.6;">';
                $inList = true;
            }
            $result[] = '<li>' . trim($matches[1]) . '</li>';
        } else {
            if ($inList) {
                $result[] = '</ul>';
                $inList = false;
            }
            if (trim($line) !== '') {
                $result[] = '<p style="margin: 8px 0; line-height: 1.6;">' . $line . '</p>';
            }
        }
    }

    if ($inList) {
        $result[] = '</ul>';
    }

    return implode("\n", $result);
}

$slug = isset($_GET['slug']) ? normalizeKursSlug((string) $_GET['slug']) : '';
$kurs = $slug !== '' ? loadKurs($slug) : null;
$allKurse = loadAllKurse();
$isLoggedIn = isSecure();

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
            background: color-mix(in oklab, var(--brand-color) 92%, black);
            border-radius: var(--border-radius);
            color: var(--brand-color);
            padding: clamp(1.5rem, 3vw, 2.5rem);
            box-shadow: 0 2px 8px color-mix(in srgb, black 6%, transparent);
            margin-bottom: clamp(1.5rem, 3vw, 2.2rem);
            position: relative;
            overflow: hidden;
            border: 1px solid color-mix(in oklab, var(--brand-color) 20%, white);
        }

        .hero-shell::after {
            display: none;
        }

        .hero-shell .eyebrow,
        .hero-shell h1,
        .hero-shell p {
            color: var(--text-color);
            position: relative;
            z-index: 1;
        }

        .hero-shell .eyebrow {
            opacity: 0.7;
        }

        .hero-shell p {
            max-width: 62ch;
            opacity: 0.85;
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
            border: 1px solid color-mix(in oklab, var(--brand-color) 20%, white);
            border-radius: 8px;
            background: transparent;
            padding: 0.75rem 0.85rem;
        }

        .hero-metric .label {
            margin: 0;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.7;
            color: var(--text-muted);
            font-weight: 700;
        }

        .hero-metric .value {
            margin: 0.3rem 0 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-color);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.4rem;
        }

        .course-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px color-mix(in srgb, black 4%, transparent);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.24s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px color-mix(in srgb, black 10%, transparent);
            border-color: color-mix(in oklab, var(--brand-color) 35%, white);
        }

        .course-image {
            width: 100%;
            aspect-ratio: 16/10;
            object-fit: cover;
            display: block;
            position: relative;
            background: color-mix(in oklab, var(--text-color) 5%, white);
        }

        .course-card-head {
            padding: 1.2rem 1.25rem 1rem;
            background: color-mix(in oklab, var(--brand-color) 92%, black);
            color: var(--brand-color);
            border-bottom: 1px solid color-mix(in oklab, var(--brand-color) 20%, white);
        }

        .course-card-head::after {
            display: none;
        }

        .course-card-head .niveau {
            display: inline-block;
            margin-bottom: 0.7rem;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: transparent;
            border: 1.5px solid color-mix(in oklab, var(--brand-color) 40%, white);
            border-radius: 4px;
            padding: 0.35rem 0.65rem;
        }

        .course-card-head h2 {
            margin: 0;
            font-size: 1.25rem;
            line-height: 1.3;
            color: var(--text-color);
            font-weight: 700;
        }

        .course-card-body {
            padding: 1.15rem 1.25rem 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.9rem;
            flex: 1;
        }

        .course-card-body > p:first-of-type {
            color: color-mix(in oklab, var(--brand-color) 75%, black);
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0;
        }

        .course-card-body p {
            margin: 0;
            color: var(--text-muted);
            line-height: 1.6;
            font-size: 0.9rem;
        }

        .course-meta {
            display: grid;
            gap: 0.65rem;
            padding: 0.8rem;
            background: #f9fafb;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            margin: 0;
        }

        .course-meta-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .course-meta-item strong {
            color: var(--text-color);
            font-weight: 600;
        }

        .course-meta-icon {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
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
            padding: 0.95rem 1.25rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.85rem;
            flex-wrap: wrap;
            background: transparent;
        }

        .course-price {
            font-weight: 700;
            color: color-mix(in oklab, var(--brand-color) 82%, black);
            font-size: 0.98rem;
            letter-spacing: -0.01em;
        }

        .button-group {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }

        .button-small {
            padding: 0.55rem 0.95rem;
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.16s ease;
            position: relative;
        }

        .button-small:hover {
            transform: translateY(-1px);
        }

        .button-small:active {
            transform: translateY(0);
        }

        .button-small.primary {
            background: var(--brand-color);
            color: #fff;
            border-color: var(--brand-color);
        }

        .button-small.primary:hover {
            background: color-mix(in oklab, var(--brand-color) 85%, black);
            border-color: color-mix(in oklab, var(--brand-color) 85%, black);
        }

        .button-small.whatsapp {
            background: #25d366;
            color: #fff;
            border-color: #25d366;
        }

        .button-small.whatsapp:hover {
            background: #1fad56;
            border-color: #1fad56;
        }

        .not-found,
        .empty-state {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: var(--border-radius);
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .not-found p,
        .empty-state p {
            color: var(--text-muted);
            margin: 0.8rem 0 1.3rem;
            font-size: 0.93rem;
        }

        .detail-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 1.4rem;
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
            box-shadow: 0 2px 4px color-mix(in srgb, black 4%, transparent);
            overflow: hidden;
            transition: box-shadow 0.24s ease;
        }

        .detail-main:hover,
        .detail-side:hover {
            box-shadow: 0 6px 12px color-mix(in srgb, black 6%, transparent);
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
            overflow: hidden;
        }

        .detail-image {
            transition: transform 0.3s ease;
        }

        .detail-image-wrap:hover .detail-image {
            transform: scale(1.01);
        }

        .detail-image-overlay {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            padding: 1.5rem 1.5rem 1.1rem;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0) 0%, rgba(15, 23, 42, 0.55) 40%, rgba(15, 23, 42, 0.92) 100%);
            color: #fff;
        }

        .detail-image-overlay .title-mini {
            margin: 0;
            font-size: 0.74rem;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            opacity: 0.8;
            font-weight: 700;
        }

        .detail-image-overlay .subtitle-mini {
            margin: 0.45rem 0 0;
            font-size: 1.08rem;
            font-weight: 700;
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        .detail-head {
            padding: 1.5rem 1.5rem 1.2rem;
            background: color-mix(in oklab, var(--brand-color) 92%, black);
            color: var(--brand-color);
            border-bottom: 1px solid color-mix(in oklab, var(--brand-color) 20%, white);
        }

        .detail-head .niveau {
            display: inline-block;
            margin-bottom: 0.7rem;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: transparent;
            border: 1.5px solid color-mix(in oklab, var(--brand-color) 40%, white);
            border-radius: 4px;
            padding: 0.35rem 0.65rem;
        }

        .detail-head h1 {
            margin: 0;
            color: var(--text-color);
            font-size: clamp(1.4rem, 3.5vw, 2rem);
            line-height: 1.2;
            font-weight: 700;
        }

        .detail-head p {
            margin: 0.5rem 0 0;
            color: var(--text-muted);
            font-size: 0.93rem;
        }

        .detail-body {
            padding: 1.5rem 1.5rem 1.8rem;
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
            gap: 0.4rem;
            font-size: 0.77rem;
            line-height: 1;
            border-radius: 4px;
            padding: 0.5rem 0.75rem;
            background: #f0f3f8;
            border: 1px solid color-mix(in oklab, var(--brand-color) 20%, white);
            color: var(--text-color);
            font-weight: 600;
        }

        .detail-meta {
            margin: 0 0 0.8rem;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .detail-section-title {
            margin: 1.5rem 0 0.75rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            font-weight: 700;
        }

        .detail-section-title:first-of-type {
            margin-top: 0;
        }

        .detail-desc {
            margin: 0.75rem 0 0;
            color: var(--text-muted);
            line-height: 1.7;
            font-size: 0.96rem;
        }

        .fact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.2rem;
            margin-top: 1.2rem;
        }

        .fact-card {
            border: none;
            border-bottom: 2px solid color-mix(in oklab, var(--brand-color) 30%, white);
            border-radius: 0;
            padding: 0 0 0.85rem 0;
            background: transparent;
            transition: border-color 0.2s ease;
        }

        .fact-card:hover {
            border-bottom-color: color-mix(in oklab, var(--brand-color) 60%, black);
            background: transparent;
            box-shadow: none;
            transform: none;
        }

        .fact-card .fact-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            margin: 0 0 0.5rem;
            font-weight: 700;
        }

        .fact-card .fact-value {
            font-size: 1rem;
            color: var(--text-color);
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.01em;
            line-height: 1.4;
        }

        .schedule-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 0.8rem;
        }

        .schedule-list li {
            font-size: 0.88rem;
            color: var(--text-color);
            line-height: 1.5;
            border: none;
            border-left: 3px solid var(--brand-color);
            border-radius: 0;
            padding: 0.5rem 0 0.5rem 0.85rem;
            background: transparent;
        }

        .side-head {
            padding: 1.35rem 1.35rem 1.15rem;
            background: color-mix(in oklab, var(--brand-color) 92%, black);
            color: var(--brand-color);
            position: relative;
            border-bottom: 1px solid color-mix(in oklab, var(--brand-color) 20%, white);
        }

        .side-head::after {
            display: none;
        }

        .side-head h3 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--text-color);
            font-weight: 700;
        }

        .side-head p {
            margin: 0.4rem 0 0;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .side-body {
            padding: 1.4rem;
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
            margin: 0.15rem 0 1.1rem;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.5rem;
        }

        .side-points li {
            font-size: 0.81rem;
            color: var(--text-muted);
            line-height: 1.5;
            padding-left: 0.3rem;
        }

        .price-box {
            margin-bottom: 1.2rem;
            padding: 1rem 1.05rem;
            border-radius: 8px;
            background: color-mix(in oklab, var(--brand-color) 5%, white);
            border: 1px solid color-mix(in oklab, var(--brand-color) 18%, white);
        }

        .price-label {
            margin: 0;
            font-size: 0.67rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            font-weight: 700;
        }

        .price-value {
            margin: 0.4rem 0 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: color-mix(in oklab, var(--brand-color) 85%, black);
            letter-spacing: -0.01em;
        }

        .field {
            margin-bottom: 1rem;
        }

        .field label {
            display: block;
            margin-bottom: 0.4rem;
            font-size: 0.8rem;
            font-weight: 650;
            color: var(--text-color);
            letter-spacing: -0.01em;
        }

        .field input {
            width: 100%;
            padding: 0.8rem 0.85rem;
            border-radius: 6px;
            border: 1px solid color-mix(in oklab, var(--text-color) 14%, transparent);
            background: #fff;
            color: var(--text-color);
            font: inherit;
            font-size: 0.92rem;
            box-sizing: border-box;
            transition: all 0.16s ease;
        }

        .field input:focus {
            outline: none;
            border-color: var(--brand-color);
            background: #fff;
        }

        .field input::placeholder {
            color: color-mix(in oklab, var(--text-muted) 85%, white);
        }

        .submit-btn {
            width: 100%;
            border: 1px solid var(--brand-color);
            border-radius: 6px;
            padding: 0.92rem 0.86rem;
            font: inherit;
            font-weight: 700;
            font-size: 0.93rem;
            cursor: pointer;
            color: #fff;
            background: var(--brand-color);
            transition: all 0.16s ease;
            letter-spacing: 0.01em;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            background: color-mix(in oklab, var(--brand-color) 85%, black);
            border-color: color-mix(in oklab, var(--brand-color) 85%, black);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .form-msg {
            margin-top: 1rem;
            border-radius: 6px;
            padding: 0.8rem 0.9rem;
            display: none;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .form-msg.ok {
            display: block;
            color: #166534;
            background: #dcfce7;
            border: 1px solid #86efac;
        }

        .form-msg.err {
            display: block;
            color: #991b1b;
            background: #fee2e2;
            border: 1px solid #fca5a5;
        }

        .floating-edit-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.9rem 1.3rem;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            background: var(--brand-color);
            color: #fff;
            box-shadow: 0 8px 24px color-mix(in srgb, var(--brand-color) 45%, transparent);
            transition: all 0.2s ease;
            text-decoration: none;
            z-index: 40;
        }

        .floating-edit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px color-mix(in srgb, var(--brand-color) 50%, transparent);
        }

        .floating-edit-btn:active {
            transform: translateY(0);
        }

        @media (max-width: 640px) {
            .floating-edit-btn {
                bottom: 1.5rem;
                right: 1.5rem;
                padding: 0.8rem 1.1rem;
                font-size: 0.85rem;
            }
        }

        .whatsapp-section {
            margin-top: 1.2rem;
            padding-top: 1.2rem;
            border-top: 1px solid var(--card-border);
        }

        .whatsapp-label {
            margin: 0 0 0.85rem;
            font-size: 0.79rem;
            color: var(--text-muted);
            font-weight: 650;
            letter-spacing: -0.01em;
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

                        <?php if (!empty($kurs['zielgruppe'])): ?>
                            <h2 class="detail-section-title">Zielgruppe</h2>
                            <p class="detail-desc" style="margin-top: 0.5rem;"><?= e($kurs['zielgruppe']) ?></p>
                        <?php endif; ?>

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
                            <?php if (!empty($kurs['max_teilnehmer'])): ?>
                                <div class="fact-card">
                                    <p class="fact-label">Teilnehmende</p>
                                    <p class="fact-value">👥 Max. <?= e((string) $kurs['max_teilnehmer']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($kurs['besonderheit'])): ?>
                                <div class="fact-card">
                                    <p class="fact-label">Besonderheit</p>
                                    <p class="fact-value">✨ <?= e($kurs['besonderheit']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($kurs['beschreibung'])): ?>
                            <p class="detail-desc"><?= renderMarkdown($kurs['beschreibung']) ?></p>
                        <?php endif; ?>
                    </div>
                </article>

                <aside class="detail-side">
                    <div class="side-head">
                        <h3>Jetzt Platz anfragen</h3>
                        <p>In 30 Sekunden abgeschlossen.</p>
                    </div>
                    <div class="side-body">
                        <?php if (!empty($kurs['preis'])): ?>
                            <div class="price-box">
                                <p class="price-label">Investition</p>
                                <p class="price-value">💶 <?= e($kurs['preis']) ?></p>
                            </div>
                        <?php endif; ?>

                        <ul class="side-points">
                            <li>✓ Persönliche Rückmeldung per E-Mail</li>
                            <li>✓ Keine automatische Newsletter-Anmeldung</li>
                            <li>✓ Unverbindliche Anfrage</li>
                        </ul>

                        <form id="anmeldeForm">
                            <input type="hidden" name="kurs" value="<?= e($kurs['name'] ?? '') ?>">

                            <div class="field">
                                <label for="name">Dein Name</label>
                                <input id="name" type="text" name="name" required placeholder="Vollständiger Name">
                            </div>

                            <div class="field">
                                <label for="email">E-Mail</label>
                                <input id="email" type="email" name="email" required placeholder="deine@email.de">
                            </div>

                            <button class="submit-btn" type="submit">Anfrage senden</button>
                        </form>

                        <div id="formMsg" class="form-msg"></div>

                        <?php if (!empty($kurs['telefon'])): ?>
                            <div class="whatsapp-section">
                                <p class="whatsapp-label">Oder per WhatsApp anfragen:</p>
                                <a href="https://wa.me/<?= e(preg_replace('/[^0-9+]/', '', (string) ($kurs['telefon'] ?? ''))) ?>?text=Ich%20interessiere%20mich%20f%C3%BCr%20den%20Kurs%20%22<?= urlencode($kurs['name'] ?? '') ?>%22%20und%20h%C3%A4tte%20gerne%20mehr%20Informationen." target="_blank" rel="noopener noreferrer" class="button-small whatsapp" style="width: 100%; justify-content: center;">
                                    <span>💬</span> WhatsApp Anfrage
                                </a>
                            </div>
                        <?php endif; ?>
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
                                <?php if (!empty($entry['zielgruppe'])): ?>
                                    <p style="font-size: 0.9rem; font-weight: 500; color: color-mix(in oklab, var(--brand-color) 80%, black);">
                                        👥 <?= e($entry['zielgruppe']) ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($entry['beschreibung'])): ?>
                                    <p><?= e(mb_substr($entry['beschreibung'], 0, 120)) ?><?= mb_strlen((string) $entry['beschreibung']) > 120 ? ' ...' : '' ?></p>
                                <?php endif; ?>

                                <div class="course-meta">
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
                                    ?>
                                    
                                    <?php if (!empty($entryZeiten)): ?>
                                        <div class="course-meta-item">
                                            <span class="course-meta-icon">🗓</span>
                                            <div><strong>Termine:</strong><br><?= e($entryZeiten[0]) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['ort'])): ?>
                                        <div class="course-meta-item">
                                            <span class="course-meta-icon">📍</span>
                                            <div><strong>Ort:</strong> <?= e($entry['ort']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['max_teilnehmer'])): ?>
                                        <div class="course-meta-item">
                                            <span class="course-meta-icon">👥</span>
                                            <div><strong>Max.:</strong> <?= e((string) $entry['max_teilnehmer']) ?> Teilnehmende</div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['besonderheit'])): ?>
                                        <div class="course-meta-item">
                                            <span class="course-meta-icon">✨</span>
                                            <div><strong>Besonderheit:</strong> <?= e($entry['besonderheit']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="course-card-footer">
                                <?php if (!empty($entry['preis'])): ?>
                                    <span class="course-price">💶 <?= e($entry['preis']) ?></span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>

                                <div class="button-group">
                                    <a href="kurse.php?slug=<?= e($entry['slug'] ?? '') ?>" class="button-small primary">Platz anfragen</a>
                                    <?php if (!empty($entry['telefon'])): ?>
                                        <a href="https://wa.me/<?= e(preg_replace('/[^0-9+]/', '', (string) ($entry['telefon'] ?? ''))) ?>?text=Ich%20interessiere%20mich%20f%C3%BCr%20den%20Kurs%20%22<?= urlencode($entry['name'] ?? '') ?>%22" target="_blank" rel="noopener noreferrer" class="button-small whatsapp" title="Auf WhatsApp anfragen">
                                            WhatsApp
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php if ($slug !== '' && $kurs !== null && $isLoggedIn): ?>
        <a href="inside/kurs-editor.php?action=edit&slug=<?= e($kurs['slug'] ?? '') ?>" class="floating-edit-btn" title="Diesen Kurs bearbeiten">
            <span>✏️</span> Bearbeiten
        </a>
    <?php endif; ?>

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
                const originalText = btn.textContent;
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
                    btn.textContent = originalText;
                }
            });
        }());
    </script>
</body>
</html>
