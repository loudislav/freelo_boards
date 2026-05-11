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

$leaderboard = getShameLeaderboard($overdue);
$visibleOverdue = filterTasksByAssignee($overdue, $selectedAssignee);
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

      <a href="<?= h(shameBoardUrl()) ?>">
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
    <section class="task-list">
      <?php foreach ($visibleOverdue as $t): ?>
        <?php renderTaskCard($t, true); ?>
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
    href="<?= h(shameBoardAssigneeUrl($row['name'])) ?>"
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