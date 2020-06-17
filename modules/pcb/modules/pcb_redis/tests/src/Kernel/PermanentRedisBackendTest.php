<?php

namespace Drupal\Tests\pcb_redis\Kernel;

use Drupal\KernelTests\Core\Cache\GenericCacheBackendUnitTestBase;

/**
 * Tests the PermanentRedisBackendTest.
 *
 * @requires module redis
 *
 * @group pcb_redis
 */
class PermanentRedisBackendTest extends GenericCacheBackendUnitTestBase {

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'pcb',
    'pcb_redis',
    'redis',
  ];

  /**
   * Creates a new instance of PhpRedis permanent cache backend.
   *
   * @return \Drupal\pcb_redis\Cache\PermanentRedisBackend
   *   A new PhpRedis cache backend.
   */
  protected function createCacheBackend($bin) {
    $cache = \Drupal::service('cache.backend.permanent_redis')->get($bin);
    $cache->setMinTtl(10);
    return $cache;
  }

  /**
   * Test Drupal\Core\Cache\CacheBackendInterface::deleteAll().
   */
  public function testDeleteAll() {
    $backend_a = $this->getCacheBackend();
    $backend_b = $this->getCacheBackend('bootstrap');

    // Set both expiring and permanent keys.
    $backend_a->set('test1', 1, Cache::PERMANENT);
    $backend_a->set('test2', 3, time() + 1000);
    $backend_b->set('test3', 4, Cache::PERMANENT);

    $backend_a->deleteAll();

    // We don't delete on deleteAll(). Keys should be here.
    $this->assertTrue($backend_a->get('test1'), 'First key has been deleted.');
    $this->assertTrue($backend_a->get('test2'), 'Second key has been deleted.');
    $this->assertTrue($backend_b->get('test3'), 'Item in other bin is preserved.');
  }

  /**
   * Testing the basic goals of the permanent redis cache.
   */
  public function testSetGet() {
    $backend = $this->getCacheBackend();
    $cid = 'test';
    $cache_value = 'This does not matter.';

    // Be sure that our cache key is empty.
    $this->assertSame(FALSE, $backend->get($cid), "Backend does not contain data for the used cache id.");

    // Initialize the cache value.
    $backend->set($cid, $cache_value);
    $cached = $backend->get($cid);
    $this->assertEquals($cache_value, $cached->data, 'Backend returned the proper value before the normal deleting process.');

    // This is the original cache deleteAll method, so we don't want to delete
    // anything at this moment.
    $backend->deleteAll();
    $cached = $backend->get($cid);
    $this->assertFalse(!is_object($cached), 'Backend did not provide result after the normal deleting process.');
    $this->assertEquals($cache_value, $cached->data, 'Backend returned the proper value after the normal deleting process.');

    // Now flush the permanent cache!
    $backend->deleteAllPermanent();
    $cached = $backend->get($cid);
    $this->assertFalse(is_object($cached), 'Backend returned result after the permanent cache was deleted.');
  }

}
