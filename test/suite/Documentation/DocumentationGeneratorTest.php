<?php
namespace Icecave\Archer\Documentation;

use Eloquent\Liberator\Liberator;
use Phunky;
use PHPUnit_Framework_TestCase;
use Sami\Sami;
use stdClass;
use Symfony\Component\Finder\Finder;

class DocumentationGeneratorTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Phunky::mock('Icecave\Archer\FileSystem\FileSystem');
        $this->composerConfigReader = Phunky::mock(
            'Icecave\Archer\Configuration\ComposerConfigurationReader'
        );
        $this->isolator = Phunky::mock('Icecave\Archer\Support\Isolator');
        $this->generator = Phunky::partialMock(
            __NAMESPACE__ . '\DocumentationGenerator',
            $this->fileSystem,
            $this->composerConfigReader,
            $this->isolator
        );

        Phunky::when($this->fileSystem)
            ->read(Phunky::anyParameters())
            ->thenReturn('{"name": "vendor/project"}');
        Phunky::when($this->isolator)
            ->sys_get_temp_dir()
            ->thenReturn('/path/to/tmp');
        Phunky::when($this->isolator)
            ->uniqid(Phunky::anyParameters())
            ->thenReturn('uniqid');

        $this->composerConfiguration = json_decode(
            '{"autoload": {"psr-0": {"Vendor\\\\Project\\\\SubProject": "src"}}}'
        );
        $this->finder = Finder::create();
        $this->sami = Phunky::mock('Sami\Sami');
        $this->samiProject = Phunky::mock('Sami\Project');

        Phunky::when($this->composerConfigReader)
            ->read(Phunky::anyParameters())
            ->thenReturn($this->composerConfiguration);
        Phunky::when($this->sami)
            ->offsetGet('project')
            ->thenReturn($this->samiProject);
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem, $this->generator->fileSystem());
        $this->assertSame(
            $this->composerConfigReader,
            $this->generator->composerConfigReader()
        );
    }

    public function testConstructorDefaults()
    {
        $this->generator = new DocumentationGenerator();

        $this->assertInstanceOf(
            'Icecave\Archer\FileSystem\FileSystem',
            $this->generator->fileSystem()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Configuration\ComposerConfigurationReader',
            $this->generator->composerConfigReader()
        );
    }

    public function testGenerate()
    {
        Phunky::when($this->generator)
            ->sourcePath(Phunky::anyParameters())
            ->thenReturn('/path/to/source');
        Phunky::when($this->generator)
            ->createFinder(Phunky::anyParameters())
            ->thenReturn($this->finder);
        Phunky::when($this->generator)
            ->createSami(Phunky::anyParameters())
            ->thenReturn($this->sami);
        Phunky::when($this->fileSystem)
            ->directoryExists('foo/artifacts/documentation/api')
            ->thenReturn(true);
        $this->generator->generate('foo');

        Phunky::inOrder(
            Phunky::verify($this->generator)->createFinder('/path/to/source'),
            Phunky::verify($this->generator)->createSami(
                $this->identicalTo($this->finder),
                array(
                    'title' => 'Project - SubProject API',
                    'default_opened_level' => 3,
                    'build_dir' => 'foo/artifacts/documentation/api',
                    'cache_dir' => '/path/to/tmp/uniqid',
                )
            ),
            Phunky::verify($this->fileSystem)->delete(
                'foo/artifacts/documentation/api'
            ),
            Phunky::verify($this->samiProject)->update()
        );
    }

    public function testGenerateDefaultPath()
    {
        Phunky::when($this->generator)
            ->sourcePath(Phunky::anyParameters())
            ->thenReturn('/path/to/source');
        Phunky::when($this->generator)
            ->createFinder(Phunky::anyParameters())
            ->thenReturn($this->finder);
        Phunky::when($this->generator)
            ->createSami(Phunky::anyParameters())
            ->thenReturn($this->sami);
        Phunky::when($this->fileSystem)
            ->directoryExists('./artifacts/documentation/api')
            ->thenReturn(true);
        $this->generator->generate();

        Phunky::inOrder(
            Phunky::verify($this->generator)->createFinder('/path/to/source'),
            Phunky::verify($this->generator)->createSami(
                $this->identicalTo($this->finder),
                array(
                    'title' => 'Project - SubProject API',
                    'default_opened_level' => 3,
                    'build_dir' => './artifacts/documentation/api',
                    'cache_dir' => '/path/to/tmp/uniqid',
                )
            ),
            Phunky::verify($this->fileSystem)->delete(
                './artifacts/documentation/api'
            ),
            Phunky::verify($this->samiProject)->update()
        );
    }

    public function testGenerateBuildDirNonExistant()
    {
        Phunky::when($this->generator)
            ->sourcePath(Phunky::anyParameters())
            ->thenReturn('/path/to/source');
        Phunky::when($this->generator)
            ->createFinder(Phunky::anyParameters())
            ->thenReturn($this->finder);
        Phunky::when($this->generator)
            ->createSami(Phunky::anyParameters())
            ->thenReturn($this->sami);
        Phunky::when($this->fileSystem)
            ->directoryExists('foo/artifacts/documentation/api')
            ->thenReturn(false);
        $this->generator->generate('foo');

        Phunky::inOrder(
            Phunky::verify($this->generator)->createFinder('/path/to/source'),
            Phunky::verify($this->generator)->createSami(
                $this->identicalTo($this->finder),
                array(
                    'title' => 'Project - SubProject API',
                    'default_opened_level' => 3,
                    'build_dir' => 'foo/artifacts/documentation/api',
                    'cache_dir' => '/path/to/tmp/uniqid',
                )
            ),
            Phunky::verify($this->samiProject)->update()
        );
        Phunky::verify($this->fileSystem, Phunky::never())->delete(
            'foo/artifacts/documentation/api'
        );
    }

    public function testSourcePath()
    {
        $this->assertSame(
            'foo/src',
            Liberator::liberate($this->generator)->sourcePath('foo')
        );
    }

    public function testProjectNameWithSingleNamespace()
    {
        $this->composerConfiguration = json_decode(
            '{"autoload": {"psr-0": {"Project": "src"}}}'
        );
        $generator = Liberator::liberate($this->generator);

        $this->assertSame(
            'Project',
            $generator->projectName($this->composerConfiguration)
        );
    }

    public function testProjectNameFallback()
    {
        $this->composerConfiguration = json_decode(
            '{"name": "vendor/project"}'
        );
        $generator = Liberator::liberate($this->generator);

        $this->assertSame(
            'vendor/project',
            $generator->projectName($this->composerConfiguration)
        );
    }

    public function testOpenedLevelWithSingleNamespace()
    {
        $this->composerConfiguration = json_decode(
            '{"autoload": {"psr-0": {"Project": "src"}}}'
        );
        $generator = Liberator::liberate($this->generator);

        $this->assertSame(
            1,
            $generator->openedLevel($this->composerConfiguration)
        );
    }

    public function testOpenedLevelFallback()
    {
        $this->composerConfiguration = json_decode(
            '{"name": "vendor/project"}'
        );
        $generator = Liberator::liberate($this->generator);

        $this->assertSame(
            2,
            $generator->openedLevel($this->composerConfiguration)
        );
    }

    public function testOpenedLevelFallbackNoEntries()
    {
        $this->composerConfiguration = json_decode(
            '{"autoload": {"psr-0": {}}}'
        );
        $generator = Liberator::liberate($this->generator);

        $this->assertSame(
            2,
            $generator->openedLevel($this->composerConfiguration)
        );
    }

    public function testOpenedLevelFallbackNamespaceTooShort()
    {
        $this->composerConfiguration = json_decode(
            '{"autoload": {"psr-0": {"": "src"}}}'
        );
        $generator = Liberator::liberate($this->generator);

        $this->assertSame(
            2,
            $generator->openedLevel($this->composerConfiguration)
        );
    }

    public function testProjectNameFailureUndefined()
    {
        $generator = Liberator::liberate($this->generator);

        $this->setExpectedException('RuntimeException');
        $generator->projectName(new stdClass());
    }

    public function testCreateFinder()
    {
        $finder = Liberator::liberate($this->generator)->createFinder(__DIR__);
        $expected = Finder::create()->in(__DIR__);

        $this->assertEquals($expected, $finder);
    }

    public function testCreateSami()
    {
        $sami = Liberator::liberate($this->generator)
            ->createSami($this->finder, array('title' => 'foo'));
        $expected = new Sami($this->finder, array('title' => 'foo'));

        $this->assertEquals($expected, $sami);
    }

    public function testPopErrorHandlers()
    {
        $handlerA = function () { return 'A'; };
        $handlerB = function () { return 'B'; };
        $handlerStack = array($handlerA, $handlerB);
        Phunky::when($this->isolator)
            ->set_error_handler(Phunky::anyParameters())
            ->thenGetReturnByLambda(function ($handler) use (&$handlerStack) {
                return array_pop($handlerStack);
            });
        $expected = array_reverse($handlerStack);

        $this->assertSame(
            $expected,
            Liberator::liberate($this->generator)->popErrorHandlers()
        );
        $setVerification = Phunky::verify($this->isolator, Phunky::times(3))
            ->set_error_handler($this->isInstanceOf('Closure'));
        $restoreVerification = Phunky::verify($this->isolator, Phunky::times(6))
            ->restore_error_handler();
        Phunky::inOrder(
            $setVerification,
            $restoreVerification,
            $restoreVerification,
            $setVerification,
            $restoreVerification,
            $restoreVerification,
            $setVerification,
            $restoreVerification,
            $restoreVerification
        );
    }

    public function testPushErrorHandlers()
    {
        $handlerA = function () { return 'A'; };
        $handlerB = function () { return 'B'; };
        $handlerStack = array($handlerB, $handlerA);
        Liberator::liberate($this->generator)->pushErrorHandlers($handlerStack);

        Phunky::inOrder(
            Phunky::verify($this->isolator)->set_error_handler(
                $this->identicalTo($handlerA)
            ),
            Phunky::verify($this->isolator)->set_error_handler(
                $this->identicalTo($handlerB)
            )
        );
    }
}
