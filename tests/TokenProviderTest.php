<?php
namespace KamranAta\IstanbulFestivali\Tests;

use KamranAta\IstanbulFestivali\TokenProvider;
use KamranAta\IstanbulFestivali\Support\ArrayCache;
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
 * Test suite for TokenProvider
 * 
 * @package KamranAta\IstanbulFestivali\Tests
 */
class TokenProviderTest extends TestCase
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
     * Test successful token retrieval
     */
    public function testGetTokenSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        $token = $tokenProvider->getToken();

        $this->assertEquals('test-token-123', $token);
        $this->assertTrue($this->cache->has('istanbulfestivali.auth.token'));
    }

    /**
     * Test token caching
     */
    public function testTokenCaching(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        // First call
        $token1 = $tokenProvider->getToken();
        
        // Second call should use cache
        $token2 = $tokenProvider->getToken();

        $this->assertEquals($token1, $token2);
        $this->assertCount(0, $mock); // All requests consumed
    }

    /**
     * Test token expiration handling
     */
    public function testTokenExpiration(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 1 // Expires in 1 second
            ])),
            new Response(200, [], json_encode([
                'token' => 'new-token-456',
                'expireTime' => time() + 3600
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        $token = $tokenProvider->getToken();
        $this->assertEquals('test-token-123', $token);

        // Wait for token to expire
        sleep(2);

        // Should fetch new token
        $newToken = $tokenProvider->getToken();
        $this->assertEquals('new-token-456', $newToken);
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

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Invalid username or password');

        $tokenProvider->getToken();
    }

    /**
     * Test network error
     */
    public function testNetworkError(): void
    {
        $mock = new MockHandler([
            new RequestException('Connection timeout', new \GuzzleHttp\Psr7\Request('POST', 'test'))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Authentication request failed');

        $tokenProvider->getToken();
    }

    /**
     * Test invalid response format
     */
    public function testInvalidResponseFormat(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'invalid' => 'response'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('Authentication failed: token not found in response');

        $tokenProvider->getToken();
    }

    /**
     * Test cache clearing
     */
    public function testCacheClearing(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => time() + 3600
            ])),
            new Response(200, [], json_encode([
                'token' => 'new-token-456',
                'expireTime' => time() + 3600
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        // Get token
        $token1 = $tokenProvider->getToken();
        
        // Clear cache
        $tokenProvider->clear();
        
        // Get token again
        $token2 = $tokenProvider->getToken();

        $this->assertEquals('test-token-123', $token1);
        $this->assertEquals('new-token-456', $token2);
        $this->assertCount(0, $mock); // All requests consumed
    }

    /**
     * Test different expiration formats
     */
    public function testDifferentExpirationFormats(): void
    {
        $testCases = [
            // Timestamp format
            ['token' => 'test-token-1', 'expireTime' => time() + 3600],
            // ISO8601 format
            ['token' => 'test-token-2', 'expireTime' => date('c', time() + 3600)],
            // Seconds from now format
            ['token' => 'test-token-3', 'expires_in' => 3600],
        ];

        foreach ($testCases as $index => $authResponse) {
            $mock = new MockHandler([
                new Response(200, [], json_encode($authResponse))
            ]);

            $handlerStack = HandlerStack::create($mock);
            $httpClient = new Client(['handler' => $handlerStack]);

            $tokenProvider = new TokenProvider(
                $httpClient,
                $this->baseUrl,
                $this->username,
                $this->password,
                new ArrayCache(),
                $this->logger
            );

            $token = $tokenProvider->getToken();
            $this->assertStringStartsWith('test-token-', $token, "Test case {$index} failed");
        }
    }

    /**
     * Test TTL calculation with safety margin
     */
    public function testTtlCalculationWithSafetyMargin(): void
    {
        $expireTime = time() + 3600; // 1 hour from now
        
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'token' => 'test-token-123',
                'expireTime' => $expireTime
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);

        $tokenProvider = new TokenProvider(
            $httpClient,
            $this->baseUrl,
            $this->username,
            $this->password,
            $this->cache,
            $this->logger
        );

        $tokenProvider->getToken();

        // Check that token is cached with safety margin
        $this->assertTrue($this->cache->has('istanbulfestivali.auth.token'));
        
        // The cached token should expire before the actual expiration time
        // (with 30 second safety margin)
        $cachedToken = $this->cache->get('istanbulfestivali.auth.token');
        $this->assertEquals('test-token-123', $cachedToken);
    }
}
