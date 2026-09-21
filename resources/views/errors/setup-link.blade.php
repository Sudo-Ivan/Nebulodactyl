<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup link expired - Nebulodactyl</title>
<style>
    body {
        background: #0a0a0b;
        color: #fafafa;
        font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
    }
    .card {
        max-width: 460px;
        padding: 32px;
        border: 1px solid #1f1f24;
        border-radius: 12px;
        background: #101013;
    }
    h1 { font-size: 20px; margin: 0 0 12px; }
    p { color: #a1a1aa; font-size: 14px; line-height: 1.6; margin: 0 0 12px; }
    code {
        background: #16161a;
        border: 1px solid #1f1f24;
        border-radius: 6px;
        padding: 2px 8px;
        font-size: 13px;
        color: #fafafa;
    }
</style>
</head>
<body>
    <div class="card">
        <h1>Setup link expired</h1>
        <p>This setup link is missing, invalid, or older than one hour.</p>
        <p>Generate a fresh link on the panel host:</p>
        <p><code>php artisan p:setup:link</code></p>
        <p>With Docker Compose:</p>
        <p><code>docker compose exec panel php artisan p:setup:link</code></p>
        <p>Or create the administrator directly:</p>
        <p><code>docker compose exec panel php artisan p:user:make</code></p>
    </div>
</body>
</html>
