# S3 Logs Parser

[![Latest Stable Version](https://img.shields.io/packagist/v/mtrdesign/s3-logs-parser.svg)](https://packagist.org/packages/mtrdesign/s3-logs-parser)
[![Total Downloads](https://img.shields.io/packagist/dt/mtrdesign/s3-logs-parser.svg)](https://packagist.org/packages/mtrdesign/s3-logs-parser)
[![Build Status](https://www.travis-ci.com/mtrdesign/s3-logs-parser.svg?branch=master)](https://www.travis-ci.com/mtrdesign/s3-logs-parser)
[![codecov](https://codecov.io/gh/mtrdesign/s3-logs-parser/branch/master/graph/badge.svg)](https://codecov.io/gh/mtrdesign/s3-logs-parser)
[![StyleCI](https://styleci.io/repos/191744669/shield?style=flat-square)](https://styleci.io/repos/191744669)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true)](https://github.com/phpstan/phpstan)

Server access logging provides detailed records for the requests that are made to a bucket.

## Installation

```
$ composer require mtrdesign/s3-logs-parser
```

## Usage

```
<?php

use S3Analytics\S3Analytics;

$s3Analytics = new S3Analytics();

$s3Analytics->setConfigs([
    'version' => 'latest',
    'region' => $awsBucketRegion,
    'access_key' => $awsAccessKey,
    'secret_key' => $awsSecretKey,
]);

$response = $s3Analytics->getStatistics($awsBucketName, $awsBucketPrefix, $date);

?>
```