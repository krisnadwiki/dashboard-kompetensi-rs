<?php
class SirsCrawler
{
    private string $baseUrl;
    private string $cookieFile;

    public function __construct(string $baseUrl = 'https://sirs.kemkes.go.id/fo')
    {
        $this->baseUrl    = rtrim($baseUrl, '/');
        $this->cookieFile = sys_get_temp_dir() . '/sirs_cookie_' . md5($baseUrl) . '.txt';
    }

    /**
     * Crawl a specific service detil URL in SIRS
     */
    public function fetchDetil(string $type, int $noLayanan, ?string $sessionCookie = null): array
    {
        // $type: 'sdm_detil' or 'alkessarana_detil'
        $url = "{$this->baseUrl}/dashboard/{$type}/{$noLayanan}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7'
        ];

        if ($sessionCookie) {
            $headers[] = "Cookie: " . $sessionCookie;
        } else if (file_exists($this->cookieFile)) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode >= 400 || !$response) {
            return [
                'success' => false,
                'error'   => $err ?: "HTTP Error {$httpCode}",
                'items'   => []
            ];
        }

        // Parse HTML table rows
        $items = $this->parseHtmlTable($response, $noLayanan, ($type === 'sdm_detil' ? 'SDM' : 'Alkes'));

        return [
            'success' => true,
            'items'   => $items,
            'html'    => substr($response, 0, 500)
        ];
    }

    /**
     * Parse HTML Table from SIRS
     */
    public function parseHtmlTable(string $html, int $noLayanan, string $komp): array
    {
        $items = [];
        // Match table rows <tr>...</tr>
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $html, $rowMatches)) {
            foreach ($rowMatches[1] as $rowHtml) {
                if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $rowHtml, $tdMatches)) {
                    $cols = array_map(function($td) {
                        return trim(strip_tags(html_entity_decode($td)));
                    }, $tdMatches[1]);

                    if (count($cols) >= 3) {
                        $nama = $cols[1] ?? $cols[0];
                        $jumlah = isset($cols[count($cols)-1]) && is_numeric($cols[count($cols)-1]) ? (int)$cols[count($cols)-1] : 1;
                        $items[] = [
                            $noLayanan,
                            "Layanan {$noLayanan}",
                            'Dasar',
                            $cols[2] ?? '',
                            $nama,
                            $komp === 'SDM' ? 'SDM Medis' : 'Alat Kesehatan',
                            'wajib',
                            $jumlah,
                            $komp === 'SDM' ? 'ksdm01' : 'kalkes01'
                        ];
                    }
                }
            }
        }
        return $items;
    }
}
