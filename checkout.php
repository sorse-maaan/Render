<?php
// checkout.php без SDK — створює Stripe Checkout Session через cURL

// ВАЖЛИВО: заміни на свій sk_test_... ключ
$secret_key = 'sk_test_51SWwALRZPo919yOosVfkezgqQpGkYuTGCnnu9JkhdSak9QOuYGqkrHdRPRLzMXnit1dyZR3icLqs3MaG7u3yKUCc008hVeHiRV';

// Отримуємо (можливо) POST або дозволяємо GET для швидкого тесту
$team_name = $_POST['team_name'] ?? $_GET['team_name'] ?? 'DefaultTeam';
$email = $_POST['email'] ?? $_GET['email'] ?? '';

// проста валідація
if (!$team_name) {
    http_response_code(400);
    echo json_encode(['error' => 'team_name required']);
    exit;
}

// Параметри Stripe (вказані в форматі form data для API)
$post_data = [
    'payment_method_types[]' => 'card',
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => 'Участь у грі',
    'line_items[0][price_data][unit_amount]' => 500, // 5.00 USD -> 500 cents
    'line_items[0][quantity]' => 1,
    'mode' => 'payment',
    // success / cancel — заміни на свій Render URL
    'success_url' => 'https://render-3tbz.onrender.com/success.php?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url' => 'https://render-3tbz.onrender.com/cancel.php',
    // metadata — передаємо назву команди та email
    'metadata[team_name]' => $team_name,
    'metadata[email]' => $email
];

// cURL на Stripe API
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_USERPWD, $secret_key . ':');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

// Якщо потрібна діагностика, розкоментуй
// curl_setopt($ch, CURLOPT_VERBOSE, true);

$result = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json');

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'curl_error', 'message' => $curlErr]);
    exit;
}

$response = json_decode($result, true);

// Якщо Stripe повернув URL — редіректимо клієнта на Checkout
if (!empty($response['url'])) {
    // якщо це виклики через fetch (наш випадок) — повертаємо JSON з url
    echo json_encode(['url' => $response['url']]);
    exit;
}

// Інакше повертаємо повний raw response для дебагу
http_response_code($httpcode ?: 500);
echo json_encode(['error' => 'no_url', 'stripe_response' => $response]);
