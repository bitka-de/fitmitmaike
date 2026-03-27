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

    $now = time();

    $getNextFuture = static function (array $kurs) use ($now): int {
        $slots = array_merge(
            (array) ($kurs['termine'] ?? []),
            (array) ($kurs['zeiten'] ?? [])
        );

        $earliest = PHP_INT_MAX;
        foreach ($slots as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $datum = trim((string) ($slot['datum'] ?? ''));
            $von = trim((string) ($slot['von'] ?? '00:00'));
            if ($datum === '') {
                continue;
            }

            $ts = strtotime($datum . ' ' . $von);
            if ($ts !== false && $ts > $now && $ts < $earliest) {
                $earliest = $ts;
            }
        }

        return $earliest;
    };

    // Remove courses with no future appointments
    $kurse = array_values(array_filter(
        $kurse,
        static fn(array $k): bool => $getNextFuture($k) < PHP_INT_MAX
    ));

    // Sort by soonest next appointment
    usort(
        $kurse,
        static fn(array $a, array $b): int => $getNextFuture($a) <=> $getNextFuture($b)
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

function formatKursDauer(array $slot): string
{
    $von = trim((string) ($slot['von'] ?? ''));
    $bis = trim((string) ($slot['bis'] ?? ''));

    if ($von === '' || $bis === '') {
        return '';
    }

    $start = DateTime::createFromFormat('H:i', $von);
    $ende = DateTime::createFromFormat('H:i', $bis);

    if (!$start || !$ende) {
        return '';
    }

    $minuten = ((int) $ende->format('H')) * 60 + (int) $ende->format('i') - (((int) $start->format('H')) * 60 + (int) $start->format('i'));
    if ($minuten <= 0) {
        return '';
    }

    return $minuten . ' min';
}

function parseKursSlotDateTime(array $slot): ?DateTimeImmutable
{
    $datumRaw = trim((string) ($slot['datum'] ?? ''));
    if ($datumRaw === '') {
        return null;
    }

    $von = trim((string) ($slot['von'] ?? '00:00'));
    $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $datumRaw . ' ' . $von);
    if ($dateTime instanceof DateTimeImmutable) {
        return $dateTime;
    }

    $dateOnly = DateTimeImmutable::createFromFormat('!Y-m-d', $datumRaw);
    return $dateOnly instanceof DateTimeImmutable ? $dateOnly : null;
}

function formatKursTerminHinweis(DateTimeImmutable $dateTime, DateTimeImmutable $now): string
{
    $startOfTarget = $dateTime->setTime(0, 0);
    $startOfToday = $now->setTime(0, 0);
    $days = (int) $startOfToday->diff($startOfTarget)->format('%r%a');

    if ($days <= 0) {
        return 'Heute';
    }

    if ($days === 1) {
        return 'Morgen';
    }

    return 'In ' . $days . ' Tagen';
}

function buildZeitSlotItems(array $slots): array
{
    $items = [];
    $now = new DateTimeImmutable('now');
    $nextUpcomingIndex = null;
    $nextUpcomingTimestamp = null;

    foreach ($slots as $slot) {
        if (!is_array($slot)) {
            continue;
        }

        $label = formatZeitSlot($slot);
        if ($label === '') {
            continue;
        }

        $dateTime = parseKursSlotDateTime($slot);
        $isPast = $dateTime instanceof DateTimeImmutable ? $dateTime < $now : false;

        $items[] = [
            'label' => $label,
            'dateTime' => $dateTime,
            'isPast' => $isPast,
        ];

        if ($dateTime instanceof DateTimeImmutable && !$isPast) {
            $timestamp = $dateTime->getTimestamp();
            if ($nextUpcomingTimestamp === null || $timestamp < $nextUpcomingTimestamp) {
                $nextUpcomingTimestamp = $timestamp;
                $nextUpcomingIndex = array_key_last($items);
            }
        }
    }

    foreach ($items as $index => $item) {
        $state = 'neutral';
        $hint = '';

        if ($item['isPast']) {
            $state = 'past';
        } elseif ($nextUpcomingIndex !== null && $index === $nextUpcomingIndex && $item['dateTime'] instanceof DateTimeImmutable) {
            $state = 'next';
            $hint = formatKursTerminHinweis($item['dateTime'], $now);
        }

        $items[$index]['state'] = $state;
        $items[$index]['hint'] = $hint;
    }

    return $items;
}

function kursExcerpt(string $text, int $length = 160): string
{
    $text = preg_replace('/[*_`>#]+/u', '', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

    if ($text === '') {
        return '';
    }

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . ' ...';
}

function renderMarkdown(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $text);
    $text = preg_replace('/^### (.+?)$/m', '<h3 class="subheadline heading">$1</h3>', $text);
    $text = preg_replace('/^## (.+?)$/m', '<h2 class="subheadline heading">$1</h2>', $text);

    $lines = explode("\n", $text);
    $inList = false;
    $result = [];

    foreach ($lines as $line) {
        if (preg_match('/^- (.+)$/', $line, $matches)) {
            if (!$inList) {
                $result[] = '<ul class="about-list">';
                $inList = true;
            }
            $result[] = '<li>' . trim($matches[1]) . '</li>';
            continue;
        }

        if ($inList) {
            $result[] = '</ul>';
            $inList = false;
        }

        if (trim($line) !== '') {
            $result[] = '<p class="copy">' . $line . '</p>';
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
</head>

<body>



    <main class="kurs-page">

    

        <?php if ($slug !== '' && $kurs !== null): ?>
            <?php
            $detailImageUrl = resolveKursImageUrl((string) ($kurs['bild'] ?? ''));
            $zeitSlotSource = (array) ($kurs['termine'] ?? []);
            $zeitSlotItems = buildZeitSlotItems($zeitSlotSource);

            if (empty($zeitSlotItems)) {
                $zeitSlotSource = (array) ($kurs['zeiten'] ?? []);
                $zeitSlotItems = buildZeitSlotItems($zeitSlotSource);
            }

            $kontaktEmail = 'maike.kropff-personaltraining@outlook.de';
            $whatsAppRaw = trim((string) ($kurs['telefon'] ?? '+49 160 98755921'));
            $whatsAppNumber = preg_replace('/[^0-9]/', '', $whatsAppRaw) ?? '';
            $whatsAppText = urlencode('Ich interessiere mich für den Kurs "' . (string) ($kurs['name'] ?? '') . '" und hätte gerne mehr Informationen.');
            ?>

            <section class="space center courses-layout">

                <header class="timeline-header px courses-header">
                    <div class="buttons" style="margin-top: 0;">
                        <a href="kurse.php" class="button">Zur Kursübersicht</a>
                        <a href="#kurs-kontakt" class="button primary">Platz anfragen</a>
                    </div>

                    <h1 class="headline heading mt"><?= e($kurs['name'] ?? '') ?></h1>
                    <?php if (!empty($kurs['zielgruppe'])): ?>
                        <p class="lead"><?= e((string) $kurs['zielgruppe']) ?></p>
                    <?php endif; ?>
                </header>

                <div class="course-detail-layout px">
                    <div class="course-detail-main">
                        <?php if ($detailImageUrl !== ''): ?>
                            <article class="course-detail-card course-detail-card--hero">
                                <div class="course-detail-media">
                                    <img src="<?= e($detailImageUrl) ?>" alt="Kursbild <?= e($kurs['name'] ?? '') ?>" loading="lazy">
                                </div>
                            </article>
                        <?php endif; ?>

                        <?php if (!empty($kurs['beschreibung'])): ?>
                            <article class="course-detail-card course-detail-card--text">
                                <div class="course-detail-body space-y">
                                    <span class="eyebrow heading">Über den Kurs</span>

                                    <?= renderMarkdown((string) $kurs['beschreibung']) ?>
                                </div>
                            </article>
                        <?php endif; ?>

                        <?php if (!empty($zeitSlotItems)): ?>
                            <article class="course-detail-card">
                                <div class="course-detail-body">

                                    <h2 class="subheadline heading"><?= count($zeitSlotItems) ?> <?= count($zeitSlotItems) === 1 ? 'Termin' : 'Termine' ?></h2>
                                    <?php if (!empty($kurs['ort'])): ?>
                                        <p class="course-time-location"><strong><?= e((string) $kurs['ort']) ?></strong></p>
                                    <?php endif; ?>
                                    <ul class="course-time-list">
                                        <?php foreach ($zeitSlotItems as $slotItem): ?>
                                            <li class="course-time-list__item course-time-list__item--<?= e($slotItem['state']) ?>">
                                                <span class="course-time-list__label"><?= e($slotItem['label']) ?></span>
                                                <?php if ($slotItem['hint'] !== ''): ?>
                                                    <span class="course-time-list__hint"><?= e($slotItem['hint']) ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </article>
                        <?php endif; ?>

                        <article class="course-detail-card">
                            <div class="course-detail-body">
                                <span class="eyebrow heading">Kursinfos</span>
                                <ul class="course-meta-list">
                                    <?php if (!empty($kurs['niveau'])): ?><li>Niveau: <?= e($kurs['niveau']) ?></li><?php endif; ?>
                                    <?php if (!empty($kurs['max_teilnehmer'])): ?><li>Teilnehmende: Max. <?= e((string) $kurs['max_teilnehmer']) ?></li><?php endif; ?>
                                    <?php if (!empty($kurs['besonderheit'])): ?><li>Besonderheit: <?= e($kurs['besonderheit']) ?></li><?php endif; ?>
                                </ul>
                            </div>
                        </article>
                    </div>

                    <aside class="course-detail-sidebar" id="kurs-kontakt" aria-labelledby="kursKontaktTitel">
                        <article class="course-request-card">
                            <span class="eyebrow heading">Anfrage</span>
                            <h2 class="subheadline heading" id="kursKontaktTitel">Unverbindlich anfragen</h2>

                            <?php if (!empty($kurs['preis'])): ?>
                                <p class="course-request-card__price"><?= e((string) $kurs['preis']) ?></p>
                            <?php endif; ?>

                            <p class="copy">Schreib mir per Kontaktformular oder WhatsApp. Du bekommst persönlich eine Antwort mit allen Infos zur Anmeldung.</p>

                            <div class="buttons course-request-card__actions">
                                <?php if ($whatsAppNumber !== ''): ?>
                                    <a href="https://wa.me/<?= e($whatsAppNumber) ?>?text=<?= $whatsAppText ?>" target="_blank" rel="noopener noreferrer" class="button-whatsapp">
                                        <svg fill-rule="evenodd" clip-rule="evenodd" image-rendering="optimizeQuality" shape-rendering="geometricPrecision" text-rendering="geometricPrecision" viewBox="0 0 510 512.46">
                                            <path fill="currentColor" d="M435.69 74.47C387.75 26.47 324 .03 256.07 0 116.1 0 2.18 113.9 2.13 253.92a253.4 253.4 0 0 0 33.9 126.94L0 512.46l134.62-35.31a253.6 253.6 0 0 0 121.34 30.9h.11c139.95 0 253.88-113.92 253.93-253.93.02-67.85-26.36-131.64-74.31-179.64zm-179.62 390.7H256a211 211 0 0 1-107.43-29.42l-7.7-4.58-79.9 20.96 21.33-77.9-5.02-7.98A210.5 210.5 0 0 1 45 253.93c.05-116.37 94.73-211.05 211.16-211.05 56.37.03 109.36 22 149.21 61.9a209.8 209.8 0 0 1 61.76 149.32c-.05 116.38-94.73 211.06-211.06 211.06M371.84 307.1c-6.34-3.18-37.54-18.52-43.36-20.64s-10.04-3.17-14.27 3.18c-4.22 6.36-16.39 20.65-20.09 24.88s-7.4 4.76-13.75 1.58-26.78-9.88-51.02-31.49c-18.86-16.83-31.6-37.6-35.3-43.95s-.4-9.8 2.77-12.95c2.85-2.85 6.35-7.41 9.53-11.11s4.22-6.36 6.34-10.58c2.12-4.24 1.06-7.94-.52-11.12-1.59-3.18-14.27-34.41-19.57-47.1-5.15-12.38-10.39-10.7-14.27-10.9-3.7-.19-7.93-.23-12.16-.23s-11.11 1.59-16.93 7.94-22.2 21.71-22.2 52.93 22.72 61.4 25.9 65.64S197.7 331.5 261.34 359a364 364 0 0 0 36.17 13.37c15.2 4.84 29.02 4.16 39.96 2.52 12.19-1.82 37.54-15.35 42.82-30.17s5.28-27.53 3.7-30.17-5.82-4.24-12.16-7.42z" />
                                        </svg>
                                        Per WhatsApp anfragen
                                    </a>
                                <?php endif; ?>
                                <button type="button" class="button primary js-toggle-request-form" aria-controls="kursAnfrageFormPanel" aria-expanded="false">Per Kontaktformular anfragen</button>
                            </div>

                            <div class="course-request-card__form-panel" id="kursAnfrageFormPanel" hidden>
                                <form class="course-request-form" id="anmeldeForm">
                                    <input type="hidden" name="kurs" value="<?= e($kurs['name'] ?? '') ?>">

                                    <div class="course-request-form__field">
                                        <label for="name">Dein Name</label>
                                        <input id="name" type="text" name="name" required placeholder="Vollständiger Name" autocomplete="name">
                                    </div>

                                    <div class="course-request-form__field">
                                        <label for="email">Deine E-Mail</label>
                                        <input id="email" type="email" name="email" required placeholder="deine@email.de" autocomplete="email">
                                    </div>

                                    <div class="course-request-form__field">
                                        <label for="fragen">Fragen zum Kurs (optional)</label>
                                        <textarea id="fragen" name="fragen" placeholder="Hast du Fragen zum Kurs? Schreib sie hier auf..."></textarea>
                                    </div>

                                    <button class="button big primary submit-btn" type="submit">Anfrage senden</button>

                                    <p class="course-request-form__note">Unverbindlich und kostenlos. Du erhältst zeitnah eine persönliche Rückmeldung per E-Mail.</p>
                                    <p id="formMsg" class="course-request-form__note" hidden></p>
                                </form>
                            </div>

                            <p class="course-request-card__note">Hinweis: Das ist eine unverbindliche Anfrage. Du erhältst zeitnah eine persönliche Rückmeldung.</p>
                        </article>
                    </aside>
                </div>
            </section>

        <?php elseif ($slug !== '' && $kurs === null): ?>
            <section class="space center courses-layout">
                
                <div class="course-detail-grid px">
                    <article class="course-detail-card course-detail-card--text">
                        <div class="course-detail-body">
                            <span class="eyebrow heading">Kurse</span>
                            <h1 class="headline heading">Kurs nicht gefunden</h1>
                            <p class="copy">Der angefragte Kurs existiert nicht oder wurde entfernt.</p>
                            <div class="buttons">
                                <a href="kurse.php" class="button primary">Zur Kursübersicht</a>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

        <?php else: ?>
            <section class="center courses-layout">
                
                <header class="timeline-header px courses-header">
                    <div class="buttons" style="margin-top: 0; margin-bottom: 1.5rem;">
                        <a href="/" class="button">Zur Startseite</a>
                    </div>
                    <h1 class="headline heading">Unsere Kurse</h1>
                    <p class="lead">Wähle den passenden Kurs und sichere dir deinen Platz mit einer direkten Online-Anfrage.</p>
                </header>
            </section>

            <?php if (empty($allKurse)): ?>
                <section class="center courses-layout">
                    <div class="course-detail-grid px">
                        <article class="course-detail-card course-detail-card--text">
                            <div class="course-detail-body">
                                <span class="eyebrow heading">Aktuell leer</span>
                                <h2 class="subheadline heading">Aktuell keine Kurse online</h2>
                                <p class="copy">Sobald neue Kurse verfügbar sind, erscheinen sie hier.</p>
                            </div>
                        </article>
                    </div>
                </section>
            <?php else: ?>
                <section class="space center courses-layout" id="kursliste">
                    <div class="courses-grid px">
                        <?php foreach ($allKurse as $entry): ?>
                            <?php
                            $listImageUrl = resolveKursImageUrl((string) ($entry['bild'] ?? ''));
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

                            $dauerChip = '';
                            foreach ((array) ($entry['termine'] ?? []) as $slot) {
                                if (!is_array($slot)) {
                                    continue;
                                }

                                $dauerChip = formatKursDauer($slot);
                                if ($dauerChip !== '') {
                                    break;
                                }
                            }

                            if ($dauerChip === '') {
                                foreach ((array) ($entry['zeiten'] ?? []) as $slot) {
                                    if (!is_array($slot)) {
                                        continue;
                                    }

                                    $dauerChip = formatKursDauer($slot);
                                    if ($dauerChip !== '') {
                                        break;
                                    }
                                }
                            }

                            $eyebrowText = '';
                            $eyebrowNow = new DateTimeImmutable('today');
                            $eyebrowSlots = array_merge(
                                (array) ($entry['termine'] ?? []),
                                (array) ($entry['zeiten'] ?? [])
                            );
                            foreach ($eyebrowSlots as $eyebrowSlot) {
                                if (!is_array($eyebrowSlot)) {
                                    continue;
                                }
                                $eyebrowDt = parseKursSlotDateTime($eyebrowSlot);
                                if ($eyebrowDt !== null && $eyebrowDt >= $eyebrowNow) {
                                    $eyebrowText = formatZeitSlot($eyebrowSlot);
                                    break;
                                }
                            }
                            if ($eyebrowText === '' && !empty($entry['niveau'])) {
                                $eyebrowText = (string) $entry['niveau'];
                            }

                            $cardTags = [];
                            if (!empty($entry['niveau'])) {
                                $cardTags[] = (string) $entry['niveau'];
                            }
                            if (!empty($entry['ort'])) {
                                $cardTags[] = 'Ort: ' . (string) $entry['ort'];
                            }
                            if (count($entryZeiten) > 1) {
                                $cardTags[] = count($entryZeiten) . ' Termine';
                            }
                            $cardTags = array_slice($cardTags, 0, 3);
                            ?>

                            <article class="course-card-clean">
                                <?php if ($listImageUrl !== ''): ?>
                                    <div class="course-card-clean__media">
                                        <img src="<?= e($listImageUrl) ?>" alt="Kursbild <?= e($entry['name'] ?? '') ?>" loading="lazy">
                                        <div class="course-card-clean__title-overlay">
                                            <h2 class="subheadline heading"><?= e($entry['name'] ?? '') ?></h2>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="course-card-clean__body">
                                    <?php if ($eyebrowText !== '' || $dauerChip !== ''): ?>
                                        <div class="course-card-clean__meta">
                                            <?php if ($eyebrowText !== ''): ?>
                                                <span class="eyebrow heading"><?= e($eyebrowText) ?></span>
                                            <?php endif; ?>
                                            <?php if ($dauerChip !== ''): ?>
                                                <span class="course-chip"><?= e($dauerChip) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($listImageUrl === ''): ?>
                                        <h2 class="subheadline heading"><?= e($entry['name'] ?? '') ?></h2>
                                    <?php endif; ?>

                                    <?php if (!empty($entry['beschreibung'])): ?>
                                        <p class="copy"><?= e(kursExcerpt((string) $entry['beschreibung'], 150)) ?></p>
                                    <?php elseif (!empty($entry['zielgruppe'])): ?>
                                        <p class="copy"><?= e((string) $entry['zielgruppe']) ?></p>
                                    <?php elseif (!empty($entry['besonderheit'])): ?>
                                        <p class="copy"><?= e((string) $entry['besonderheit']) ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($cardTags)): ?>
                                        <div class="course-card-clean__tags">
                                            <?php foreach ($cardTags as $tag): ?>
                                                <span><?= e($tag) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="buttons">
                                        <a href="kurse.php?slug=<?= e($entry['slug'] ?? '') ?>" class="button primary">Details &amp; Anfrage</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
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
        (function() {
            const formToggleBtn = document.querySelector('.js-toggle-request-form');
            const formPanel = document.getElementById('kursAnfrageFormPanel');

            if (formToggleBtn && formPanel) {
                formToggleBtn.addEventListener('click', function() {
                    const expanded = formToggleBtn.getAttribute('aria-expanded') === 'true';
                    formToggleBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                    formPanel.hidden = expanded;

                    if (!expanded) {
                        const firstField = formPanel.querySelector('input[name="name"]');
                        if (firstField) {
                            firstField.focus();
                        }
                    }
                });
            }

            const form = document.getElementById('anmeldeForm');
            const msg = document.getElementById('formMsg');
            if (!form || !msg) {
                return;
            }

            form.addEventListener('submit', async function(event) {
                event.preventDefault();
                const btn = form.querySelector('.submit-btn');

                msg.textContent = '';
                msg.hidden = true;

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
                    msg.hidden = false;

                    if (data.success) {
                        form.reset();
                    }
                } catch (error) {
                    msg.textContent = 'Ein Fehler ist aufgetreten. Bitte versuche es später erneut.';
                    msg.hidden = false;
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        }());
    </script>
</body>

</html>