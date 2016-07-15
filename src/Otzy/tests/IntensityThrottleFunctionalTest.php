<?php
namespace Otzy\Intensity;

class IntensityThrottleFunctionalTest extends \PHPUnit_Framework_TestCase
{

    protected $limits = [
        ['max_hits' => 3, 'interval_seconds' => 1],
        ['max_hits' => 5, 'interval_seconds' => 1],
    ];

    /**
     * @test
     */
    public function runCases()
    {
        $throttle = new IntensityThrottle('test', new InProcessStorage());

        foreach ($this->limits as $limit) {
            $throttle->addLimit($limit['max_hits'], $limit['interval_seconds']);
        }

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $hit_result = $throttle->hit();

            if ($hit_result === false){
                echo 'Throttle Test: Hits limit exceeded as expected after '.(microtime(true) - $start)." seconds.\n\n";
                $this->assertTrue(true);
                return;
            }

            usleep(250000);
        }

        $this->assertTrue(false, 'Hits limit had to exceed, but it\'s not.');
    }

}
