<?php
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$data_dir = __DIR__ . '/data/';
$encryption_key = "todo_secret_key_32_chars_long_!!!";
$auth_token_base = "todo_token_secure_";

// Crypto Helpers
function encrypt($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt($data, $key) {
    $data = base64_decode($data);
    $iv_len = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_len);
    $encrypted = substr($data, $iv_len);
    return openssl_decrypt($encrypted, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
}

function read_secure_file($filename) {
    global $data_dir, $encryption_key;
    $path = $data_dir . $filename;
    if (!file_exists($path)) return null;
    $encrypted = file_get_contents($path);
    $json = decrypt($encrypted, $encryption_key);
    return json_decode($json, true);
}

function write_secure_file($filename, $data) {
    global $data_dir, $encryption_key;
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $encrypted = encrypt($json, $encryption_key);
    file_put_contents($data_dir . $filename, $encrypted);
}

// User Management
function get_user_credentials() {
    $users = read_secure_file('users.json');
    if (!$users) {
        $users = ['username' => 'frost0xx', 'password' => '381984'];
        write_secure_file('users.json', $users);
    }
    return $users;
}

function get_auth_token() {
    if (isset($_COOKIE['todo_auth_token'])) {
        return $_COOKIE['todo_auth_token'];
    }
    return null;
}

function is_authorized() {
    global $auth_token_base;
    $creds = get_user_credentials();
    return get_auth_token() === $auth_token_base . $creds['username'];
}

// Logging
function log_change($todoId, $action, $oldData, $newData, $description = null) {
    $changes = read_secure_file('changelog.json') ?: ['changes' => []];
    if (!isset($changes['changes'][$todoId])) $changes['changes'][$todoId] = [];
    $changes['changes'][$todoId][] = [
        'id' => (string)(time() * 1000),
        'timestamp' => date('c'),
        'action' => $action,
        'oldData' => $oldData,
        'newData' => $newData,
        'description' => $description
    ];
    if (count($changes['changes'][$todoId]) > 100) {
        $changes['changes'][$todoId] = array_slice($changes['changes'][$todoId], -100);
    }
    write_secure_file('changelog.json', $changes);
}

// Routing
// Remove query string
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Strip /api and /index.php for routing
$path = str_replace(['/api', '/index.php'], '', $path);
$method = $_SERVER['REQUEST_METHOD'];

// Auth Endpoint
if ($path === '/auth/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $creds = get_user_credentials();
    if ($input['username'] === $creds['username'] && $input['password'] === $creds['password']) {
        $token = $auth_token_base . $creds['username'];
        setcookie('todo_auth_token', $token, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true
        ]);
        echo json_encode(['token' => $token, 'username' => $creds['username']]);
    } else {
        http_response_code(401);
        echo json_encode(['message' => 'Ungültige Zugangsdaten']);
    }
    exit;
}
elseif ($path === '/auth/logout' && $method === 'POST') {
    setcookie('todo_auth_token', '', time() - 3600, '/');
    echo json_encode(['success' => true]);
    exit;
}

// All other endpoints require auth
if (!is_authorized()) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

if ($path === '/auth/update' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['username']) && !empty($input['password'])) {
        write_secure_file('users.json', ['username' => $input['username'], 'password' => $input['password']]);
        // Update cookie with new token
        $token = $auth_token_base . $input['username'];
        setcookie('todo_auth_token', $token, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => true
        ]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['message' => 'Username und Passwort erforderlich']);
    }
}
elseif ($path === '/data' && $method === 'GET') {
    echo json_encode(read_secure_file('db.json') ?: ['todos' => []]);
}
elseif ($path === '/todos' && $method === 'POST') {
    $newData = json_decode(file_get_contents('php://input'), true);
    $oldData = read_secure_file('db.json') ?: ['todos' => []];
    foreach ($newData['todos'] as $newTodo) {
        $existed = null;
        foreach ($oldData['todos'] as $old) { if ($old['id'] === $newTodo['id']) { $existed = $old; break; } }
        if (!$existed) log_change($newTodo['id'], 'created', null, $newTodo);
        elseif (json_encode($existed) !== json_encode($newTodo)) log_change($newTodo['id'], 'updated', $existed, $newTodo);
    }
    foreach ($oldData['todos'] as $oldTodo) {
        $exists = false;
        foreach ($newData['todos'] as $new) { if ($new['id'] === $oldTodo['id']) { $exists = true; break; } }
        if (!$exists) log_change($oldTodo['id'], 'deleted', $oldTodo, null);
    }
    write_secure_file('db.json', $newData);
    echo json_encode(['success' => true]);
}
elseif ($path === '/settings' && $method === 'GET') {
    echo json_encode(read_secure_file('settings.json') ?: []);
}
elseif ($path === '/settings' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    write_secure_file('settings.json', $data);
    echo json_encode(['success' => true]);
}
elseif ($path === '/archive' && $method === 'GET') {
    echo json_encode(read_secure_file('archive.json') ?: ['archivedTodos' => []]);
}
elseif ($path === '/archive' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    write_secure_file('archive.json', $data);
    echo json_encode(['success' => true]);
}
elseif ($path === '/backup/export' && $method === 'GET') {
    $data = [
        'timestamp' => date('c'),
        'version' => '1.0',
        'data' => [
            'todos' => (read_secure_file('db.json') ?: ['todos' => []])['todos'],
            'archivedTodos' => (read_secure_file('archive.json') ?: ['archivedTodos' => []])['archivedTodos'],
            'settings' => read_secure_file('settings.json') ?: []
        ]
    ];
    echo json_encode($data);
}
elseif ($path === '/backup/import' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['data'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Ungültiges Format']);
        exit;
    }
    
    $data = $input['data'];
    $currentTodos = (read_secure_file('db.json') ?: ['todos' => []])['todos'];
    $currentArchived = (read_secure_file('archive.json') ?: ['archivedTodos' => []])['archivedTodos'];
    
    $skippedCount = 0;
    
    // Import Todos
    if (isset($data['todos'])) {
        foreach ($data['todos'] as $imported) {
            $duplicate = false;
            foreach ($currentTodos as $existing) {
                if (json_encode($existing) === json_encode($imported)) { $duplicate = true; break; }
            }
            if (!$duplicate) {
                $currentTodos[] = $imported;
                log_change($imported['id'], 'imported', null, $imported, 'Aus Backup importiert');
            } else {
                $skippedCount++;
            }
        }
        write_secure_file('db.json', ['todos' => $currentTodos]);
    }
    
    // Import Archive
    if (isset($data['archivedTodos'])) {
        foreach ($data['archivedTodos'] as $imported) {
            $duplicate = false;
            foreach ($currentArchived as $existing) {
                if (json_encode($existing) === json_encode($imported)) { $duplicate = true; break; }
            }
            if (!$duplicate) {
                $currentArchived[] = $imported;
                log_change($imported['id'], 'imported', null, $imported, 'Aus Backup importiert (archiviert)');
            } else {
                $skippedCount++;
            }
        }
        write_secure_file('archive.json', ['archivedTodos' => $currentArchived]);
    }
    
    // Import Settings (Overwrite)
    if (isset($data['settings'])) {
        write_secure_file('settings.json', $data['settings']);
    }
    
    echo json_encode(['success' => true, 'skippedCount' => $skippedCount]);
}
elseif ($path === '/changelog' && $method === 'GET') {
    $changes = read_secure_file('changelog.json') ?: ['changes' => []];
    $todos = (read_secure_file('db.json') ?: ['todos' => []])['todos'];
    $archived = (read_secure_file('archive.json') ?: ['archivedTodos' => []])['archivedTodos'];
    $allChanges = [];
    foreach ($changes['changes'] as $todoId => $list) {
        $todoName = "Unbekannt";
        foreach ($todos as $t) { if ($t['id'] === $todoId) { $todoName = $t['name']; break; } }
        if ($todoName === "Unbekannt") foreach ($archived as $t) { if ($t['id'] === $todoId) { $todoName = $t['name']; break; } }
        foreach ($list as $c) {
            if (!isset($c['undone']) || !$c['undone']) {
                $c['todoId'] = $todoId;
                $c['todoName'] = $todoName;
                $allChanges[] = $c;
            }
        }
    }
    usort($allChanges, function($a, $b) { return strcmp($b['timestamp'], $a['timestamp']); });
    echo json_encode(['changes' => $allChanges]);
}
elseif ($path === '/changelog/undo' && $method === 'POST') {
    echo json_encode(['success' => true]);
}
elseif ($path === '/changelog/delete' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['todoId']) || !isset($input['changeId'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing todoId or changeId']);
        exit;
    }
    $changes = read_secure_file('changelog.json') ?: ['changes' => []];
    $todoId = $input['todoId'];
    $changeId = $input['changeId'];
    if (isset($changes['changes'][$todoId])) {
        $filtered = array_filter($changes['changes'][$todoId], function($c) use ($changeId) {
            return $c['id'] !== $changeId;
        });
        $changes['changes'][$todoId] = array_values($filtered);
        write_secure_file('changelog.json', $changes);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Change not found']);
    }
}
else {
    http_response_code(404);
}
