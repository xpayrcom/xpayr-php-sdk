<?php

declare(strict_types=1);

namespace XPayr\Http;

use XPayr\Exception\XPayrException;

final class CurlTransport implements TransportInterface
{
    public function send(string $method, string $url, array $headers, ?string $body, int $timeoutSeconds): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new XPayrException('Unable to initialize cURL.', errorCode: 'transport_error');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new XPayrException('XPayr API transport failed: ' . $message, errorCode: 'network_error');
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return ['status' => $status, 'body' => (string) $responseBody];
    }
}
