<?php

namespace SilverStripe\Workable\Tasks;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Workable\Workable;

class LatestWorkableJobsTask extends BuildTask
{
    /**
     * @inheritdoc
     */
    protected $title = 'Refresh cache of Workable Jobs';

    /**
     * @inheritdoc
     */
    protected $description = 'Refresh cache of Workable Jobs';

    /**
     * @inheritdoc
     */
    public function run($request)
    {
        Workable::flush();

        $params = ['state' => 'published'];
        $jobs = singleton(Workable::class)->getFullJobs($params);

        $output = "0 jobs to import";

        if ($jobs && $jobs->count()) {
            $cacheKey = 'FullJobs' . implode('-', $params);
            $cache = singleton(Workable::class)->getCache();

            if ($cache->has($cacheKey)) {
                $output = $jobs->count() . " total jobs saved successfully";
            }
        }

        echo $output;
    }
}
