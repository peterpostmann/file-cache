<?php
/**
 * FileCache
 *
 * http://github.com/inouet/file-cache/
 *
 * A simple PHP class for caching data in the filesystem.
 *
 * License
 *   This software is released under the MIT License, see LICENSE.txt.
 *
 * @package FileCache
 * @author  Taiji Inoue <inudog@gmail.com>
 */

class FileCache
{
    
    /**
     * The root cache directory.
     * @var string
     */
    private $cache_dir = '/tmp/cache';

    /**
     * Creates a FileCache object
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $available_options = array('cache_dir');
        foreach ($available_options as $name) {
            if (isset($options[$name])) {
                $this->$name = $options[$name];
            }
        }
    }

    /**
     * Fetches an entry from the cache.
     *
     * @param string $id
     * @param int    $timestamp
     */
    public function get($id, $timestamp = 0)
    {
        $file_name = $this->getFileName($id);

        if (!is_file($file_name) || !is_readable($file_name)) {
            return false;
        }
        
        $filetime = filemtime($file_name);
        
        if ($filetime < $timestamp) {
            @unlink($file_name);
            return false;
        }

        $lines    = file($file_name);
        $lifetime = array_shift($lines);
        $lifetime = (int) trim($lifetime);

        if ($lifetime !== 0 && $lifetime < time()) {
            @unlink($file_name);
            return false;
        }
        $serialized = join('', $lines);
        $data       = unserialize($serialized);
        return $data;
    }

    /**
     * Deletes a cache entry.
     *
     * @param string $id
     *
     * @return bool
     */
    public function delete($id)
    {
        $file_name = $this->getFileName($id);
        return unlink($file_name);
    }

    /**
     * Generates a Globally Unique Identifier
     *
     * @return string
     */
    public static function guid()
    {
        if (function_exists('com_create_guid') === true)
        {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', 
                        mt_rand(0, 65535), 
                        mt_rand(0, 65535), 
                        mt_rand(0, 65535), 
                        mt_rand(16384, 20479), 
                        mt_rand(32768, 49151), 
                        mt_rand(0, 65535), 
                        mt_rand(0, 65535), 
                        mt_rand(0, 65535));
    }
 
    /**
     * Writes data atomically.
     *
     * @param string $filename
     * @param mixed  $data
     *
     * @return bool
     */
    public static function atomic_file_put_contents($filename, $data)
    {
        $tmpName = $filename.'-'.static::guid();

        if(!file_put_contents($tmpName, $data)) return false;

        return rename($tmpName, $filename);
    }

    /**
     * Puts data into the cache.
     *
     * @param string $id
     * @param mixed  $data
     * @param int    $lifetime
     *
     * @return bool
     */
    public function save($id, $data, $lifetime = 3600)
    {
        $dir = $this->getDirectory($id);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                return false;
            }
        }
        $file_name  = $this->getFileName($id);
        $lifetime   = ($lifetime > 0) ? (time() + $lifetime) : 0;
        $serialized = serialize($data);
        $result     = static::atomic_file_put_contents($file_name, $lifetime . PHP_EOL . $serialized);
        if ($result === false) {
            return false;
        }
        return true;
    }

    //------------------------------------------------
    // PRIVATE METHODS
    //------------------------------------------------

    /**
     * Fetches a directory to store the cache data
     *
     * @param string $id
     *
     * @return string
     */
    protected function getDirectory($id)
    {
        $hash = sha1($id, false);
        $dirs = array(
            $this->getCacheDirectory(),
            substr($hash, 0, 2),
            substr($hash, 2, 2)
        );
        return join(DIRECTORY_SEPARATOR, $dirs);
    }

    /**
     * Fetches a base directory to store the cache data
     *
     * @return string
     */
    protected function getCacheDirectory()
    {
        return $this->cache_dir;
    }

    /**
     * Fetches a file path of the cache data
     *
     * @param string $id
     *
     * @return string
     */
    protected function getFileName($id)
    {
        $directory = $this->getDirectory($id);
        $hash      = sha1($id, false);
        $file      = $directory . DIRECTORY_SEPARATOR . $hash . '.cache';
        return $file;
    }
}
