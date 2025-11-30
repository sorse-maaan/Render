<?php
// webhook.php для Render без Stripe SDK

$payload = @file_get_contents('php://input');
file_put_contents('debug_webhook.txt', $payload);

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

    // Тут можна додати виклик Qeng API для створення команди
    // Наприклад:
    // createTeamInQeng($team_name, $email);

    // Логування
    file_put_contents('teams_log.txt', "$team_name | $email\n", FILE_APPEND);

    echo json_encode(['status'=>'processed']);
    exit;
}

// Якщо інша подія
echo json_encode(['status'=>'ignored']);
