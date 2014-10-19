<?php
namespace Icecave\Archer\Configuration;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\FileSystem\FileSystem;
use PHPUnit_Framework_TestCase;
use stdClass;

class ComposerConfigurationReaderTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->subject = new ComposerConfigurationReader($this->fileSystem->mock());
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem->mock(), $this->subject->fileSystem());
    }

    public function testConstructorDefaults()
    {
        $this->subject = new ComposerConfigurationReader();

        $this->assertEquals(new FileSystem(), $this->subject->fileSystem());
    }

    public function testRead()
    {
        $this->fileSystem->read->returns('{"foo": "bar"}');

        $this->assertEquals((object) array('foo' => 'bar'), $this->subject->read('baz'));
    }

    public function testReadFailureJson()
    {
        $this->fileSystem->read->returns('{');

        $this->setExpectedException(
            'Icecave\Archer\FileSystem\Exception\ReadException',
            "Unable to read from 'baz/composer.json'."
        );
        $this->subject->read('baz');
    }
}
