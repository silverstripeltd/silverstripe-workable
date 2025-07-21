<?php

namespace SilverStripe\Workable\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Workable\Workable;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class LatestWorkableJobsTask extends BuildTask
{
    /**
     * @inheritdoc
     */
    protected string $title = 'Refresh cache of Workable Jobs';

    /**
     * @inheritdoc
     */
    protected static string $description = 'Refresh cache of Workable Jobs';

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
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

        return Command::SUCCESS;
    }
}
