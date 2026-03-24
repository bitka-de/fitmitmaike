<?php
require_once 'auth.php';

if (!isSecure()) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <style>
        * {
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #f4f6f8;
            color: #333;
        }

        .wrapper {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            color: #222;
        }

        .subtitle {
            margin-top: 6px;
            color: #666;
            font-size: 15px;
        }

        .logout-btn {
            display: inline-block;
            padding: 10px 16px;
            background: #fff;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: 0.2s;
        }

        .logout-btn:hover {
            background: #f0f0f0;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .card {
            display: block;
            text-decoration: none;
            background: #fff;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            color: inherit;
            border: 1px solid rgba(0,0,0,0.03);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 14px 34px rgba(0,0,0,0.10);
        }

        .icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            font-size: 24px;
        }

        .card h2 {
            margin: 0 0 10px;
            font-size: 20px;
            font-weight: 600;
            color: #222;
        }

        .card p {
            margin: 0;
            color: #666;
            line-height: 1.5;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="wrapper">
    <div class="topbar">
        <div>
            <h1>Dashboard</h1>
            <div class="subtitle">Willkommen im internen Bereich</div>
        </div>

        <a class="logout-btn" href="logout.php">Abmelden</a>
    </div>

    <div class="grid">
        <a class="card" href="media.php">
            <div class="icon">🎬</div>
            <h2>Medien</h2>
            <p>Zur Medienübersicht wechseln und Inhalte verwalten.</p>
        </a>

        <a class="card" href="news-editor.php">
            <div class="icon">📰</div>
            <h2>News</h2>
            <p>Zur Newsübersicht wechseln und Inhalte verwalten.</p>
        </a>

        <a class="card" href="kurs-editor.php">
            <div class="icon">🏋️</div>
            <h2>Kurse</h2>
            <p>Kurse anlegen, bearbeiten und verwalten.</p>
        </a>
    </div>
</div>

</body>
</html>