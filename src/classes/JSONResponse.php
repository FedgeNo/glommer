<?php

declare(strict_types=1);

class JSONResponse
{
    public int $statusCode = 200;
    public mixed $response = null;
    public ?string $error = null;

    public static function success(mixed $response = null, int $status_code = 200): self
    {
        $json = new self();
        $json -> response = $response;
        $json -> statusCode = $status_code;

        return $json;
    }

    public static function error(string $error, int $status_code = 400): self
    {
        $json = new self();
        $json -> error = $error;
        $json -> statusCode = $status_code;

        return $json;
    }

    public function send(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($this -> statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode([
            'error' => $this -> error,
            'response' => $this -> response,
        ]);

        exit;
    }
}
