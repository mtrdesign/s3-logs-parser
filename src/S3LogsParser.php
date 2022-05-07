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
        'debug_mode' => false,
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

    const COLUMNS_TO_KEEP_RUNNING_TOTALS_FOR = ['totaltime', 'downloads', 'bandwidth', 'totalRequestTimeInMinutes'];

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
    public function getStats($bucketName = null, $bucketPrefix = null, $date = null) : array
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
     * @param string $bucketName
     * @param string $bucketPrefix
     * @param string $date
     *
     * @return string|false
     */
    public function getStatsAsJSON($bucketName = null, $prefix = null, $date = null) : string
    {
      $logStats = ['data' => $this->getStats($bucketName, $prefix, $date)];

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
        $listObjectsParams = [
            'Bucket' => $bucketName,
            'Prefix' => $bucketPrefix + (is_null($date) ? '' : Carbon::parse($date)->format('Y-m-d')),
        ];

        $results = $this->getClient()->getPaginator('ListObjects', $listObjectsParams);
        $logLines = [];

        foreach ($results as $result) {
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $logLines += $this->parseS3Object($bucketName, $object['Key']);
                }
            }
        }

        return $logLines;
    }

    /**
     * @param string $logDir
     *
     * @return hash
     */
    public function loadLogsFromLocalDir(string $logDir) : array
    {
      $logLines = [];
      $httpOperationCounts = [];
      print "Reading files from " . $logDir . "\n";

      foreach (new \DirectoryIterator($logDir) as $file) {
          if ($file->isFile()) {
              $fileContents = file_get_contents($file->getPathname(), true);
              $processedLogs = $this->processLogsStringToArray($fileContents);
              $logLines = array_merge($logLines, $processedLogs['output']);

              if ($this->debugMode()) {
                print 'Read ' . count($processedLogs['output']) . ' lines from file: ' . $file->getFilename() . "...\n";
              }

              foreach ($processedLogs['httpOperationCounts'] as $httpOperationName => $count) {
                  if (!array_key_exists($httpOperationName, $httpOperationCounts)) {
                      $httpOperationCounts[$httpOperationName] = 0;
                  }

                  $httpOperationCounts[$httpOperationName] += (int) $count;
              }
          }
      }

      print "\n\n****HTTP OPERATION COUNTS****\n" . print_r($httpOperationCounts);
      return $logLines;
    }

    /**
     * @param string $parsedLogs
     *
     * @return hash
     */
    public function computeStatistics(array $parsedLogs)
    {
        $statistics = [];

        foreach ($parsedLogs as $item) {
            if (isset($item['key']) && mb_strlen($item['key'])) {
                foreach(self::COLUMNS_TO_KEEP_RUNNING_TOTALS_FOR as $column_name) {
                    if (!isset($statistics[$item['key']][$column_name])) {
                        $statistics[$item['key']][$column_name] = 0;
                    }
                }

                if (!isset($statistics[$item['key']]['dates'])) {
                    $statistics[$item['key']]['dates'] = [];
                }

                $statistics[$item['key']]['downloads'] += 1;
                $date = $this->parseLogDateString($item['time']);

                if (!in_array($date, $statistics[$item['key']]['dates'])) {
                  array_push($statistics[$item['key']]['dates'], $date);
                }

                if (isset($item['bytes'])) {
                    if ($this->debugMode()) {
                        print "DEBUG: OBJECT DOWNLOAD: " . $item['bytes'] . " bytes from " . $item['key'] . "\n";
                        print "\n" . print_r($item) . "\n\n";
                    }

                    $statistics[$item['key']]['bandwidth'] += (int) $item['bytes'];
                }

                // TODO: Sum milliseconds now; convert to minutes later.
                if (isset($item['totaltime'])) {
                    $totalRequestTimeInMinutes = (float) $item['totaltime'] / 1000.0 / 60.0;
                    $statistics[$item['key']]['totalRequestTimeInMinutes'] += $totalRequestTimeInMinutes;
                }
            }
        }

        return $statistics;
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

        return $this->processLogsStringToArray((string) $file['Body']);
    }

    /**
     * @param string $logsString
     *
     * @return array
     */
    public function processLogsStringToArray(string $logsString) : array
    {
        $rows = explode("\n", $logsString);
        $processedLogs = [];
        $httpOperationCounts = [];
        $excludedRowsCount = 0;

        foreach ($rows as $row) {
            $exclude_lines_with_substring = $this->getConfig('exclude_lines_with_substring');

            // Skip rows containing exclusion string
            if (!empty($exclude_lines_with_substring) && str_contains($row, $exclude_lines_with_substring)) {
              print "WARNING: Skipping excluded row:\n" . $row . "\n\n";
              $excludedRowsCount += 1;
              continue;
            }

            preg_match($this->regex, $row, $matches);

            if (array_key_exists('operation', $matches)) {
                $httpOperationName = $matches['operation'];

                if (!array_key_exists($httpOperationName, $httpOperationCounts)) {
                    $httpOperationCounts[$httpOperationName] = 0;
                }

                $httpOperationCounts[$httpOperationName] += 1;
            }

            if (isset($matches['operation']) && $matches['operation'] == 'REST.GET.OBJECT') {
                $processedLogs[] = $matches;
            }
        }

        if ($this->debugMode()) {
            print "\n\nPROCESSED DATA:";
            var_dump($processedLogs);
        }

        return [
            'httpOperationCounts' => $httpOperationCounts,
            'output' => $processedLogs,
            'rowCount' => count($rows),
            '$excludedRowsCount' => $$excludedRowsCount = 0,
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
    private function debugMode()
    {
        return $this->getConfig('debug_mode');
    }
}

?>
