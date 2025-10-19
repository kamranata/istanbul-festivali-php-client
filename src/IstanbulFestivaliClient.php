<?php
namespace KamranAta\IstanbulFestivali;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use KamranAta\IstanbulFestivali\Exceptions\ApiException;
use KamranAta\IstanbulFestivali\Exceptions\AuthException;
use KamranAta\IstanbulFestivali\Exceptions\NetworkException;
use KamranAta\IstanbulFestivali\Exceptions\InvalidCredentialsException;
use KamranAta\IstanbulFestivali\Support\ArrayCache;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Istanbul Festivali API Client
 * 
 * A framework-agnostic PHP client for the Istanbul Festivali API with automatic
 * token management, caching, and comprehensive error handling.
 * 
 * @package KamranAta\IstanbulFestivali
 * @author Kamran Ata
 */
final class IstanbulFestivaliClient
{
    private ClientInterface $http;
    private TokenProvider $tokens;
    private LoggerInterface $logger;
    private string $baseUrl;
    private int $timeout;
    private array $defaultHeaders;

    /**
     * Initialize the Istanbul Festivali API client
     * 
     * @param string $baseUrl The base URL of the Istanbul Festivali API
     * @param string $username Username for authentication
     * @param string $password Password for authentication
     * @param ClientInterface|null $http HTTP client instance (defaults to Guzzle)
     * @param CacheInterface|null $cache Cache implementation (defaults to ArrayCache)
     * @param LoggerInterface|null $logger Logger instance (defaults to NullLogger)
     * @param int $timeout Request timeout in seconds (default: 20)
     * @param array $defaultHeaders Additional default headers to include in requests
     * 
     * @throws InvalidCredentialsException When credentials are invalid
     */
    public function __construct(
        string $baseUrl,
        string $username,
        string $password,
        ?ClientInterface $http = null,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        int $timeout = 20,
        array $defaultHeaders = []
    ) {
        $this->validateCredentials($username, $password);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->defaultHeaders = $defaultHeaders;
        
        $this->http = $http ?? new Client(['http_errors' => false, 'timeout' => $timeout]);
        $this->logger = $logger ?? new NullLogger();
        $cache ??= new ArrayCache();

        $this->tokens = new TokenProvider(
            http: $this->http,
            baseUrl: $this->baseUrl,
            username: $username,
            password: $password,
            cache: $cache,
            logger: $this->logger,
            timeout: 15
        );
    }

    /**
     * Create a new reservation
     * 
     * Makes a POST request to the Istanbul Festivali API to create a reservation
     * with the specified ID and payload data.
     * 
     * @param string|int $reservationId The reservation ID to create
     * @param array $payload The reservation data to send
     * @return array The API response as an associative array
     * @throws AuthException When authentication fails
     * @throws ApiException When the API request fails
     * @throws NetworkException When network errors occur
     */
    public function createReservation(string|int $reservationId, array $payload): array
    {
        $this->validateReservationPayload($payload);
        
        $token = $this->tokens->getToken();
        $url = $this->baseUrl . '/api/iticket-reservations/' . urlencode((string)$reservationId);

        $this->logger->info('Creating reservation', [
            'url' => $url,
            'reservationId' => $reservationId,
            'payload' => $payload
        ]);

        try {
            $res = $this->http->request('POST', $url, [
                'headers' => array_merge([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'X-Auth-Token'  => $token,
                ], $this->defaultHeaders),
                'json' => $payload,
                'timeout' => $this->timeout,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Network error during reservation creation', [
                'error' => $e->getMessage(),
                'url' => $url
            ]);
            throw new NetworkException('Network error during reservation creation: ' . $e->getMessage(), 0, $e);
        }

        $status = $res->getStatusCode();
        $body = (string) $res->getBody();
        $json = json_decode($body, true);

        $this->logger->debug('Reservation creation response', [
            'status' => $status,
            'body'   => $this->truncateForLog($body),
        ]);

        if ($status >= 200 && $status < 300 && is_array($json)) {
            return $json;
        }

        throw new ApiException(
            "Reservation creation failed (HTTP {$status})", 
            $status, 
            null, 
            $json ?? ['raw' => $body]
        );
    }

    /**
     * Clear the authentication token cache
     * 
     * Use this method to force a fresh authentication on the next request
     * when you suspect the cached token is invalid.
     */
    public function flushAuthCache(): void
    {
        $this->tokens->clear();
        $this->logger->info('Authentication cache cleared');
    }

    /**
     * Perform a health check on the API
     * 
     * @return bool True if the API is accessible, false otherwise
     */
    public function healthCheck(): bool
    {
        try {
            $res = $this->http->request('GET', $this->baseUrl . '/api/health', [
                'timeout' => 5,
                'headers' => $this->defaultHeaders,
                'http_errors' => false,
            ]);
            return $res->getStatusCode() < 400;
        } catch (\Throwable $e) {
            $this->logger->warning('Health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get the current base URL
     * 
     * @return string The base URL being used for API requests
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Validate credentials before initialization
     * 
     * @param string $username The username to validate
     * @param string $password The password to validate
     * @throws InvalidCredentialsException When credentials are invalid
     */
    private function validateCredentials(string $username, string $password): void
    {
        if (empty(trim($username))) {
            throw new InvalidCredentialsException('Username cannot be empty');
        }
        
        if (empty(trim($password))) {
            throw new InvalidCredentialsException('Password cannot be empty');
        }
        
        if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidCredentialsException('Username must be a valid email address');
        }
    }

    /**
     * Validate reservation payload structure
     * 
     * @param array $payload The payload to validate
     * @throws ApiException When payload is invalid
     */
    private function validateReservationPayload(array $payload): void
    {
        if (empty($payload)) {
            throw new ApiException('Reservation payload cannot be empty');
        }
        
        if (!isset($payload['tickets']) || !is_array($payload['tickets'])) {
            throw new ApiException('Reservation payload must contain a "tickets" array');
        }
        
        if (!isset($payload['customer']) || !is_array($payload['customer'])) {
            throw new ApiException('Reservation payload must contain a "customer" array');
        }
    }

    /**
     * Truncate long strings for logging purposes
     * 
     * @param string $s The string to truncate
     * @param int $max Maximum length before truncation
     * @return string The truncated string
     */
    private function truncateForLog(string $s, int $max = 2000): string
    {
        return strlen($s) > $max ? substr($s, 0, $max) . '…(truncated)' : $s;
    }
}
