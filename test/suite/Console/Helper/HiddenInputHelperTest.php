<?php
namespace Icecave\Archer\Console\Helper;

use Phunky;
use PHPUnit_Framework_TestCase;
use RuntimeException;

class HiddenInputHelperTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->isolator = Phunky::mock('Icecave\Archer\Support\Isolator');
        $this->helper = new HiddenInputHelper(
            'foo',
            $this->isolator
        );

        Phunky::when($this->isolator)
            ->sys_get_temp_dir(Phunky::anyParameters())
            ->thenReturn('doom')
        ;
        Phunky::when($this->isolator)
            ->uniqid(Phunky::anyParameters())
            ->thenReturn('splat')
        ;

        $this->output = Phunky::mock(
            'Symfony\Component\Console\Output\OutputInterface'
        );
    }

    public function testConstructor()
    {
        $this->assertSame('hidden-input', $this->helper->getName());
        $this->assertSame('foo', $this->helper->hiddenInputPath());
    }

    public function testConstructorDefaults()
    {
        $this->helper = new HiddenInputHelper();
        $expectedHiddenInputPath = __DIR__ . '/../../../../res/bin/hiddeninput.exe';

        $this->assertTrue(file_exists($expectedHiddenInputPath));

        $this->assertSame(
            realpath($expectedHiddenInputPath),
            realpath($this->helper->hiddenInputPath())
        );
    }

    public function testAskHiddenResponseStty()
    {
        Phunky::when($this->isolator)
            ->defined(Phunky::anyParameters())
            ->thenReturn(false)
        ;
        Phunky::when($this->isolator)
            ->shell_exec(Phunky::anyParameters())
            ->thenReturn('baz')
            ->thenReturn('')
        ;
        Phunky::when($this->isolator)
            ->fgets(Phunky::anyParameters())
            ->thenReturn('qux')
        ;
        $actual = $this->helper->askHiddenResponse($this->output, 'bar');

        $this->assertSame('qux', $actual);
        Phunky::inOrder(
            Phunky::verify($this->isolator)->defined('PHP_WINDOWS_VERSION_BUILD'),
            Phunky::verify($this->output)->write('bar'),
            Phunky::verify($this->isolator)->shell_exec('stty -g'),
            Phunky::verify($this->isolator)->shell_exec('stty -echo'),
            Phunky::verify($this->isolator)->fgets(STDIN),
            Phunky::verify($this->isolator)->shell_exec('stty baz'),
            Phunky::verify($this->output)->writeln('')
        );
    }

    public function testAskHiddenResponseSttyFailureFgets()
    {
        $errorException = Phunky::mock('ErrorException');
        Phunky::when($this->isolator)
            ->defined(Phunky::anyParameters())
            ->thenReturn(false)
        ;
        Phunky::when($this->isolator)
            ->shell_exec(Phunky::anyParameters())
            ->thenReturn('baz')
            ->thenReturn('')
        ;
        Phunky::when($this->isolator)
            ->fgets(Phunky::anyParameters())
            ->thenThrow($errorException)
        ;
        $actual = null;
        try {
            $this->helper->askHiddenResponse($this->output, 'bar');
        } catch (RuntimeException $actual) {
        }
        $expected = new RuntimeException('Unable to read response.', 0, $errorException);

        $this->assertEquals($expected, $actual);
        Phunky::inOrder(
            Phunky::verify($this->isolator)->defined('PHP_WINDOWS_VERSION_BUILD'),
            Phunky::verify($this->output)->write('bar'),
            Phunky::verify($this->isolator)->shell_exec('stty -g'),
            Phunky::verify($this->isolator)->shell_exec('stty -echo'),
            Phunky::verify($this->isolator)->fgets(STDIN),
            Phunky::verify($this->isolator)->shell_exec('stty baz')
        );
    }

    public function testAskHiddenResponseSttyFailureExecute()
    {
        Phunky::when($this->isolator)
            ->defined(Phunky::anyParameters())
            ->thenReturn(false)
        ;
        Phunky::when($this->isolator)
            ->shell_exec(Phunky::anyParameters())
            ->thenReturn(false)
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Unable to create or read hidden input dialog.'
        );
        $this->helper->askHiddenResponse($this->output, 'bar');
    }

    public function testAskHiddenResponseWindows()
    {
        Phunky::when($this->isolator)
            ->defined(Phunky::anyParameters())
            ->thenReturn(true)
        ;
        Phunky::when($this->isolator)
            ->shell_exec(Phunky::anyParameters())
            ->thenReturn('baz')
        ;
        $actual = $this->helper->askHiddenResponse($this->output, 'bar');

        $this->assertSame('baz', $actual);
        Phunky::inOrder(
            Phunky::verify($this->isolator)->defined('PHP_WINDOWS_VERSION_BUILD'),
            Phunky::verify($this->output)->write('bar'),
            Phunky::verify($this->isolator)->copy('foo', 'doom/hiddeninput-splat.exe'),
            Phunky::verify($this->isolator)->shell_exec('doom/hiddeninput-splat.exe'),
            Phunky::verify($this->output)->writeln('')
        );
    }

    public function testAskHiddenResponseWindowsFailureExecute()
    {
        Phunky::when($this->isolator)
            ->defined(Phunky::anyParameters())
            ->thenReturn(true)
        ;
        Phunky::when($this->isolator)
            ->shell_exec(Phunky::anyParameters())
            ->thenReturn(false)
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Unable to create or read hidden input dialog.'
        );
        $this->helper->askHiddenResponse($this->output, 'bar');
    }
}
