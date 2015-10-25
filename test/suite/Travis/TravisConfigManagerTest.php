<?php

namespace Icecave\Archer\Travis;

use Icecave\Archer\Configuration\ComposerConfigurationReader;
use Icecave\Archer\Configuration\ConfigurationFileFinder;
use Icecave\Archer\FileSystem\FileSystem;
use PHPUnit_Framework_TestCase;
use Phunky;
use stdClass;

class TravisConfigManagerTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->fileSystem = Phunky::mock('Icecave\Archer\FileSystem\FileSystem');
        $this->fileFinder = Phunky::mock('Icecave\Archer\Configuration\ConfigurationFileFinder');
        $this->composerConfigReader = Phunky::mock('Icecave\Archer\Configuration\ComposerConfigurationReader');
        $this->isolator = Phunky::mock('Icecave\Archer\Support\Isolator');
        $this->manager = new TravisConfigManager(
            $this->fileSystem,
            $this->fileFinder,
            $this->composerConfigReader,
            $this->isolator
        );

        Phunky::when($this->fileFinder)->find(Phunky::anyParameters())->thenReturn('/real/path/to/template');

        $this->composerConfig = new stdClass();
        $this->composerConfig->require = new stdClass();
        $this->composerConfig->require->php = '>=5.3';

        Phunky::when($this->composerConfigReader)->read(Phunky::anyParameters())->thenReturn($this->composerConfig);
    }

    public function testConstructor()
    {
        $this->assertSame($this->fileSystem, $this->manager->fileSystem());
        $this->assertSame($this->fileFinder, $this->manager->fileFinder());
        $this->assertSame($this->composerConfigReader, $this->manager->composerConfigReader());
    }

    public function testConstructorDefaults()
    {
        $this->manager = new TravisConfigManager();

        $this->assertEquals(new FileSystem(), $this->manager->fileSystem());
        $this->assertEquals(new ConfigurationFileFinder(), $this->manager->fileFinder());
        $this->assertEquals(new ComposerConfigurationReader(), $this->manager->composerConfigReader());
    }

    public function testPublicKeyCache()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key')->thenReturn(false);

        $this->assertNull($this->manager->publicKeyCache('/path/to/project'));

        Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key');
    }

    public function testPublicKeyCacheExists()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.key')->thenReturn('<key data>');

        $this->assertSame('<key data>', $this->manager->publicKeyCache('/path/to/project'));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key'),
            Phunky::verify($this->fileSystem)->read('/path/to/project/.archer/travis.key')
        );
    }

    public function testSetPublicKeyCache()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key')->thenReturn(false);

        $this->assertTrue($this->manager->setPublicKeyCache('/path/to/project', '<key data>'));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.archer/travis.key', '<key data>')
        );
    }

    public function testSetPublicKeyCacheSame()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.key')->thenReturn('<key data>');

        $this->assertFalse($this->manager->setPublicKeyCache('/path/to/project', '<key data>'));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key'),
            Phunky::verify($this->fileSystem)->read('/path/to/project/.archer/travis.key')
        );

        Phunky::verify($this->fileSystem, Phunky::never())->write('/path/to/project/.archer/travis.key', '<key data>');
    }

    public function testSetPublicKeyCacheDelete()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.key')->thenReturn('<key data>');

        $this->assertTrue($this->manager->setPublicKeyCache('/path/to/project', null));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.key'),
            Phunky::verify($this->fileSystem)->read('/path/to/project/.archer/travis.key'),
            Phunky::verify($this->fileSystem)->delete('/path/to/project/.archer/travis.key')
        );
    }

    public function testSecureEnvironmentCache()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env')->thenReturn(false);

        $this->assertNull($this->manager->secureEnvironmentCache('/path/to/project'));
        Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env');
    }

    public function testSecureEnvironmentCacheExists()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.env')->thenReturn('<env data>');

        $this->assertSame('<env data>', $this->manager->secureEnvironmentCache('/path/to/project'));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env'),
            Phunky::verify($this->fileSystem)->read('/path/to/project/.archer/travis.env')
        );
    }

    public function testSetSecureEnvironmentCache()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env')->thenReturn(false);

        $this->assertTrue($this->manager->setSecureEnvironmentCache('/path/to/project', '<env data>'));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env'),
            Phunky::verify($this->fileSystem)->write('/path/to/project/.archer/travis.env', '<env data>')
        );
    }

    public function testSetSecureEnvironmentCacheSame()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.env')->thenReturn('<env data>');

        $this->assertFalse($this->manager->setSecureEnvironmentCache('/path/to/project', '<env data>'));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env'),
            Phunky::verify($this->fileSystem)->read('/path/to/project/.archer/travis.env')
        );
        Phunky::verify($this->fileSystem, Phunky::never())->write('/path/to/project/.archer/travis.env', '<env data>');
    }

    public function testSetSecureEnvironmentCacheDelete()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.env')->thenReturn('<env data>');

        $this->assertTrue($this->manager->setSecureEnvironmentCache('/path/to/project', null));
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env'),
            Phunky::verify($this->fileSystem)->read('/path/to/project/.archer/travis.env'),
            Phunky::verify($this->fileSystem)->delete('/path/to/project/.archer/travis.env')
        );
    }

    public function testUpdateConfig()
    {
        Phunky::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        $templateRead = Phunky::verify($this->fileSystem, Phunky::times(2))->read('/real/path/to/template');
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)
                ->copy('/path/to/archer/res/travis/travis.install.php', '/path/to/project/.archer/travis.install'),
            Phunky::verify($this->fileSystem)->chmod('/path/to/project/.archer/travis.install', 0755),
            Phunky::verify($this->fileFinder)->find(
                array('/path/to/project/test/travis-matrix.tpl.yml'),
                '/path/to/archer/res/travis/travis-matrix.tpl.yml'
            ),
            $templateRead,
            Phunky::verify($this->fileFinder)
                ->find(array('/path/to/project/test/travis.tpl.yml'), '/path/to/archer/res/travis/travis.tpl.yml'),
            $templateRead,
            Phunky::verify($this->fileSystem)->write(
                '/path/to/project/.travis.yml',
                '<travis: ["5.3", "5.4", "5.5", "5.6", "7.0", "hhvm"], 7.0, <matrix: [{"php": "hhvm"}]>, >'
            )
        );
        $this->assertFalse($result);
    }

    public function testUpdateConfigWithOAuth()
    {
        Phunky::when($this->fileSystem)->fileExists('/path/to/project/.archer/travis.env')->thenReturn(true);
        Phunky::when($this->fileSystem)->read('/path/to/project/.archer/travis.env')->thenReturn('<env data>');
        Phunky::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        $templateRead = Phunky::verify($this->fileSystem, Phunky::times(2))->read('/real/path/to/template');
        Phunky::inOrder(
            Phunky::verify($this->fileSystem)
                ->copy('/path/to/archer/res/travis/travis.install.php', '/path/to/project/.archer/travis.install'),
            Phunky::verify($this->fileSystem)->chmod('/path/to/project/.archer/travis.install', 0755),
            Phunky::verify($this->fileFinder)->find(
                array('/path/to/project/test/travis-matrix.tpl.yml'),
                '/path/to/archer/res/travis/travis-matrix.tpl.yml'
            ),
            $templateRead,
            Phunky::verify($this->fileFinder)
                ->find(array('/path/to/project/test/travis.tpl.yml'), '/path/to/archer/res/travis/travis.tpl.yml'),
            $templateRead,
            Phunky::verify($this->fileSystem)->write(
                '/path/to/project/.travis.yml',
                '<travis: ["5.3", "5.4", "5.5", "5.6", "7.0", "hhvm"], 7.0, <matrix: [{"php": "hhvm"}]>, - secure: "<env data>">'
            )
        );
        $this->assertTrue($result);
    }

    public function testUpdateConfigPhpVersionConstraint()
    {
        $this->composerConfig->require->php = '>=5.4';
        Phunky::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phunky::verify($this->composerConfigReader)->read('/path/to/project');
        Phunky::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.4", "5.5", "5.6", "7.0", "hhvm"], 7.0, <matrix: [{"php": "hhvm"}]>, >'
        );
    }

    /**
     * @group regression
     * @link https://github.com/IcecaveStudios/archer/issues/62
     */
    public function testUpdateConfigPhpVersionConstraintWithPatchVersion()
    {
        $this->composerConfig->require->php = '>=5.3.3';
        Phunky::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phunky::verify($this->composerConfigReader)->read('/path/to/project');
        Phunky::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.3", "5.4", "5.5", "5.6", "7.0", "hhvm"], 7.0, <matrix: [{"php": "hhvm"}]>, >'
        );
    }

    public function testUpdateConfigPhpVersionConstraintNoMatches()
    {
        $this->composerConfig->require->php = '>=99.0';
        Phunky::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phunky::verify($this->composerConfigReader)->read('/path/to/project');
        Phunky::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["7.0", "hhvm"], 7.0, <matrix: [{"php": "hhvm"}]>, >'
        );
    }

    public function testUpdateConfigPhpVersionNoConstraint()
    {
        unset($this->composerConfig->require->php);
        Phunky::when($this->fileSystem)->read('/real/path/to/template')
            ->thenReturn('<matrix: {allow-failure-versions}>')
            ->thenReturn('<travis: {php-versions}, {php-publish-version}, {matrix}, {token-env}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phunky::verify($this->composerConfigReader)->read('/path/to/project');
        Phunky::verify($this->fileSystem)->write(
            '/path/to/project/.travis.yml',
            '<travis: ["5.3", "5.4", "5.5", "5.6", "7.0", "hhvm"], 7.0, <matrix: [{"php": "hhvm"}]>, >'
        );
    }

    /**
     * @dataProvider getPublishVersionData
     */
    public function testUpdateConfigPhpPublishVersion($versionConstraint, $expectedVersion)
    {
        $this->composerConfig->require->php = $versionConstraint;
        Phunky::when($this->fileSystem)->read(Phunky::anyParameters())->thenReturn('<template content: {php-publish-version}>');
        $result = $this->manager->updateConfig('/path/to/archer', '/path/to/project');

        Phunky::verify($this->composerConfigReader)->read('/path/to/project');
        Phunky::verify($this->fileSystem)
            ->write('/path/to/project/.travis.yml', '<template content: ' . $expectedVersion . '>');
    }

    public function getPublishVersionData()
    {
        return array(
            array('>=5.3',  '7.0'),
            array('<=5.5',  '5.4'),
            array('>=6.0',  '7.0'),
            array('>=99.0', '7.0'),
        );
    }
}
