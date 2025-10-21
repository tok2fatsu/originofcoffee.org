<?php
// src/lib/Chapa.php
declare(strict_types=1);

/**
 * Lightweight Chapa API client.
 * - initializeTransaction($amount, $currency, $email, $firstName, $callbackUrl, $metadata = [])
 * - verifyTransaction($reference)
 *
 * Requires config.php with 'chapa' => ['secret' => 'YOUR_KEY', 'base' => 'https://api.chapa.co']
 */

class Chapa {
    private string $secret;
    private string $base;

    public function __construct(array $cfg) {
        $this->secret = $cfg['secret'] ?? '';
        $this->base = rtrim($cfg['base'] ?? 'https://api.chapa.co', '/');
        if (empty($this->secret)) throw new RuntimeException('Chapa secret key not configured.');
    }

    /**
     * Initialize a transaction. Returns array with at least ['data' => ['checkout_url' => '...'], ...]
     */
    public function initializeTransaction(float $amount, string $currency, string $email, string $firstName, string $callbackUrl, array $metadata = []): array {
        $url = $this->base . '/v1/transaction/initialize';
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'email' => $email,
            'firstname' => $firstName,
            'callback_url' => $callbackUrl,
            'metadata' => json_encode($metadata)
        ];

        return $this->post($url, $payload);
    }

    /**
     * Verify transaction. Returns response array (data includes status, etc)
     */
    public function verifyTransaction(string $reference): array {
        $url = $this->base . '/v1/transaction/verify/' . urlencode($reference);
        return $this->get($url);
    }

    private function post(string $url, array $payload): array {
        $ch = curl_init($url);
        $json = json_encode($payload);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->secret
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Chapa POST error: ' . $err);
        }
        curl_close($ch);
        $arr = json_decode($res, true);
        if (!is_array($arr)) throw new RuntimeException('Chapa POST: invalid JSON response');
        return $arr;
    }

    private function get(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->secret
            ],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Chapa GET error: ' . $err);
        }
        curl_close($ch);
        $arr = json_decode($res, true);
        if (!is_array($arr)) throw new RuntimeException('Chapa GET: invalid JSON response');
        return $arr;
    }
}
