<?php
require_once __DIR__ . "/_claude_config.php";

// Great-circle distance in kilometers between two lat/lng points.
function haversineKm($lat1, $lng1, $lat2, $lng2) {
    $earthRadiusKm = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// Builds a cheap, deterministic stand-in reply for $claudeMockMode so the whole
// chat/recommend flow can be exercised end to end without spending API credits.
// Reads naturally (no "[test mode]" labeling) — it's still just template text
// built from the context lines the caller already assembled, not a real model.
function mockClaudeReply($systemPrompt, $userMessage) {
    $lines = preg_split('/\r?\n/', $systemPrompt);
    $dataLines = array_values(array_filter($lines, function ($line) {
        $trimmed = trim($line);
        return $trimmed !== "" && (str_starts_with($trimmed, "-") || str_starts_with($trimmed, "•"));
    }));

    if (!$dataLines) {
        return "ขอโทษนะครับ ตอนนี้น้องพิกัดยังไม่มีข้อมูลเรื่องนี้เลย ลองถามใหม่อีกครั้ง หรือเปิดตำแหน่ง GPS ดูนะครับ";
    }

    $items = array_map(
        fn($l) => trim(ltrim(trim($l), "-• ")),
        array_slice($dataLines, 0, 3)
    );

    $intro = count($items) > 1
        ? "จากข้อมูลที่น้องพิกัดมีตอนนี้ ขอแนะนำดังนี้ครับ:"
        : "น้องพิกัดขอแนะนำครับ:";

    return $intro . "\n" . implode("\n", array_map(fn($item) => "• " . $item, $items));
}

// Calls Claude with $systemPrompt + $userMessage and returns plain reply text.
// In mock mode (see _claude_config.php) no network call is made at all.
// Never throws — on any failure it returns a user-friendly Thai error string.
function askClaude($systemPrompt, $userMessage, $maxTokens = 600) {
    global $anthropicApiKey, $claudeMockMode, $claudeModel;

    if ($claudeMockMode) {
        return mockClaudeReply($systemPrompt, $userMessage);
    }

    $payload = json_encode([
        "model" => $claudeModel,
        "system" => $systemPrompt,
        "messages" => [
            ["role" => "user", "content" => $userMessage],
        ],
        "max_tokens" => $maxTokens,
    ]);

    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "x-api-key: " . $anthropicApiKey,
            "anthropic-version: 2023-06-01",
            "content-type: application/json",
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false || $curlError) {
        error_log("askClaude: curl error - " . $curlError);
        return "ขออภัยครับ ตอนนี้น้องพิกัดเชื่อมต่อ AI ไม่ได้ กรุณาลองใหม่อีกครั้ง";
    }

    if ($httpCode !== 200) {
        error_log("askClaude: API returned HTTP $httpCode - " . $rawResponse);
        return "ขออภัยครับ น้องพิกัดตอบไม่ได้ในตอนนี้ กรุณาลองใหม่อีกครั้ง";
    }

    $data = json_decode($rawResponse, true);
    if (!is_array($data)) {
        error_log("askClaude: invalid JSON response - " . $rawResponse);
        return "ขออภัยครับ น้องพิกัดตอบไม่ได้ในตอนนี้ กรุณาลองใหม่อีกครั้ง";
    }

    $text = "";
    foreach ($data["content"] ?? [] as $block) {
        if (($block["type"] ?? "") === "text") {
            $text .= $block["text"];
        }
    }

    return $text !== "" ? $text : "ขออภัยครับ น้องพิกัดไม่มีคำตอบให้ในตอนนี้";
}
?>
