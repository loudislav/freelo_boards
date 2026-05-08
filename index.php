<?php
require __DIR__ . '/freelo_client.php';
require __DIR__ . '/helpers.php';

$cfg = require __DIR__ . '/config.php';
$api = new FreeloClient($cfg);

$tz = 'Europe/Prague';

$activeId = getStateIdByName($api, 'active');

$dateFrom = lastMondayYmd($tz);
$dateTo   = addDaysYmd($dateFrom, 13, $tz); // 14 dní včetně prvního dne

$tasks = fetchAllTasks($api, [
  'state_id' => $activeId,
  'order_by' => 'date_add',
  'order' => 'asc',
  'due_date_range[date_from]' => $dateFrom,
  'due_date_range[date_to]'   => $dateTo,
]);

// Volitelně: ještě “dofiltrovat” podle due_date_end, pokud existuje:
$filtered = [];
foreach ($tasks as $t) {
  $due = $t['due_date_end'] ?? $t['due_date'] ?? null;
  $d = parseIsoDate($due);
  if (!$d) continue;
  $ymd = $d->format('Y-m-d');
  if ($ymd >= $dateFrom && $ymd <= $dateTo) $filtered[] = $t;
}
?>
<!doctype html>
<html lang="cs">
<meta charset="utf-8">
<title>Úkoly na tento a příští týden</title>
<head>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <main class="container">
    <div class="header">
      <div>
        <h1>Úkoly na tento a příští týden</h1>
        <p>Rozsah: <strong><?= h($dateFrom) ?></strong> – <strong><?= h($dateTo) ?></strong></p>
      </div>
      <div class="nav">
        <a href="./shame">Deska hanby</a>
      </div>
    </div>

    <?php if (empty($filtered)): ?>
      <p>Žádné nedokončené úkoly v tomto období 🎉</p>
    <?php else: ?>
      <section class="task-list">
        <?php foreach ($filtered as $t): ?>
          <?php renderTaskCard($t, false); ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>