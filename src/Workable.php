<?php

namespace SilverStripe\Workable;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Extensible;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class Workable implements Flushable
{
    use Extensible;
    use Injectable;
    use Configurable;

    public const CACHE_NAME = 'workable';

    /**
     * Reference to the HTTP Client dependency
     */
    private ClientInterface $httpClient;

    /**
     * Reference to the Cache dependency
     */
    private ?CacheInterface $cache;

    /**
     * Subdomain for Workable API call (e.g. $subdomain.workable.com)
     * @config
     */
    private static string $subdomain = '';

    /**
     * Constructor, inject the restful service dependency
     */
    public function __construct(ClientInterface $httpClient, CacheInterface $cache = null)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    /**
     * Gets all the jobs from the Workable API
     * @param array $params Array of params, e.g. ['state' => 'published'].
     *                        see https://workable.readme.io/docs/jobs for full list of query params
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getJobs(array $params = []): ArrayList
    {
        $cacheKey = 'Jobs' . implode('-', $params);
        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $list = ArrayList::create();
        $response = $this->callWorkableApi('jobs', $params);

        if (!$response) {
            return $list;
        }

        $jobs = $response['jobs'] ?? [];
        foreach ($jobs as $record) {
            $list->push(WorkableResult::create($record));
        }

        $this->cache->set($cacheKey, $list);

        return $list;
    }

    /**
     * Gets information on a specific job form the Workable API
     * @param string $shortcode Workable shortcode for the job, e.g. 'GROOV005'
     * @param array $params Array of params, e.g. ['state' => 'published'].
     *                           see https://workable.readme.io/docs/jobs for full list of query params
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getJob(string $shortcode, array $params = []): ?WorkableResult
    {
        $cacheKey = 'Job-' . $shortcode . implode('-', $params);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $job = null;
        $response = $this->callWorkableApi('jobs/' . $shortcode, $params);

        if ($response && isset($response['id'])) {
            $job = WorkableResult::create($response);
            $this->cache->set($cacheKey, $job);
        }

        return $job;
    }

    /**
     * Gets all the jobs from the workable API, populating each job with its full data
     * Note: This calls the API multiple times so should be used with caution
     * @param array $params Array of params, e.g. ['state' => 'published'].
     *                        see https://workable.readme.io/docs/jobs for full list of query params
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getFullJobs(array $params = []): ArrayList
    {
        $cacheKey = 'FullJobs' . implode('-', $params);

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $list = ArrayList::create();
        $response = $this->callWorkableApi('jobs', $params);

        if (!$response) {
            return $list;
        }

        $jobs = $response['jobs'] ?? [];
        foreach ($jobs as $record) {
            $job = $this->getJob($record['shortcode'], $params);
            $list->push($job);
        }

        $this->cache->set($cacheKey, $list);

        return $list;
    }

    /**
     * Sends request to Workable API.
     * Should it exceed the rate limit, this is caught and put to sleep until the next interval.
     * The interval duration is provided by Workable via a header.
     * When its awaken, it will call itself again, this repeats until its complete.
     * This returns a json body from the response.
     *
     * Note: See rate limit docs from Workable https://workable.readme.io/docs/rate-limits
     *
     * @throws RequestException if client is not configured correctly, handles 429 error
     * @throws \JsonException from json_decode
     * @throws \Psr\Container\NotFoundExceptionInterface from Injector
     * @throws \GuzzleHttp\Exception\GuzzleException from $this->httpClient->request
     */
    public function callWorkableApi(string $url, array $params = [], string $method = 'GET'): array
    {
        try {
            $response = $this->httpClient->request($method, $url, ['query' => $params]);
            return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $errorResponse = $e->getResponse();
                $statusCode = $errorResponse->getStatusCode();

                if ($statusCode === 429) {
                    Injector::inst()->get(LoggerInterface::class)->info(
                        'Rate limit exceeded - sleeping until next interval'
                    );

                    $this->sleepUntil((array) $errorResponse->getHeader('X-Rate-Limit-Reset'));

                    return $this->callWorkableApi($url, $params, $method);
                }

                Injector::inst()->get(LoggerInterface::class)->warning(
                    'Failed to retrieve valid response from workable',
                    ['exception' => $e]
                );

                throw $e;
            }
        }

        return [];
    }

    /**
     * Sleeps until the next interval.
     * Should the interval header be empty, the script sleeps for 10 seconds - Workable's default interval.
     */
    private function sleepUntil(array $resetIntervalHeader): void
    {
        $defaultSleepInterval = 10;

        if (!empty($resetIntervalHeader)) {
            time_sleep_until($resetIntervalHeader[0]);
        } else {
            sleep($defaultSleepInterval);
        }
    }

    /**
     * Flush any cached data
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function flush()
    {
        Injector::inst()->get(CacheInterface::class . '.' . self::CACHE_NAME)->clear();
    }

    /**
     * Gets any cached data. If there is no cached data, a blank cache is created.
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getCache(): CacheInterface
    {
        if (!$this->cache instanceof CacheInterface) {
            $this->setCache(Injector::inst()->get(CacheInterface::class . '.'. self::CACHE_NAME));
        }

        return $this->cache;
    }

    /**
     * Sets the cache.
     */
    public function setCache(CacheInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }
}
