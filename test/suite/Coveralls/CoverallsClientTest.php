<?php
namespace Icecave\Archer\Coveralls;

use Phunky;
use PHPUnit_Framework_TestCase;

class CoverallsClientTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->isolator = Phunky::mock('Icecave\Archer\Support\Isolator');
        $this->client = new CoverallsClient($this->isolator);
    }

    public function testExists()
    {
        Phunky::when($this->isolator)
            ->file_get_contents(Phunky::anyParameters())
            ->thenReturn('{}');

        $this->assertTrue($this->client->exists('vendor', 'project'));
        Phunky::verify($this->isolator)
            ->file_get_contents('https://coveralls.io/r/vendor/project.json');
    }

    public function testExistsFailureHttpError()
    {
        Phunky::when($this->isolator)
            ->file_get_contents(Phunky::anyParameters())
            ->thenThrow(Phunky::mock('ErrorException'));

        $this->assertFalse($this->client->exists('vendor', 'project'));
        Phunky::verify($this->isolator)
            ->file_get_contents('https://coveralls.io/r/vendor/project.json');
    }

    public function testExistsFailureJsonError()
    {
        Phunky::when($this->isolator)
            ->file_get_contents(Phunky::anyParameters())
            ->thenReturn('{');

        $this->assertFalse($this->client->exists('vendor', 'project'));
        Phunky::verify($this->isolator)
            ->file_get_contents('https://coveralls.io/r/vendor/project.json');
    }
}
