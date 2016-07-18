<?php
/**
 * Created by PhpStorm.
 * User: eugene
 * Date: 7/8/2016
 * Time: 10:17 PM
 */

namespace Otzy\Intensity;

class IntensityThrottle
{
    /**
     * maximum rate of hits per second when throttle is still more or less precise
     * The level of buckets is multiplied by this number, and all increments and decrements are also multiplied.
     * We need this because increment and decrement operations does not support float numbers,
     * so we count 1/1000 fractions
     */
    const PRECISION = 1000;

    /**
     * @var string
     */
    protected $name;

    protected $max_interval = 0;

    /**
     * @var int microseconds
     */
    protected $lock_timeout = 300000;

    /**
     * @var StorageInterface
     */
    protected $storage;

    protected $leaky_buckets = array();

    /**
     * IntensityThrottle constructor.
     * @param string $name
     * @param StorageInterface $storage
     */
    public function __construct($name = '', $storage = null)
    {
        $this->name = $name;

        if ($storage instanceof StorageInterface) {
            $this->storage = $storage;
        } else {
            $this->storage = new InProcessStorage();
        }
    }

    public function drip()
    {
        $result = true;

        $this->lockStorage($this->lock_timeout);
        $this->leak();
        for ($i = 0; $i < count($this->leaky_buckets); $i++) {
            $this->storage->add($this->getCounterKey($i), 0, $this->max_interval);
            if ($this->storage->increment($this->getCounterKey($i), self::PRECISION) > $this->leaky_buckets[$i]['capacity']) {
                $result = false;
            }

//            echo $i.': after hit: '.$this->storage->get($this->getCounterKey($i)). "\n ";
        }

        $this->unlockStorage();
        return $result;
    }

    protected function leak()
    {
        $micro_time = microtime(true);
        for ($i = 0; $i < count($this->leaky_buckets); $i++) {
//            $last_leak_time = $this->getLastLeakTime($i);
            
            $to_leak = intval($this->leaky_buckets[$i]['leak_rate'] * ($micro_time - $this->getLastLeakTime($i)));
            if ($to_leak <= 0){
                //nothing to leak, skip this bucket
                continue;
            }

            $this->setLastLeakTime($i, $micro_time);

            $this->storage->add($this->getCounterKey($i), 0, $this->max_interval);
            $bucket_level = $this->storage->decrement($this->getCounterKey($i), $to_leak);
//            echo $i . ': after leak: ' . $bucket_level . "\n";
            if ($bucket_level < 0) {
                $this->reset($i);
            }
        }
    }

    /**
     * @param $max_drops
     * @param $interval_seconds
     * @return int ID of the added interval
     */
    public function addLimit($max_drops, $interval_seconds)
    {
        $this->leaky_buckets[] = [
            'capacity' => $max_drops * self::PRECISION,
            'leak_rate' => $max_drops * self::PRECISION / $interval_seconds,
            'interval' => $interval_seconds
        ];

        $this->max_interval = max($this->max_interval, $interval_seconds);

        $this->setLastLeakTime(count($this->leaky_buckets) - 1, microtime(true));

        return count($this->leaky_buckets);
    }

    public function reset($interval_id)
    {
        $this->storage->delete($this->getCounterKey($interval_id));
    }

    public function resetAll()
    {
        for ($i = 0; $i < count($this->leaky_buckets); $i++) {
            $this->storage->delete($this->getCounterKey($i));
        }
    }

    /**
     * @param int $lock_timeout microseconds. Must be >=1000
     */
    public function setLockTimeout($lock_timeout)
    {
        if ($lock_timeout < 1000){
            throw new \InvalidArgumentException('Lock timeout must be >= 1000');
        }

        $this->lock_timeout = $lock_timeout;
    }

    protected function getCurrentLevel($interval_id)
    {
        $result = $this->storage->get($this->getCounterKey($interval_id));
        if ($result === false){
            return 0;
        }

        return $result;
    }

    protected function getLastLeakTime($i)
    {
        $result = $this->storage->get($this->getLastLeakTimeKey($i));
        if ($result === false){
            return 0;
        }
        return $result;
    }

    protected function setLastLeakTime($i, $micro_time)
    {
        $this->storage->set(
            $this->getLastLeakTimeKey($i),
            $micro_time,
            $this->max_interval);
    }

    protected function getCounterKey($interval_id)
    {
        return __METHOD__ . $this->name . ':' . $interval_id;
    }

    protected function getLastLeakTimeKey($i)
    {
        return __METHOD__ . $i . ':' . $this->name;
    }

    /**
     * @param int $timeout microseconds
     * @return bool
     * @throws
     */
    protected function lockStorage($timeout){
        $key = 'otzy/throttle/locker/' .$this->name;
        $start = microtime(true);
        while (!$this->storage->add($key, 1, $timeout*10)){
            if ((microtime(true) - $start) > $timeout ){
                throw new LockTimeoutException('Can not lock the storage. Lock timeout exceeded.');
            }
            usleep(intval($timeout / 10));
        }

        return true;
    }

    protected function unlockStorage(){
        $this->storage->delete('otzy/throttle/locker/' .$this->name);
    }

}