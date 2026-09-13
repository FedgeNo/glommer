<?php

declare(strict_types=1);

/** Real isolated daemon on ephemeral loopback ports, with a disposable signing key. */
class WebSocketFixture
{
    public const SECRET = 'test-only-websocket-daemon-secret';
    private $process;
    private array $pipes = [];
    private array $sockets = [];
    private array $environment = [];
    private int $port;
    private int $pushPort;

    public function __construct(array $limits = [])
    {
        Env::get('WS_SECRET');
        $listeners = [stream_socket_server('tcp://127.0.0.1:0'), stream_socket_server('tcp://127.0.0.1:0')];
        $this -> port = (int) substr(strrchr(stream_socket_get_name($listeners[0], false), ':'), 1);
        $this -> pushPort = (int) substr(strrchr(stream_socket_get_name($listeners[1], false), ':'), 1);
        foreach ($listeners as $listener) fclose($listener);

        foreach (['WS_SECRET' => self::SECRET, 'WS_HOST' => '127.0.0.1', 'WS_PORT' => (string) $this -> port,
            'WS_PUSH_PORT' => (string) $this -> pushPort, 'WS_TLS_CERT' => '', 'WS_TLS_KEY' => '', 'WATCHDOG_USEC' => '0'] + $limits as $name => $value) {
            $this -> environment[$name] = getenv($name);
            putenv($name . '=' . $value);
        }
        Config::reload();
        $this -> process = proc_open([PHP_BINARY, __DIR__ . '/../bin/websocket-server.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $this -> pipes);
        fclose($this -> pipes[0]);
        stream_set_blocking($this -> pipes[1], false);
        stream_set_blocking($this -> pipes[2], false);
        $deadline = microtime(true) + 3;
        do {
            $probe = @stream_socket_client('tcp://127.0.0.1:' . $this -> pushPort, $code, $error, 0.05);
            if ($probe !== false) {
                fclose($probe);
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this -> close();
        throw new \RuntimeException('Isolated WebSocket daemon did not start.');
    }

    public function connect(string $token)
    {
        $socket = $this -> pending();
        fwrite($socket, "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: "
            . base64_encode(random_bytes(16)) . "\r\nSec-WebSocket-Version: 13\r\n\r\n");
        $headers = '';
        while (!str_ends_with($headers, "\r\n\r\n")) {
            $byte = fread($socket, 1);
            if ($byte === '') throw new \RuntimeException('WebSocket handshake timed out.');
            $headers .= $byte;
        }
        if (!str_starts_with($headers, 'HTTP/1.1 101')) throw new \RuntimeException('WebSocket handshake refused.');
        $this -> text($socket, $token);
        return $socket;
    }

    public function pending()
    {
        $socket = stream_socket_client('tcp://127.0.0.1:' . $this -> port, $code, $error, 2);
        $this -> sockets[] = $socket;
        stream_set_timeout($socket, 2);
        return $socket;
    }

    public function text($socket, string $text, int $opcode = 1, bool $fin = true): void
    {
        $mask = random_bytes(4);
        $length = strlen($text);
        $frame = chr(($fin ? 128 : 0) | $opcode) . ($length < 126 ? chr(128 | $length) : chr(254) . pack('n', $length)) . $mask;
        for ($i = 0; $i < $length; $i++) $frame .= $text[$i] ^ $mask[$i % 4];
        fwrite($socket, $frame);
    }

    public function control(string $line): array
    {
        $socket = stream_socket_client('tcp://127.0.0.1:' . $this -> pushPort, $code, $error, 2);
        stream_set_timeout($socket, 2);
        fwrite($socket, $line . "\n");
        $reply = fgets($socket);
        fclose($socket);
        return json_decode($reply === false ? '{}' : $reply, true) ?? [];
    }

    public function push(int $user_id, array $payload = ['test' => true]): int
    {
        return $this -> control(WebSocketPushRequest::encode($user_id, $payload))['delivered'] ?? 0;
    }

    public function waitForClients(int $user_id, int $count): void
    {
        $deadline = microtime(true) + 2;
        do {
            if ($this -> push($user_id) === $count) return;
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new \RuntimeException('Unexpected number of authenticated fixture connections.');
    }

    public function close(): void
    {
        foreach ($this -> sockets as $socket) if (is_resource($socket)) fclose($socket);
        $this -> sockets = [];
        if (is_resource($this -> process)) {
            proc_terminate($this -> process);
            foreach ($this -> pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
            proc_close($this -> process);
        }
        foreach ($this -> environment as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
        $this -> environment = [];
        Config::reload();
    }
}
