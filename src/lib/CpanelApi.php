<?php
// src/lib/CpanelApi.php
// Safe wrapper for cPanel API 2 & UAPI over HTTPS using API Tokens
// Works on shared hosting — no shell access required

declare(strict_types=1);

class CpanelApi
{
    private string $cpanelUser;
    private string $apiToken;
    private string $host;
    private bool $useSSL;

    public function __construct(array $config)
    {
        $this->cpanelUser = $config['user'] ?? '';
        $this->apiToken   = $config['token'] ?? '';
        $this->host       = $config['host'] ?? 'localhost';
        $this->useSSL     = !empty($config['ssl']);
    }

    /**
     * Call a cPanel UAPI function
     * Example: callUapi("Email", "list_pops", ["api.version" => 1])
     */
    public function callUapi(string $module, string $function, array $params = []): array
    {
        return $this->request("uapi", $module, $function, $params);
    }

    /**
     * Call a cPanel API2 function (legacy)
     * Example: callApi2("MysqlFE", "listdbs", ["api.version" => 2])
     */
    public function callApi2(string $module, string $function, array $params = []): array
    {
        return $this->request("json-api/cpanel", $module, $function, $params, 2);
    }

    /**
     * Core HTTP request handler
     */
    private function request(string $endpoint, string $module, string $function, array $params, int $version = 3): array
    {
        $scheme = $this->useSSL ? 'https' : 'http';
        $url = "{$scheme}://{$this->host}:2083/{$endpoint}?cpanel_jsonapi_user={$this->cpanelUser}"
             . "&cpanel_jsonapi_apiversion={$version}"
             . "&cpanel_jsonapi_module={$module}"
             . "&cpanel_jsonapi_func={$function}";

        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $url .= '&' . urlencode($key) . '=' . urlencode((string)$value);
            }
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: cpanel {$this->cpanelUser}:{$this->apiToken}",
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return ['status' => 'error', 'error' => $err];
        }
        curl_close($ch);

        $data = json_decode($resp, true);
        return $data ?: ['status' => 'error', 'error' => 'Invalid JSON or empty response'];
    }
}


// EXAMPLE USAGE
// require_once __DIR__ . '/../src/lib/CpanelApi.php';
// $cfg = require __DIR__ . '/../config.php';
// $cp = new CpanelApi($cfg['cpanel']);

// Example: list databases
// $response = $cp->callUapi('Mysql', 'list_databases');
// print_r($response);
