<?php
namespace Icecave\Archer\Git;

use PHPUnit_Framework_TestCase;
use Phunky;

class GitDotFilesManagerTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Phunky::mock('Icecave\Archer\FileSystem\FileSystem');
        $this->manager = new GitDotFilesManager($this->fileSystem);

        $this->ignore     = 'foo' . PHP_EOL . 'bar' . PHP_EOL;
        $this->attributes = 'foo export-ignore' . PHP_EOL . 'bar export-ignore' . PHP_EOL;

        Phunky::when($this->fileSystem)
            ->fileExists(Phunky::anyParameters())
            ->thenReturn(false);

        Phunky::when($this->fileSystem)
            ->read('/path/to/archer/res/git/gitignore')
            ->thenReturn($this->ignore);

        Phunky::when($this->fileSystem)
            ->read('/path/to/archer/res/git/gitattributes')
            ->thenReturn($this->attributes);
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem, $this->manager->fileSystem());
    }

    public function testConstructorDefaults()
    {
        $this->manager = new GitDotFilesManager();

        $this->assertInstanceOf(
            'Icecave\Archer\FileSystem\FileSystem',
            $this->manager->fileSystem()
        );
    }

    public function testUpdateDotFiles()
    {
        $expectedIgnore  = '# archer start' . PHP_EOL;
        $expectedIgnore .= $this->ignore;
        $expectedIgnore .= '# archer end' . PHP_EOL;

        $expectedAttributes  = '# archer start' . PHP_EOL;
        $expectedAttributes .= $this->attributes;
        $expectedAttributes .= '# archer end' . PHP_EOL;

        $result = $this->manager->updateDotFiles('/path/to/archer', '/path/to/project');

        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.gitignore'),
            Phunky::verify($this->fileSystem)->read('/path/to/archer/res/git/gitignore'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.gitignore', $expectedIgnore),
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.gitattributes'),
            Phunky::verify($this->fileSystem)->read('/path/to/archer/res/git/gitattributes'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.gitattributes', $expectedAttributes)
        );

        $expected = array(
            '.gitignore'     => true,
            '.gitattributes' => true,
        );

        $this->assertSame($expected, $result);
    }

    public function testUpdateDotFilesAppend()
    {
        Phunky::when($this->fileSystem)
            ->fileExists('/path/to/project/.gitignore')
            ->thenReturn(true);

        Phunky::when($this->fileSystem)
            ->read('/path/to/project/.gitignore')
            ->thenReturn('existing' . PHP_EOL);

        $expectedIgnore  = 'existing' . PHP_EOL . PHP_EOL;
        $expectedIgnore .= '# archer start' . PHP_EOL;
        $expectedIgnore .= $this->ignore;
        $expectedIgnore .= '# archer end' . PHP_EOL;

        $result = $this->manager->updateDotFiles('/path/to/archer', '/path/to/project');
        $actualIgnore = null;

        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.gitignore'),
            Phunky::verify($this->fileSystem)->read('/path/to/archer/res/git/gitignore'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.gitignore', Phunky::capture($actualIgnore))
        );

        $expected = array(
            '.gitignore'     => true,
            '.gitattributes' => true,
        );

        $this->assertSame($expectedIgnore, $actualIgnore);
        $this->assertSame($expected, $result);
    }

    public function testUpdateDotFilesReplace()
    {
        $existingIgnore  = 'existing' . PHP_EOL . PHP_EOL;
        $existingIgnore .= '  # archer start  ' . PHP_EOL;
        $existingIgnore .= 'old archer content' . PHP_EOL;
        $existingIgnore .= '  # archer end   ' . PHP_EOL;
        $existingIgnore .= 'more existing content';

        Phunky::when($this->fileSystem)
            ->fileExists('/path/to/project/.gitignore')
            ->thenReturn(true);

        Phunky::when($this->fileSystem)
            ->read('/path/to/project/.gitignore')
            ->thenReturn($existingIgnore);

        $expectedIgnore  = 'existing' . PHP_EOL . PHP_EOL;
        $expectedIgnore .= '# archer start' . PHP_EOL;
        $expectedIgnore .= $this->ignore;
        $expectedIgnore .= '# archer end' . PHP_EOL;
        $expectedIgnore .= 'more existing content' . PHP_EOL;

        $result = $this->manager->updateDotFiles('/path/to/archer', '/path/to/project');
        $actualIgnore = null;

        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.gitignore'),
            Phunky::verify($this->fileSystem)->read('/path/to/archer/res/git/gitignore'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.gitignore', Phunky::capture($actualIgnore))
        );

        $expected = array(
            '.gitignore'     => true,
            '.gitattributes' => true,
        );

        $this->assertSame($expectedIgnore, $actualIgnore);
        $this->assertSame($expected, $result);
    }

    public function testUpdateDotFilesNoChange()
    {
        $existingIgnore  = 'existing' . PHP_EOL . PHP_EOL;
        $existingIgnore .= '# archer start' . PHP_EOL;
        $existingIgnore .= $this->ignore;
        $existingIgnore .= '# archer end' . PHP_EOL;
        $existingIgnore .= 'more existing content' . PHP_EOL;

        Phunky::when($this->fileSystem)
            ->fileExists('/path/to/project/.gitignore')
            ->thenReturn(true);

        Phunky::when($this->fileSystem)
            ->read('/path/to/project/.gitignore')
            ->thenReturn($existingIgnore);

        $result = $this->manager->updateDotFiles('/path/to/archer', '/path/to/project');

        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.gitignore'),
            Phunky::verify($this->fileSystem)->read('/path/to/archer/res/git/gitignore')
        );

        Phunky::verify($this->fileSystem, Phunky::never())->write('/path/to/project/.gitignore', $this->anything());

        $expected = array(
            '.gitignore'     => false,
            '.gitattributes' => true,
        );

        $this->assertSame($expected, $result);
    }

    public function testUpdateDotFilesMissingTags()
    {
        Phunky::when($this->fileSystem)
            ->fileExists('/path/to/project/.gitignore')
            ->thenReturn(true);

        Phunky::when($this->fileSystem)
            ->read('/path/to/project/.gitignore')
            ->thenReturn($this->ignore);

        $expectedIgnore  = '# archer start' . PHP_EOL;
        $expectedIgnore .= $this->ignore;
        $expectedIgnore .= '# archer end' . PHP_EOL;

        $result = $this->manager->updateDotFiles('/path/to/archer', '/path/to/project');
        $actualIgnore = null;

        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.gitignore'),
            Phunky::verify($this->fileSystem)->read('/path/to/archer/res/git/gitignore'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.gitignore', Phunky::capture($actualIgnore))
        );

        $expected = array(
            '.gitignore'     => true,
            '.gitattributes' => true,
        );

        $this->assertSame($expectedIgnore, $actualIgnore);
        $this->assertSame($expected, $result);
    }
}
