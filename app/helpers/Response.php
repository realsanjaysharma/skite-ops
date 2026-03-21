<?php

class Response
{
    public static function success($data = null, int $statusCode = 200): void
    {
        http_response_code($statusCode);

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        http_response_code($statusCode);

        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
}
