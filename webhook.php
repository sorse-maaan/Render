<?php
// webhook.php для Stripe → Qeng

// Логування отриманого payload
$payload = @file_get_contents('php://input');
file_put_contents('debug_webhook.txt', $payload);

// Декодуємо JSON
$event = json_decode($payload, true);
if (!$event) {
    http_response_code(400);
    echo json_encode(['status'=>'invalid payload']);
    exit;
}

// Обробляємо тільки подію завершення оплати
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

    $cookie_path = __DIR__ . "/cookies/qeng_cookie.txt";
    $auth_result = auth('alexem','{Q_W)9m12f',$cookie_path);

    // Перевірка авторизації через user_id
    if (!isset($auth_result['user_id']) || !$auth_result['user_id']) {
        http_response_code(500);
        echo json_encode(['status'=>'auth_failed','auth_result'=>$auth_result]);
        exit;
    }

    // --- Створення команди ---
    $url = 'https://consensus.qeng.org/admin/game_teams.php?gid=5181&json';
    $data_string = json_encode([$team_name]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_path);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    $result_data = json_decode($result, true);

    // Логування результату
    file_put_contents('teams_log.txt', date('Y-m-d H:i:s') . " | Team: $team_name | Email: $email | Result: $result\n", FILE_APPEND);

    // Перевірка, чи команда створена
    if (!empty($result_data[0]['id']) && !empty($result_data[0]['access_key'])) {
        $team_id = $result_data[0]['id'];
        $key = $result_data[0]['access_key'];
        $link = "https://consensus.qeng.org/game/5181/?team_id=$team_id&key=$key&lang=auto";

        // Додаткове логування посилання
        file_put_contents('teams_log.txt', "Link: $link\n", FILE_APPEND);
    }

    http_response_code(200);
    echo json_encode(['status'=>'team_created','result'=>$result_data]);
    exit;
}

// Інші події ігноруються
http_response_code(200);
echo json_encode(['status'=>'ignored']);
