<?php
declare(strict_types=1);

$apiUrl = 'https://oneal-api.arkturian.com/v1/products?limit=1000';
$apiKey = 'oneal_demo_token';

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $status !== 200) {
    http_response_code(500);
    echo '<h1>Fehler beim Abrufen der Oneal API</h1>';
    echo '<p>Status: ' . htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<pre>' . htmlspecialchars($error ?: 'Unbekannter Fehler', ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$payload = json_decode($response, true);
if (!is_array($payload) || !isset($payload['results']) || !is_array($payload['results'])) {
    http_response_code(500);
    echo '<h1>API Antwort konnte nicht verarbeitet werden</h1>';
    echo '<pre>' . htmlspecialchars($response, ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

$products = $payload['results'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Oneal API Testliste</title>
  <style>
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 24px; background: #f5f7fb; color: #0f172a; }
    header { margin-bottom: 24px; }
    header h1 { margin: 0 0 8px; }
    header p { margin: 4px 0; color: #475569; }
    ul.product-list { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
    li.product-card { background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); padding: 16px; display: flex; flex-direction: column; }
    .thumb { width: 100%; aspect-ratio: 1 / 1; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; overflow: hidden; }
    .thumb img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .meta { font-size: 13px; line-height: 1.4; color: #475569; }
    .meta strong { color: #0f172a; font-weight: 600; }
    .tag { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 12px; margin-right: 6px; margin-bottom: 6px; }
    .section { margin-top: 12px; }
    .section h3 { font-size: 14px; margin: 0 0 6px; color: #1e293b; }
    .json-dump { font-family: "Fira Code", monospace; font-size: 12px; background: #f1f5f9; padding: 8px; border-radius: 8px; overflow-x: auto; max-height: 180px; }
    details summary { cursor: pointer; font-weight: 600; margin-top: 10px; }
    .stats { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 12px; font-size: 13px; }
    .stats span { background: #e2e8f0; border-radius: 999px; padding: 4px 10px; }
  </style>
</head>
<body>
  <header>
    <h1>Oneal Produkt-API – Rohdaten</h1>
    <p>Total: <?= htmlspecialchars((string)count($products), ENT_QUOTES, 'UTF-8') ?> Produkte</p>
    <p>Quelle: <code><?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
  </header>

  <ul class="product-list">
    <?php foreach ($products as $product): ?>
      <?php
        $id = htmlspecialchars($product['id'] ?? '–', ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($product['name'] ?? 'Unbenannt', ENT_QUOTES, 'UTF-8');
        $categories = $product['category'] ?? [];
        $categoryDisplay = array_map(fn($c) => htmlspecialchars($c, ENT_QUOTES, 'UTF-8'), $categories);
        $derived = $product['derived_taxonomy'] ?? [];
        $meta = $product['meta'] ?? [];
        $price = $product['price'] ?? null;
        $media = $product['media'][0] ?? null;

        $imageUrl = null;
        if ($media) {
          if (isset($media['storage_id'])) {
            $imageUrl = sprintf(
              'https://share.arkturian.com/proxy.php?id=%d&width=200&format=webp&quality=75',
              (int)$media['storage_id']
            );
          } elseif (isset($media['src'])) {
            $imageUrl = $media['src'];
          }
        }
      ?>
      <li class="product-card">
        <div class="thumb">
          <?php if ($imageUrl): ?>
            <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $name ?>">
          <?php else: ?>
            <span>Kein Bild</span>
          <?php endif; ?>
        </div>

        <div class="meta">
          <strong><?= $name ?></strong><br>
          <small><?= $id ?></small>
        </div>

        <div class="stats">
          <?php if ($price): ?>
            <span>Preis: <?= htmlspecialchars($price['formatted'] ?? number_format($price['value'] ?? 0, 2) . ' ' . ($price['currency'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <?php if (!empty($meta['source'])): ?>
            <span>Quelle: <?= htmlspecialchars($meta['source'], ENT_QUOTES, 'UTF-8') ?></span>
          <?php endif; ?>
          <?php if (!empty($product['variants'])): ?>
            <span>Varianten: <?= count($product['variants']) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($categoryDisplay): ?>
          <div class="section">
            <h3>Kategorien</h3>
            <?php foreach ($categoryDisplay as $cat): ?>
              <span class="tag"><?= $cat ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($derived): ?>
          <div class="section">
            <h3>Derived Taxonomy</h3>
            <div class="json-dump"><?= htmlspecialchars(json_encode($derived, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        <?php endif; ?>

        <?php if ($meta): ?>
          <div class="section">
            <h3>Meta</h3>
            <div class="json-dump"><?= htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($product['variants'])): ?>
          <details>
            <summary>Varianten (<?= count($product['variants']) ?>)</summary>
            <div class="json-dump"><?= htmlspecialchars(json_encode($product['variants'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?></div>
          </details>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
