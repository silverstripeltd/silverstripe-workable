<?php

namespace SilverStripe\Workable\Tests;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Config\Collections\CachedConfigCollection;
use SilverStripe\Core\Cache\DefaultCacheFactory;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\InjectorLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Caching\VersionedCacheAdapter;
use SilverStripe\Workable\Tests\TestWorkableRestfulService;
use SilverStripe\Workable\Workable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Workable\WorkableRestfulServiceFactory;
use SilverStripe\Workable\WorkableResult;

class WorkableTest extends SapphireTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        Workable::config()->set('subdomain', 'example');
        $config = Config::inst()->get(Injector::class, 'GuzzleHttp\ClientInterface.workable');
        $config['class'] = TestWorkableRestfulService::class;
        Config::inst()->merge(Injector::class, 'GuzzleHttp\ClientInterface.workable', $config);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Environment::setEnv('WORKABLE_API_KEY', 'test');
    }

    public function testThrowsIfNoSubdomain()
    {
        Config::inst()->remove(Workable::class, 'subdomain');
        $this->setExpectedException('RuntimeException');

        Workable::create()->callHttpClient('test');
    }

    public function testThrowsIfNoApiKey()
    {
        Environment::setEnv('WORKABLE_API_KEY', null);
        $this->setExpectedException('RuntimeException');

        Workable::create()->callHttpClient('test');
    }

    public function testConvertsSnakeCase()
    {
        $data = WorkableResult::create(['snake_case' => 'foo']);

        $this->assertEquals('foo', $data->SnakeCase);
    }

    public function testAcceptsDotSyntax()
    {
        $data = WorkableResult::create(['snake_case' => ['nested_property' => 'foo']]);
        $result = $data->SnakeCase;
        $this->assertInstanceOf(WorkableResult::class, $result);
        $this->assertEquals('foo', $result->NestedProperty);
    }

    public function testGetJobs()
    {
        $data = Workable::create()->getJobs();

        $this->assertCount(2, $data);
        $this->assertEquals('Job 1', $data[0]->title);
        $this->assertEquals('Job 2', $data[1]->title);
    }

    public function testGetJobsWithDraftState()
    {
        $data = Workable::create()->getJobs(['state' => 'draft']);

        $this->assertCount(1, $data);
    }

    public function testGetJob()
    {
        $data = Workable::create()->getJob('GROOV001');

        $this->assertNotNull($data);
        $this->assertEquals('Job x', $data->title);
        $this->assertEquals('GROOV001', $data->shortcode);
    }

    public function testGetJobWithDraftState()
    {
        $data = Workable::create()->getJob('GROOV001', ['state' => 'draft']);

        $this->assertNotNull($data);
        $this->assertEquals('Draft Job x', $data->title);
        $this->assertEquals('GROOV001', $data->shortcode);
    }

    public function testFullJobs()
    {
        $data = Workable::create()->getFullJobs();

        $this->assertCount(2, $data);
        $this->assertEquals('full data', $data[0]->test);
        $this->assertEquals('GROOV001', $data[0]->shortcode);
        $this->assertEquals('full data', $data[1]->test);
        $this->assertEquals('GROOV002', $data[1]->shortcode);
    }

    public function testFullJobsWithDraftState()
    {
        $data = Workable::create()->getFullJobs(['state' => 'draft']);

        $this->assertCount(1, $data);
        $this->assertEquals('Draft Job x', $data[0]->title);
        $this->assertEquals('full draft data', $data[0]->test);
        $this->assertEquals('GROOV001', $data[0]->shortcode);
    }
}
