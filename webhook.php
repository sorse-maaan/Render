<?php
// webhook.php для Stripe → Qeng без файлів cookie

// Отримуємо payload від Stripe
$payload = @file_get_contents('php://input');
$event = json_decode($payload, true);

if(!$event) {
    http_response_code(400);
    echo json_encode(['status'=>'invalid payload']);
    exit;
}

// Перевіряємо тип події
if($event['type'] === 'checkout.session.completed'){
    $session = $event['data']['object'];

    $team_name = $session['metadata']['team_name'] ?? 'DefaultTeam';
    $email = $session['metadata']['email'] ?? '';

    // --- Авторизація на Qeng ---
    function auth($login, $pass) {
        $url = 'https://consensus.qeng.org/login.php?json';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            "user"=>$login,
            "pass"=>$pass
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1); // щоб отримати Set-Cookie
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        curl_close($ch);

        // витягаємо cookie
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches);
        $cookies = implode("; ", $matches[1]);

        $body = substr($response, $header_size);
        $json = json_decode($body, true);

        return ['cookies'=>$cookies, 'body'=>$json];
    }

    $auth_result = auth('alexem', '{Q_W)9m12f');

    if(isset($auth_result['body']['error'])) {
        http_response_code(500);
        echo json_encode(['status'=>'auth_failed','auth_result'=>$auth_result['body']]);
        exit;
    }

    $cookies = $auth_result['cookies'];

    // --- Створення команди ---
    $url = 'https://consensus.qeng.org/admin/game_teams.php?gid=5181&json';
    $data_string = json_encode([$team_name]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Cookie: $cookies"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);

    $result_data = json_decode($result, true);

    // --- Формуємо посилання на гру ---
    if(!empty($result_data[0]['id']) && !empty($result_data[0]['access_key'])){
        $team_id = $result_data[0]['id'];
        $key = $result_data[0]['access_key'];
        $link = "https://consensus.qeng.org/game/5181/?team_id=$team_id&key=$key&lang=auto";

        // Логування
        file_put_contents('teams_log.txt', "$team_name | $email | $link\n", FILE_APPEND);

        http_response_code(200);
        echo json_encode(['status'=>'team_created','link'=>$link]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['status'=>'error_creating_team','result'=>$result_data]);
    exit;
}

// Інші події
http_response_code(200);
echo json_encode(['status'=>'ignored']);
