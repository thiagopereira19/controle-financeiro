<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($d, $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function sign($p) { return hash_hmac('sha256', $p, SECRET); }
function make_token($uid) { $p = $uid . '.' . (time() + 30 * 86400); return $p . '.' . sign($p); }
function token_uid($t) {
  $a = explode('.', (string)$t);
  if (count($a) !== 3 || !ctype_digit($a[0]) || !ctype_digit($a[1])) return 0;
  if ((int)$a[1] < time() || !hash_equals(sign($a[0] . '.' . $a[1]), $a[2])) return 0;
  return (int)$a[0];
}

try {
  $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
  if (DB_SSL) {
    $ca = __DIR__ . '/ca.pem';
    $opts[PDO::MYSQL_ATTR_SSL_CA] = file_exists($ca) ? $ca : '/etc/ssl/certs/ca-certificates.crt';
    $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = DB_SSL_VERIFY;
  }
  $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4' . (DB_AUTOCREATE ? '' : ';dbname=' . DB_NAME);
  $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
  if (DB_AUTOCREATE) {
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4');
    $pdo->exec('USE `' . DB_NAME . '`');
  }
  $pdo->exec('CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE,
    pass_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) CHARACTER SET utf8mb4');
  $pdo->exec('CREATE TABLE IF NOT EXISTS itens (
    user_id INT NOT NULL, id VARCHAR(64) NOT NULL, data LONGTEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, id)) CHARACTER SET utf8mb4');
} catch (Exception $e) {
  out(['error' => 'Erro no banco de dados: ' . $e->getMessage()], 500);
}

$r = isset($_GET['r']) ? $_GET['r'] : '';
$m = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

if ($r === 'register' && $m === 'POST') {
  if (!ALLOW_REGISTER) out(['error' => 'Cadastro desativado.'], 403);
  $name = trim(isset($body['name']) ? (string)$body['name'] : '');
  $email = strtolower(trim(isset($body['email']) ? (string)$body['email'] : ''));
  $pw = isset($body['password']) ? (string)$body['password'] : '';
  if ($name === '' || mb_strlen($name) > 80) out(['error' => 'Informe seu nome.'], 400);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'E-mail inválido.'], 400);
  if (strlen($pw) < 6) out(['error' => 'A senha precisa ter pelo menos 6 caracteres.'], 400);
  $st = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $st->execute([$email]);
  if ($st->fetch()) out(['error' => 'Este e-mail já tem conta. Use Entrar.'], 409);
  $pdo->prepare('INSERT INTO users (name, email, pass_hash) VALUES (?, ?, ?)')
      ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
  out(['token' => make_token((int)$pdo->lastInsertId()), 'name' => $name]);
}

if ($r === 'login' && $m === 'POST') {
  $email = strtolower(trim(isset($body['email']) ? (string)$body['email'] : ''));
  $pw = isset($body['password']) ? (string)$body['password'] : '';
  $st = $pdo->prepare('SELECT id, name, pass_hash FROM users WHERE email = ?'); $st->execute([$email]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if ($u && password_verify($pw, $u['pass_hash'])) out(['token' => make_token((int)$u['id']), 'name' => $u['name']]);
  sleep(1);
  out(['error' => 'E-mail ou senha incorretos.'], 401);
}

$uid = token_uid(isset($_SERVER['HTTP_X_TOKEN']) ? $_SERVER['HTTP_X_TOKEN'] : '');
if (!$uid) out(['error' => 'unauthorized'], 401);

if ($r === 'me' && $m === 'GET') {
  $st = $pdo->prepare('SELECT name, email FROM users WHERE id = ?'); $st->execute([$uid]);
  $u = $st->fetch(PDO::FETCH_ASSOC);
  if (!$u) out(['error' => 'unauthorized'], 401);
  out($u);
}

if ($r === 'items' && $m === 'GET') {
  $st = $pdo->prepare('SELECT id, data FROM itens WHERE user_id = ? ORDER BY updated_at'); $st->execute([$uid]);
  $res = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $d = json_decode($row['data'], true);
    $res[] = array_merge(['id' => $row['id']], is_array($d) ? $d : []);
  }
  out($res);
}

if (strpos($r, 'items/') === 0) {
  $id = substr($r, 6);
  if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) out(['error' => 'id inválido'], 400);
  if ($m === 'PUT') {
    unset($body['id']);
    $pdo->prepare('INSERT INTO itens (user_id, id, data) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data = VALUES(data)')
        ->execute([$uid, $id, json_encode($body, JSON_UNESCAPED_UNICODE)]);
    out(['ok' => true]);
  }
  if ($m === 'DELETE') {
    $pdo->prepare('DELETE FROM itens WHERE user_id = ? AND id = ?')->execute([$uid, $id]);
    out(['ok' => true]);
  }
}
out(['error' => 'not found'], 404);
