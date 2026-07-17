<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use XPayr\Exception\XPayrException;
use XPayr\Http\TransportInterface;
use XPayr\Webhook;
use XPayr\XPayrClient;

final class FakeTransport implements TransportInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $requests = [];
    /** @param array{status: int, body: string} $response */
    public function __construct(private array $response) {}
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return $this->response;
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$transport = new FakeTransport(['status' => 201, 'body' => '{"id":"ps_test","payment_url":"https://xpayr.com/pay/ps_test"}']);
$client = new XPayrClient('sk_test_example', transport: $transport);
$session = $client->createPayment(['amount' => '49.90', 'currency' => 'USDC', 'network' => 'bsc-testnet']);
$assert($session['id'] === 'ps_test', 'Payment response should be decoded.');
$assert($transport->requests[0]['method'] === 'POST', 'Payment should use POST.');
$assert($transport->requests[0]['headers']['Authorization'] === 'Bearer sk_test_example', 'Bearer token missing.');
$assert(str_contains((string) $transport->requests[0]['body'], '49.90'), 'Payment body missing.');

$failed = new FakeTransport(['status' => 422, 'body' => '{"error":{"code":"validation_error","message":"Invalid amount"}}']);
try {
    (new XPayrClient('sk_test_example', transport: $failed))->createPayment([]);
    throw new RuntimeException('Expected API exception.');
} catch (XPayrException $exception) {
    $assert($exception->status === 422, 'API status should be preserved.');
    $assert($exception->errorCode === 'validation_error', 'API error code should be preserved.');
}

$raw = '{"id":"evt_test","type":"payment.completed"}';
$secret = 'whsec_test_example';
$signature = hash_hmac('sha256', $raw, $secret);
$assert(Webhook::verify($raw, $signature, $secret), 'Valid webhook signature should pass.');
$assert(Webhook::verify($raw, 'sha256=' . $signature, $secret), 'Prefixed signature should pass.');
$assert(!Webhook::verify($raw . 'x', $signature, $secret), 'Mutated body should fail.');
$assert(Webhook::constructEvent($raw, $signature, $secret)['id'] === 'evt_test', 'Event should decode.');

fwrite(STDOUT, "OK ({$assertions} assertions)\n");
