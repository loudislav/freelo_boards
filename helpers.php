<?php
// helpers.php

function previousWeekRange(string $tz = 'Europe/Prague'): array {
  $now     = new DateTimeImmutable('now', new DateTimeZone($tz));
  $weekday = (int)$now->format('N'); // 1=Mon … 7=Sun
  $monday  = $now->modify('-' . ($weekday + 6) . ' days');
  $sunday  = $monday->modify('+6 days');
  return [$monday->format('Y-m-d'), $sunday->format('Y-m-d')];
}

function todayYmd(string $tz = 'Europe/Prague'): string {
  $dt = new DateTimeImmutable('now', new DateTimeZone($tz));
  return $dt->format('Y-m-d');
}

/**
 * Vrátí poslední proběhlé pondělí (včetně dneška, pokud je pondělí).
 */
function lastMondayYmd(string $tz = 'Europe/Prague'): string {
  $now = new DateTimeImmutable('now', new DateTimeZone($tz));
  $weekday = (int)$now->format('N'); // 1=Mon ... 7=Sun
  $monday = $now->modify('-' . ($weekday - 1) . ' days');
  return $monday->format('Y-m-d');
}

function addDaysYmd(string $ymd, int $days, string $tz = 'Europe/Prague'): string {
  $dt = new DateTimeImmutable($ymd, new DateTimeZone($tz));
  return $dt->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function parseIsoDate(?string $iso): ?DateTimeImmutable {
  if (!$iso) return null;
  try {
    return new DateTimeImmutable($iso);
  } catch (Throwable $e) {
    return null;
  }
}

function getStateIdByName(FreeloClient $api, string $stateName): int {
  $res = $api->get('/states');
  $states = $res['states'] ?? [];
  foreach ($states as $st) {
    if (($st['state'] ?? null) === $stateName) {
      return (int)$st['id'];
    }
  }
  throw new RuntimeException("State '$stateName' not found in /states");
}

/**
 * Načte všechny stránky z /all-tasks (API vrací paginaci).
 * V blueprintu je standardně total/count/page/per_page + data.tasklists apod.
 * U all-tasks je v těle obvykle data.tasks.
 */
function fetchAllTasks(FreeloClient $api, array $query): array {
  $dateFrom = $query['due_date_range[date_from]'] ?? null;
  $dateTo   = $query['due_date_range[date_to]']   ?? null;

  if ($dateFrom === null || $dateTo === null) {
    $res   = $api->get('/all-tasks', $query);
    $tasks = $res['data']['tasks'] ?? $res['tasks'] ?? [];
    return is_array($tasks) ? $tasks : [];
  }

  $base = $query;
  unset($base['due_date_range[date_from]'], $base['due_date_range[date_to]']);

  return fetchTasksInDateRange($api, $base, $dateFrom, $dateTo);
}

// The Freelo /all-tasks API is capped at 100 results and ignores pagination
// parameters. To get all tasks, we split the date range in half whenever we
// hit the cap and recurse until each slice fits under 100.
function fetchTasksInDateRange(FreeloClient $api, array $base, string $from, string $to): array {
  if ($from > $to) return [];

  $res   = $api->get('/all-tasks', $base + [
    'due_date_range[date_from]' => $from,
    'due_date_range[date_to]'   => $to,
  ]);
  $tasks = $res['data']['tasks'] ?? $res['tasks'] ?? [];
  if (!is_array($tasks)) $tasks = [];
  $total = (int)($res['total'] ?? count($tasks));

  if (count($tasks) < 100 || count($tasks) >= $total || $from === $to) {
    return $tasks;
  }

  $midTs = intdiv(strtotime($from) + strtotime($to), 2);
  $mid   = date('Y-m-d', $midTs);
  $next  = date('Y-m-d', strtotime($mid . ' +1 day'));

  return array_merge(
    fetchTasksInDateRange($api, $base, $from, $mid),
    fetchTasksInDateRange($api, $base, $next, $to)
  );
}

function h(?string $value): string {
  return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function getTaskAssignee(array $task): string {
  return
    $task['worker']['fullname']
    ?? $task['worker']['name']
    ?? $task['assigned_user']['fullname']
    ?? $task['assigned_user']['name']
    ?? $task['assignee']['fullname']
    ?? $task['assignee']['name']
    ?? 'Bez řešitele';
}

function getShameLeaderboard(array $tasks): array {
  $counts = [];

  foreach ($tasks as $task) {
    $assignee = getTaskAssignee($task);
    $counts[$assignee] = ($counts[$assignee] ?? 0) + 1;
  }

  $rows = [];
  foreach ($counts as $name => $count) {
    $rows[] = [
      'name' => $name,
      'count' => $count,
    ];
  }

  usort($rows, function ($a, $b) {
    $byCount = $b['count'] <=> $a['count'];

    if ($byCount !== 0) {
      return $byCount;
    }

    return strcasecmp($a['name'], $b['name']);
  });

  return $rows;
}

function filterTasksByAssignee(array $tasks, string $assignee): array {
  if ($assignee === '') {
    return $tasks;
  }

  return array_values(array_filter($tasks, function ($task) use ($assignee) {
    return getTaskAssignee($task) === $assignee;
  }));
}

function shameBoardAssigneeUrl(string $assignee, array $extra = []): string {
  return '?' . http_build_query(array_merge($extra, ['assignee' => $assignee]));
}

function shameBoardUrl(array $extra = []): string {
  return empty($extra) ? '?' : '?' . http_build_query($extra);
}

function sortTasks(array $tasks, string $by, string $dir): array {
  usort($tasks, function ($a, $b) use ($by): int {
    switch ($by) {
      case 'assignee':
        return strcasecmp(getTaskAssignee($a), getTaskAssignee($b));
      case 'tasklist':
        $ta = ($a['project']['name'] ?? '') . ($a['tasklist']['name'] ?? '');
        $tb = ($b['project']['name'] ?? '') . ($b['tasklist']['name'] ?? '');
        return strcasecmp($ta, $tb);
      case 'completed':
        return strcmp($a['date_edited_at'] ?? '', $b['date_edited_at'] ?? '');
      default: // deadline
        $da = $a['due_date_end'] ?? $a['due_date'] ?? '';
        $db = $b['due_date_end'] ?? $b['due_date'] ?? '';
        if ($da === $db) return 0;
        if ($da === '') return 1;  // no deadline → always last
        if ($db === '') return -1;
        return strcmp($da, $db);
    }
  });
  if ($dir === 'desc') $tasks = array_reverse($tasks);
  return $tasks;
}

function renderSortBar(string $currentSort, string $currentDir, array $extra = [], array $cols = []): void {
  if (empty($cols)) {
    $cols = ['deadline' => 'Deadline', 'assignee' => 'Řešitel', 'tasklist' => 'To-Do list'];
  }
  echo '<div class="sort-bar"><span>Seřadit:</span>';
  foreach ($cols as $field => $label) {
    $active = $currentSort === $field;
    $newDir = ($active && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow  = $active ? ($currentDir === 'asc' ? ' ↑' : ' ↓') : '';
    $url    = '?' . http_build_query(array_merge($extra, ['sort' => $field, 'dir' => $newDir]));
    $class  = 'sort-link' . ($active ? ' active' : '');
    echo '<a href="' . h($url) . '" class="' . $class . '">' . h($label . $arrow) . '</a>';
  }
  echo '</div>';
}

function getTaskUrl(array $task): ?string {
  // Pokud API vrací přímý odkaz, použijeme ho.
  foreach (['url', 'html_url', 'web_url', 'app_url'] as $key) {
    if (!empty($task[$key])) {
      return $task[$key];
    }
  }

  // Fallback – pokud ve tvé odpovědi existuje jiné pole s URL,
  // pošli mi jeden dump úkolu a upravíme přesně.
  if (!empty($task['id'])) {
    return 'https://app.freelo.io/task/' . rawurlencode((string)$task['id']);
  }

  return null;
}

function renderTaskCard(array $t, bool $overdue = false, bool $showCompleteBtn = false): void {
  $due = $t['due_date_end'] ?? $t['due_date'] ?? null;
  $project = $t['project']['name'] ?? '';
  $tasklist = $t['tasklist']['name'] ?? '';
  $worker = getTaskAssignee($t);
  $url = getTaskUrl($t);
  ?>
  <article class="task-card <?= $overdue ? 'overdue' : '' ?>">
    <div class="task-title"><?= h($t['name'] ?? '(bez názvu)') ?></div>

    <div class="task-meta">
      <span class="badge <?= $overdue ? 'danger' : '' ?>">
        Deadline: <?= h($due ?: '-') ?>
      </span>
      <?php if ($overdue): ?>
  <?php $lateDays = daysAfterDeadline($due); ?>
  <?php if ($lateDays !== null && $lateDays > 0): ?>
    <span class="badge danger">
      Po deadlinu: <?= h((string)$lateDays) ?> dní
    </span>
  <?php endif; ?>
<?php endif; ?>
      <span class="badge">
        Řešitel: <?= h($worker) ?>
      </span>
    </div>

    <?php if ($project || $tasklist): ?>
      <div class="task-project">
        To-Do list: <?= h(trim($project . ' / ' . $tasklist, ' /')) ?>
      </div>
    <?php endif; ?>

    <div class="task-link">
      <?php if ($url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener">
          Otevřít ve Freelu →
        </a>
      <?php endif; ?>
      <?php if ($showCompleteBtn && !empty($t['id'])): ?>
        <button class="btn-complete" data-task-id="<?= h((string)$t['id']) ?>">
          ✓ Hotovo
        </button>
      <?php endif; ?>
    </div>
  </article>
  <?php
}

function daysAfterDeadline(?string $dueDate, string $tz = 'Europe/Prague'): ?int {
  if (!$dueDate) {
    return null;
  }

  try {
    $due = new DateTimeImmutable($dueDate, new DateTimeZone($tz));
    $today = new DateTimeImmutable('today', new DateTimeZone($tz));

    $dueDay = new DateTimeImmutable($due->format('Y-m-d'), new DateTimeZone($tz));

    if ($dueDay >= $today) {
      return 0;
    }

    return (int)$dueDay->diff($today)->days;
  } catch (Throwable $e) {
    return null;
  }
}