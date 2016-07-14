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
    protected $namespace;

    protected $max_interval = 0;

    /**
     * @var KeyValuePersistenceInterface
     */
    protected $storage;

    protected $leaky_buckets = array();

    /**
     * IntensityThrottle constructor.
     * @param KeyValuePersistenceInterface $storage
     * @param string $namespace
     */
    public function __construct($storage = null, $namespace = '')
    {
        $this->namespace = $namespace;

        if ($storage instanceof KeyValuePersistenceInterface) {
            $this->storage = $storage;
        } else {
            $this->storage = new InProcessStorage();
        }
    }

    public function hit()
    {
        $this->leakOut();
        $this->setLastHitTime();
        for ($i = 0; $i < count($this->leaky_buckets); $i++) {
            if ($this->storage->increment($this->getCounterKey($i), self::PRECISION) > $this->leaky_buckets[$i]['capacity']) {
                return $i;
            }
        }

        return true;
    }

    protected function leakOut()
    {
        for ($i = 0; $i < count($this->leaky_buckets); $i++) {
            $to_leak = intval($this->leaky_buckets[$i]['leak_rate'] * (microtime(true) - $this->getLastHitTime()));
            $to_leak = max($to_leak, 1);
            $bucket_level = $this->storage->decrement($this->getCounterKey($i), $to_leak);
            if ($bucket_level < 0){
                $this->reset($i);
            }
        }
    }

    /**
     * @param $max_hits
     * @param $interval_seconds
     * @return int ID of the added interval
     */
    public function addInterval($max_hits, $interval_seconds)
    {
        $this->leaky_buckets[] = [
            'capacity' => $max_hits * self::PRECISION,
            'leak_rate' => $max_hits * self::PRECISION / $interval_seconds,
            'interval' => $interval_seconds
        ];

        $this->max_interval = max($this->max_interval, $interval_seconds);

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

    protected function getCurrentLevel($interval_id)
    {
        return $this->storage->get($this->getCounterKey($interval_id), 0);
    }

    protected function getLastHitTime()
    {
        $this->storage->get($this->getLastHitTimeKey(), 0);
    }

    protected function setLastHitTime()
    {
        $this->storage->set(
            $this->getLastHitTimeKey(),
            microtime(true),
            $this->max_interval);
    }

    protected function getCounterKey($interval_id)
    {
        return __METHOD__ . $this->namespace . ':' . $interval_id;
    }

    protected function getLastHitTimeKey()
    {
        return __METHOD__ . $this->namespace;
    }

}