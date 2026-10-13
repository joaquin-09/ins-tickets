<?php

declare(strict_types=1);

namespace InsTickets\Verification;

use RuntimeException;

final class HttpClient
{
    private array $cookies = [];

    public function __construct(private readonly string $origin = 'http://web-validation')
    {
        if ($origin !== 'http://web-validation') {
            throw new RuntimeException('El cliente automático solo puede usar web-validation.');
        }
    }

    public function request(string $path, ?array $post = null): array
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, "\r") || str_contains($path, "\n")) {
            throw new RuntimeException('Ruta inválida.');
        }
        $headers = ['Connection: close'];
        if ($this->cookies !== []) {
            $parts = [];
            foreach ($this->cookies as $name => $value) { $parts[] = $name . '=' . $value; }
            $headers[] = 'Cookie: ' . implode('; ', $parts);
        }
        if ($post !== null) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
        $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers), 'content' => $post === null ? '' : http_build_query($post),
            'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
        $start = hrtime(true);
        $body = @file_get_contents($this->origin . $path, false, $context);
        $elapsed = (hrtime(true) - $start) / 1e9;
        $responseHeaders = $http_response_header ?? [];
        if ($body === false || !preg_match('/^HTTP\/\S+ ([0-9]{3})/', $responseHeaders[0] ?? '', $match)) {
            throw new RuntimeException('Fallo de transporte HTTP, sin resultados válidos.');
        }
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $cookie)) {
                $this->cookies[$cookie[1]] = $cookie[2];
            }
        }
        return ['status' => (int) $match[1], 'body' => $body, 'headers' => $responseHeaders, 'seconds' => $elapsed];
    }

    public static function token(string $body, string $name = '_csrf'): string
    {
        if (!preg_match('/name="' . preg_quote($name, '/') . '"\s+value="([^"]+)"/', $body, $match)) {
            throw new RuntimeException('No se encontró el token del formulario.');
        }
        return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    }

    public function login(string $email): array
    {
        $form = $this->request('/login');
        $response = $this->request('/login', ['_csrf' => self::token($form['body']), 'email' => $email, 'password' => 'ValidacionLocal2026!']);
        if ($response['status'] !== 303) { throw new RuntimeException('No se pudo autenticar fixture.'); }
        return $response;
    }

    public function sessionId(): ?string
    {
        return $this->cookies['INSSESSID'] ?? null;
    }
}
