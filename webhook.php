<?php
// webhook.php для Stripe → Qeng з розширеним логуванням

// Створюємо папку для cookie, якщо нема
$cookie_dir = __DIR__ . '/cookies';
if (!is_dir($cookie_dir)) {
    mkdir($cookie_dir, 0777, true);
}

$cookie_path = $cookie_dir . "/qeng_cookie.txt";

// Отримуємо payload від Stripe
$payload = @file_get_contents('php://input');
file_put_contents('debug_webhook.txt', $payload);

// Розпарсимо JSON
$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    echo json_encode(['status'=>'invalid payload']);
    exit;
}

// Перевіряємо тип події
if ($event['type'] === 'checkout.session.completed') {
    $session = $event['data']['object'];
    $team_name = $session['metadata']['team_name'] ?? 'DefaultTeam';
    $email = $session['metadata']['email'] ?? '';

    // --- Авторизація на Qeng ---
    function auth($login, $pass, $cookie_path) {
        $url = 'https://consensus.qeng.org/login.php?json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_path);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ["user"=>$login,"pass"=>$pass]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return json_decode($result, true);
    }

    $auth_result = auth('alexem','{Q_W)9m12f',$cookie_path);
    file_put_contents('auth_log.txt', print_r($auth_result, true), FILE_APPEND);

    if (!isset($auth_result['success']) || !$auth_result['success']) {
        http_response_code(500);
        echo json_encode(['status'=>'auth_failed','auth_result'=>$auth_result]);
        exit;
    }

    // --- Створення команди ---
    $url = 'https://consensus.qeng.org/admin/game_teams.php?gid=5181&json';
    $data_string = json_encode([["name" => $team_name]]); // правильний формат

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    $result_data = json_decode($result, true);
    file_put_contents('create_log.txt', print_r($result_data, true), FILE_APPEND);

    if (!empty($result_data[0]['id']) && !empty($result_data[0]['access_key'])) {
        $team_id = $result_data[0]['id'];
        $key = $result_data[0]['access_key'];
        $link = "https://consensus.qeng.org/game/5181/?team_id=$team_id&key=$key&lang=auto";
        file_put_contents('teams_log.txt', "$team_name | $email | $link\n", FILE_APPEND);

        http_response_code(200);
        echo json_encode(['status'=>'team_created','link'=>$link]);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['status'=>'creation_failed','result'=>$result_data]);
        exit;
    }
}

// Інші події
http_response_code(200);
echo json_encode(['status'=>'ignored']);
