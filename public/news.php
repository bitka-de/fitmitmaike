<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

const DATA_DIR   = __DIR__ . '/inside/news_data';
const UPLOAD_DIR = __DIR__ . '/inside/uploads';

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

    return $slug;
}

function getPostFilePath(string $slug): string
{
    return DATA_DIR . '/' . $slug . '.json';
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

function buildImageUrl(string $relativePath): string
{
    $parts = explode('/', str_replace('\\', '/', $relativePath));
    $parts = array_map('rawurlencode', $parts);
    return 'inside/uploads/' . implode('/', $parts);
}

$slug = isset($_GET['slug']) ? normalizeSlug((string) $_GET['slug']) : '';
$post = $slug !== '' ? loadPost($slug) : null;
$allPosts = loadAllPosts();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>News</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #222;
        }
        .wrap {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 6px 25px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .item {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 25px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .item img,
        .single img {
            width: 100%;
            display: block;
            object-fit: cover;
        }
        .item img {
            max-height: 260px;
        }
        .single img {
            max-height: 420px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .post-body {
            padding: 16px;
        }
        .meta {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .preview-text {
            color: #4b5563;
            font-style: italic;
            margin-bottom: 16px;
        }
        .content-rendered {
            line-height: 1.7;
        }
        .content-rendered img {
            max-width: 100%;
            height: auto;
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
    </style>
</head>
<body>

<div class="wrap">

<?php if ($slug !== ''): ?>

    <?php if ($post === null): ?>
        <div class="card">
            <h1>Beitrag nicht gefunden</h1>
            <a class="btn secondary" href="news.php">Zurück zur Übersicht</a>
        </div>
    <?php else: ?>
        <div class="card single">
            <a class="btn secondary" href="/#news" style="margin-bottom:15px;">Zurück zur Übersicht</a>

            <h1><?= e($post['headline'] ?? '') ?></h1>

            <div class="meta">
                Veröffentlicht: <?= e($post['created_at'] ?? '') ?>
                <?php if (!empty($post['updated_at'])): ?>
                    | Aktualisiert: <?= e($post['updated_at']) ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($post['image'])): ?>
                <img src="<?= e(buildImageUrl($post['image'])) ?>" alt="<?= e($post['headline'] ?? '') ?>">
            <?php endif; ?>

            <?php if (!empty($post['preview'])): ?>
                <div class="preview-text"><?= nl2br(e($post['preview'])) ?></div>
            <?php endif; ?>

            <div class="content-rendered">
                <?= $post['content'] ?? '' ?>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>

    <div class="card">
        <h1>News</h1>
        <p>Alle veröffentlichten Beiträge.</p>
    </div>

    <?php if (empty($allPosts)): ?>
        <div class="card">
            <p>Aktuell sind noch keine News vorhanden.</p>
        </div>
    <?php else: ?>
        <?php foreach ($allPosts as $entry): ?>
            <div class="item">
                <?php if (!empty($entry['image'])): ?>
                    <img src="<?= e(buildImageUrl($entry['image'])) ?>" alt="<?= e($entry['headline'] ?? '') ?>">
                <?php endif; ?>

                <div class="post-body">
                    <div class="meta"><?= e($entry['created_at'] ?? '') ?></div>
                    <h2><?= e($entry['headline'] ?? '') ?></h2>

                    <?php if (!empty($entry['preview'])): ?>
                        <p><?= nl2br(e($entry['preview'])) ?></p>
                    <?php endif; ?>

                    <a class="btn" href="news.php?slug=<?= e($entry['slug'] ?? '') ?>">Weiterlesen</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

</div>

</body>
</html>