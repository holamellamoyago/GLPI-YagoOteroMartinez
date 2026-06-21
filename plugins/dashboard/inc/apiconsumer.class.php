<?php
class PluginDashboardApiConsumer {
    static function fetchData(string $url) : array {
        $out = [
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30
        ];

        $error = null;
        $response = Toolbox::callCurl($url, $out, $error);

        if($error != null || empty($response)) {
            return ['error' => $error ?? 'Empty response'];
        }

        return json_decode($response, true) ?? ['error' => 'Invalid JSON'];
    }
}