<?php
// Lê variáveis de ambiente (nuvem). Sem elas, usa os valores padrão do WAMP.
function envv($k, $d) { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }

define('SECRET', envv('SECRET', 'troque-por-um-texto-longo-e-aleatorio-123xyz')); // assina o login: troque!
define('ALLOW_REGISTER', envv('ALLOW_REGISTER', '1') === '1');  // 0 = ninguém mais cria conta

define('DB_HOST', envv('DB_HOST', 'localhost'));
define('DB_PORT', (int)envv('DB_PORT', 3306));   // no WAMP o MariaDB às vezes usa 3307
define('DB_USER', envv('DB_USER', 'root'));
define('DB_PASS', envv('DB_PASS', ''));
define('DB_NAME', envv('DB_NAME', 'controle_financeiro'));
define('DB_AUTOCREATE', envv('DB_AUTOCREATE', '1') === '1'); // WAMP: cria o banco. Nuvem: use 0
define('DB_SSL', envv('DB_SSL', '0') === '1');               // Aiven: use 1
define('DB_SSL_VERIFY', envv('DB_SSL_VERIFY', '0') === '1'); // 1 = confere o certificado com o ca.pem
