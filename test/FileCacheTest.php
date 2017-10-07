<?php

require_once 'PHPUnit/Autoload.php';

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;

class Data {

    public static $id = "user/10";

    public static $data = array(
        'name' => 'John',
        'age'  => 20,
        'sex'  => 'f',
    );

    public static $options = array();
}

final class FileCacheTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('cache'));

        Data::$options['cache_dir'] = vfsStream::url('cache');
    }

    public function testDataIsSavedAndCanBeRead($lifetime = 3600)
    {
        $cache = new FileCache(Data::$options);

        $cache->save(Data::$id, Data::$data, $lifetime);
        $data = $cache->get(Data::$id);

        $this->assertEquals(Data::$data, $data, 'Data read must be same as written');

        return $cache;
    }
    
    public function testDataExpiresAfterLifetime()
    {
        $cache = static::testDataIsSavedAndCanBeRead(1);

        sleep(2);

        $data = $cache->get(Data::$id);
        $this->assertEquals(null, $data, 'Data must be null because it is expired');

    }

    public function testDataIsDeleted()
    {
        $cache = static::testDataIsSavedAndCanBeRead();
        
        $result = $cache->delete(Data::$id);
        $this->assertEquals(true, $result, 'Data must be null because it was deleted');
    }

    public function testDataExpiresIfCustomTimestampIsNewerThanFiletime()
    {
        $time = time();
        $cache = static::testDataIsSavedAndCanBeRead(0);

        $data = $cache->get(Data::$id, $time + 1);
        $this->assertEquals(null, $data, 'Data must be null because it is outdated');
        
        $data = $cache->get(Data::$id, 0);
        $this->assertEquals(null, $data, 'Data must be null because it was deleted');
    }
}
