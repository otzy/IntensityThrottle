<?php
namespace Otzy\Intensity;

class inProcessStorageTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InProcessStorage
     */
    protected $storage;
    
    protected function setUp()
    {
        $this->storage = new InProcessStorage();
    }

    /**
     * @test
     */
    public function set_get(){

        //test not expired value
        $this->storage->set('v1', 'xxx', 2);
        $this->assertEquals($this->storage->get('v1'), 'xxx');

        //test expired value
        sleep(3);
        $this->assertEquals($this->storage->get('v1', 'zzz'), 'zzz');
    }

    /**
     * @test
     */
    public function increment(){

        $this->assertFalse($this->storage->increment('not_existing_key', 2), 'Increment of not existing key must return boolean false');

        $this->storage->set('inc_test', 0, 20);
        $this->assertEquals($this->storage->increment('inc_test'), 1);
        $this->assertEquals($this->storage->increment('inc_test', 4), 5);
        $this->assertEquals($this->storage->decrement('inc_test', 3), 2);
    }

}
