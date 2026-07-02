<?php
/**
 * TestClient.php
 * A lightweight HTTP client for endpoint testing.
 * Wraps cURL to support GET, POST (form & JSON), sessions, and cookies.
 */
class TestClient
{
    private string $baseUrl;
    private string $cookieJar;
    private bool   $verbose;

    public function __construct(string $baseUrl, bool $verbose = false)
    {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->cookieJar = sys_get_temp_dir() . '/nmims_test_cookies_' . getmypid() . '.txt';
        $this->verbose   = $verbose;
    }

    /**
     * Make a GET request.
     */
    public function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url, []);
    }

    /**
     * Make a POST request with form-encoded data.
     */
    public function post(string $path, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        return $this->request('POST', $url, $data, 'form');
    }

    /**
     * Make a POST request with JSON body.
     */
    public function postJson(string $path, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        return $this->request('POST', $url, $data, 'json');
    }

    /**
     * Reset the session (clear cookies).
     */
    public function clearSession(): void
    {
        if (file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    /**
     * Core cURL driver.
     *
     * @return array{body: string, json: mixed, status: int, headers: string}
     */
    private function request(string $method, string $url, array $data, string $bodyType = 'form'): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,         // include headers in output
            CURLOPT_FOLLOWLOCATION => false,         // do NOT follow redirects — we want to see 302s
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_VERBOSE        => $this->verbose,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($bodyType === 'json') {
                $json = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hdrLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($raw, 0, $hdrLen);
        $body    = substr($raw, $hdrLen);
        $json    = json_decode($body, true);

        return [
            'body'    => $body,
            'json'    => $json,
            'status'  => $status,
            'headers' => $headers,
        ];
    }

    public function __destruct()
    {
        $this->clearSession();
    }
}
