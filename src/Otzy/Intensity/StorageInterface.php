<?php
/**
 * Created by PhpStorm.
 * User: eugene
 * Date: 7/8/2016
 * Time: 10:28 PM
 */

namespace Otzy\Intensity;


interface StorageInterface
{
    public function add($key, $value, $expire);

    public function set($key, $value, $expire);

    /**
     * @param string $key
     * @return mixed returns boolean false if key does not exist
     */
    public function get($key);

    /**
     * @param string $key
     * @param int $value
     * @return int
     */
    public function increment($key, $value);

    /**
     * @param $key
     * @param int $value
     * @return mixed
     */
    public function decrement($key, $value);
    
    public function delete($key);
}