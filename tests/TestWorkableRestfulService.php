<?php

namespace SilverStripe\Workable\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class TestWorkableRestfulService extends Client
{
    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        switch ($uri) {
            case 'jobs':
                return $this->getMockJobs($options);
            case 'jobs/GROOV001':
            case 'jobs/GROOV002':
                return $this->getMockJob($uri, $options);
        }
    }

    protected function getMockJobs(array $params): Response
    {
        $state = $params['query']['state'] ?? '';

        switch ($state) {
            case 'draft':
                $response = ['jobs' => [
                    [
                        'title' => 'draft job',
                        'shortcode' => 'GROOV001',
                    ],
                ]];
                break;
            default:
                $response = ['jobs' => [
                    [
                        'title' => 'Job 1',
                        'shortcode' => 'GROOV001',
                    ],
                    [
                        'title' => 'Job 2',
                        'shortcode' => 'GROOV002',
                    ],
                ]];
                break;
        }

        return new Response(200, [], json_encode($response));
    }

    protected function getMockJob(string $url, array $params): Response
    {
        $state = $params['query']['state'] ?? '';

        switch ($state) {
            case 'draft':
                $response = [
                    'title' => 'Draft Job x',
                    'test' => 'full draft data',
                    'id' => 1,
                    'shortcode' => substr($url, 5),
                ];
                break;
            default:
                $response = [
                    'title' => 'Job x',
                    'test' => 'full data',
                    'id' => 1,
                    'shortcode' => substr($url, 5),
                ];
                break;
        }

        return new Response(200, [], json_encode($response));
    }
}
