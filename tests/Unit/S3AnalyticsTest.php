<?php

namespace Tests\Unit;

use Mockery;
use Carbon\Carbon;
use Tests\TestCase;
use S3Analytics\S3Analytics;
use Aws\S3\S3Client;

class S3AnalyticsTest extends TestCase
{
    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::getConfig
     */
    public function iShouldBeAbleToSetConfigsViaConstructor()
    {
        $s3Analytics = new S3Analytics([
            'region' => '1',
            'access_key' => '2',
            'secret_key' => '3',
        ]);

        $this->assertEquals('1', $s3Analytics->getConfig('region'));
        $this->assertEquals('2', $s3Analytics->getConfig('access_key'));
        $this->assertEquals('3', $s3Analytics->getConfig('secret_key'));
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::setConfigs
     * @covers \S3Analytics\S3Analytics::getConfig
     */
    public function iShouldBeAbleToSetConfigsViaSetConfigsMethod()
    {
        $s3Analytics = new S3Analytics();

        $s3Analytics->setConfigs([
            'region' => '1',
            'access_key' => '2',
            'secret_key' => '3',
        ]);

        $this->assertEquals('1', $s3Analytics->getConfig('region'));
        $this->assertEquals('2', $s3Analytics->getConfig('access_key'));
        $this->assertEquals('3', $s3Analytics->getConfig('secret_key'));
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::setConfigs
     * @covers \S3Analytics\S3Analytics::getConfig
     */
    public function iShouldBeAbleToOverrideConstructorConfigsViaSetConfigsMethod()
    {
        $s3Analytics = new S3Analytics([
            'region' => '1',
            'access_key' => '2',
            'secret_key' => '3',
        ]);

        $s3Analytics->setConfigs([
            'access_key' => '4',
            'secret_key' => '5',
        ]);

        $this->assertEquals('1', $s3Analytics->getConfig('region'));
        $this->assertEquals('4', $s3Analytics->getConfig('access_key'));
        $this->assertEquals('5', $s3Analytics->getConfig('secret_key'));
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::getConfigs
     */
    public function iShouldNotBeAbleToSetInvalidConfigs()
    {
        $s3Analytics = new S3Analytics([
            'test' => 'test12345',
        ]);

        $this->assertEmpty($s3Analytics->getConfig('test'));

        $s3Analytics = new S3Analytics([
            'region' => [],
        ]);

        $this->assertEmpty($s3Analytics->getConfig('region'));

        $s3Analytics->setConfigs([
            'region' => [],
        ]);

        $this->assertEmpty($s3Analytics->getConfig('region'));
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::getStatistics
     * @covers \S3Analytics\S3Analytics::parseObject
     * @covers \S3Analytics\S3Analytics::getClient
     */
    public function iShouldBeAbleToSetDateAsCarbon()
    {
        $S3Client = Mockery::mock(S3Client::class);

        $S3Client->shouldReceive('getPaginator')->andReturn([]);

        $s3Analytics = new S3Analytics([], $S3Client);

        $response = $s3Analytics->getStatistics('bn-test', 'bp-', Carbon::now());

        $this->assertIsString($response);

        $responseToArray = json_decode($response, true);

        $this->assertIsArray($responseToArray);
        $this->assertEquals(true, $responseToArray['success']);
        $this->assertEquals('bn-test', $responseToArray['statistics']['bucket']);
        $this->assertEquals('bp-' . Carbon::now()->format('Y-m-d'), $responseToArray['statistics']['prefix']);
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::getStatistics
     * @covers \S3Analytics\S3Analytics::parseObject
     * @covers \S3Analytics\S3Analytics::getClient
     */
    public function iShouldBeAbleToSetDateAsString()
    {
        $S3Client = Mockery::mock(S3Client::class);

        $S3Client->shouldReceive('getPaginator')->andReturn([]);

        $s3Analytics = new S3Analytics([], $S3Client);

        $response = $s3Analytics->getStatistics('bn-test', 'bp-', '2018-10-31');

        $this->assertIsString($response);

        $responseToArray = json_decode($response, true);

        $this->assertIsArray($responseToArray);
        $this->assertEquals(true, $responseToArray['success']);
        $this->assertEquals('bn-test', $responseToArray['statistics']['bucket']);
        $this->assertEquals('bp-2018-10-31', $responseToArray['statistics']['prefix']);
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::getStatistics
     */
    public function iShouldNotBeAbleToSetInvalidDate()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'DateTime::__construct(): Failed to parse time string (123) at position 0 (1): Unexpected character'
        );

        $S3Client = Mockery::mock(S3Client::class);

        $S3Client->shouldReceive('getPaginator')->andReturn([]);

        $s3Analytics = new S3Analytics([], $S3Client);

        $s3Analytics->getStatistics('', '', '123');
    }

    /**
     * @test
     * @covers \S3Analytics\S3Analytics::__construct
     * @covers \S3Analytics\S3Analytics::getStatistics
     * @covers \S3Analytics\S3Analytics::parseObject
     * @covers \S3Analytics\S3Analytics::getClient
     */
    public function iShouldBeAbleToGetStatistics()
    {
        $S3Client = Mockery::mock(S3Client::class);

        $S3Client
            ->shouldReceive('getPaginator')
            ->andReturn($this->getFixture('s3.list-objects.json'));

        $fixture = $this->getFixture('s3.get-object.json');
        $fixture_1 = str_replace('{{ key }}', 'test.png', $fixture);
        $fixture_2 = str_replace('{{ key }}', 'test.png', $fixture);
        $fixture_3 = str_replace('{{ key }}', 'test.png', $fixture);
        $fixture_4 = str_replace('{{ key }}', 'test.png', $fixture);
        $fixture_5 = str_replace('{{ key }}', 'test2.png', $fixture);
        $fixture_6 = str_replace('{{ key }}', 'test2.png', $fixture);

        $S3Client
            ->shouldReceive('getObject')
            ->once()
            ->andReturn($fixture_1);

        $S3Client
            ->shouldReceive('getObject')
            ->once()
            ->andReturn($fixture_2);

        $S3Client
            ->shouldReceive('getObject')
            ->once()
            ->andReturn($fixture_3);

        $S3Client
            ->shouldReceive('getObject')
            ->once()
            ->andReturn($fixture_4);

        $S3Client
            ->shouldReceive('getObject')
            ->once()
            ->andReturn($fixture_5);

        $S3Client
            ->shouldReceive('getObject')
            ->once()
            ->andReturn($fixture_6);

        $s3Analytics = new S3Analytics([], $S3Client);

        $response = $s3Analytics->getStatistics('bn-test', 'bp-', '2018-10-31');

        $this->assertIsString($response);

        $responseToArray = json_decode($response, true);

        $this->assertIsArray($responseToArray);
        $this->assertEquals(true, $responseToArray['success']);
        $this->assertEquals('bn-test', $responseToArray['statistics']['bucket']);
        $this->assertEquals('bp-2018-10-31', $responseToArray['statistics']['prefix']);
        $this->assertEquals('4', $responseToArray['statistics']['data']['test.png']['downloads']);
        $this->assertEquals('2', $responseToArray['statistics']['data']['test2.png']['downloads']);
        $this->assertEquals('4096', $responseToArray['statistics']['data']['test.png']['bandwidth']);
        $this->assertEquals('2048', $responseToArray['statistics']['data']['test2.png']['bandwidth']);
    }
}
