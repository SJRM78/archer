<?php
namespace Icecave\Archer\Console\Command;

use Icecave\Archer\Console\Application;
use Icecave\Archer\FileSystem\Exception\ReadException;
use PHPUnit_Framework_TestCase;
use Phunky;
use Symfony\Component\Console\Input\StringInput;

class UpdateCommandTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->dotFilesManager      = Phunky::mock('Icecave\Archer\Git\GitDotFilesManager');
        $this->configReader         = Phunky::mock('Icecave\Archer\Git\GitConfigReader');
        $this->configReaderFactory  = Phunky::mock('Icecave\Archer\Git\GitConfigReaderFactory');
        $this->travisClient         = Phunky::mock('Icecave\Archer\Travis\TravisClient');
        $this->travisConfigManager  = Phunky::mock('Icecave\Archer\Travis\TravisConfigManager');
        $this->processA             = Phunky::mock('Symfony\Component\Process\Process');
        $this->processB             = Phunky::mock('Symfony\Component\Process\Process');
        $this->processFactory       = Phunky::mock('Icecave\Archer\Process\ProcessFactory');

        $this->application = new Application('/path/to/archer');

        $this->helperSet = Phunky::mock('Symfony\Component\Console\Helper\HelperSet');
        $this->dialogHelper = Phunky::mock('Symfony\Component\Console\Helper\DialogHelper');
        $this->hiddenInputHelper = Phunky::mock('Icecave\Archer\Console\Helper\HiddenInputHelper');
        Phunky::when($this->helperSet)
            ->get('dialog')
            ->thenReturn($this->dialogHelper)
        ;
        Phunky::when($this->helperSet)
            ->get('hidden-input')
            ->thenReturn($this->hiddenInputHelper)
        ;

        $this->command = new UpdateCommand(
            $this->dotFilesManager,
            $this->configReaderFactory,
            $this->travisClient,
            $this->travisConfigManager,
            $this->processFactory
        );

        $this->command->setApplication($this->application);
        $this->command->setHelperSet($this->helperSet);

        $this->output = Phunky::mock('Symfony\Component\Console\Output\OutputInterface');

        Phunky::when($this->configReader)
            ->isGitHubRepository()
            ->thenReturn(true);

        Phunky::when($this->dotFilesManager)
            ->updateDotFiles(Phunky::anyParameters())
            ->thenReturn(array('.gitignore' => true, '.gitattributes' => false));

        Phunky::when($this->configReaderFactory)
            ->create(Phunky::anyParameters())
            ->thenReturn($this->configReader);

        Phunky::when($this->configReader)
            ->repositoryOwner()
            ->thenReturn('owner');

        Phunky::when($this->configReader)
            ->repositoryName()
            ->thenReturn('repo-name');

        Phunky::when($this->travisConfigManager)
            ->updateConfig(Phunky::anyParameters())
            ->thenReturn(true);

        Phunky::when($this->travisClient)
            ->publicKey(Phunky::anyParameters())
            ->thenReturn('<key data>');

        Phunky::when($this->travisClient)
            ->encryptEnvironment(Phunky::anyParameters())
            ->thenReturn('<env data>');

        Phunky::when($this->processFactory)
            ->createFromArray(Phunky::anyParameters())
            ->thenReturn($this->processA)
            ->thenReturn($this->processB);

        Phunky::when($this->processA)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(true);

        Phunky::when($this->processB)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(true);
    }

    public function testConstructor()
    {
        $this->assertSame($this->dotFilesManager, $this->command->dotFilesManager());
        $this->assertSame($this->configReaderFactory, $this->command->configReaderFactory());
        $this->assertSame($this->travisClient, $this->command->travisClient());
        $this->assertSame($this->travisConfigManager, $this->command->travisConfigManager());
        $this->assertSame($this->processFactory, $this->command->processFactory());
    }

    public function testConstructorDefaults()
    {
        $this->command = new UpdateCommand();

        $this->assertInstanceOf(
            'Icecave\Archer\Git\GitDotFilesManager',
            $this->command->dotFilesManager()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Git\GitConfigReaderFactory',
            $this->command->configReaderFactory()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Travis\TravisClient',
            $this->command->travisClient()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Travis\TravisConfigManager',
            $this->command->travisConfigManager()
        );
        $this->assertInstanceOf(
            'Icecave\Archer\Process\ProcessFactory',
            $this->command->processFactory()
        );
    }

    public function testExecute()
    {
        $input = new StringInput('update /path/to/project');

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );

        Phunky::verifyNoInteraction($this->travisClient);
    }

    public function testExecuteWithNonGitHubRepository()
    {
        Phunky::when($this->configReader)
            ->isGitHubRepository()
            ->thenReturn(false);

        $input = new StringInput('update /path/to/project');

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.')
        );

        Phunky::verifyNoInteraction($this->travisClient);
        Phunky::verifyNoInteraction($this->travisConfigManager);
        Phunky::verifyNoInteraction($this->processFactory);
    }

    public function testExecuteWithoutArtifactSupport()
    {
        Phunky::when($this->travisConfigManager)
            ->updateConfig(Phunky::anyParameters())
            ->thenReturn(false);

        $input = new StringInput('update /path/to/project');

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('<comment>Artifact publication is not available as no GitHub OAuth token has been configured.</comment>'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.')
        );

        Phunky::verifyNoInteraction($this->travisClient);
    }

    public function testExecuteWithAuthorizeExistingToken()
    {
        $input = new StringInput('update --authorize --username foo --password bar /path/to/project');

        Phunky::when($this->processA)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn("1584201: b1a94b90073382b330f601ef198bb0729b0168aa Archer (API) [repo] https://github.com/IcecaveStudios/archer\n")
        ;

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->processFactory)->createFromArray(Phunky::capture($processAArguments)),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->output)->writeln('Fetching public key for <info>owner/repo-name</info>.'),
            Phunky::verify($this->travisClient)->publicKey('owner', 'repo-name'),
            Phunky::verify($this->travisConfigManager)->setPublicKeyCache('/path/to/project', '<key data>'),
            Phunky::verify($this->output)->writeln('Encrypting OAuth token.'),
            Phunky::verify($this->travisClient)->encryptEnvironment('<key data>', 'b1a94b90073382b330f601ef198bb0729b0168aa'),
            Phunky::verify($this->travisConfigManager)->setSecureEnvironmentCache('/path/to/project', '<env data>'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );
        $this->assertSame(array(
            '/path/to/archer/bin/woodhouse',
            'github:list-auth',
            '--name',
            '/^Archer$/',
            '--url',
            '~^https://github\.com/IcecaveStudios/archer$~',
            '--username',
            'foo',
            '--password',
            'bar',
        ), $processAArguments);
    }

    public function testExecuteWithAuthorizeNewToken()
    {
        $input = new StringInput('update --authorize --username foo --password bar /path/to/project');

        Phunky::when($this->processA)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn("\n")
        ;
        Phunky::when($this->processB)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn("1584201: b1a94b90073382b330f601ef198bb0729b0168aa Archer (API) [repo] https://github.com/IcecaveStudios/archer\n")
        ;

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->processFactory)->createFromArray(Phunky::capture($processAArguments)->when($this->contains('github:list-auth'))),
            Phunky::verify($this->processFactory)->createFromArray(Phunky::capture($processBArguments)->when($this->contains('github:create-auth'))),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->output)->writeln('Fetching public key for <info>owner/repo-name</info>.'),
            Phunky::verify($this->travisClient)->publicKey('owner', 'repo-name'),
            Phunky::verify($this->travisConfigManager)->setPublicKeyCache('/path/to/project', '<key data>'),
            Phunky::verify($this->output)->writeln('Encrypting OAuth token.'),
            Phunky::verify($this->travisClient)->encryptEnvironment('<key data>', 'b1a94b90073382b330f601ef198bb0729b0168aa'),
            Phunky::verify($this->travisConfigManager)->setSecureEnvironmentCache('/path/to/project', '<env data>'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );
        $this->assertSame(array(
            '/path/to/archer/bin/woodhouse',
            'github:list-auth',
            '--name',
            '/^Archer$/',
            '--url',
            '~^https://github\.com/IcecaveStudios/archer$~',
            '--username',
            'foo',
            '--password',
            'bar',
        ), $processAArguments);
        $this->assertSame(array(
            '/path/to/archer/bin/woodhouse',
            'github:create-auth',
            '--name',
            'Archer',
            '--url',
            'https://github.com/IcecaveStudios/archer',
            '--username',
            'foo',
            '--password',
            'bar',
        ), $processBArguments);
    }

    public function testExecuteWithAuthorizeInteractiveCredentials()
    {
        $input = new StringInput('update --authorize /path/to/project');

        Phunky::when($this->dialogHelper)
            ->ask(Phunky::anyParameters())
            ->thenReturn('foo')
        ;
        Phunky::when($this->hiddenInputHelper)
            ->askHiddenResponse(Phunky::anyParameters())
            ->thenReturn('bar')
        ;
        Phunky::when($this->processA)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn("1584201: b1a94b90073382b330f601ef198bb0729b0168aa Archer (API) [repo] https://github.com/IcecaveStudios/archer\n")
        ;

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->processFactory)->createFromArray(Phunky::capture($processAArguments)),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->output)->writeln('Fetching public key for <info>owner/repo-name</info>.'),
            Phunky::verify($this->travisClient)->publicKey('owner', 'repo-name'),
            Phunky::verify($this->travisConfigManager)->setPublicKeyCache('/path/to/project', '<key data>'),
            Phunky::verify($this->output)->writeln('Encrypting OAuth token.'),
            Phunky::verify($this->travisClient)->encryptEnvironment('<key data>', 'b1a94b90073382b330f601ef198bb0729b0168aa'),
            Phunky::verify($this->travisConfigManager)->setSecureEnvironmentCache('/path/to/project', '<env data>'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );
        $this->assertSame(array(
            '/path/to/archer/bin/woodhouse',
            'github:list-auth',
            '--name',
            '/^Archer$/',
            '--url',
            '~^https://github\.com/IcecaveStudios/archer$~',
            '--username',
            'foo',
            '--password',
            'bar',
        ), $processAArguments);
    }

    public function testExecuteWithAuthorizeFailureMultipleAuthorizations()
    {
        $input = new StringInput('update --authorize --username foo --password bar /path/to/project');

        Phunky::when($this->processA)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn(
                "1584201: b1a94b90073382b330f601ef198bb0729b0168aa Archer (API) [repo] https://github.com/IcecaveStudios/archer\n" .
                "1584202: c1a94b90073382b330f601ef198bb0729b0168aa Archer (API) [repo] https://github.com/IcecaveStudios/archer\n"
            )
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Mutiple Archer GitHub authorizations found. Delete redundant authorizations before continuing.'
        );
        $this->command->run($input, $this->output);
    }

    public function testExecuteWithAuthorizeFailureIncorrectScope()
    {
        $input = new StringInput('update --authorize --username foo --password bar /path/to/project');

        Phunky::when($this->processA)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn("1584201: b1a94b90073382b330f601ef198bb0729b0168aa Archer (API) [user, repo] https://github.com/IcecaveStudios/archer\n")
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Archer GitHub authorization has incorrect scope. Expected [repo], but actual token scope is [user, repo].'
        );
        $this->command->run($input, $this->output);
    }

    public function testExecuteWithAuthorizeFailureParseError()
    {
        $input = new StringInput('update --authorize --username foo --password bar /path/to/project');

        Phunky::when($this->processA)
            ->getOutput(Phunky::anyParameters())
            ->thenReturn('baz')
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Unable to parse authorization token.'
        );
        $this->command->run($input, $this->output);
    }

    public function testExecuteWithAuthorizeFailureWoodhouseError()
    {
        $input = new StringInput('update --authorize --username foo --password bar /path/to/project');

        Phunky::when($this->processA)
            ->isSuccessful(Phunky::anyParameters())
            ->thenReturn(false)
        ;

        $this->setExpectedException(
            'RuntimeException',
            'Failed to execute authorization management command (github:list-auth).'
        );
        $this->command->run($input, $this->output);
    }

    public function testExecuteWithSuppliedToken()
    {
        $input = new StringInput('update --auth-token b1a94b90073382b330f601ef198bb0729b0168aa /path/to/project');

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->output)->writeln('Fetching public key for <info>owner/repo-name</info>.'),
            Phunky::verify($this->travisClient)->publicKey('owner', 'repo-name'),
            Phunky::verify($this->travisConfigManager)->setPublicKeyCache('/path/to/project', '<key data>'),
            Phunky::verify($this->output)->writeln('Encrypting OAuth token.'),
            Phunky::verify($this->travisClient)->encryptEnvironment('<key data>', 'b1a94b90073382b330f601ef198bb0729b0168aa'),
            Phunky::verify($this->travisConfigManager)->setSecureEnvironmentCache('/path/to/project', '<env data>'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );
    }

    public function testExecuteWithSuppliedTokenAndExistingKey()
    {
        Phunky::when($this->travisConfigManager)
            ->publicKeyCache(Phunky::anyParameters())
            ->thenReturn('<cached key data>');

        $input = new StringInput('update --auth-token b1a94b90073382b330f601ef198bb0729b0168aa /path/to/project');

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->output)->writeln('Encrypting OAuth token.'),
            Phunky::verify($this->travisClient)->encryptEnvironment('<cached key data>', 'b1a94b90073382b330f601ef198bb0729b0168aa'),
            Phunky::verify($this->travisConfigManager)->setSecureEnvironmentCache('/path/to/project', '<env data>'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );
    }

    public function testExecuteWithUpdatePublicKey()
    {
        $input = new StringInput('update --auth-token b1a94b90073382b330f601ef198bb0729b0168aa --update-public-key /path/to/project');

        $this->command->run($input, $this->output);

        Phunky::inOrder(
            Phunky::verify($this->dotFilesManager)->updateDotFiles('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.gitignore</info>.'),
            Phunky::verify($this->configReader)->repositoryOwner(),
            Phunky::verify($this->configReader)->repositoryName(),
            Phunky::verify($this->travisConfigManager)->publicKeyCache('/path/to/project'),
            Phunky::verify($this->output)->writeln('Fetching public key for <info>owner/repo-name</info>.'),
            Phunky::verify($this->travisClient)->publicKey('owner', 'repo-name'),
            Phunky::verify($this->travisConfigManager)->setPublicKeyCache('/path/to/project', '<key data>'),
            Phunky::verify($this->travisConfigManager)->updateConfig('/path/to/archer', '/path/to/project'),
            Phunky::verify($this->output)->writeln('Updated <info>.travis.yml</info>.'),
            Phunky::verify($this->output)->writeln('Configuration updated successfully.'),
            Phunky::verify($this->output)->writeln('')
        );
    }

    public function testExecuteFailureUnsyncedRepo()
    {
        $input = new StringInput('update --auth-token b1a94b90073382b330f601ef198bb0729b0168aa --update-public-key /path/to/project');

        Phunky::when($this->travisClient)
            ->publicKey(Phunky::anyParameters())
            ->thenThrow(new ReadException('foo'));

        $this->setExpectedException(
            'RuntimeException',
            'Unable to retrieve the public key for repository owner/repo-name. Check that the repository has been synced to Travis CI.'
        );
        $this->command->run($input, $this->output);
    }

    public function testExecuteWithInvalidToken()
    {
        $input = new StringInput('update --auth-token XXX');

        $exitCode = $this->command->run($input, $this->output);

        $this->assertSame(1, $exitCode);

        Phunky::inOrder(
            Phunky::verify($this->output)->writeln('Invalid GitHub OAuth token <comment>"XXX"</comment>.'),
            Phunky::verify($this->output)->writeln('')
        );
    }

    public function testExecuteWithUpdatePublicKeyAndNoToken()
    {
        $input = new StringInput('update --update-public-key');

        $exitCode = $this->command->run($input, $this->output);

        $this->assertSame(1, $exitCode);

        Phunky::inOrder(
            Phunky::verify($this->output)->writeln('Can not update public key without --authorize or --auth-token.'),
            Phunky::verify($this->output)->writeln('')
        );
    }
}
