<?php
namespace Pesapal;

class Pesapal {
    private $consumerKey;
    private $consumerSecret;
    private $environment;
    private $baseUrl;

    public function __construct($consumerKey, $consumerSecret, $environment = 'sandbox') {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->environment = $environment;
        $this->baseUrl = $environment === 'sandbox' ? 'https://demo.pesapal.com/API/v3' : 'https://www.pesapal.com/API/v3';
    }

    public function submitOrder($orderData) {
        $data = json_encode($orderData);
        $token = $this->getOAuthToken();
        if (!$token) return false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/SubmitOrderRequest');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['token'] ?? false;
    }

    private function getOAuthToken() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/OAuthRequestToken');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        parse_str($response, $params);
        return $params['oauth_token'] ?? false;
    }

    public static function transactionStatus($txnId, $consumerKey, $consumerSecret, $environment = 'sandbox') {
        $pesapal = new self($consumerKey, $consumerSecret, $environment);
        $token = $pesapal->getOAuthToken();
        if (!$token) return false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pesapal->baseUrl . '/TransactionStatus?pesapal_transaction_tracking_id=' . $txnId);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['payment_status'] ?? 'FAILED';
    }
}

