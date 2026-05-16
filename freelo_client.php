<?php
// freelo_client.php

class FreeloClient {
  private string $baseUrl;
  private string $email;
  private string $apiKey;
  private string $userAgent;
  private bool   $sslVerify;

  public function __construct(array $cfg) {
    $this->baseUrl   = rtrim($cfg['base_url'], '/');
    $this->email     = $cfg['email'];
    $this->apiKey    = $cfg['api_key'];
    $this->userAgent = $cfg['user_agent'];
    $this->sslVerify = $cfg['ssl_verify'] ?? true;
  }

  public function get(string $path, array $query = []): array {
    $url = $this->baseUrl . '/' . ltrim($path, '/');
    if (!empty($query)) {
      $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_USERPWD        => $this->email . ':' . $this->apiKey,
      CURLOPT_HTTPHEADER     => [
        'User-Agent: ' . $this->userAgent,
        'Accept: application/json',
      ],
      CURLOPT_TIMEOUT        => 30,
      CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
      CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
      throw new RuntimeException('cURL error: ' . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($body, true);
    
    if ($status < 200 || $status >= 300) {
      $msg = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : $body;
      throw new RuntimeException("HTTP $status: $msg");
    }

    if (!is_array($json)) {
      throw new RuntimeException('Invalid JSON response');
    }

    return $json;
  }

  public function post(string $path, array $body = []): array {
    $url = $this->baseUrl . '/' . ltrim($path, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => json_encode($body),
      CURLOPT_USERPWD        => $this->email . ':' . $this->apiKey,
      CURLOPT_HTTPHEADER     => [
        'User-Agent: ' . $this->userAgent,
        'Accept: application/json',
        'Content-Type: application/json',
      ],
      CURLOPT_TIMEOUT        => 30,
      CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
      CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
      throw new RuntimeException('cURL error: ' . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw, true);

    if ($status < 200 || $status >= 300) {
      $msg = is_array($json) ? json_encode($json, JSON_UNESCAPED_UNICODE) : $raw;
      throw new RuntimeException("HTTP $status: $msg");
    }

    return is_array($json) ? $json : [];
  }
}