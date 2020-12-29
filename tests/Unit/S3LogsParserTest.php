<?php

namespace Tests\Unit;

use Aws\S3\S3Client;
use Carbon\Carbon;
use Mockery;
use S3LogsParser\S3LogsParser;
use Tests\TestCase;

class S3LogsParserTest extends TestCase
{
    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getConfig
     */
    public function iShouldBeAbleToSetConfigsViaConstructor()
    {
        $S3LogsParser = new S3LogsParser([
            'region' => '1',
            'access_key' => '2',
            'secret_key' => '3',
        ]);

        $this->assertEquals('1', $S3LogsParser->getConfig('region'));
        $this->assertEquals('2', $S3LogsParser->getConfig('access_key'));
        $this->assertEquals('3', $S3LogsParser->getConfig('secret_key'));
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::setConfigs
     * @covers \S3LogsParser\S3LogsParser::getConfig
     */
    public function iShouldBeAbleToSetConfigsViaSetConfigsMethod()
    {
        $S3LogsParser = new S3LogsParser();

        $S3LogsParser->setConfigs([
            'region' => '1',
            'access_key' => '2',
            'secret_key' => '3',
        ]);

        $this->assertEquals('1', $S3LogsParser->getConfig('region'));
        $this->assertEquals('2', $S3LogsParser->getConfig('access_key'));
        $this->assertEquals('3', $S3LogsParser->getConfig('secret_key'));
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::setConfigs
     * @covers \S3LogsParser\S3LogsParser::getConfig
     */
    public function iShouldBeAbleToOverrideConstructorConfigsViaSetConfigsMethod()
    {
        $S3LogsParser = new S3LogsParser([
            'region' => '1',
            'access_key' => '2',
            'secret_key' => '3',
        ]);

        $S3LogsParser->setConfigs([
            'access_key' => '4',
            'secret_key' => '5',
        ]);

        $this->assertEquals('1', $S3LogsParser->getConfig('region'));
        $this->assertEquals('4', $S3LogsParser->getConfig('access_key'));
        $this->assertEquals('5', $S3LogsParser->getConfig('secret_key'));
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getConfig
     */
    public function iShouldNotBeAbleToSetInvalidConfigs()
    {
        $S3LogsParser = new S3LogsParser([
            'test' => 'test12345',
        ]);

        $this->assertEmpty($S3LogsParser->getConfig('test'));

        $S3LogsParser = new S3LogsParser([
            'region' => [],
        ]);

        $this->assertEmpty($S3LogsParser->getConfig('region'));

        $S3LogsParser->setConfigs([
            'region' => [],
        ]);

        $this->assertEmpty($S3LogsParser->getConfig('region'));
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getStats
     * @covers \S3LogsParser\S3LogsParser::getClient
     */
    public function iShouldSeeErrorOnWrongAwsCredentials()
    {
        $this->expectException(\Aws\Exception\InvalidRegionException::class);

        $S3LogsParser = new S3LogsParser();

        $S3LogsParser->getStats('bn-test', 'bp-', Carbon::now());
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getStats
     * @covers \S3LogsParser\S3LogsParser::parseObject
     * @covers \S3LogsParser\S3LogsParser::getClient
     */
    public function iShouldBeAbleToSetDateAsCarbon()
    {
        $S3Client = Mockery::mock(S3Client::class);

        $S3Client->shouldReceive('getPaginator')->andReturn([]);

        $S3LogsParser = new S3LogsParser([], $S3Client);

        $response = $S3LogsParser->getStats('bn-test', 'bp-', Carbon::now());

        $this->assertIsString($response);

        $responseToArray = json_decode($response, true);

        $this->assertIsArray($responseToArray);
        $this->assertEquals(true, $responseToArray['success']);
        $this->assertEquals('bn-test', $responseToArray['statistics']['bucket']);
        $this->assertEquals('bp-'.Carbon::now()->format('Y-m-d'), $responseToArray['statistics']['prefix']);
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getStats
     * @covers \S3LogsParser\S3LogsParser::parseObject
     * @covers \S3LogsParser\S3LogsParser::getClient
     */
    public function iShouldBeAbleToSetDateAsString()
    {
        $S3Client = Mockery::mock(S3Client::class);

        $S3Client->shouldReceive('getPaginator')->andReturn([]);

        $S3LogsParser = new S3LogsParser([], $S3Client);

        $response = $S3LogsParser->getStats('bn-test', 'bp-', '2018-10-31');

        $this->assertIsString($response);

        $responseToArray = json_decode($response, true);

        $this->assertIsArray($responseToArray);
        $this->assertEquals(true, $responseToArray['success']);
        $this->assertEquals('bn-test', $responseToArray['statistics']['bucket']);
        $this->assertEquals('bp-2018-10-31', $responseToArray['statistics']['prefix']);
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getStats
     */
    public function iShouldNotBeAbleToSetInvalidDate()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'DateTime::__construct(): Failed to parse time string (123) at position 0 (1): Unexpected character'
        );

        $S3Client = Mockery::mock(S3Client::class);

        $S3Client->shouldReceive('getPaginator')->andReturn([]);

        $S3LogsParser = new S3LogsParser([], $S3Client);

        $S3LogsParser->getStats('', '', '123');
    }

    /**
     * @test
     * @covers \S3LogsParser\S3LogsParser::__construct
     * @covers \S3LogsParser\S3LogsParser::getStats
     * @covers \S3LogsParser\S3LogsParser::parseObject
     * @covers \S3LogsParser\S3LogsParser::getClient
     */
    public function iShouldBeAbleTogetStats()
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

        $S3LogsParser = new S3LogsParser([], $S3Client);

        $response = $S3LogsParser->getStats('bn-test', 'bp-', '2018-10-31');

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
