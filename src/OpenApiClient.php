<?php

namespace Dave\UblBuilder;

use Exception;

/**
 * OpenAPI Client for OpenAI API
 * 
 * A simple, comprehensive client that handles all OpenAI API interactions
 * with proper error handling, retry logic, and configuration management.
 */
class OpenApiClient
{
    // Default configuration constants
    private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_TIMEOUT = 60;
    private const DEFAULT_CONNECT_TIMEOUT = 30;
    private const DEFAULT_MAX_RETRIES = 3;
    private const DEFAULT_USER_AGENT = 'UBL-Invoice-Parser/1.0';
    private const DEFAULT_SSL_VERIFY = true;
    private const DEFAULT_DEBUG = false;

    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private int $connectTimeout;
    private int $maxRetries;
    private string $userAgent;
    private bool $sslVerify;
    private bool $debug;

    /**
     * Constructor
     *
     * @param string $apiKey OpenAI API key
     * @param array  $config Configuration options
     */
    public function __construct(string $apiKey, array $config = [])
    {
        if (empty(trim($apiKey))) {
            throw new Exception('OpenAI API key cannot be empty');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = $config['base_url'] ?? self::DEFAULT_BASE_URL;
        $this->timeout = $config['timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->connectTimeout = $config['connect_timeout'] ?? self::DEFAULT_CONNECT_TIMEOUT;
        $this->maxRetries = $config['max_retries'] ?? self::DEFAULT_MAX_RETRIES;
        $this->userAgent = $config['user_agent'] ?? self::DEFAULT_USER_AGENT;
        $this->sslVerify = $config['ssl_verify'] ?? self::DEFAULT_SSL_VERIFY;
        $this->debug = $config['debug'] ?? self::DEFAULT_DEBUG;
    }

    /**
     * Send chat completion request
     *
     * @param string $model       Model to use (e.g., 'gpt-4o-mini')
     * @param array  $messages    Messages array
     * @param array  $options     Additional options
     * @return array Response data
     * @throws Exception If request fails
     */
    public function chatCompletion(string $model, array $messages, array $options = []): array
    {
        $data = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ], $options);

        return $this->makeRequest('POST', '/chat/completions', $data);
    }

    /**
     * Send vision request (for image processing)
     *
     * @param string $model       Vision-enabled model (e.g., 'gpt-4o')
     * @param string $textPrompt  Text prompt
     * @param string $imageData   Base64 encoded image data
     * @param string $mimeType    MIME type (e.g., 'image/jpeg', 'image/png')
     * @param array  $options     Additional options
     * @return array Response data
     * @throws Exception If request fails
     */
    public function visionRequest(string $model, string $textPrompt, string $imageData, string $mimeType = 'image/jpeg', array $options = []): array
    {
        // Validate that it's an image MIME type
        if (!str_starts_with($mimeType, 'image/')) {
            throw new Exception("Invalid MIME type for vision request: {$mimeType}. Only image types are supported.");
        }

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $textPrompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mimeType};base64,{$imageData}"
                        ]
                    ]
                ]
            ]
        ];

        $data = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object']
        ], $options);

        return $this->makeRequest('POST', '/chat/completions', $data);
    }

    /**
     * Make HTTP request with retry logic
     *
     * @param string $method   HTTP method
     * @param string $endpoint API endpoint
     * @param array  $data     Request data
     * @return array Response data
     * @throws Exception If all retries fail
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                if ($this->debug) {
                    error_log("OpenApiClient: Attempt {$attempt}/{$this->maxRetries} - {$method} {$url}");
                }

                $response = $this->executeCurlRequest($method, $url, $data);
                
                if ($this->debug) {
                    error_log("OpenApiClient: Request successful on attempt {$attempt}");
                }

                return $response;

            } catch (Exception $e) {
                $lastException = $e;
                
                if ($this->debug) {
                    error_log("OpenApiClient: Attempt {$attempt} failed: " . $e->getMessage());
                }

                // Don't retry on authentication errors or client errors (4xx)
                if ($this->isNonRetryableError($e)) {
                    throw $e;
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $this->maxRetries) {
                    $waitTime = pow(2, $attempt - 1); // 1, 2, 4 seconds
                    if ($this->debug) {
                        error_log("OpenApiClient: Waiting {$waitTime} seconds before retry");
                    }
                    sleep($waitTime);
                }
            }
        }

        throw new Exception("Request failed after {$this->maxRetries} attempts. Last error: " . $lastException->getMessage());
    }

    /**
     * Execute cURL request
     *
     * @param string $method HTTP method
     * @param string $url    Request URL
     * @param array  $data   Request data
     * @return array Response data
     * @throws Exception If request fails
     */
    private function executeCurlRequest(string $method, string $url, array $data = []): array
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $curlOptions[CURLOPT_POST] = true;
            if (!empty($data)) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            }
        }

        if ($this->debug) {
            $curlOptions[CURLOPT_VERBOSE] = true;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            $this->handleHttpError($response, $httpCode);
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decodedResponse;
    }

    /**
     * Handle HTTP error responses
     *
     * @param string $response Raw response
     * @param int    $httpCode HTTP status code
     * @throws Exception HTTP error
     */
    private function handleHttpError(string $response, int $httpCode): void
    {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'Unknown error';
        $errorType = $errorData['error']['type'] ?? 'unknown_error';
        $errorCode = $errorData['error']['code'] ?? null;

        $message = "OpenAI API error (HTTP {$httpCode}";
        if ($errorCode) {
            $message .= ", Code: {$errorCode}";
        }
        $message .= ", Type: {$errorType}): {$errorMessage}";

        throw new Exception($message, $httpCode);
    }

    /**
     * Check if error should not be retried
     *
     * @param Exception $exception The exception to check
     * @return bool True if error should not be retried
     */
    private function isNonRetryableError(Exception $exception): bool
    {
        $code = $exception->getCode();
        
        // Don't retry client errors (4xx) except for rate limiting (429)
        if ($code >= 400 && $code < 500 && $code !== 429) {
            return true;
        }

        // Don't retry authentication errors
        if (str_contains($exception->getMessage(), 'authentication') || 
            str_contains($exception->getMessage(), 'unauthorized')) {
            return true;
        }

        return false;
    }

    /**
     * Extract content from OpenAI response
     *
     * @param array $response OpenAI response
     * @return string Response content
     * @throws Exception If response structure is invalid
     */
    public function extractContent(array $response): string
    {
        if (!isset($response['choices'][0]['message']['content'])) {
            throw new Exception('Invalid response structure: missing content');
        }

        return $response['choices'][0]['message']['content'];
    }

    /**
     * Create client from environment variables
     *
     * @return self
     * @throws Exception If required environment variables are missing
     */
    public static function fromEnvironment(): self
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY');
        if (empty($apiKey)) {
            throw new Exception('OPENAI_API_KEY environment variable is required');
        }

        $config = [
            'base_url' => self::DEFAULT_BASE_URL,
            'timeout' => self::DEFAULT_TIMEOUT,
            'connect_timeout' => self::DEFAULT_CONNECT_TIMEOUT,
            'max_retries' => self::DEFAULT_MAX_RETRIES,
            'debug' => self::DEFAULT_DEBUG,
        ];

        return new self($apiKey, $config);
    }

    /**
     * Set debug mode
     *
     * @param bool $debug Whether to enable debug mode
     * @return self
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Set timeout
     *
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set max retries
     *
     * @param int $maxRetries Maximum number of retries
     * @return self
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }
}