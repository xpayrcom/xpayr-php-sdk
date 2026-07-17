<?php

declare(strict_types=1);

namespace XPayr;

use InvalidArgumentException;
use JsonException;
use XPayr\Exception\XPayrException;
use XPayr\Http\CurlTransport;
use XPayr\Http\TransportInterface;

final class XPayrClient
{
    private readonly string $baseUrl;
    private readonly TransportInterface $transport;

    public function __construct(
        private readonly string $secretKey,
        string $baseUrl = 'https://xpayr.com/api/v1',
        private readonly int $timeoutSeconds = 20,
        ?TransportInterface $transport = null,
    ) {
        if ($secretKey === '') {
            throw new InvalidArgumentException('secretKey is required.');
        }
        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('timeoutSeconds must be positive.');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        if (!str_starts_with($this->baseUrl, 'https://')) {
            throw new InvalidArgumentException('baseUrl must use HTTPS.');
        }
        $this->transport = $transport ?? new CurlTransport();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed>|list<mixed>|null */
    public function createPayment(array $payload): array|null { return $this->request('POST', '/payments', $payload); }
    /** @param array<string, scalar|null> $query @return array<string, mixed>|list<mixed>|null */
    public function listPayments(array $query = []): array|null { return $this->request('GET', '/payments', query: $query); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function getPayment(string $id): array|null { return $this->request('GET', '/payments/' . rawurlencode($id)); }
    /** @param array<string, mixed> $payload @return array<string, mixed>|list<mixed>|null */
    public function completePayment(string $id, array $payload): array|null { return $this->request('POST', '/payments/' . rawurlencode($id) . '/complete', $payload); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function getMerchant(): array|null { return $this->request('GET', '/me'); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function getBalance(): array|null { return $this->request('GET', '/me/balance'); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function listNetworks(): array|null { return $this->request('GET', '/me/networks'); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function registerWebhook(string $url): array|null { return $this->request('POST', '/webhooks', ['url' => $url]); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function getWebhook(): array|null { return $this->request('GET', '/webhooks'); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function testWebhook(): array|null { return $this->request('POST', '/webhooks/test', []); }
    /** @return array<string, mixed>|list<mixed>|null */
    public function deleteWebhook(): array|null { return $this->request('DELETE', '/webhooks'); }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, scalar|null> $query
     * @return array<string, mixed>|list<mixed>|null
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): array|null
    {
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== '');
        $url = $this->baseUrl . $path . ($query === [] ? '' : '?' . http_build_query($query));
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->secretKey,
        ];
        $encodedBody = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            try {
                $encodedBody = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (JsonException $exception) {
                throw new XPayrException('Request body could not be encoded.', errorCode: 'encoding_error', previous: $exception);
            }
        }

        $response = $this->transport->send($method, $url, $headers, $encodedBody, $this->timeoutSeconds);
        $payload = null;
        if ($response['body'] !== '') {
            try {
                $decoded = json_decode($response['body'], true, flags: JSON_THROW_ON_ERROR);
                $payload = is_array($decoded) ? $decoded : ['data' => $decoded];
            } catch (JsonException) {
                $payload = ['raw' => $response['body']];
            }
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $apiError = is_array($payload['error'] ?? null) ? $payload['error'] : [];
            throw new XPayrException(
                (string) ($apiError['message'] ?? 'XPayr API request failed with status ' . $response['status']),
                status: $response['status'],
                errorCode: (string) ($apiError['code'] ?? 'api_error'),
                details: $payload,
            );
        }

        return $payload;
    }
}
