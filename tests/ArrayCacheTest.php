<?php
namespace KamranAta\IstanbulFestivali\Tests;

use KamranAta\IstanbulFestivali\Support\ArrayCache;
use PHPUnit\Framework\TestCase;
use DateInterval;

/**
 * Test suite for ArrayCache
 * 
 * @package KamranAta\IstanbulFestivali\Tests
 */
class ArrayCacheTest extends TestCase
{
    private ArrayCache $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCache();
    }

    /**
     * Test basic set and get operations
     */
    public function testSetAndGet(): void
    {
        $this->cache->set('test-key', 'test-value');
        $this->assertEquals('test-value', $this->cache->get('test-key'));
    }

    /**
     * Test get with default value
     */
    public function testGetWithDefault(): void
    {
        $this->assertEquals('default', $this->cache->get('non-existent-key', 'default'));
    }

    /**
     * Test has method
     */
    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('non-existent-key'));
        
        $this->cache->set('test-key', 'test-value');
        $this->assertTrue($this->cache->has('test-key'));
    }

    /**
     * Test delete operation
     */
    public function testDelete(): void
    {
        $this->cache->set('test-key', 'test-value');
        $this->assertTrue($this->cache->has('test-key'));
        
        $this->cache->delete('test-key');
        $this->assertFalse($this->cache->has('test-key'));
    }

    /**
     * Test clear operation
     */
    public function testClear(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        
        $this->cache->clear();
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    /**
     * Test TTL expiration
     */
    public function testTtlExpiration(): void
    {
        $this->cache->set('test-key', 'test-value', 1); // 1 second TTL
        
        $this->assertTrue($this->cache->has('test-key'));
        $this->assertEquals('test-value', $this->cache->get('test-key'));
        
        // Wait for expiration
        sleep(2);
        
        $this->assertFalse($this->cache->has('test-key'));
        $this->assertNull($this->cache->get('test-key'));
    }

    /**
     * Test TTL with DateInterval
     */
    public function testTtlWithDateInterval(): void
    {
        $interval = new DateInterval('PT1S'); // 1 second
        $this->cache->set('test-key', 'test-value', $interval);
        
        $this->assertTrue($this->cache->has('test-key'));
        
        // Wait for expiration
        sleep(2);
        
        $this->assertFalse($this->cache->has('test-key'));
    }

    /**
     * Test getMultiple operation
     */
    public function testGetMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3', 'non-existent']);
        
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
            'non-existent' => null
        ], $result);
    }

    /**
     * Test getMultiple with default value
     */
    public function testGetMultipleWithDefault(): void
    {
        $this->cache->set('key1', 'value1');
        
        $result = $this->cache->getMultiple(['key1', 'non-existent'], 'default');
        
        $this->assertEquals([
            'key1' => 'value1',
            'non-existent' => 'default'
        ], $result);
    }

    /**
     * Test setMultiple operation
     */
    public function testSetMultiple(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        $this->cache->setMultiple($values);
        
        $this->assertEquals('value1', $this->cache->get('key1'));
        $this->assertEquals('value2', $this->cache->get('key2'));
        $this->assertEquals('value3', $this->cache->get('key3'));
    }

    /**
     * Test setMultiple with TTL
     */
    public function testSetMultipleWithTtl(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];
        
        $this->cache->setMultiple($values, 1); // 1 second TTL
        
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        
        // Wait for expiration
        sleep(2);
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    /**
     * Test deleteMultiple operation
     */
    public function testDeleteMultiple(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');
        
        $this->cache->deleteMultiple(['key1', 'key2']);
        
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));
    }

    /**
     * Test different data types
     */
    public function testDifferentDataTypes(): void
    {
        $testData = [
            'string' => 'test string',
            'integer' => 123,
            'float' => 123.45,
            'boolean' => true,
            'array' => ['a', 'b', 'c'],
            'object' => (object)['key' => 'value'],
            'null' => null
        ];
        
        foreach ($testData as $key => $value) {
            $this->cache->set($key, $value);
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    /**
     * Test automatic cleanup of expired keys
     */
    public function testAutomaticCleanup(): void
    {
        $this->cache->set('expired-key', 'value', 1);
        $this->cache->set('valid-key', 'value', 3600);
        
        $this->assertTrue($this->cache->has('expired-key'));
        $this->assertTrue($this->cache->has('valid-key'));
        
        // Wait for expiration
        sleep(2);
        
        // Check expired key
        $this->assertFalse($this->cache->has('expired-key'));
        
        // Check valid key
        $this->assertTrue($this->cache->has('valid-key'));
    }

    /**
     * Test edge cases
     */
    public function testEdgeCases(): void
    {
        // Empty string key
        $this->cache->set('', 'empty-key-value');
        $this->assertEquals('empty-key-value', $this->cache->get(''));
        
        // Very long key
        $longKey = str_repeat('a', 1000);
        $this->cache->set($longKey, 'long-key-value');
        $this->assertEquals('long-key-value', $this->cache->get($longKey));
        
        // Very long value
        $longValue = str_repeat('b', 10000);
        $this->cache->set('long-value-key', $longValue);
        $this->assertEquals($longValue, $this->cache->get('long-value-key'));
    }

    /**
     * Test TTL edge cases
     */
    public function testTtlEdgeCases(): void
    {
        // Zero TTL
        $this->cache->set('zero-ttl', 'value', 0);
        $this->assertFalse($this->cache->has('zero-ttl'));
        
        // Negative TTL
        $this->cache->set('negative-ttl', 'value', -1);
        $this->assertFalse($this->cache->has('negative-ttl'));
        
        // Very large TTL
        $this->cache->set('large-ttl', 'value', 999999999);
        $this->assertTrue($this->cache->has('large-ttl'));
    }
}
