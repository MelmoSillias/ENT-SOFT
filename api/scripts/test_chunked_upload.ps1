$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8000/api'

# 1. Login
$login = Invoke-RestMethod -Uri "$base/login" -Method POST -ContentType 'application/json' -Body '{"login":"admin","password":"123"}'
$token = $login.token; if (-not $token) { $token = $login.accessToken }; if (-not $token) { $token = $login.access_token }
if (-not $token) { Write-Host 'LOGIN FAILED'; $login | ConvertTo-Json -Depth 5; exit 1 }
$headers = @{ Authorization = "Bearer $token" }
Write-Host 'LOGIN OK'

# 2. Fichier de test 12 Mo
$size = 12MB
$bytes = New-Object byte[] $size
(New-Object Random(42)).NextBytes($bytes)
$sha = [System.Security.Cryptography.SHA256]::Create()
$checksum = ([BitConverter]::ToString($sha.ComputeHash($bytes)) -replace '-', '').ToLower()

# 3. Créer la session
$meta = @{ filename = 'gros_fichier_test.bin'; size = $size; mimeType = 'application/octet-stream'; checksum = $checksum } | ConvertTo-Json
$session = Invoke-RestMethod -Uri "$base/uploads" -Method POST -Headers $headers -ContentType 'application/json' -Body $meta
Write-Host "SESSION $($session.id) chunks=$($session.chunkCount)"

# 4. Envoyer les chunks
$chunkSize = $session.chunkSize
for ($i = 0; $i -lt $session.chunkCount; $i++) {
    $start = $i * $chunkSize
    $len = [Math]::Min($chunkSize, $size - $start)
    $slice = New-Object byte[] $len
    [Array]::Copy($bytes, $start, $slice, 0, $len)
    $r = Invoke-RestMethod -Uri "$base/uploads/$($session.id)/chunks/$i" -Method PUT -Headers $headers -ContentType 'application/octet-stream' -Body $slice
    Write-Host "CHUNK $i OK bytesReceived=$($r.bytesReceived)"
}

# 5. Compléter
$done = Invoke-RestMethod -Uri "$base/uploads/$($session.id)/complete" -Method POST -Headers $headers
Write-Host "COMPLETE status=$($done.status)"

# 6. Créer le document depuis la session
$docBody = @{ uploadSessionId = $session.id; title = 'Gros fichier test'; ownerType = 'client'; ownerId = '11111111-1111-1111-1111-111111111111' } | ConvertTo-Json
$doc = Invoke-RestMethod -Uri "$base/documents/upload" -Method POST -Headers $headers -ContentType 'application/json' -Body $docBody
Write-Host "DOCUMENT id=$($doc.id) filePath=$($doc.filePath)"

# 7. Vérifier le fichier sur disque
$path = Join-Path (Join-Path $PSScriptRoot '..\public') ($doc.filePath -replace '/', '\')
$file = Get-Item $path
Write-Host "FILE SIZE ON DISK: $($file.Length) (attendu $size)"
if ($file.Length -eq $size) { Write-Host 'E2E TEST PASSED' } else { Write-Host 'E2E TEST FAILED'; exit 1 }
