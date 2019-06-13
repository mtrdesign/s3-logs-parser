<?php

namespace Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getFixture(string $file = '', bool $toJson = false)
    {
        $content = file_get_contents(sprintf('%s/Fixtures/%s', __DIR__, $file));

        if ($toJson === false) {
            $content = json_decode($content, true);
        }

        return $content;
    }
}
