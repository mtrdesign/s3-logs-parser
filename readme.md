# AWS S3 Logs Parser

[![Latest Stable Version](https://img.shields.io/packagist/v/mtrdesign/s3-logs-parser.svg)](https://packagist.org/packages/mtrdesign/s3-logs-parser)
[![Total Downloads](https://img.shields.io/packagist/dt/mtrdesign/s3-logs-parser.svg)](https://packagist.org/packages/mtrdesign/s3-logs-parser)
[![Build Status](https://www.travis-ci.com/mtrdesign/s3-logs-parser.svg?branch=master)](https://www.travis-ci.com/mtrdesign/s3-logs-parser)
[![codecov](https://codecov.io/gh/mtrdesign/s3-logs-parser/branch/master/graph/badge.svg)](https://codecov.io/gh/mtrdesign/s3-logs-parser)
[![StyleCI](https://styleci.io/repos/191744669/shield?style=flat-square)](https://styleci.io/repos/191744669)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true)](https://github.com/phpstan/phpstan)

* [Getting Started](#getting-started)
* [Usage](#usage)
* [Contributing](#contributing)
  * [Requirements](#requirements)
  * [Installation steps](#installation-steps)
* [License](#license)

AWS S3 Logs Parser is a simple [PHP](https://php.net/) package to parse [Amazon Simple Storage Service (Amazon S3)](https://aws.amazon.com/s3/) logs into a readable JSON format. The detailed usage report will show you how much times a file is downloaded and how much bytes are transferred.

## Getting Started

1. **Sign up for AWS** – Before you begin, you need to [sign up](https://docs.aws.amazon.com/AmazonS3/latest/gsg/SigningUpforS3.html) for an AWS account and retrieve your [AWS credentials](http://aws.amazon.com/developers/access-keys/).
1. **Create your own bucket** – Now that you've signed up for Amazon S3, you're ready to create a bucket using the [AWS Management Console](https://docs.aws.amazon.com/AmazonS3/latest/gsg/CreatingABucket.html).
1. **Enable server access logging** – When you [enable logging](https://docs.aws.amazon.com/AmazonS3/latest/user-guide/server-access-logging.html), Amazon S3 delivers access logs for a source bucket to a target bucket that you choose.
1. **Install the service** – Using [Composer](http://getcomposer.org/) is the recommended way to install it. The service is available via [Packagist](http://packagist.org/) under the [`mtrdesign/s3-logs-parser`](https://packagist.org/packages/mtrdesign/s3-logs-parser) package.

```ssh
$ composer require mtrdesign/s3-logs-parser
```

## Usage

Create a service instance:

```php
<?php

use S3LogsParser\S3LogsParser;

$S3LogsParser = new S3LogsParser([
    'version' => 'latest',
    'region' => $awsBucketRegion,
    'access_key' => $awsAccessKey,
    'secret_key' => $awsSecretKey,
]);

?>
```

Optionally, you can set and update service configurations via `setConfigs()` method:

```php
<?php

$S3LogsParser->setConfigs([
    'version' => 'latest',
    'region' => $awsBucketRegion,
    'access_key' => $awsAccessKey,
    'secret_key' => $awsSecretKey,
]);

?>
```

Finally, you can get file's `download` and `bandwidth` statistics for a specific date in this way:


```php
<?php

$S3LogsParser->getStats($awsBucketName, $awsBucketPrefix, $date);

?>
```

> It is recommended to pass [Carbon](https://carbon.nesbot.com/) date string to this method.

This is how service response should look like:

```json
{
    "success":true,
    "statistics":{
        "bucket":"bn-test",
        "prefix":"bp-2018-10-31",
        "data":{
            "test.png":{
                "downloads":4,
                "bandwidth":4096
            },
            "test2.png":{
                "downloads":2,
                "bandwidth":2048
            }
        }
    }
}
```

## Contributing

Ensure all the guides are followed and style/test checkers pass before pushing your code.

### Requirements

* [Git](https://git-scm.com)
* [Docker](https://docker.com)
* [Docker Compose](https://docs.docker.com/compose)
* [GNU Make](https://www.gnu.org/software/make)

### Installation steps

1. Build the required services and Docker container with `$ make docker-build`
2. SSH into the container with `$ make docker-bash`
3. Confirm [code style checker](https://github.com/squizlabs/php_codesniffer) passes with `$ make run-phpcs`
4. Confirm [code quality checker](https://github.com/phpstan/phpstan) passes with `$ make run-phpstan`
5. Confirm [code texts checker](https://github.com/sebastianbergmann/phpunit) passes with `$ make run-phpunit`

## License

AWS S3 Logs Parser is open source and available under the [MIT License](LICENSE.md).
