# Istanbul Festivali PHP Client

A robust, framework-agnostic PHP client for the Istanbul Festivali API with automatic token management, comprehensive error handling, and full PSR compliance.

## Features

- 🔐 **Automatic Authentication**: Handles token retrieval, caching, and refresh automatically
- 🚀 **Framework Agnostic**: Works with any PHP framework or vanilla PHP
- 📦 **PSR Compliant**: Implements PSR-3 (Logging) and PSR-16 (Caching) standards
- 🛡️ **Comprehensive Error Handling**: Specific exception types for different error scenarios
- ⚡ **Performance Optimized**: Built-in caching, connection pooling, and configurable timeouts
- 🔧 **Highly Configurable**: Custom headers, timeouts, and cache implementations
- 📊 **Full Test Coverage**: Comprehensive test suite with unit and integration tests
- 📝 **Well Documented**: Extensive PHPDoc comments and usage examples

## Installation

```bash
composer require kamranata/istanbul-festivali-php-client
```

## Requirements

- PHP 8.0 or higher
- GuzzleHTTP 7.9+ or 8.0+
- PSR-3 compatible logger (optional)
- PSR-16 compatible cache (optional)

## Quick Start

```php
<?php
use KamranAta\IstanbulFestivali\IstanbulFestivaliClient;
use KamranAta\IstanbulFestivali\Support\ArrayCache;
use GuzzleHttp\Client as GuzzleClient;

// Initialize the client
$client = new IstanbulFestivaliClient(
    baseUrl: 'https://api.istanbulfestivali.com',
    username: 'your-email@example.com',
    password: 'your-password',
    http: new GuzzleClient(),
    cache: new ArrayCache()
);

// Create a reservation
$response = $client->createReservation(12345, [
    'tickets' => [
        ['seat' => 'A1', 'price' => 120],
        ['seat' => 'A2', 'price' => 120],
    ],
    'customer' => [
        'name' => 'John Doe',
        'phone' => '+1234567890',
        'email' => 'john@example.com',
    ],
]);

print_r($response);
```

## Advanced Usage

### Custom Configuration

```php
use KamranAta\IstanbulFestivali\IstanbulFestivaliClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

// Custom logger
$logger = new Logger('istanbul-festivali');
$logger->pushHandler(new StreamHandler('app.log', Logger::INFO));

// Custom cache (Redis, Memcached, etc.)
$cache = new FilesystemAdapter('istanbul-festivali', 0, '/tmp/cache');

// Custom HTTP client with additional options
$httpClient = new GuzzleClient([
    'timeout' => 30,
    'connect_timeout' => 10,
    'verify' => false, // Only for development, enable on production
]);

$client = new IstanbulFestivaliClient(
    baseUrl: 'https://api.istanbulfestivali.com',
    username: 'your-email@example.com',
    password: 'your-password',
    http: $httpClient,
    cache: $cache,
    logger: $logger,
    timeout: 30,
    defaultHeaders: [
        'X-Custom-Header' => 'custom-value',
        'User-Agent' => 'MyApp/1.0',
    ]
);
```

### Error Handling

```php
use KamranAta\IstanbulFestivali\Exceptions\ApiException;
use KamranAta\IstanbulFestivali\Exceptions\AuthException;
use KamranAta\IstanbulFestivali\Exceptions\NetworkException;
use KamranAta\IstanbulFestivali\Exceptions\InvalidCredentialsException;

try {
    $response = $client->createReservation(12345, $payload);
} catch (InvalidCredentialsException $e) {
    // Handle invalid credentials
    echo "Invalid credentials: " . $e->getMessage();
} catch (AuthException $e) {
    // Handle authentication errors
    echo "Authentication failed: " . $e->getMessage();
} catch (NetworkException $e) {
    // Handle network errors
    echo "Network error: " . $e->getMessage();
} catch (ApiException $e) {
    // Handle API errors
    echo "API error (HTTP {$e->getHttpStatusCode()}): " . $e->getMessage();
    $responseBody = $e->getResponseBody();
    if ($responseBody) {
        print_r($responseBody);
    }
}
```

### Health Check

```php
// Check if the API is accessible
if ($client->healthCheck()) {
    echo "API is healthy";
} else {
    echo "API is not accessible";
}
```

### Cache Management

```php
// Clear authentication cache (force re-authentication)
$client->flushAuthCache();

// Get current base URL
echo $client->getBaseUrl();
```

## API Reference

### IstanbulFestivaliClient

#### Constructor

```php
public function __construct(
    string $baseUrl,
    string $username,
    string $password,
    ?ClientInterface $http = null,
    ?CacheInterface $cache = null,
    ?LoggerInterface $logger = null,
    int $timeout = 20,
    array $defaultHeaders = []
)
```

#### Methods

- `createReservation(string|int $reservationId, array $payload): array`
- `flushAuthCache(): void`
- `healthCheck(): bool`
- `getBaseUrl(): string`

### TokenProvider

Handles authentication token management with automatic caching and refresh.

#### Methods

- `getToken(): string`
- `clear(): void`

### ArrayCache

A simple in-memory cache implementation for development and testing.

#### Methods

Implements all PSR-16 Simple Cache interface methods:
- `get($key, $default = null)`
- `set($key, $value, $ttl = null): bool`
- `delete($key): bool`
- `clear(): bool`
- `getMultiple($keys, $default = null)`
- `setMultiple($values, $ttl = null): bool`
- `deleteMultiple($keys): bool`
- `has($key): bool`

## Exception Types

### ApiException
Thrown when API requests fail with HTTP error status codes.
- `getHttpStatusCode(): int` - Get the HTTP status code
- `getResponseBody(): ?array` - Get the API response body

### AuthException
Thrown when authentication fails or tokens are invalid.

### NetworkException
Thrown when network-related errors occur during API requests.

### InvalidCredentialsException
Thrown when provided credentials are invalid or malformed.

## Testing

The package includes comprehensive tests covering:

- Unit tests for all components
- Integration tests with mocked HTTP responses
- Error handling scenarios
- Token caching and expiration
- Different authentication response formats
- Edge cases and error conditions

### Running Tests

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test-coverage
```

## Configuration Options

### Timeout Settings
- **Authentication timeout**: 15 seconds (configurable in TokenProvider)
- **Request timeout**: 20 seconds (configurable in client constructor)
- **Health check timeout**: 5 seconds (hardcoded for quick checks)

### Cache Settings
- **Default TTL**: 3600 seconds (1 hour)
- **Safety margin**: 30 seconds before token expiration
- **Cache key**: `istanbulfestivali.auth.token`

### Supported Token Formats
The client automatically handles various token response formats:
- Standard: `{ "token": "...", "expireTime": "..." }`
- Nested: `{ "data": { "token": "...", "expireTime": "..." } }`
- OAuth: `{ "access_token": "...", "expires_in": 3600 }`

## Security Considerations

- Credentials are validated before making requests
- Tokens are cached securely with proper TTL management
- All requests use HTTPS by default
- Sensitive data is not logged in debug output
- Automatic token refresh prevents expired token usage

## Performance Features

- **Automatic token caching**: Reduces authentication requests
- **Connection reuse**: HTTP client reuses connections
- **Configurable timeouts**: Prevents hanging requests
- **Efficient error handling**: Minimal overhead for error scenarios
- **Memory-efficient caching**: ArrayCache uses minimal memory

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/kamranata/istanbul-festivali-php-client).
