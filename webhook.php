<?php
// webhook.php для Stripe → Qeng (Render-ready)

// Логування всіх помилок
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Отримуємо payload від Stripe
$payload = @file_get_contents('php://input');
file_put_contents('debug_webhook.txt', $payload); // лог для перевірки

$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    echo json_encode(['status' => 'invalid payload']);
    exit;
}

// --- Перевірка події Stripe ---
if ($event['type'] === 'checkout.session.completed') {
    $session = $event['data']['object'];

    $team_name = $session['metadata']['team_name'] ?? 'DefaultTeam';
    $email = $session['metadata']['email'] ?? '';

    // --- Створюємо папку cookies якщо нема ---
    $cookie_dir = __DIR__ . '/cookies';
    if (!is_dir($cookie_dir)) {
        mkdir($cookie_dir, 0777, true);
    }
    $cookie_path = $cookie_dir . '/qeng_cookie.txt';

    // --- Авторизація на Qeng ---
    function auth($login, $pass, $cookie_path) {
        $url = 'https://consensus.qeng.org/login.php?json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_path);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'user' => $login,
            'pass' => $pass
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        if ($result === false) {
            return ['error' => curl_error($ch)];
        }
        curl_close($ch);

        return json_decode($result, true);
    }

    $auth_result = auth('alexem', '{Q_W)9m12f', $cookie_path);

    if (!isset($auth_result['user_id'])) {
        http_response_code(500);
        echo json_encode(['status' => 'auth_failed', 'auth_result' => $auth_result]);
        exit;
    }

    // --- Створення команди ---
    $url = 'https://consensus.qeng.org/admin/game_teams.php?gid=5181&json';
    $data = [$team_name]; // Назва команди з Stripe
    $data_string = json_encode($data);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $result = curl_exec($ch);
    $result_data = json_decode($result, true);
    curl_close($ch);

    // --- Логування результату ---
    file_put_contents('teams_log.txt', date('Y-m-d H:i:s') . " | $team_name | $email | " . json_encode($result_data) . "\n", FILE_APPEND);

    if (!empty($result_data[0]['id']) && !empty($result_data[0]['access_key'])) {
        $team_id = $result_data[0]['id'];
        $key = $result_data[0]['access_key'];
        $link = "https://consensus.qeng.org/game/5181/?team_id=$team_id&key=$key&lang=auto";

        echo json_encode(['status' => 'team_created', 'link' => $link]);
        exit;
    } else {
        echo json_encode(['status' => 'error_creating_team', 'result' => $result_data]);
        exit;
    }
}

// --- Інші події Stripe ---
http_response_code(200);
echo json_encode(['status' => 'ignored']);
