<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$apiKey       = $_ENV['GEMINI_API_KEY'] ?? '';
$webhookToken = $_ENV['API_WEBHOOK_TOKEN'] ?? '';
$portalUrl    = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');

if (empty($apiKey) || empty($webhookToken)) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: GEMINI_API_KEY ou API_WEBHOOK_TOKEN nao configurados no .env\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando curadoria autonoma com IA...\n";

$prompt = <<<EOT
Atue como editor-chefe e curador de notícias técnicas sobre Tecnologia, Inteligência Artificial, Desenvolvimento de Software e Inovação.
Pesquise ou selecione um tema de altíssima relevância e impacto tecnológico recente.
Gere uma matéria aprofundada, profissional e analítica.

Retorne EXCLUSIVAMENTE um objeto JSON válido (sem delimitadores markdown como ```json ou ```, apenas o texto bruto do JSON) contendo:
- "titulo": Título chamativo e jornalístico (máx 150 caracteres).
- "categoria": Categoria da matéria (ex: "Inteligência Artificial", "Desenvolvimento", "Segurança", "Cloud").
- "conteudo_resumo": Resumo atrativo de 2 a 3 frases para SEO e capa.
- "conteudo_completo": Artigo completo e bem estruturado (mínimo de 3 parágrafos explicativos).
- "opiniao_editorial": Análise técnica crítica sobre o impacto no mercado (essencial para valor agregado do AdSense).
- "url_fonte": Uma URL primária de referência sobre o assunto.
EOT;

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7
    ]
];

// Fila de modelos ativos identificados na sua conta para contingência automática
$modelosParaTentativa = [
    'gemini-3.8-flash',
    'gemini-3.7-flash',
    'gemini-3.6-flash',
    'gemini-flash-latest'
];

$responseGemini = '';
$sucessoIA = false;

foreach ($modelosParaTentativa as $modelo) {
    echo "[" . date('Y-m-d H:i:s') . "] Requisitando modelo: {$modelo}...\n";
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

    // Até 2 tentativas por modelo com pausa curta em caso de 503
    for ($tentativa = 1; $tentativa <= 2; $tentativa++) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $resultado = (string) curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 200 && !empty($resultado)) {
            $responseGemini = $resultado;
            $sucessoIA = true;
            break 2;
        }

        if ($httpCode === 503) {
            echo "  -> Modelo ocupado (503). Aguardando 3 segundos para reprocessar...\n";
            sleep(3);
        } else {
            break;
        }
    }
}

if (!$sucessoIA) {
    echo "[" . date('Y-m-d H:i:s') . "] Falha em todos os modelos. Última resposta recebida:\n{$resultado}\n";
    exit(1);
}

$geminiData = json_decode($responseGemini, true);
$rawText = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';

$rawText = trim($rawText);
$rawText = preg_replace('/^```(?:json)?\s*/i', '', $rawText);
$rawText = preg_replace('/```$/', '', $rawText);
$rawText = trim($rawText);

$artigoJson = json_decode($rawText, true);

if (!$artigoJson || empty($artigoJson['titulo'])) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: Falha ao decodificar o JSON retornado pela IA.\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Artigo criado com sucesso: \"" . $artigoJson['titulo'] . "\"\n";

// Disparo no Webhook interno do Portal
$webhookEndpoint = "{$portalUrl}/api/v1/noticias/webhook";

$chWebhook = curl_init($webhookEndpoint);
curl_setopt_array($chWebhook, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $webhookToken
    ],
    CURLOPT_POSTFIELDS => json_encode($artigoJson, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false
]);

$responseWebhook = (string) curl_exec($chWebhook);
$httpCodeWebhook = curl_getinfo($chWebhook, CURLINFO_HTTP_CODE);

if ($httpCodeWebhook === 201 || $httpCodeWebhook === 200) {
    echo "[" . date('Y-m-d H:i:s') . "] SUCESSO: Notícia gravada no banco de dados via Webhook!\n";
    echo "Retorno: {$responseWebhook}\n";
} else {
    echo "[" . date('Y-m-d H:i:s') . "] FALHA no Webhook. HTTP: {$httpCodeWebhook}\nDetalhes: {$responseWebhook}\n";
}
