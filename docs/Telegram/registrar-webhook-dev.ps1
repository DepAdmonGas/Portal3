$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$envPath = Join-Path $projectRoot '.env'
if (-not (Test-Path -LiteralPath $envPath)) {
    throw "No se encontró el .env del proyecto: $envPath"
}

$config = @{}
Get-Content -LiteralPath $envPath | ForEach-Object {
    if ($_ -match '^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$') {
        $key = $Matches[1]
        $value = $Matches[2].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or
            ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $config[$key] = $value
    }
}

$token = $config['TELEGRAM_BOT_TOKEN_DEV']
$secret = $config['TELEGRAM_WEBHOOK_SECRET_DEV']
$demoToken = $config['TELEGRAM_BOT_TOKEN_DEMO']

if ([string]::IsNullOrWhiteSpace($token) -or [string]::IsNullOrWhiteSpace($secret)) {
    throw 'Falta TELEGRAM_BOT_TOKEN_DEV o TELEGRAM_WEBHOOK_SECRET_DEV en el .env.'
}
if ($demoToken -and $token -ceq $demoToken) {
    throw 'DEV y DEMO tienen el mismo token. Se canceló para evitar reemplazar el webhook DEMO.'
}
if ($secret -notmatch '^[A-Za-z0-9_-]{1,256}$') {
    throw 'El secreto DEV debe contener únicamente letras, números, guion bajo o guion medio.'
}

$api = "https://api.telegram.org/bot$token"
$url = 'https://portal3.admongas.com.mx/telegram/webhook/dev'
try {
    $response = Invoke-RestMethod -Method Post -Uri "$api/setWebhook" `
        -ContentType 'application/x-www-form-urlencoded' `
        -Body @{ url = $url; secret_token = $secret; allowed_updates = '["message"]' }
} catch {
    throw 'Falló el registro de DEV. No compartas el detalle completo del error porque puede incluir el token.'
}

if (-not $response.ok) {
    throw 'Telegram no aceptó el registro del webhook DEV.'
}
Write-Host "Registro DEV: $($response.description)"

try {
    $info = (Invoke-RestMethod -Method Get -Uri "$api/getWebhookInfo").result
} catch {
    throw 'El webhook se registró, pero falló la consulta de estado. No compartas el detalle completo porque puede incluir el token.'
}

[pscustomobject]@{
    Perfil = 'DEV'
    Url = $info.url
    Pendientes = $info.pending_update_count
    UltimoError = $info.last_error_message
}

Remove-Variable token, secret, demoToken, api, response, info, config -ErrorAction SilentlyContinue
