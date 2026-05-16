<?php
require __DIR__ . '/../freelo_client.php';
require __DIR__ . '/../helpers.php';

$cfg = require __DIR__ . '/../config.php';
$api = new FreeloClient($cfg);

$tz = 'Europe/Prague';

$activeId = getStateIdByName($api, 'active');

$today = todayYmd($tz);
$yesterday = addDaysYmd($today, -1, $tz);

$tasks = fetchAllTasks($api, [
  'state_id' => $activeId,
  'order_by' => 'date_add',
  'order' => 'desc',
  'due_date_range[date_from]' => '1970-01-01',
  'due_date_range[date_to]'   => $yesterday,
]);

// Pro jistotu ještě dofiltrujeme:
$overdue = [];
foreach ($tasks as $t) {
  $due = $t['due_date_end'] ?? $t['due_date'] ?? null;
  $d = parseIsoDate($due);
  if (!$d) continue;
  if ($d->format('Y-m-d') < $today) $overdue[] = $t;
}
$selectedAssignee = trim((string)($_GET['assignee'] ?? ''));

$validSorts = ['deadline', 'assignee', 'tasklist'];
$sort = in_array($_GET['sort'] ?? '', $validSorts) ? $_GET['sort'] : 'deadline';
$dir  = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$leaderboard    = getShameLeaderboard($overdue);
$visibleOverdue = sortTasks(filterTasksByAssignee($overdue, $selectedAssignee), $sort, $dir);

// Extra params to carry sort through assignee filter links
$sortParams = ['sort' => $sort, 'dir' => $dir];
?>
<!doctype html>
<html lang="cs">
<script>(function(){var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);})();</script>
<meta charset="utf-8">
<title>Deska hanby</title>
<head>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <main class="container">
    <div class="header">
      <div>
        <h1>Deska hanby 😈</h1>
        <p>Overdue k datu: <strong><?= h($today) ?></strong></p>
      </div>

      <div class="nav">
        <a href="https://app.freelo.io" target="_blank" rel="noopener">Freelo</a>
        <a href="../fame">Deska vítězů</a>
        <a href="../index.php">Úkoly na tento a příští týden</a>
        <button class="theme-toggle" id="theme-toggle" aria-label="Přepnout tmavý/světlý režim">🌙</button>
      </div>
    </div>

    <section class="shame-layout">
<div>
  <?php if ($selectedAssignee !== ''): ?>
    <div class="active-filter-box">
      <div>
        Zobrazuji úkoly řešitele:
        <strong><?= h($selectedAssignee) ?></strong>
      </div>

      <a href="<?= h(shameBoardUrl($sortParams)) ?>">
        Zrušit filtr
      </a>
    </div>
  <?php endif; ?>

  <?php if (empty($visibleOverdue)): ?>
    <?php if ($selectedAssignee !== ''): ?>
      <p>Pro vybraného řešitele nejsou žádné úkoly po deadlinu.</p>
    <?php else: ?>
      <p>Nic po deadlinu. Krása ✨</p>
    <?php endif; ?>
  <?php else: ?>
    <?php $assigneeExtra = $selectedAssignee !== '' ? array_merge($sortParams, ['assignee' => $selectedAssignee]) : $sortParams; ?>
    <?php renderSortBar($sort, $dir, $assigneeExtra); ?>
    <section class="task-list">
      <?php foreach ($visibleOverdue as $t): ?>
        <?php renderTaskCard($t, true, true); ?>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>

      <aside class="shame-sidebar">
        <div class="stats-card">
          <h2>Největší hanbář</h2>
          <p>Počet nesplněných úkolů po deadlinu podle řešitele.</p>

          <?php if (empty($leaderboard)): ?>
            <div class="empty-stats">
              Zatím není koho pranýřovat.
            </div>
          <?php else: ?>
            <table class="shame-table">
              <thead>
                <tr>
                  <th>Řešitel</th>
                  <th>Úkoly</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($leaderboard as $index => $row): ?>
                  <tr class="<?= $index === 0 ? 'leader' : '' ?> <?= $selectedAssignee === $row['name'] ? 'selected-assignee' : '' ?>">
                    <td>
  <a
    class="assignee-filter-link <?= $selectedAssignee === $row['name'] ? 'active' : '' ?>"
    href="<?= h(shameBoardAssigneeUrl($row['name'], $sortParams)) ?>"
  >
    <?= $index === 0 ? '👑 ' : '' ?>
    <?= h($row['name']) ?>
  </a>
</td>
                    <td>
                      <span class="shame-count">
                        <?= h((string)$row['count']) ?>
                      </span>
                    </td>
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
  (function() {
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-complete');
      if (!btn) return;
      var taskId = btn.dataset.taskId;
      var card = btn.closest('.task-card');
      btn.disabled = true;
      btn.textContent = '...';
      fetch('../complete_task.php', {
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