<?php

namespace S3LogsParser;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Carbon\Carbon;


class S3LogsParserException extends \Exception { }


class S3LogsParser
{
    /** @var \Aws\S3\S3Client|null $client */
    protected $client = null;

    /** @var array $configs */
    protected $configs = [
        'version' => 'latest',
        'debug_mode' => '',
        'region' => '',
        'access_key' => '',
        'secret_key' => '',
        'local_log_dir' => '',
        'exclude_lines_with_substring' => '',
    ];

    /** @var string $regex https://docs.aws.amazon.com/AmazonS3/latest/dev/LogFormat.html */
    protected $regex = '/(?P<owner>\S+) (?P<bucket>\S+) (?P<time>\[[^]]*\]) (?P<ip>\S+) '.
        '(?P<requester>\S+) (?P<reqid>\S+) (?P<operation>\S+) (?P<key>\S+) (?P<request>"[^"]*") '.
        '(?P<status>\S+) (?P<error>\S+) (?P<bytes>\S+) (?P<size>\S+) (?P<totaltime>\S+) '.
        '(?P<turnaround>\S+) (?P<referrer>"[^"]*") (?P<useragent>"[^"]*") (?P<version>\S)/';

    const METRICS_TO_KEEP_RUNNING_TOTALS_FOR = ['totaltime', 'downloads', 'bandwidth', 'totalRequestTimeInMinutes'];


    /**
     * S3LogsParser constructor.
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
                    } else {
                        print "WARNING: " . $key . " is not a configuration parameter; ignoring.\n\n";
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
     * @return array
     */
    public function getStatsAsArray($bucketName = null, $bucketPrefix = null, $date = null) : array
    {
        if (array_key_exists('local_log_dir', $this->configs)) {
          $logsLocation = $this->getConfig('local_log_dir');

          if (!is_dir($logsLocation)) {
              throw new S3LogsParserException($logsLocation . ' is not a directory!');
          }

          if (isset($date)) {
              print "WARNING: date parameter is not currently supported for local files.";
          }

          $logLines = $this->loadLogsFromLocalDir($logsLocation);
        } else {
          if (is_null($bucketName)) {
              throw new S3LogsParserException('bucketName not provided!');
          }

          $logLines = $this->loadLogsFromS3($bucketName, $bucketPrefix, $date);
        }

        return $this->computeStatistics($logLines);
    }

    /**
     * TODO: Should probably get renamed getStatsAsJSON or similar
     *
     * @param string $bucketName
     * @param string $bucketPrefix
     * @param string $date
     *
     * @return string|false
     */
    public function getStats($bucketName = null, $prefix = null, $date = null) : string
    {
      $logStats = $this->getStatsAsArray($bucketName, $prefix, $date);

      if (!is_null($bucketName)) {
          $logStats['bucket'] = $bucketName;
          $logStats['prefix'] = $prefix;
      }

      return json_encode([
          'success' => true,
          'statistics' => $logStats,
      ]);
    }

    /**
     * @param string $parsedLogs
     *
     * @return hash
     */
    public function loadLogsFromS3(string $bucketName, string $bucketPrefix, $date) : array
    {
        print 'Reading from S3 bucket ' . $bucketName . ' with prefix: ' . $bucketPrefix . ', date: ' . $date . '...';

        $listObjectsParams = [
            'Bucket' => $bucketName,
            'Prefix' => $bucketPrefix + (is_null($date) ? '' : Carbon::parse($date)->format('Y-m-d')),
        ];

        $logLines = [];
        $results = $this->getClient()->getPaginator('ListObjects', $listObjectsParams);

        foreach ($results as $result) {
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $logLines = array_merge($logLines, $this->parseS3Object($bucketName, $object['Key']));
                }
            }
        }

        return $logLines;
    }

    /**
     * @param string $logDir
     *
     * @return array
     */
    public function loadLogsFromLocalDir(string $logDir) : array
    {
      $logLines = [];
      $httpOperationCounts = [];
      print "Reading files from local directory: " . $logDir . "...\n";

      foreach (new \DirectoryIterator($logDir) as $file) {
          if ($file->isFile()) {
              $fileContents = file_get_contents($file->getPathname(), true);
              $processedLogs = $this->processLogsString($fileContents);
              $logLines = array_merge($logLines, $processedLogs['requestLogs']);

              if ($this->isDebugModeEnabled()) {
                  print 'Read ' . count($processedLogs['rowCount']) . ' lines from ' . $file->getFilename() . "\n";
              }
          }
      }

      return $logLines;
    }

    /**
     * @param string $parsedLogs
     *
     * @return hash
     */
    public function computeStatistics(array $parsedLogs) : array
    {
        $statistics = [];
        $httpOperationCounts = [];

        foreach ($parsedLogs as $item) {
            // Count operations
            $httpOperation = $item['operation'];

            if (!array_key_exists($httpOperation, $httpOperationCounts)) {
                $httpOperationCounts[$httpOperation] = 0;
            }

            $httpOperationCounts[$httpOperation] += 1;

            // Only GET requests get the extra processing around bytes, request time, etc.
            if ($httpOperation != 'REST.GET.OBJECT') {
                continue;
            }

            if (!isset($item['key']) || !mb_strlen($item['key'])) {
                print "WARNING: Missing key in log line; skipping:\n" . $item;
                continue;
            }

            $s3ObjectKey = $item['key'];

            foreach(self::METRICS_TO_KEEP_RUNNING_TOTALS_FOR as $metricName) {
                if (!isset($statistics[$s3ObjectKey][$metricName])) {
                    $statistics[$s3ObjectKey][$metricName] = 0;
                }
            }

            // Count downloads and maintain list of access dates
            $statistics[$s3ObjectKey]['downloads'] += 1;
            $date = $this->parseLogDateString($item['time']);

            if (!isset($statistics[$s3ObjectKey]['dates'])) {
                $statistics[$s3ObjectKey]['dates'] = [];
            }

            if (!in_array($date, $statistics[$s3ObjectKey]['dates'])) {
                $statistics[$s3ObjectKey]['dates'][] = $date;
            }

            // Track bandwidth bytes transferred
            if (isset($item['bytes'])) {
                $statistics[$s3ObjectKey]['bandwidth'] += (int) $item['bytes'];
            }

            // TODO: Sum milliseconds now; convert to minutes later.
            if (isset($item['totaltime'])) {
                $totalRequestTimeInMinutes = (float) $item['totaltime'] / 1000.0 / 60.0;
                $statistics[$s3ObjectKey]['totalRequestTimeInMinutes'] += $totalRequestTimeInMinutes;
                $statistics[$s3ObjectKey]['totaltime'] += (int) $item['totaltime'];
            }
        }

        return [
            'data' => $statistics,
            'httpOperationCounts' => $httpOperationCounts,
        ];
    }

    /**
     * @param string $bucketName
     * @param string $key
     *
     * @return array
     */
    public function parseS3Object(string $bucketName, string $key) : array
    {
        $file = $this->getClient()->getObject([
            'Bucket' => $bucketName,
            'Key' => $key,
        ]);

        return $this->processLogsString((string) $file['Body']);
    }

    /**
     * Process a string containing 0-n lines of logs
     *
     * @param string $logsString
     *
     * @return array
     */
    public function processLogsString(string $logsString) : array
    {
        $rows = explode("\n", $logsString);
        $requestLogs = [];
        $httpOperationCounts = [];
        $excludedRowsCount = 0;
        $excludeLinesWithSubstring = $this->getConfig('exclude_lines_with_substring');

        foreach ($rows as $row) {
            // Skip rows containing exclusion string
            if (!empty($excludeLinesWithSubstring) && str_contains($row, $excludeLinesWithSubstring)) {
              if ($this->isDebugModeEnabled()) {
                  print "WARNING: Skipping excluded row:\n" . $row . "\n\n";
              }

              $excludedRowsCount += 1;
              continue;
            }

            preg_match($this->regex, $row, $matches);

            if (array_key_exists('operation', $matches)) {
                $requestLogs[] = $matches;
            }
        }

        if ($this->isDebugModeEnabled()) {
            print "\n\nProcessed log lines:\n";
            var_dump($requestLogs);
        }

        return [
            'requestLogs' => $requestLogs,
            'rowCount' => count($rows),
            'excludedRowsCount' => $excludedRowsCount,
        ];
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

    /**
     * @param string $dateString
     *
     * @return date
     */
    private function parseLogDateString(string $dateString)
    {
        $dateString = explode(' ', $dateString)[0];
        $dateString = ltrim($dateString, '[');
        $dateString = explode(':', $dateString)[0];
        return Carbon::createFromFormat('d/M/Y', $dateString)->format('Y-m-d');
    }

    /**
     * @return true|false
     */
    private function isDebugModeEnabled() : bool
    {
        return $this->configs['debug_mode'];
    }
}

?>
