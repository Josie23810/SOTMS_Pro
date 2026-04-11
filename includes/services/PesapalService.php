<?php

class PesapalService {
    public static function getConfig() {
        return require dirname(__DIR__, 2) . '/config/pesapal.php';
    }

    public static function getReadiness() {
        $config = self::getConfig();
        $issues = [];

        if (trim((string) ($config['consumer_key'] ?? '')) === '') {
            $issues[] = 'Missing PesaPal consumer key.';
        }

        if (trim((string) ($config['consumer_secret'] ?? '')) === '') {
            $issues[] = 'Missing PesaPal consumer secret.';
        }

        if (trim((string) ($config['app_base_url'] ?? '')) === '') {
            $issues[] = 'Missing app base URL.';
        }

        $publicBaseUrl = trim((string) ($config['public_base_url'] ?? ''));
        if ($publicBaseUrl === '') {
            $issues[] = 'Set a public base URL for PesaPal IPN registration.';
        } elseif (preg_match('/localhost|127\.0\.0\.1/i', $publicBaseUrl)) {
            $issues[] = 'PesaPal IPN must use a public URL, not localhost.';
        }

        return [
            'ready' => empty($issues),
            'issues' => $issues,
            'config' => $config,
        ];
    }

    public static function createCheckout(array $payment, array $payer) {
        $readiness = self::getReadiness();
        if (!$readiness['ready']) {
            throw new RuntimeException(implode(' ', $readiness['issues']));
        }

        $config = $readiness['config'];
        $token = self::requestToken($config);
        $notificationId = trim((string) ($config['notification_id'] ?? ''));

        if ($notificationId === '') {
            $notificationId = self::registerIpnUrl($config, $token);
        }

        $payload = [
            'id' => $payment['reference'],
            'currency' => $payment['currency'] ?? 'KES',
            'amount' => (float) $payment['amount'],
            'description' => substr((string) ($payment['description'] ?? 'Tutoring session payment'), 0, 100),
            'callback_url' => self::joinUrl($config['app_base_url'], $config['callback_path'] ?? '/payments/callback.php'),
            'cancellation_url' => self::joinUrl($config['app_base_url'], $config['cancel_path'] ?? '/payments/fail.php?reason=cancelled'),
            'notification_id' => $notificationId,
            'redirect_mode' => 'TOP_WINDOW',
            'branch' => $config['branch'] ?? 'SOTMS Pro',
            'billing_address' => self::buildBillingAddress($payer),
        ];

        $response = self::requestJson(
            'POST',
            self::apiBaseUrl($config) . '/Transactions/SubmitOrderRequest',
            $payload,
            $token
        );

        if ((string) ($response['status'] ?? '') !== '200' || empty($response['redirect_url']) || empty($response['order_tracking_id'])) {
            $message = $response['message'] ?? 'PesaPal did not return a checkout URL.';
            throw new RuntimeException('PesaPal order request failed. ' . $message);
        }

        return [
            'order_tracking_id' => $response['order_tracking_id'],
            'merchant_reference' => $response['merchant_reference'] ?? $payment['reference'],
            'redirect_url' => $response['redirect_url'],
            'notification_id' => $notificationId,
            'raw' => $response,
        ];
    }

    public static function getTransactionStatus($orderTrackingId) {
        $config = self::getConfig();
        $token = self::requestToken($config);
        $response = self::requestJson(
            'GET',
            self::apiBaseUrl($config) . '/Transactions/GetTransactionStatus?orderTrackingId=' . rawurlencode((string) $orderTrackingId),
            null,
            $token
        );

        return $response;
    }

    public static function mapStatusToLocal($gatewayStatus) {
        $normalized = strtoupper(trim((string) $gatewayStatus));

        if ($normalized === 'COMPLETED') {
            return 'paid';
        }

        if ($normalized === 'REVERSED') {
            return 'refunded';
        }

        if (in_array($normalized, ['FAILED', 'INVALID'], true)) {
            return 'failed';
        }

        return 'gateway_submitted';
    }

    private static function apiBaseUrl(array $config) {
        return (($config['environment'] ?? 'live') === 'sandbox')
            ? 'https://cybqa.pesapal.com/pesapalv3/api'
            : 'https://pay.pesapal.com/v3/api';
    }

    private static function requestToken(array $config) {
        $response = self::requestJson(
            'POST',
            self::apiBaseUrl($config) . '/Auth/RequestToken',
            [
                'consumer_key' => $config['consumer_key'],
                'consumer_secret' => $config['consumer_secret'],
            ]
        );

        if ((string) ($response['status'] ?? '') !== '200' || empty($response['token'])) {
            $message = $response['message'] ?? 'Unable to authenticate with PesaPal.';
            throw new RuntimeException('PesaPal authentication failed. ' . $message);
        }

        return $response['token'];
    }

    private static function registerIpnUrl(array $config, $token) {
        $response = self::requestJson(
            'POST',
            self::apiBaseUrl($config) . '/URLSetup/RegisterIPN',
            [
                'url' => self::joinUrl($config['public_base_url'], $config['ipn_path'] ?? '/payments/ipn.php'),
                'ipn_notification_type' => $config['ipn_notification_type'] ?? 'GET',
            ],
            $token
        );

        if ((string) ($response['status'] ?? '') !== '200' || empty($response['ipn_id'])) {
            $message = $response['message'] ?? 'Unable to register the IPN URL.';
            throw new RuntimeException('PesaPal IPN setup failed. ' . $message);
        }

        return $response['ipn_id'];
    }

    private static function buildBillingAddress(array $payer) {
        $nameParts = preg_split('/\s+/', trim((string) ($payer['name'] ?? 'Student')), 3) ?: ['Student'];
        $firstName = $nameParts[0] ?? 'Student';
        $middleName = count($nameParts) === 3 ? ($nameParts[1] ?? '') : '';
        $lastName = count($nameParts) >= 2 ? ($nameParts[count($nameParts) - 1] ?? '') : '';

        return [
            'email_address' => trim((string) ($payer['email'] ?? '')),
            'phone_number' => trim((string) ($payer['phone'] ?? '')),
            'country_code' => 'KE',
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'line_1' => trim((string) ($payer['address_line'] ?? 'SOTMS Pro')),
            'line_2' => '',
            'city' => trim((string) ($payer['city'] ?? '')),
            'state' => '',
            'postal_code' => '',
            'zip_code' => '',
        ];
    }

    private static function joinUrl($baseUrl, $path) {
        $baseUrl = rtrim((string) $baseUrl, '/');
        if ($baseUrl === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', (string) $path)) {
            return (string) $path;
        }

        return $baseUrl . '/' . ltrim((string) $path, '/');
    }

    private static function requestJson($method, $url, array $payload = null, $bearerToken = null) {
        $ch = curl_init($url);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        if (!empty($bearerToken)) {
            $headers[] = 'Authorization: Bearer ' . $bearerToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $responseBody = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('PesaPal request failed. ' . $curlError);
        }

        $decoded = json_decode($responseBody, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unexpected PesaPal response: ' . $responseBody);
        }

        if ($httpStatus >= 400) {
            $message = $decoded['message'] ?? ('HTTP ' . $httpStatus);
            throw new RuntimeException('PesaPal request failed. ' . $message);
        }

        return $decoded;
    }
}
