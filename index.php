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

$filtered = [];
foreach ($tasks as $t) {
  $due = $t['due_date_end'] ?? $t['due_date'] ?? null;
  $d = parseIsoDate($due);
  if (!$d) continue;
  $ymd = $d->format('Y-m-d');
  if ($ymd >= $dateFrom && $ymd <= $dateTo) $filtered[] = $t;
}

$validSorts = ['deadline', 'assignee', 'tasklist'];
$sort = in_array($_GET['sort'] ?? '', $validSorts) ? $_GET['sort'] : 'deadline';
$dir  = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
$filtered = sortTasks($filtered, $sort, $dir);
?>
<!doctype html>
<html lang="cs">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
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
        <a href="https://app.freelo.io" target="_blank" rel="noopener">Freelo</a>
        <a href="./fame">Deska vítězů</a>
        <a href="./shame">Deska hanby</a>
        <button class="theme-toggle" id="theme-toggle" aria-label="Přepnout tmavý/světlý režim">🌙</button>
      </div>
    </div>

    <?php if (empty($filtered)): ?>
      <p>Žádné nedokončené úkoly v tomto období 🎉</p>
    <?php else: ?>
      <?php renderSortBar($sort, $dir); ?>
      <section class="task-list">
        <?php foreach ($filtered as $t): ?>
          <?php renderTaskCard($t, false, true); ?>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
  <script>
  (function() {
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-complete');
      if (!btn) return;
      var taskId = btn.dataset.taskId;
      var card = btn.closest('.task-card');
      btn.disabled = true;
      btn.textContent = '...';
      fetch('complete_task.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'task_id=' + encodeURIComponent(taskId)
      }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.ok) {
          var rect = btn.getBoundingClientRect();
          var cx = rect.left + rect.width / 2;
          var cy = rect.top + rect.height / 2;
          for (var fi = 0; fi < 14; fi++) {
            (function() {
              var angle = Math.random() * Math.PI * 2;
              var dist = 50 + Math.random() * 70;
              var fire = document.createElement('span');
              fire.textContent = '🔥';
              fire.style.cssText = [
                'position:fixed',
                'left:' + cx + 'px',
                'top:' + cy + 'px',
                'font-size:' + (14 + Math.random() * 18) + 'px',
                'pointer-events:none',
                'z-index:9999',
                '--fire-tx:' + (Math.cos(angle) * dist).toFixed(1) + 'px',
                '--fire-ty:' + (Math.sin(angle) * dist).toFixed(1) + 'px',
                'animation:fire-burst ' + (0.45 + Math.random() * 0.45).toFixed(2) + 's ease-out forwards',
                'transform-origin:center',
                'line-height:1',
              ].join(';');
              document.body.appendChild(fire);
              fire.addEventListener('animationend', function() { fire.remove(); });
            })();
          }
          card.style.transition = 'opacity 0.4s, transform 0.4s';
          card.style.opacity = '0';
          card.style.transform = 'translateX(20px)';
          setTimeout(function() { card.remove(); }, 400);
        } else {
          btn.disabled = false;
          btn.textContent = '✓ Hotovo';
          alert('Chyba: ' + (data.error || 'Neznámá chyba'));
        }
      }).catch(function() {
        btn.disabled = false;
        btn.textContent = '✓ Hotovo';
        alert('Chyba při komunikaci se serverem.');
      });
    });
  })();
  (function() {
    var btn = document.getElementById('theme-toggle');
    function isDark() {
      var t = document.documentElement.getAttribute('data-theme');
      return t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    function update() { btn.textContent = isDark() ? '☀️' : '🌙'; }
    btn.addEventListener('click', function() {
      var next = isDark() ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('theme', next);
      update();
    });
    update();
  })();
  </script>
</body>
</html>