<?php
require __DIR__ . '/../freelo_client.php';
require __DIR__ . '/../helpers.php';

$cfg = require __DIR__ . '/../config.php';
$api = new FreeloClient($cfg);

$tz = 'Europe/Prague';
[$weekFrom, $weekTo] = previousWeekRange($tz);
$finishedId = getStateIdByName($api, 'finished');

$res      = $api->get('/all-tasks', [
  'state_id' => $finishedId,
  'order_by' => 'date_edited_at',
  'order'    => 'desc',
]);
$allTasks = $res['data']['tasks'] ?? $res['tasks'] ?? [];

// Keep only tasks last edited (= marked done) during the previous week
$weekTasks = array_values(array_filter($allTasks, function ($t) use ($weekFrom, $weekTo, $tz) {
  $edited = $t['date_edited_at'] ?? null;
  if (!$edited) return false;
  try {
    $ymd = (new DateTimeImmutable($edited, new DateTimeZone($tz)))->format('Y-m-d');
    return $ymd >= $weekFrom && $ymd <= $weekTo;
  } catch (Throwable) {
    return false;
  }
}));

// Reuse shame leaderboard logic (sort by count desc); exclude "no assignee"
$leaderboard = array_values(array_filter(
  getShameLeaderboard($weekTasks),
  fn($r) => $r['name'] !== 'Bez řešitele'
));

$validSorts = ['completed', 'assignee', 'tasklist'];
$sort = in_array($_GET['sort'] ?? '', $validSorts) ? $_GET['sort'] : 'completed';
$dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
$weekTasks = sortTasks($weekTasks, $sort, $dir);
?>
<!doctype html>
<html lang="cs">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta charset="utf-8">
<title>Deska vítězů</title>
<head>
  <link rel="stylesheet" href="../style.css">
  <style>
    @keyframes confetti-fall {
      0%   { transform: translateY(0) rotate(0deg); opacity: 1; }
      85%  { opacity: 1; }
      100% { transform: translateY(105vh) rotate(800deg); opacity: 0; }
    }

    .fame-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 300px;
      gap: 22px;
      align-items: start;
    }

    .fame-sidebar {
      position: sticky;
      top: 24px;
    }

    .fame-card {
      background: var(--bg-card);
      border-radius: 16px;
      padding: 18px 20px;
      box-shadow: 0 8px 24px var(--shadow);
      border-left: 6px solid #16a34a;
    }

    .fame-card .task-title { margin-bottom: 10px; }

    .podium-card {
      background: var(--bg-card);
      border-radius: 16px;
      padding: 18px;
      box-shadow: 0 8px 24px var(--shadow);
      border-top: 6px solid #f59e0b;
      margin-bottom: 16px;
    }

    .podium-card h2 { margin: 0 0 4px; font-size: 20px; }
    .podium-card > p { margin: 0 0 20px; color: var(--text-muted); font-size: 14px; }

    .podium {
      display: flex;
      align-items: flex-end;
      justify-content: center;
      gap: 6px;
    }

    .podium-place {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      flex: 1;
    }

    .podium-name {
      font-size: 12px;
      font-weight: 700;
      text-align: center;
      line-height: 1.3;
    }

    .podium-count {
      font-size: 11px;
      color: var(--text-muted);
    }

    .podium-block {
      width: 100%;
      border-radius: 8px 8px 0 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 26px;
    }

    .podium-block.gold   { height: 90px; background: #fbbf24; }
    .podium-block.silver { height: 65px; background: #94a3b8; }
    .podium-block.bronze { height: 45px; background: #b45309; }

    .fame-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      margin-top: 16px;
    }

    .fame-table th,
    .fame-table td {
      padding: 10px 6px;
      border-bottom: 1px solid var(--border);
      text-align: left;
    }

    .fame-table th:last-child,
    .fame-table td:last-child { text-align: right; }

    .fame-table tr.fame-leader td {
      font-weight: 700;
      color: #d97706;
    }

    .fame-count {
      display: inline-block;
      min-width: 28px;
      padding: 4px 8px;
      border-radius: 999px;
      background: #dcfce7;
      color: #15803d;
      font-weight: 700;
      text-align: center;
    }

    [data-theme="dark"] .fame-count {
      background: #14532d;
      color: #86efac;
    }

    .empty-fame {
      color: var(--text-muted);
      font-size: 14px;
      padding: 12px 0;
    }

    .badge.success {
      background: #dcfce7;
      color: #15803d;
    }

    [data-theme="dark"] .badge.success {
      background: #14532d;
      color: #86efac;
    }

    @media (max-width: 850px) {
      .fame-layout { grid-template-columns: 1fr; }
      .fame-sidebar { position: static; }
    }
  </style>
</head>
<body>
  <main class="container">
    <div class="header">
      <div>
        <h1>Deska vítězů 🏆</h1>
        <p>Úkoly splněné minulý týden: <strong><?= h($weekFrom) ?></strong> – <strong><?= h($weekTo) ?></strong></p>
      </div>
      <div class="nav">
        <a href="https://app.freelo.io" target="_blank" rel="noopener">Freelo</a>
        <a href="../shame">Deska hanby</a>
        <a href="../index.php">Tento týden</a>
        <button class="theme-toggle" id="theme-toggle" aria-label="Přepnout tmavý/světlý režim">🌙</button>
      </div>
    </div>

    <section class="fame-layout">
      <div>
        <?php if (empty($weekTasks)): ?>
          <p>Minulý týden nebyl splněn žádný úkol. Hanba! 😤</p>
        <?php else: ?>
          <?php renderSortBar($sort, $dir, [], ['completed' => 'Datum splnění', 'assignee' => 'Řešitel', 'tasklist' => 'To-Do list']); ?>
          <div class="task-list">
            <?php foreach ($weekTasks as $t):
              $worker  = getTaskAssignee($t);
              $project = $t['project']['name']  ?? '';
              $tasklist= $t['tasklist']['name'] ?? '';
              $url     = getTaskUrl($t);
              $edited  = $t['date_edited_at'] ?? null;
              $editedFmt = null;
              if ($edited) {
                try { $editedFmt = (new DateTimeImmutable($edited, new DateTimeZone($tz)))->format('d.m.Y H:i'); }
                catch (Throwable) {}
              }
            ?>
              <article class="fame-card">
                <div class="task-title"><?= h($t['name'] ?? '(bez názvu)') ?></div>
                <div class="task-meta">
                  <span class="badge success">✓ <?= h($worker) ?></span>
                  <?php if ($editedFmt): ?>
                    <span class="badge">Splněno: <?= h($editedFmt) ?></span>
                  <?php endif; ?>
                </div>
                <?php if ($project || $tasklist): ?>
                  <div class="task-project">
                    To-Do list: <?= h(trim($project . ' / ' . $tasklist, ' /')) ?>
                  </div>
                <?php endif; ?>
                <?php if ($url): ?>
                  <div class="task-link">
                    <a href="<?= h($url) ?>" target="_blank" rel="noopener">Otevřít ve Freelu →</a>
                  </div>
                <?php endif; ?>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside class="fame-sidebar">
        <div class="podium-card">
          <h2>Šampioni týdne</h2>
          <p>Počet splněných úkolů za minulý týden.</p>

          <?php if (empty($leaderboard)): ?>
            <div class="empty-fame">Zatím nikdo nic nesplnil. 😢</div>
          <?php else: ?>

            <?php if (count($leaderboard) >= 2): // podium needs at least 2 ?>
            <div class="podium">
              <?php
                $medals = [
                  0 => ['class' => 'gold',   'emoji' => '🥇'],
                  1 => ['class' => 'silver',  'emoji' => '🥈'],
                  2 => ['class' => 'bronze',  'emoji' => '🥉'],
                ];
                // Display order: 2nd, 1st, 3rd
                $displayOrder = array_filter([1, 0, 2], fn($i) => isset($leaderboard[$i]));
              ?>
              <?php foreach ($displayOrder as $i): ?>
                <div class="podium-place">
                  <div class="podium-name"><?= h($leaderboard[$i]['name']) ?></div>
                  <div class="podium-count"><?= $leaderboard[$i]['count'] ?> úkolů</div>
                  <div class="podium-block <?= $medals[$i]['class'] ?>"><?= $medals[$i]['emoji'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <table class="fame-table">
              <thead>
                <tr><th>Řešitel</th><th>Úkoly</th></tr>
              </thead>
              <tbody>
                <?php foreach ($leaderboard as $index => $row): ?>
                  <tr class="<?= $index === 0 ? 'fame-leader' : '' ?>">
                    <td><?= $index === 0 ? '👑 ' : '' ?><?= h($row['name']) ?></td>
                    <td><span class="fame-count"><?= h((string)$row['count']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

          <?php endif; ?>
        </div>
      </aside>
    </section>
  </main>

  <script>
  // Confetti 🎊
  (function () {
    var colors = ['#f59e0b','#2563eb','#16a34a','#dc2626','#a855f7','#f97316','#06b6d4','#ec4899','#fbbf24'];
    for (var i = 0; i < 160; i++) {
      (function (delay) {
        setTimeout(function () {
          var el = document.createElement('div');
          var size = (6 + Math.random() * 9) + 'px';
          el.style.cssText = [
            'position:fixed',
            'left:' + (Math.random() * 100) + 'vw',
            'top:-16px',
            'width:' + size,
            'height:' + size,
            'background:' + colors[Math.floor(Math.random() * colors.length)],
            'border-radius:' + (Math.random() > 0.45 ? '50%' : '2px'),
            'animation:confetti-fall ' + (1.8 + Math.random() * 2.2) + 's linear forwards',
            'pointer-events:none',
            'z-index:9999',
          ].join(';');
          document.body.appendChild(el);
          el.addEventListener('animationend', function () { el.remove(); });
        }, delay);
      })(i * 25);
    }
  })();
  </script>

  <script>
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
