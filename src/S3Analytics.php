<?php

namespace S3Analytics;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Carbon\Carbon;

class S3Analytics
{
    /** @var \Aws\S3\S3Client|null $client */
    protected $client = null;

    /** @var array $configs */
    protected $configs = [
        'version' => 'latest',
        'region' => '',
        'access_key' => '',
        'secret_key' => '',
    ];

    /** @var string $regex https://docs.aws.amazon.com/AmazonS3/latest/dev/LogFormat.html */
    protected $regex = '/(?P<owner>\S+) (?P<bucket>\S+) (?P<time>\[[^]]*\]) (?P<ip>\S+) '.
        '(?P<requester>\S+) (?P<reqid>\S+) (?P<operation>\S+) (?P<key>\S+) (?P<request>"[^"]*") '.
        '(?P<status>\S+) (?P<error>\S+) (?P<bytes>\S+) (?P<size>\S+) (?P<totaltime>\S+) '.
        '(?P<turnaround>\S+) (?P<referrer>"[^"]*") (?P<useragent>"[^"]*") (?P<version>\S)/';

    /**
     * S3Analytics constructor.
     *
     * @param array         $configs
     * @param S3Client|null $client
     */
    public function __construct(array $configs = [], S3Client $client = null)
    {
        $this->setConfigs($configs);
        $this->client = $client;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    public function getConfig(string $key) : string
    {
        return isset($this->configs[$key]) ? $this->configs[$key] : '';
    }

    /**
     * @param array $configs
     *
     * @return array
     */
    public function setConfigs(array $configs = []) : array
    {
        foreach ($configs as $key => $value) {
            if (is_string($key) && is_string($value)) {
                if (mb_strlen($key) && mb_strlen($value)) {
                    if (array_key_exists($key, $this->configs)) {
                        $this->configs[$key] = $value;
                    }
                }
            }
        }

        return $this->configs;
    }

    /**
     * @param string $bucketName
     * @param string $bucketPrefix
     * @param string $date
     *
     * @return string|false
     */
    public function getStatistics(string $bucketName, string $bucketPrefix, string $date)
    {
        $statistics = [];

        $listObjectsParams = [
            'Bucket' => $bucketName,
            'Prefix' => sprintf('%s%s', $bucketPrefix, Carbon::parse($date)->format('Y-m-d')),
        ];

        $results = $this->getClient()->getPaginator('ListObjects', $listObjectsParams);

        foreach ($results as $result) {
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $data = $this->parseObject($bucketName, $object['Key']);

                    foreach ($data as $item) {
                        if (isset($item['key']) && mb_strlen($item['key'])) {
                            if (!isset($statistics[$item['key']]['downloads'])) {
                                $statistics[$item['key']]['downloads'] = 0;
                            }

                            if (!isset($statistics[$item['key']]['bandwidth'])) {
                                $statistics[$item['key']]['bandwidth'] = 0;
                            }

                            $statistics[$item['key']]['downloads'] += 1;

                            if (isset($item['bytes'])) {
                                $statistics[$item['key']]['bandwidth'] += (int) $item['bytes'];
                            }
                        }
                    }
                }
            }
        }

        return json_encode([
            'success' => true,
            'statistics' => [
                'bucket' => $listObjectsParams['Bucket'],
                'prefix' => $listObjectsParams['Prefix'],
                'data' => $statistics,
            ],
        ]);
    }

    /**
     * @param string $bucketName
     * @param string $key
     *
     * @return array
     */
    public function parseObject(string $bucketName, string $key) : array
    {
        $output = [];

        $file = $this->getClient()->getObject([
            'Bucket' => $bucketName,
            'Key' => $key,
        ]);

        $rows = explode("\n", (string) $file['Body']);

        foreach ($rows as $row) {
            preg_match($this->regex, $row, $matches);

            if (isset($matches['operation']) && $matches['operation'] == 'REST.GET.OBJECT') {
                $output[] = $matches;
            }
        }

        return $output;
    }

    /**
     * @return S3Client
     */
    public function getClient() : S3Client
    {
        if (is_null($this->client)) {
            $this->client = new S3Client([
                'version' => $this->getConfig('version'),
                'region' => $this->getConfig('region'),
                'credentials' => new Credentials(
                    $this->getConfig('access_key'),
                    $this->getConfig('secret_key')
                ),
            ]);
        }

        return $this->client;
    }
}
