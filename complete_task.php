<?php
require __DIR__ . '/freelo_client.php';
$cfg = require __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing task_id']);
  exit;
}

try {
  $api = new FreeloClient($cfg);
  $api->post("/task/$taskId/finish");
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
