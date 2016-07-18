#IntensityThrottle

With IntensityThrottle package you can easily limit the rate of events of any kind. For example you can keep an eye on the rate of requests to your servers or the rate of errors in cron jobs.

IntensityThrottle implements [Leaky Bucket](https://en.wikipedia.org/wiki/Leaky_bucket) algorithm, which means that you are able to set not only bandwidth, 
but burstiness as well. That is to say, short spikes in the event rate are possible.

Due to the nature of php, the leakage happens not on regular basis (not continuously),
but at the moment when an event is being handled. This changes the behavior of buckets a bit, but in the end they do their job.

##Usage

Package comes with InProcessStorage, which can be used when you need to limit events in the single process, for example in a long running cron job:

```
use Otzy\Intensity\InProcessStorage;
use Otzy\Intensity\IntensityThrottle;

//Create throttle
$throttle = new IntensityThrottle('test', new InProcessStorage());

//add leaky bucket to the throttle. You can add so many buckets as you wish
$max_drops = 3; //this is a backet volume
$time_span = 1; //how long it takes to get bucket empty

//add a bucket
$throttle->addLimit($max_drops, $time_span)

//simulate events
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {

    //Register event. If buckets get overflowed, the method drip() returns false. If there is still space in them, it returns true
    $drip_result = $throttle->drip();

    if ($drip_result === false) {
        printf("Throttle Test: Drops limit exceeded after %.3f seconds.", (microtime(true) - $start));
        return;
    }

    usleep(250000);
}
```


If you want to throttle web requests or any other events across different requests and/or servers, you should use memcached server.
You can use Memcached class directly as a storage:

```
$memcached = new \Memcached();
$memcached->addServer('localhost', 11211);
$throttle = new IntensityThrottle('test', $memcached);
```


##Bucket volume

`$throttle->addLimit($max_drops, $time_span)` adds a bucket with volume=$max_drops.

At first glance, `$throttle->addLimit(3, 1)` and `$throttle->addLimit(6, 2)` are the same. The second bucket has twice bigger volume and twice higher leakage rate.
So if the average rate of incoming events will be 3 events/second, both buckets will not overflow.

The difference is that second bucket is capable to support a higher burstiness rate, i.e. temporary unevenness in the event flow.

The example above (with bucket volume = 3) will stop after 2.255 seconds. If you replace `$throttle->addLimit(3, 1)` with `$throttle->addLimit(6, 2)`, the script will stop after 5.26 seconds.