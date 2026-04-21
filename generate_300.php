<?php
$data_dir = __DIR__ . '/api/data/';
$encryption_key = "todo_secret_key_32_chars_long_!!!";

function encrypt($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function write_secure_file($filename, $data) {
    global $data_dir, $encryption_key;
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $encrypted = encrypt($json, $encryption_key);
    file_put_contents($data_dir . $filename, $encrypted);
}

$todos = [];
$tagsPool = ['Arbeit', 'Privat', 'Wichtig', 'Idee', 'Projekt A', 'Einkauf', 'Sport', 'Lernen'];
$baseTime = time() * 1000 - (300 * 60000);

for ($i = 0; $i < 300; $i++) {
    $id = $baseTime + $i * 60000;
    
    $targetDateOffset = rand(-30, 30);
    $targetDate = date('Y-m-d', strtotime("$targetDateOffset days"));
    if (rand(1, 10) == 1) $targetDate = null;
    
    $numTags = rand(0, 3);
    shuffle($tagsPool);
    $tags = array_slice($tagsPool, 0, $numTags);
    
    $status = rand(1, 10) > 8 ? 'erledigt' : 'offen';
    
    $todos[] = [
        'id' => $id,
        'order' => $i,
        'name' => "Test Aufgabe " . ($i + 1) . " - Generiert",
        'description' => "Dies ist eine automatisch generierte Test-Aufgabe (Nummer " . ($i + 1) . ") für Performance- und Lasttests der Anwendung.<br><br>Zufälliger Hash: " . bin2hex(random_bytes(4)),
        'targetDate' => $targetDate,
        'tags' => $tags,
        'status' => $status
    ];
}

write_secure_file('db.json', ['todos' => $todos]);
echo "Successfully generated 300 test tasks.";
