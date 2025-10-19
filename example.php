<?php
/**
 * Example usage of Istanbul Festivali PHP Client
 * 
 * This file demonstrates how to use the client in a real application.
 * Make sure to install the dependencies first: composer install
 */

require_once 'vendor/autoload.php';

use KamranAta\IstanbulFestivali\IstanbulFestivaliClient;
use KamranAta\IstanbulFestivali\Support\ArrayCache;
use KamranAta\IstanbulFestivali\Exceptions\ApiException;
use KamranAta\IstanbulFestivali\Exceptions\AuthException;
use KamranAta\IstanbulFestivali\Exceptions\NetworkException;
use KamranAta\IstanbulFestivali\Exceptions\InvalidCredentialsException;
use GuzzleHttp\Client as GuzzleClient;

// Configuration
$config = [
    'baseUrl' => 'https://api.istanbulfestivali.com',
    'username' => 'your-email@example.com',
    'password' => 'your-password',
];

try {
    // Initialize the client
    $client = new IstanbulFestivaliClient(
        baseUrl: $config['baseUrl'],
        username: $config['username'],
        password: $config['password'],
        http: new GuzzleClient(),
        cache: new ArrayCache()
    );

    echo "✅ Client initialized successfully\n";
    echo "📍 Base URL: " . $client->getBaseUrl() . "\n";

    // Health check
    if ($client->healthCheck()) {
        echo "✅ API is healthy\n";
    } else {
        echo "❌ API is not accessible\n";
        exit(1);
    }

    // Create a reservation
    $reservationId = 12345;
    $payload = [
        'tickets' => [
            [
                'seat' => 'A1',
                'price' => 120,
                'type' => 'adult'
            ],
            [
                'seat' => 'A2', 
                'price' => 120,
                'type' => 'adult'
            ]
        ],
        'customer' => [
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@example.com',
            'address' => '123 Main St, City, Country'
        ],
        'payment' => [
            'method' => 'credit_card',
            'amount' => 240
        ]
    ];

    echo "🎫 Creating reservation {$reservationId}...\n";
    
    $response = $client->createReservation($reservationId, $payload);
    
    echo "✅ Reservation created successfully!\n";
    echo "📋 Response:\n";
    print_r($response);

} catch (InvalidCredentialsException $e) {
    echo "❌ Invalid credentials: " . $e->getMessage() . "\n";
    echo "Please check your username and password.\n";
    exit(1);
} catch (AuthException $e) {
    echo "❌ Authentication failed: " . $e->getMessage() . "\n";
    exit(1);
} catch (NetworkException $e) {
    echo "❌ Network error: " . $e->getMessage() . "\n";
    echo "Please check your internet connection and API availability.\n";
    exit(1);
} catch (ApiException $e) {
    echo "❌ API error (HTTP {$e->getHttpStatusCode()}): " . $e->getMessage() . "\n";
    
    $responseBody = $e->getResponseBody();
    if ($responseBody) {
        echo "📋 Error details:\n";
        print_r($responseBody);
    }
    exit(1);
} catch (Exception $e) {
    echo "❌ Unexpected error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 Example completed successfully!\n";
