<?php
declare(strict_types=1);

/**
 * Storage warmup monitor for Oneal tenant.
 *
 * Lists objects from storage API and lets you manually trigger warmups
 * (130px + 1300px WebP derivatives with refresh=true).
 * Also checks cached derivatives via HEAD requests to storage API.
 */

@set_time_limit(0);
@ini_set('memory_limit', '512M');

const STORAGE_API_BASE = 'https://api-storage.arkturian.com';
const STORAGE_API_KEY = 'oneal_demo_token';
const SHARE_PROXY_BASE = 'https://share.arkturian.com/proxy.php';

$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = (int)($_GET['limit'] ?? 25);
if ($limit < 1) $limit = 1;
if ($limit > 100) $limit = 100;
$checkStatus = isset($_GET['check']) ? (bool)(int)$_GET['check'] : true;

// Warmup action
$warmupLog = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['warmup_action'])) {
    $ids = [];
    if (!empty($_POST['warmup_ids']) && is_array($_POST['warmup_ids'])) {
        foreach ($_POST['warmup_ids'] as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }
    if (!$ids) {
        $warmupLog[] = ['level' => 'warn', 'message' => 'Keine gültigen Objekt-IDs übergeben.'];
    } else {
        $warmupLog[] = ['level' => 'info', 'message' => sprintf('Starte Warmup für %d Objekte …', count($ids))];
        foreach ($ids as $objectId) {
            foreach ([['w' => 130, 'quality' => 75], ['w' => 1300, 'quality' => 85]] as $cfg) {
                $result = warmupDerivative($objectId, $cfg['w'], $cfg['quality']);
                $warmupLog[] = $result;
            }
        }
    }
}

$listResponse = fetchStorageList($offset, $limit);
if (!$listResponse['success']) {
    http_response_code(502);
    echo '<h1>Storage-API konnte nicht geladen werden</h1>';
    echo '<pre>' . htmlspecialchars($listResponse['error'], ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}
$items = $listResponse['items'];
$total = $listResponse['total'];

// Optionally check derivative status for each object
if ($checkStatus) {
    foreach ($items as &$item) {
        $id = (int)$item['id'];
        $item['status'] = [
            '130' => checkDerivative($id, 130, 75),
            '1300' => checkDerivative($id, 1300, 85),
        ];
    }
    unset($item);
}

function fetchStorageList(int $offset, int $limit): array
{
    $url = STORAGE_API_BASE . '/storage/list?mine=true&limit=' . $limit . '&offset=' . $offset . '&_=' . time();
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-API-KEY: ' . STORAGE_API_KEY,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) {
        return [
            'success' => false,
            'error' => sprintf('Storage API Fehler (%s): %s', $code, $error ?: 'unbekannt'),
        ];
    }
    $json = json_decode($body, true);
    if (!is_array($json) || !isset($json['items']) || !is_array($json['items'])) {
        return [
            'success' => false,
            'error' => 'Unerwartete Storage-Antwort: ' . substr($body, 0, 500),
        ];
    }
    return [
        'success' => true,
        'items' => $json['items'],
        'total' => (int)($json['total'] ?? count($json['items'])),
    ];
}

function warmupDerivative(int $objectId, int $width, int $quality): array
{
    $url = sprintf(
        '%s/storage/media/%d?width=%d&format=webp&quality=%d&refresh=true',
        STORAGE_API_BASE,
        $objectId,
        $width,
        $quality
    );
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['X-API-KEY: ' . STORAGE_API_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $duration = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    if ($code !== 200 || $body === false) {
        return [
            'level' => 'error',
            'message' => sprintf('Warmup %d (width=%d) fehlgeschlagen: %s (%s)', $objectId, $width, $error ?: 'HTTP ' . $code, $url),
        ];
    }
    return [
        'level' => 'success',
        'message' => sprintf('Warmup %d (width=%d) OK in %.2fs (%.1f KB)', $objectId, $width, $duration, strlen($body) / 1024),
    ];
}

function checkDerivative(int $objectId, int $width, int $quality): array
{
    $url = sprintf(
        '%s/storage/media/%d?width=%d&format=webp&quality=%d',
        STORAGE_API_BASE,
        $objectId,
        $width,
        $quality
    );
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['X-API-KEY: ' . STORAGE_API_KEY],
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $bytes = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $code,
        'bytes' => $bytes,
        'time' => $time,
        'error' => $error ?: null,
        'url' => $url,
    ];
}

function formatStatus(?array $status): string
{
    if (!$status) {
        return '<span class="status status-unknown">–</span>';
    }
    $code = (int)$status['code'];
    $class = 'status-unknown';
    if ($code >= 200 && $code < 300) {
        $class = 'status-ok';
    } elseif ($code >= 300 && $code < 500) {
        $class = 'status-warn';
    } elseif ($code >= 500) {
        $class = 'status-error';
    }
    $size = $status['bytes'] !== -1 ? sprintf('%.1f KB', ($status['bytes'] ?? 0) / 1024) : 'n/a';
    $time = sprintf('%.2fs', $status['time'] ?? 0);
    $html = sprintf('<span class="status %s">%s • %s • %s</span>', $class, $code, $size, $time);
    if (!empty($status['error'])) {
        $html .= '<br><small class="error">' . htmlspecialchars($status['error'], ENT_QUOTES, 'UTF-8') . '</small>';
    }
    return $html;
}

function shareUrl(int $objectId, int $width): string
{
    $quality = $width >= 1000 ? 85 : 75;
    return sprintf('%s?id=%d&width=%d&format=webp&quality=%d', SHARE_PROXY_BASE, $objectId, $width, $quality);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Storage Warmup Monitor</title>
  <style>
    :root {
      color-scheme: light dark;
    }
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f5f7fb;
      color: #0f172a;
      margin: 24px;
    }
    h1 { margin-bottom: 8px; }
    a { color: #0369a1; }
    .controls, .log { margin-bottom: 20px; }
    .controls form { display: inline-flex; gap: 12px; align-items: center; margin-right: 16px; }
    label { font-size: 13px; color: #475569; }
    input[type=number] { width: 80px; padding: 6px; border-radius: 6px; border: 1px solid #cbd5f5; background: white; }
    input[type=checkbox] { transform: translateY(1px); }
    button {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 6px;
      padding: 8px 14px;
      font-size: 13px;
      cursor: pointer;
    }
    button.secondary {
      background: #e2e8f0;
      color: #1e293b;
    }
    table {
      border-collapse: collapse;
      width: 100%;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
    }
    th, td {
      padding: 12px;
      border-bottom: 1px solid #e2e8f0;
      font-size: 13px;
      vertical-align: top;
    }
    th {
      background: #eef2ff;
      color: #1e293b;
      text-align: left;
    }
    tr:nth-child(even) td { background: #f8fafc; }
    .status { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
    .status-ok { background: #dcfce7; color: #166534; }
    .status-warn { background: #fee2e2; color: #b91c1c; }
    .status-error { background: #fecdd3; color: #9f1239; }
    .status-unknown { background: #e2e8f0; color: #334155; }
    small.error { color: #b91c1c; display: block; margin-top: 4px; }
    .thumbs { display: flex; gap: 8px; margin-bottom: 6px; }
    .thumbs img { width: 60px; height: 60px; object-fit: contain; background: #e2e8f0; border-radius: 8px; }
    .meta { font-size: 12px; color: #475569; line-height: 1.5; }
    .log-entry { margin-bottom: 6px; font-size: 13px; }
    .log-entry.success { color: #047857; }
    .log-entry.error { color: #b91c1c; }
    .log-entry.warn { color: #b45309; }
    .tag { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 999px; font-size: 11px; margin-right: 4px; }
    .actions { display: flex; flex-direction: column; gap: 6px; }
    .checkbox-col { width: 36px; text-align: center; }
  </style>
</head>
<body>
  <h1>Storage Warmup Monitor</h1>
  <p>Zeigt Storage-Objekte für den Oneal-Tenant. Prüft optional die 130px/1300px-Derivate und erlaubt gezieltes Warmup (refresh=true).</p>

  <div class="controls">
    <form method="get">
      <label>Offset <input type="number" name="offset" value="<?= htmlspecialchars((string)$offset, ENT_QUOTES, 'UTF-8') ?>"></label>
      <label>Limit <input type="number" name="limit" value="<?= htmlspecialchars((string)$limit, ENT_QUOTES, 'UTF-8') ?>"></label>
      <label><input type="checkbox" name="check" value="1" <?= $checkStatus ? 'checked' : '' ?>> Derivate prüfen</label>
      <button type="submit" class="secondary">Aktualisieren</button>
    </form>
    <form method="post">
      <input type="hidden" name="warmup_action" value="1">
      <?php foreach ($items as $it): ?>
        <input type="hidden" name="warmup_ids[]" value="<?= htmlspecialchars((string)$it['id'], ENT_QUOTES, 'UTF-8') ?>">
      <?php endforeach; ?>
      <button type="submit">Warmup für diese Seite</button>
    </form>
  </div>

  <?php if ($warmupLog): ?>
    <section class="log">
      <h2>Warmup-Protokoll</h2>
      <?php foreach ($warmupLog as $entry): ?>
        <div class="log-entry <?= htmlspecialchars($entry['level'], ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars($entry['message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th class="checkbox-col">Warmup</th>
        <th>ID &amp; Medien</th>
        <th>Metadaten</th>
        <th>Status 130px</th>
        <th>Status 1300px</th>
        <th>Aktion</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <?php
          $id = (int)$item['id'];
          $share130 = shareUrl($id, 130);
          $share1300 = shareUrl($id, 1300);
          $mediaUrl = $item['file_url'] ?? null;
        ?>
        <tr>
          <td class="checkbox-col">
            <form method="post" style="margin:0;">
              <input type="hidden" name="warmup_action" value="1">
              <input type="hidden" name="warmup_ids[]" value="<?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="secondary">Run</button>
            </form>
          </td>
          <td>
            <div class="thumbs">
              <a href="<?= htmlspecialchars($share1300, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                <img src="<?= htmlspecialchars($share130 . '&cb=' . time(), ENT_QUOTES, 'UTF-8') ?>" alt="Storage 130px">
              </a>
              <?php if (!empty($item['external_uri'])): ?>
                <a href="<?= htmlspecialchars($item['external_uri'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                  <img src="<?= htmlspecialchars($item['external_uri'], ENT_QUOTES, 'UTF-8') ?>" alt="Original">
                </a>
              <?php endif; ?>
            </div>
            <div class="meta">
              <strong>#<?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?></strong><br>
              <?php if (!empty($item['collection_id'])): ?>
                <span class="tag">Collection</span> <?= htmlspecialchars($item['collection_id'], ENT_QUOTES, 'UTF-8') ?><br>
              <?php endif; ?>
              <?php if (!empty($item['link_id'])): ?>
                <span class="tag">Link</span> <?= htmlspecialchars($item['link_id'], ENT_QUOTES, 'UTF-8') ?><br>
              <?php endif; ?>
              <?php if ($mediaUrl): ?>
                <a href="<?= htmlspecialchars($mediaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Share-Link ↗</a>
              <?php endif; ?>
            </div>
          </td>
          <td class="meta">
            <?= htmlspecialchars($item['mime_type'] ?? 'n/a', ENT_QUOTES, 'UTF-8') ?><br>
            <?= htmlspecialchars(number_format((float)($item['file_size_bytes'] ?? 0) / 1024, 1) . ' KB', ENT_QUOTES, 'UTF-8') ?><br>
            Erstellt: <?= htmlspecialchars($item['created_at'] ?? 'n/a', ENT_QUOTES, 'UTF-8') ?><br>
            Aktualisiert: <?= htmlspecialchars($item['updated_at'] ?? 'n/a', ENT_QUOTES, 'UTF-8') ?>
          </td>
          <td><?= $checkStatus ? formatStatus($item['status']['130'] ?? null) : '–' ?></td>
          <td><?= $checkStatus ? formatStatus($item['status']['1300'] ?? null) : '–' ?></td>
          <td class="actions">
            <a href="<?= htmlspecialchars($share130 . '&refresh=true&cb=' . time(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Proxy 130px</a>
            <a href="<?= htmlspecialchars($share1300 . '&refresh=true&cb=' . time(), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Proxy 1300px</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p style="margin-top:16px; font-size: 13px; color: #475569;">
    Gesamtobjekte: <?= htmlspecialchars((string)$total, ENT_QUOTES, 'UTF-8') ?> • Offset <?= htmlspecialchars((string)$offset, ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars((string)($offset + count($items) - 1), ENT_QUOTES, 'UTF-8') ?>
  </p>
</body>
</html>
