<?php

file_put_contents('debug.txt', "RAW: " . file_get_contents('php://input') . "\n\n", FILE_APPEND);

header("Content-Type: application/json");

echo json_encode(["status" => "ok"]);
http_response_code(200);
