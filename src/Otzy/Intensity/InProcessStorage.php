<?php
namespace Otzy\Intensity;

class InProcessStorage implements StorageInterface
{

    private $data = array();

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expire seconds to expiration
     * @return bool
     */
    public function add($key, $value, $expire)
    {
        if (!$this->unsetExpired($key)) {
            return false;
        }

        return $this->set($key, $value, $expire);
    }

    /**
     * unsets value if it's expired
     *
     * @param string $key
     * @return bool returns true if value does not exist or expired. false if it's not expired yet
     */
    protected function unsetExpired($key)
    {
        if (!isset($this->data[$key])) {
            return true;
        }

        if ($this->data[$key]['expire'] < time()) {
            unset($this->data[$key]);
            return true;
        }

        return false;
    }

    public function set($key, $value, $expire)
    {
        $this->data[$key] = [
            'value' => $value,
            'expire' => time() + $expire,
            'lifespan' => $expire
        ];

        return true;
    }

    public function get($key)
    {
       if ($this->unsetExpired($key)){
           return false;
       }

        return $this->data[$key]['value'];
    }

    /**
     * @param string $key
     * @param int $value
     * @return bool|int
     */
    public function increment($key, $value = 1)
    {
        if ($this->unsetExpired($key)){
            return false;
        }
        
        $data = &$this->data[$key];
        
        if (!is_numeric($this->data[$key]['value'])){
            return $value; //memcache behavior
        }

        $data['value'] += $value;
        $data['expire'] = time() + $data['lifespan'];
        
        return $data['value'];
    }

    /**
     * @param $key
     * @param int $value
     * @return bool|int
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, -$value);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        if (isset($this->data[$key])){
            unset($this->data[$key]);
        }
        
        return true;
    }
}