<?php

declare(strict_types=1);

namespace XPayr;

use InvalidArgumentException;
use JsonException;

final class Webhook
{
    public static function verify(string $rawBody, string $signatureHeader, string $secret): bool
    {
        if ($secret === '') {
            throw new InvalidArgumentException('Webhook secret is required.');
        }

        $signature = strtolower(trim($signatureHeader));
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }
        if (preg_match('/^[a-f0-9]{64}$/', $signature) !== 1) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /** @return array<string, mixed> */
    public static function constructEvent(string $rawBody, string $signatureHeader, string $secret): array
    {
        if (!self::verify($rawBody, $signatureHeader, $secret)) {
            throw new InvalidArgumentException('Invalid XPayr webhook signature.');
        }

        try {
            $event = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Webhook body is not valid JSON.', previous: $exception);
        }
        if (!is_array($event)) {
            throw new InvalidArgumentException('Webhook body must decode to an object.');
        }

        return $event;
    }
}
