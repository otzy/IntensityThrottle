<?php
namespace Otzy\Intensity;

class IntensityThrottleFunctionalTest extends \PHPUnit_Framework_TestCase
{

    protected $limits = [
        ['max_hits' => 3, 'interval_seconds' => 1],
        ['max_hits' => 5, 'interval_seconds' => 1],
    ];


    /**
     * we test with inProcessStorage and Memcached
     *
     * @return StorageInterface[]
     */
    public function storages()
    {
        static $result = false;

        if (!is_array($result)) {
            $result = [];
            $result[] = new InProcessStorage();

//            $memcached = new \Memcached();
//            $memcached->addServer('192.168.33.100', 11211);
//            $result[] = $memcached;
        }
        return $result;
    }

    /**
     * @test
     */
    public function runCases()
    {
        foreach ($this->storages() as $storage) {

            $throttle = new IntensityThrottle('test', $storage);

            foreach ($this->limits as $limit) {
                $throttle->addLimit($limit['max_hits'], $limit['interval_seconds']);
            }

            $start = microtime(true);
            for ($i = 0; $i < 100; $i++) {
                $hit_result = $throttle->drip();

                if ($hit_result === false) {
                    printf("Throttle Test: Drops limit exceeded as expected after %.3f seconds.", (microtime(true) - $start));
                    $this->assertTrue(true);
                    return;
                }

                usleep(250000);
            }

            $this->assertTrue(false, 'Drops limit had to exceed, but it\'s not.');
        }
    }

}
