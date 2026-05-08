<?php
// helpers.php

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
  $all = [];
  $page = 0;
  $perPage = 100;

  while (true) {
    $q = $query + ['page' => $page, 'per_page' => $perPage];
    $res = $api->get('/all-tasks', $q);

    $tasks = $res['data']['tasks'] ?? $res['tasks'] ?? [];
    if (!is_array($tasks)) $tasks = [];

    $all = array_merge($all, $tasks);

    $count = (int)($res['count'] ?? count($tasks));
    $total = (int)($res['total'] ?? count($all));
    $pageFromApi = (int)($res['page'] ?? $page);

    // Když API neposkytuje total, ukončíme při prázdné stránce.
    if ($count === 0) break;

    // Pokud total existuje a už ho máme, končíme.
    if ($total > 0 && count($all) >= $total) break;

    $page = $pageFromApi + 1;
  }

  return $all;
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

function shameBoardAssigneeUrl(string $assignee): string {
  return '?assignee=' . rawurlencode($assignee);
}

function shameBoardUrl(): string {
  return '?';
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

function renderTaskCard(array $t, bool $overdue = false): void {
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

    <?php if ($url): ?>
      <div class="task-link">
        <a href="<?= h($url) ?>" target="_blank" rel="noopener">
          Otevřít ve Freelu →
        </a>
      </div>
    <?php endif; ?>
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