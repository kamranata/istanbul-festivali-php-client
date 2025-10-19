<?php
namespace KamranAta\IstanbulFestivali;

use GuzzleHttp\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use KamranAta\IstanbulFestivali\Exceptions\AuthException;
use KamranAta\IstanbulFestivali\Exceptions\NetworkException;
use KamranAta\IstanbulFestivali\Exceptions\InvalidCredentialsException;

/**
 * Token Provider for Istanbul Festivali API
 * 
 * Handles authentication token retrieval, caching, and automatic refresh
 * with comprehensive error handling and security features.
 * 
 * @package KamranAta\IstanbulFestivali
 */
final class TokenProvider
{
    private const CACHE_KEY = 'istanbulfestivali.auth.token';
    private const TOKEN_REFRESH_MARGIN = 30; // Refresh token 30 seconds before expiry
    private const DEFAULT_TTL = 3600; // Default 1 hour cache

    /**
     * Initialize the token provider
     * 
     * @param ClientInterface $http HTTP client for API requests
     * @param string $baseUrl Base URL of the Istanbul Festivali API
     * @param string $username Username for authentication
     * @param string $password Password for authentication
     * @param CacheInterface $cache Cache implementation for token storage
     * @param LoggerInterface $logger Logger instance
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(
        private ClientInterface $http,
        private string $baseUrl,
        private string $username,
        private string $password,
        private CacheInterface $cache,
        private LoggerInterface $logger = new NullLogger(),
        private int $timeout = 15
    ) {}

    /**
     * Get a valid authentication token
     * 
     * Retrieves a cached token if valid, otherwise fetches a new one from the API.
     * Automatically handles token refresh and caching with proper TTL management.
     * 
     * @return string The authentication token
     * @throws AuthException When authentication fails
     * @throws NetworkException When network errors occur
     * @throws InvalidCredentialsException When credentials are invalid
     */
    public function getToken(): string
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if (is_string($cached) && strlen($cached) > 0) {
            $this->logger->debug('Using cached authentication token');
            return $cached;
        }

        $this->logger->info('Fetching new authentication token');
        return $this->fetchNewToken();
    }

    /**
     * Clear the cached authentication token
     * 
     * Forces the next request to fetch a new token from the API.
     */
    public function clear(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->logger->info('Authentication token cache cleared');
    }

    /**
     * Fetch a new authentication token from the API
     * 
     * @return string The new authentication token
     * @throws AuthException When authentication fails
     * @throws NetworkException When network errors occur
     * @throws InvalidCredentialsException When credentials are invalid
     */
    private function fetchNewToken(): string
    {
        try {
            $res = $this->http->request('POST', rtrim($this->baseUrl, '/') . '/api/auth/login', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'username' => $this->username,
                    'password' => $this->password,
                ],
                'timeout' => $this->timeout,
                'http_errors' => false,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Network error during authentication', [
                'error' => $e->getMessage(),
                'url' => rtrim($this->baseUrl, '/') . '/api/auth/login'
            ]);
            throw new NetworkException('Authentication request failed: ' . $e->getMessage(), 0, $e);
        }

        $code = $res->getStatusCode();
        $body = json_decode((string)$res->getBody(), true);

        $this->logger->debug('Authentication response', [
            'status' => $code,
            'body' => $body
        ]);

        if ($code === 401) {
            throw new InvalidCredentialsException('Invalid username or password');
        }

        if ($code >= 300 || !is_array($body)) {
            throw new AuthException("Authentication failed: unexpected response (HTTP {$code})");
        }

        // Extract token from various possible response structures
        $token = $body['token'] ?? $body['data']['token'] ?? $body['access_token'] ?? null;
        $expire = $body['expireTime'] ?? $body['data']['expireTime'] ?? $body['expires_in'] ?? null;

        if (!is_string($token) || $token === '') {
            throw new AuthException('Authentication failed: token not found in response');
        }

        // Calculate TTL with safety margin
        $ttl = $this->calculateTokenTtl($expire);

        // Cache the token with calculated TTL
        $this->cache->set(self::CACHE_KEY, $token, $ttl);

        $this->logger->info('Authentication token stored in cache', [
            'ttl' => $ttl,
            'expires_at' => $expire
        ]);

        return $token;
    }

    /**
     * Calculate token TTL with safety margin
     * 
     * @param mixed $expire Expiration time (timestamp, ISO8601 string, or seconds)
     * @return int TTL in seconds
     */
    private function calculateTokenTtl($expire): int
    {
        if (!$expire) {
            return self::DEFAULT_TTL;
        }

        $now = time();
        $expTs = null;

        if (is_numeric($expire)) {
            // If it's a small number, treat as seconds from now
            if ($expire < 1000000000) {
                $expTs = $now + (int)$expire;
            } else {
                // Otherwise treat as absolute timestamp
                $expTs = (int)$expire;
            }
        } else {
            // Try to parse as ISO8601 or other date format
            $expTs = strtotime($expire);
        }

        if ($expTs && $expTs > $now) {
            // Apply safety margin
            return max(1, $expTs - $now - self::TOKEN_REFRESH_MARGIN);
        }

        return self::DEFAULT_TTL;
    }
}
