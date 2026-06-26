<?php

/**
 * Generic HTTP client for glpIA plugin.
 *
 * Wraps GLPI's Toolbox::callCurl() to provide a clean interface
 * for JSON POST requests to external APIs (DeepSeek, etc.).
 *
 * @since 1.0.0
 */
class PluginGlpiaApiConsumer
{
    /**
     * Send a POST request with JSON body.
     *
     * @param string $url     API endpoint URL
     * @param array  $data    Data to send as JSON body
     * @param array  $headers Additional HTTP headers (e.g. Authorization)
     * @param int    $timeout Request timeout in seconds (default 60)
     *
     * @return array Decoded JSON response, or ['error' => '...'] on failure
     */
    public static function postJson(string $url, array $data, array $headers = [], int $timeout = 60): array
    {
        $defaultHeaders = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $out = [
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_TIMEOUT    => $timeout,
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => json_encode($data),
        ];

        $error    = null;
        $response = Toolbox::callCurl($url, $out, $error);

        if ($error !== null || empty($response)) {
            return ['error' => $error ?? 'Empty response'];
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            return ['error' => 'Invalid JSON response: ' . substr($response, 0, 200)];
        }

        return $decoded;
    }
}
