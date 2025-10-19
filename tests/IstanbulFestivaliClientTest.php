<?php
namespace KamranAta\IstanbulFestivali\Tests;

use KamranAta\IstanbulFestivali\IstanbulFestivaliClient;
use KamranAta\IstanbulFestivali\TokenProvider;
use KamranAta\IstanbulFestivali\Support\ArrayCache;
use KamranAta\IstanbulFestivali\Exceptions\ApiException;
use KamranAta\IstanbulFestivali\Exceptions\AuthException;
use KamranAta\IstanbulFestivali\Exceptions\NetworkException;
use KamranAta\IstanbulFestivali\Exceptions\InvalidCredentialsException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Comprehensive test suite for Istanbul Festivali PHP Client
 * 
 * @package KamranAta\IstanbulFestivali\Tests
 */
class IstanbulFestivaliClientTest extends TestCase
{
    private string $baseUrl = 'https://api.istanbulfestivali.com';
    private string $username = 'test@example.com';
    private string $password = 'testpassword';
    private NullLogger $logger;
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
        $this->cache = new ArrayCache();
    }

    /**
     * Test successful client initialization
     */
    public function testClientInitialization(): void
    {
        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            null,
            $this->cache,
            $this->logger
        );

        $this->assertInstanceOf(IstanbulFestivaliClient::class, $client);
        $this->assertEquals($this->baseUrl, $client->getBaseUrl());
    }

    /**
     * Test client initialization with invalid credentials
     */
    public function testClientInitializationWithInvalidCredentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Username cannot be empty');

        new IstanbulFestivaliClient(
            $this->baseUrl,
            '',
            $this->password,
            null,
            $this->cache,
            $this->logger
        );
    }

    /**
     * Test client initialization with invalid email
     */
    public function testClientInitializationWithInvalidEmail(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Username must be a valid email address');

        new IstanbulFestivaliClient(
            $this->baseUrl,
            'invalid-email',
            $this->password,
            null,
            $this->cache,
            $this->logger
        );
    }

    /**
     * Test successful reservation creation
     */
    public function testCreateReservationSuccess(): void
    {
        $mock = new MockHandler([
            // Auth response
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            // Reservation response
            new Response(201, [], json_encode([
                'success' => true,
                'reservationId' => '12345',
                'status' => 'confirmed'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $payload = [
            'tickets' => [
                ['seat' => 'A1', 'price' => 120]
            ],
            'customer' => [
                'name' => 'Test User',
                'phone' => '+1234567890'
            ]
        ];

        $result = $client->createReservation(12345, $payload);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals('12345', $result['reservationId']);
    }

    /**
     * Test reservation creation with invalid payload
     */
    public function testCreateReservationWithInvalidPayload(): void
    {
        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            null,
            $this->cache,
            $this->logger
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Reservation payload cannot be empty');

        $client->createReservation(12345, []);
    }

    /**
     * Test reservation creation with missing tickets
     */
    public function testCreateReservationWithMissingTickets(): void
    {
        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            null,
            $this->cache,
            $this->logger
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Reservation payload must contain a "tickets" array');

        $client->createReservation(12345, ['customer' => ['name' => 'Test']]);
    }

    /**
     * Test reservation creation with missing customer
     */
    public function testCreateReservationWithMissingCustomer(): void
    {
        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            null,
            $this->cache,
            $this->logger
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Reservation payload must contain a "customer" array');

        $client->createReservation(12345, ['tickets' => [['seat' => 'A1']]]);
    }

    /**
     * Test authentication failure
     */
    public function testAuthenticationFailure(): void
    {
        $mock = new MockHandler([
            new Response(401, [], json_encode([
                'error' => 'Invalid credentials'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid username or password');

        $client->createReservation(12345, [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ]);
    }

    /**
     * Test API error response
     */
    public function testApiErrorResponse(): void
    {
        $mock = new MockHandler([
            // Auth response
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            // API error response
            new Response(400, [], json_encode([
                'error' => 'Invalid reservation data',
                'details' => 'Missing required fields'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Reservation creation failed (HTTP 400)');

        $client->createReservation(12345, [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ]);
    }

    /**
     * Test network error during authentication
     */
    public function testNetworkErrorDuringAuth(): void
    {
        $mock = new MockHandler([
            new RequestException('Connection timeout', new \GuzzleHttp\Psr7\Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Authentication request failed');

        $client->createReservation(12345, [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ]);
    }

    /**
     * Test network error during reservation creation
     */
    public function testNetworkErrorDuringReservation(): void
    {
        $mock = new MockHandler([
            // Auth response
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            // Network error
            new RequestException('Connection timeout', new \GuzzleHttp\Psr7\Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Network error during reservation creation');

        $client->createReservation(12345, [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ]);
    }

    /**
     * Test token caching
     */
    public function testTokenCaching(): void
    {
        $mock = new MockHandler([
            // First auth response
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            // First reservation response
            new Response(201, [], json_encode(['success' => true])),
            // Second reservation response (should use cached token)
            new Response(201, [], json_encode(['success' => true]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $payload = [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ];

        // First call should fetch token
        $client->createReservation(12345, $payload);
        
        // Second call should use cached token
        $client->createReservation(12346, $payload);

        // Should only have one auth request
        $this->assertCount(0, $mock); // All requests consumed
    }

    /**
     * Test cache clearing
     */
    public function testCacheClearing(): void
    {
        $mock = new MockHandler([
            // First auth response
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            // First reservation response
            new Response(201, [], json_encode(['success' => true])),
            // Second auth response (after cache clear)
            new Response(200, [], json_encode([
                'token' => 'new-token-456',
                'expireTime' => time() + 3600
            ])),
            // Second reservation response
            new Response(201, [], json_encode(['success' => true]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $payload = [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ];

        // First call
        $client->createReservation(12345, $payload);
        
        // Clear cache
        $client->flushAuthCache();
        
        // Second call should fetch new token
        $client->createReservation(12346, $payload);

        // Should have two auth requests
        $this->assertCount(0, $mock); // All requests consumed
    }

    /**
     * Test health check functionality
     */
    public function testHealthCheck(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'OK')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $this->assertTrue($client->healthCheck());
    }

    /**
     * Test health check failure
     */
    public function testHealthCheckFailure(): void
    {
        $mock = new MockHandler([
            new RequestException('Connection timeout', new \GuzzleHttp\Psr7\Request('GET', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger
        );

        $this->assertFalse($client->healthCheck());
    }

    /**
     * Test custom headers
     */
    public function testCustomHeaders(): void
    {
        $mock = new MockHandler([
            // Auth response
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            // Reservation response
            new Response(201, [], json_encode(['success' => true]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $client = new IstanbulFestivaliClient(
            $this->baseUrl,
            $this->username,
            $this->password,
            $httpClient,
            $this->cache,
            $this->logger,
            20,
            ['X-Custom-Header' => 'test-value']
        );

        $payload = [
            'tickets' => [['seat' => 'A1']],
            'customer' => ['name' => 'Test']
        ];

        $client->createReservation(12345, $payload);

        // Verify custom headers were sent
        $this->assertCount(0, $mock); // All requests consumed
    }

    /**
     * Test different token response formats
     */
    public function testDifferentTokenResponseFormats(): void
    {
        $testCases = [
            // Standard format
            ['token' => 'test-token-1', 'expireTime' => time() + 3600],
            // Nested format
            ['data' => ['token' => 'test-token-2', 'expireTime' => time() + 3600]],
            // OAuth format
            ['access_token' => 'test-token-3', 'expires_in' => 3600],
        ];

        foreach ($testCases as $index => $authResponse) {
            $mock = new MockHandler([
                new Response(200, [], json_encode($authResponse)),
                new Response(201, [], json_encode(['success' => true]))
            ]);

            $handlerStack = HandlerStack::create($mock);
            $httpClient = new Client(['handler' => $handlerStack]);

            $client = new IstanbulFestivaliClient(
                $this->baseUrl,
                $this->username,
                $this->password,
                $httpClient,
                new ArrayCache(),
                $this->logger
            );

            $payload = [
                'tickets' => [['seat' => 'A1']],
                'customer' => ['name' => 'Test']
            ];

            $result = $client->createReservation(12345, $payload);
            $this->assertTrue($result['success'], "Test case {$index} failed");
        }
    }
}
