<?php
// Direct upload and transcription endpoint that bypasses Symfony
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// API credentials
$apiKey = 'JR-LCWjnds3scsjrtCtn2wCNlyR2GWGAQq_hJ1D6Fhh-';
$serviceUrl = 'https://api.au-syd.speech-to-text.watson.cloud.ibm.com/instances/760398a7-3622-4319-83f8-415080a4ee25';

// Log function for debugging
function logDebug($message, $data = null) {
    // Create logs directory if it doesn't exist
    $logsDir = __DIR__ . '/../var/log';
    if (!file_exists($logsDir)) {
        mkdir($logsDir, 0777, true);
    }
    
    $logFile = $logsDir . '/direct_upload.log';
    $timestamp = date('Y-m-d H:i:s');
    $dataString = $data ? json_encode($data, JSON_PRETTY_PRINT) : '';
    
    file_put_contents(
        $logFile,
        "[$timestamp] $message\n$dataString\n\n",
        FILE_APPEND
    );
}

// Start logging
logDebug('Request received', [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
    'files' => isset($_FILES) ? array_keys($_FILES) : 'No files'
]);

// Check if we have an audio file
if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] != 0) {
    logDebug('No valid audio file received', $_FILES ?? 'No files uploaded');
    echo json_encode(['error' => 'No valid audio file received']);
    exit;
}

$audioFile = $_FILES['audio_file'];
$tempPath = $audioFile['tmp_name'];
$fileName = $audioFile['name'];
$fileSize = $audioFile['size'];
$fileType = $audioFile['type'];

logDebug('Audio file received', [
    'name' => $fileName,
    'type' => $fileType,
    'size' => $fileSize,
    'temp_path' => $tempPath
]);

// Make sure the file exists
if (!file_exists($tempPath)) {
    logDebug('Temp file does not exist', ['path' => $tempPath]);
    echo json_encode(['error' => 'Temporary file does not exist']);
    exit;
}

// Determine content type from file extension
$extension = pathinfo($fileName, PATHINFO_EXTENSION);
$contentType = 'audio/wav';  // Default

if ($extension === 'mp3') {
    $contentType = 'audio/mpeg';
} elseif ($extension === 'ogg') {
    $contentType = 'audio/ogg';
} elseif ($extension === 'flac') {
    $contentType = 'audio/flac';
} elseif ($extension === 'webm') {
    $contentType = 'audio/webm';
}

logDebug('Using content type', ['content_type' => $contentType, 'extension' => $extension]);

try {
    // Read file content
    $audioContent = file_get_contents($tempPath);
    
    if ($audioContent === false) {
        throw new Exception("Failed to read audio file");
    }
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $serviceUrl . '/v1/recognize');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Disable SSL verification
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode('apikey:' . $apiKey),
        'Content-Type: ' . $contentType
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $audioContent);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Set a longer timeout for larger files
    
    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    
    logDebug('cURL request completed', [
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: 'None',
        'info' => $curlInfo
    ]);
    
    if ($curlError) {
        throw new Exception("cURL Error: " . $curlError);
    }
    
    if ($response === false) {
        throw new Exception("Failed to get response from IBM Watson API");
    }
    
    logDebug('Response received', [
        'response_preview' => substr($response, 0, 500) . '...'
    ]);
    
    // Parse response
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response: " . json_last_error_msg());
    }
    
    // Extract transcription
    $transcript = '';
    if (isset($result['results'])) {
        foreach ($result['results'] as $segment) {
            if (isset($segment['alternatives'][0]['transcript'])) {
                $transcript .= $segment['alternatives'][0]['transcript'] . ' ';
            }
        }
        
        logDebug('Transcription successful', [
            'transcription' => trim($transcript)
        ]);
        
        echo json_encode([
            'success' => true,
            'transcription' => trim($transcript)
        ]);
    } else {
        logDebug('No transcription results', $result);
        
        echo json_encode([
            'success' => false,
            'error' => 'No transcription results found',
            'response' => $result
        ]);
    }
    
    curl_close($ch);
} catch (Exception $e) {
    logDebug('Exception occurred', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 