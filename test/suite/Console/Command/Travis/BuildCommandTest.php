<?php

namespace Icecave\Archer\Console\Command\Travis;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\Console\Application;
use Icecave\Archer\Coveralls\CoverallsClient;
use Icecave\Archer\FileSystem\FileSystem;
use Icecave\Archer\GitHub\GitHubClient;
use Icecave\Archer\Support\Isolator;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Input\StringInput;

class BuildCommandTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->githubClient = x\mock('Icecave\Archer\GitHub\GitHubClient');
        $this->coverallsClient = x\mock('Icecave\Archer\Coveralls\CoverallsClient');
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->isolator = x\mock(Isolator::className());

        $this->application = new Application('/path/to/archer');

        $this->subject = new BuildCommand(
            $this->githubClient->mock(),
            $this->coverallsClient->mock(),
            $this->fileSystem->mock(),
            $this->isolator->mock()
        );

        $this->subject->setApplication($this->application);

        $this->input = new StringInput('travis:build /path/to/project');
        $this->output = x\mock('Symfony\Component\Console\Output\OutputInterface');

        $this->githubClient->defaultBranch->returns('master');
        $this->isolator->getenv('TRAVIS_BRANCH')->returns('master');
        $this->isolator->getenv('TRAVIS_BUILD_NUMBER')->returns('543');
        $this->isolator->getenv('TRAVIS_REPO_SLUG')->returns('Vendor/package');
        $this->isolator->getenv('ARCHER_PUBLISH_VERSION')->returns('5.4');
        $this->isolator->getenv('ARCHER_TOKEN')->returns('b1a94b90073382b330f601ef198bb0729b0168aa');
        $this->isolator->is_dir('/path/to/project/src')->returns(true);
        $this->isolator->passthru->setsArgument(1, 0)->returns();
    }

    public function testConstructor()
    {
        $this->assertSame($this->githubClient->mock(), $this->subject->githubClient());
        $this->assertSame($this->coverallsClient->mock(), $this->subject->coverallsClient());
    }

    public function testConstructorDefaults()
    {
        $this->subject = new BuildCommand();

        $this->assertEquals(new GitHubClient(), $this->subject->githubClient());
        $this->assertEquals(new CoverallsClient(), $this->subject->coverallsClient());
    }

    public function testExecute()
    {
        $this->isolator->passthru->setsArgument(1, 123)->returns();

        $this->assertSame(123, $this->subject->run($this->input, $this->output->mock()));
        $this->isolator->passthru->calledWith('/path/to/archer/bin/archer test', 255);
        $this->githubClient->setUserAgent->calledWith('Archer/' . $this->application->getVersion());
    }

    public function testExecuteWithPublishVersionButWrongBranch()
    {
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->getenv('TRAVIS_BRANCH')->returns('feature/some-thing');
        $this->isolator->passthru->setsArgument(1, 123)->returns();

        $this->assertSame(123, $this->subject->run($this->input, $this->output->mock()));
        $this->isolator->passthru->calledWith('/path/to/archer/bin/archer test', 255);
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
    }

    public function testExecuteWithPublishVersionButNoToken()
    {
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->getenv('ARCHER_TOKEN')->returns(false);
        $this->isolator->passthru->setsArgument(1, 123)->returns();

        $this->assertSame(123, $this->subject->run($this->input, $this->output->mock()));
        $this->isolator->passthru->calledWith('/path/to/archer/bin/archer test', 255);
        $this->githubClient->setAuthToken->never()->called();
    }

    public function testExecuteWithPublishVersionButIsPullRequest()
    {
        $this->isolator->getenv('TRAVIS_PULL_REQUEST')->returns('100');
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 123)->returns();

        $this->assertSame(123, $this->subject->run($this->input, $this->output->mock()));
        $this->isolator->passthru->calledWith('/path/to/archer/bin/archer test', 255);
        $this->githubClient->setAuthToken->never()->called();
    }

    public function testExecuteWithPublish()
    {
        $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
        $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
        $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
        $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
        $expectedWoodhouseCommand .= ' --no-interaction';
        $expectedWoodhouseCommand .= ' --verbose';
        $expectedWoodhouseCommand .= ' --coverage-image artifacts/images/coverage.png';
        $expectedWoodhouseCommand .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
        $expectedWoodhouseCommand .= ' --image-theme buckler/buckler';
        $this->coverallsClient->exists('Vendor', 'package')->returns(false);
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 0)->returns();

        $this->assertSame(0, $this->subject->run($this->input, $this->output->mock()));
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
        x\inOrder(
            $this->output->write->calledWith('Checking for Coveralls... '),
            $this->coverallsClient->exists->calledWith('Vendor', 'package'),
            $this->output->writeln->calledWith('not enabled.'),
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer coverage', 255),
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer documentation', 255),
            $this->isolator->passthru->calledWith($expectedWoodhouseCommand, 255)
        );
    }

    public function testExecuteWithPublishAndNoSource()
    {
        $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
        $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
        $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
        $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
        $expectedWoodhouseCommand .= ' --no-interaction';
        $expectedWoodhouseCommand .= ' --verbose';
        $expectedWoodhouseCommand .= ' --coverage-image artifacts/images/coverage.png';
        $expectedWoodhouseCommand .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
        $expectedWoodhouseCommand .= ' --image-theme buckler/buckler';
        $this->coverallsClient->exists('Vendor', 'package')->returns(false);
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 0)->returns();
        $this->isolator->is_dir('/path/to/project/src')->returns(false);

        $this->assertSame(0, $this->subject->run($this->input, $this->output->mock()));
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
        x\inOrder(
            $this->output->write->calledWith('Checking for Coveralls... '),
            $this->coverallsClient->exists->calledWith('Vendor', 'package'),
            $this->output->writeln->calledWith('not enabled.'),
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer coverage', 255),
            $this->isolator->passthru->calledWith($expectedWoodhouseCommand, 255)
        );
        $this->isolator->passthru->never()->calledWith('/path/to/archer/bin/archer documentation', '*');
    }

    public function testExecuteWithPublishErrorCode()
    {
        $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
        $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
        $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
        $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
        $expectedWoodhouseCommand .= ' --no-interaction';
        $expectedWoodhouseCommand .= ' --verbose';
        $expectedWoodhouseCommand .= ' --coverage-image artifacts/images/coverage.png';
        $expectedWoodhouseCommand .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
        $expectedWoodhouseCommand .= ' --image-theme buckler/buckler';
        $this->coverallsClient->exists('Vendor', 'package')->returns(false);
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 0)->returns();
        $this->isolator->passthru($expectedWoodhouseCommand, '*')->setsArgument(1, 222)->returns();

        $this->assertSame(222, $this->subject->run($this->input, $this->output->mock()));
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
        x\inOrder(
            $this->output->write->calledWith('Checking for Coveralls... '),
            $this->coverallsClient->exists->calledWith('Vendor', 'package'),
            $this->output->writeln->calledWith('not enabled.'),
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer coverage', 255),
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer documentation', 255),
            $this->isolator->passthru->calledWith($expectedWoodhouseCommand, 255)
        );
    }

    // public function testExecuteWithPublishAndCoveralls()
    // {
    //     $expectedTestCommand = '/path/to/archer/bin/archer coverage';
    //     $expectedDocumentationCommand = '/path/to/archer/bin/archer documentation';

    //     $expectedCoverallsCommand = '/path/to/project/vendor/bin/coveralls --config';
    //     $expectedCoverallsCommand .= " '/path/to/project/.coveralls.yml'";

    //     $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
    //     $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
    //     $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
    //     $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
    //     $expectedWoodhouseCommand .= ' --no-interaction';
    //     $expectedWoodhouseCommand .= ' --verbose';

    //     Phunky::when($this->coverallsClient)
    //         ->exists('Vendor', 'package')
    //         ->thenReturn(true);

    //     Phunky::when($this->isolator)
    //         ->getenv('TRAVIS_PHP_VERSION')
    //         ->thenReturn('5.4');

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedTestCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedDocumentationCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedCoverallsCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedWoodhouseCommand,
    //             Phunky::setReference(222)
    //         )
    //         ->thenReturn(null);

    //     $exitCode = $this->subject->run($this->input, $this->output->mock());

    //     Phunky::inOrder(
    //         Phunky::verify($this->githubClient)->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa'),
    //         Phunky::verify($this->githubClient)->defaultBranch('Vendor', 'package'),
    //         Phunky::verify($this->output)->write('Checking for Coveralls... '),
    //         Phunky::verify($this->coverallsClient)->exists('Vendor', 'package'),
    //         Phunky::verify($this->output)->writeln('enabled.'),
    //         Phunky::verify($this->isolator)->passthru($expectedTestCommand, 255),
    //         Phunky::verify($this->output)->write('Publishing Coveralls data... '),
    //         Phunky::verify($this->isolator)->copy('/path/to/archer/res/coveralls/coveralls.yml', '/path/to/project/.coveralls.yml'),
    //         Phunky::verify($this->isolator)->passthru($expectedCoverallsCommand, 255),
    //         Phunky::verify($this->output)->writeln('done.'),
    //         Phunky::verify($this->isolator)->passthru($expectedDocumentationCommand, 255),
    //         Phunky::verify($this->fileSystem)->delete('/path/to/project/artifacts/tests'),
    //         Phunky::verify($this->isolator)->passthru($expectedWoodhouseCommand, 255)
    //     );

    //     $this->assertSame(222, $exitCode);
    // }

    // public function testExecuteWithAlwaysPublishFlag()
    // {
    //     $expectedTestCommand = '/path/to/archer/bin/archer coverage';
    //     $expectedDocumentationCommand = '/path/to/archer/bin/archer documentation';

    //     $expectedCoverallsCommand = '/path/to/project/vendor/bin/coveralls --config';
    //     $expectedCoverallsCommand .= " '/path/to/project/.coveralls.yml'";

    //     $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
    //     $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
    //     $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
    //     $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
    //     $expectedWoodhouseCommand .= ' --no-interaction';
    //     $expectedWoodhouseCommand .= ' --verbose';
    //     $expectedWoodhouseCommand .= ' --coverage-image artifacts/images/coverage.png';
    //     $expectedWoodhouseCommand .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
    //     $expectedWoodhouseCommand .= ' --image-theme buckler/buckler';

    //     Phunky::when($this->coverallsClient)
    //         ->exists('Vendor', 'package')
    //         ->thenReturn(true);

    //     Phunky::when($this->isolator)
    //         ->getenv('TRAVIS_PHP_VERSION')
    //         ->thenReturn('5.4');

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedTestCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedDocumentationCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedCoverallsCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedWoodhouseCommand,
    //             Phunky::setReference(222)
    //         )
    //         ->thenReturn(null);

    //     $input = new StringInput('travis:build /path/to/project --always-publish');
    //     $exitCode = $this->subject->run($input, $this->output);

    //     Phunky::inOrder(
    //         Phunky::verify($this->githubClient)->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa'),
    //         Phunky::verify($this->githubClient)->defaultBranch('Vendor', 'package'),
    //         Phunky::verify($this->output)->write('Checking for Coveralls... '),
    //         Phunky::verify($this->coverallsClient)->exists('Vendor', 'package'),
    //         Phunky::verify($this->output)->writeln('enabled.'),
    //         Phunky::verify($this->isolator)->passthru($expectedTestCommand, 255),
    //         Phunky::verify($this->output)->write('Publishing Coveralls data... '),
    //         Phunky::verify($this->isolator)->copy('/path/to/archer/res/coveralls/coveralls.yml', '/path/to/project/.coveralls.yml'),
    //         Phunky::verify($this->isolator)->passthru($expectedCoverallsCommand, 255),
    //         Phunky::verify($this->output)->writeln('done.'),
    //         Phunky::verify($this->isolator)->passthru($expectedDocumentationCommand, 255),
    //         Phunky::verify($this->isolator)->passthru($expectedWoodhouseCommand, 255)
    //     );

    //     $this->assertSame(222, $exitCode);
    // }
    // public function testExecuteWithPublishAndTestFailure()
    // {
    //     $expectedTestCommand = '/path/to/archer/bin/archer coverage';
    //     $expectedDocumentationCommand = '/path/to/archer/bin/archer documentation';

    //     $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
    //     $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
    //     $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
    //     $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
    //     $expectedWoodhouseCommand .= ' --no-interaction';
    //     $expectedWoodhouseCommand .= ' --verbose';
    //     $expectedWoodhouseCommand .= ' --coverage-image artifacts/images/coverage.png';
    //     $expectedWoodhouseCommand .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
    //     $expectedWoodhouseCommand .= ' --image-theme buckler/buckler';

    //     Phunky::when($this->coverallsClient)
    //         ->exists('Vendor', 'package')
    //         ->thenReturn(false);

    //     Phunky::when($this->isolator)
    //         ->getenv('TRAVIS_PHP_VERSION')
    //         ->thenReturn('5.4');

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedTestCommand,
    //             Phunky::setReference(111)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedDocumentationCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedWoodhouseCommand,
    //             Phunky::setReference(222)
    //         )
    //         ->thenReturn(null);

    //     $exitCode = $this->subject->run($this->input, $this->output->mock());

    //     Phunky::inOrder(
    //         Phunky::verify($this->githubClient)->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa'),
    //         Phunky::verify($this->githubClient)->defaultBranch('Vendor', 'package'),
    //         Phunky::verify($this->output)->write('Checking for Coveralls... '),
    //         Phunky::verify($this->coverallsClient)->exists('Vendor', 'package'),
    //         Phunky::verify($this->output)->writeln('not enabled.'),
    //         Phunky::verify($this->isolator)->passthru($expectedTestCommand, 255),
    //         Phunky::verify($this->isolator)->passthru($expectedDocumentationCommand, 255),
    //         Phunky::verify($this->isolator)->passthru($expectedWoodhouseCommand, 255)
    //     );

    //     $this->assertSame(111, $exitCode);
    // }

    // public function testExecuteWithPublishAndDocumentationFailure()
    // {
    //     $expectedTestCommand = '/path/to/archer/bin/archer coverage';
    //     $expectedDocumentationCommand = '/path/to/archer/bin/archer documentation';

    //     $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
    //     $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
    //     $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
    //     $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
    //     $expectedWoodhouseCommand .= ' --no-interaction';
    //     $expectedWoodhouseCommand .= ' --verbose';
    //     $expectedWoodhouseCommand .= ' --coverage-image artifacts/images/coverage.png';
    //     $expectedWoodhouseCommand .= ' --coverage-phpunit artifacts/tests/coverage/coverage.txt';
    //     $expectedWoodhouseCommand .= ' --image-theme buckler/buckler';

    //     Phunky::when($this->coverallsClient)
    //         ->exists('Vendor', 'package')
    //         ->thenReturn(false);

    //     Phunky::when($this->isolator)
    //         ->getenv('TRAVIS_PHP_VERSION')
    //         ->thenReturn('5.4');

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedTestCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedDocumentationCommand,
    //             Phunky::setReference(111)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedWoodhouseCommand,
    //             Phunky::setReference(222)
    //         )
    //         ->thenReturn(null);

    //     $exitCode = $this->subject->run($this->input, $this->output->mock());

    //     Phunky::inOrder(
    //         Phunky::verify($this->githubClient)->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa'),
    //         Phunky::verify($this->githubClient)->defaultBranch('Vendor', 'package'),
    //         Phunky::verify($this->output)->write('Checking for Coveralls... '),
    //         Phunky::verify($this->coverallsClient)->exists('Vendor', 'package'),
    //         Phunky::verify($this->output)->writeln('not enabled.'),
    //         Phunky::verify($this->isolator)->passthru($expectedTestCommand, 255),
    //         Phunky::verify($this->isolator)->passthru($expectedDocumentationCommand, 255),
    //         Phunky::verify($this->isolator)->passthru($expectedWoodhouseCommand, 255)
    //     );

    //     $this->assertSame(111, $exitCode);
    // }

    // public function testExecuteWithPublishAndCoverallsPublishFailure()
    // {
    //     $expectedTestCommand = '/path/to/archer/bin/archer coverage';
    //     $expectedDocumentationCommand = '/path/to/archer/bin/archer documentation';

    //     $expectedCoverallsCommand = '/path/to/project/vendor/bin/coveralls --config';
    //     $expectedCoverallsCommand .= " '/path/to/project/.coveralls.yml'";

    //     $expectedWoodhouseCommand  = "/path/to/archer/bin/woodhouse publish 'Vendor/package'";
    //     $expectedWoodhouseCommand .= ' /path/to/project/artifacts:artifacts';
    //     $expectedWoodhouseCommand .= ' --message "Publishing artifacts from build 543."';
    //     $expectedWoodhouseCommand .= ' --auth-token-env ARCHER_TOKEN';
    //     $expectedWoodhouseCommand .= ' --no-interaction';
    //     $expectedWoodhouseCommand .= ' --verbose';

    //     Phunky::when($this->coverallsClient)
    //         ->exists('Vendor', 'package')
    //         ->thenReturn(true);

    //     Phunky::when($this->isolator)
    //         ->getenv('TRAVIS_PHP_VERSION')
    //         ->thenReturn('5.4');

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedTestCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedDocumentationCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedCoverallsCommand,
    //             Phunky::setReference(222)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedWoodhouseCommand,
    //             Phunky::setReference(333)
    //         )
    //         ->thenReturn(null);

    //     $exitCode = $this->subject->run($this->input, $this->output->mock());

    //     Phunky::inOrder(
    //         Phunky::verify($this->githubClient)->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa'),
    //         Phunky::verify($this->githubClient)->defaultBranch('Vendor', 'package'),
    //         Phunky::verify($this->output)->write('Checking for Coveralls... '),
    //         Phunky::verify($this->coverallsClient)->exists('Vendor', 'package'),
    //         Phunky::verify($this->output)->writeln('enabled.'),
    //         Phunky::verify($this->isolator)->passthru($expectedTestCommand, 255),
    //         Phunky::verify($this->output)->write('Publishing Coveralls data... '),
    //         Phunky::verify($this->isolator)->copy('/path/to/archer/res/coveralls/coveralls.yml', '/path/to/project/.coveralls.yml'),
    //         Phunky::verify($this->isolator)->passthru($expectedCoverallsCommand, 255),
    //         Phunky::verify($this->output)->writeln('failed.'),
    //         Phunky::verify($this->isolator)->passthru($expectedDocumentationCommand, 255),
    //         Phunky::verify($this->isolator)->passthru($expectedWoodhouseCommand, 255)
    //     );

    //     $this->assertSame(222, $exitCode);
    // }

    // public function testExecuteWithCoverallsButNoPublish()
    // {
    //     $expectedTestCommand = '/path/to/archer/bin/archer coverage';

    //     $expectedCoverallsCommand = '/path/to/project/vendor/bin/coveralls --config';
    //     $expectedCoverallsCommand .= " '/path/to/project/.coveralls.yml'";

    //     Phunky::when($this->coverallsClient)
    //         ->exists('Vendor', 'package')
    //         ->thenReturn(true);

    //     Phunky::when($this->isolator)
    //         ->getenv('TRAVIS_PHP_VERSION')
    //         ->thenReturn('5.4');

    //     Phunky::when($this->isolator)
    //         ->getenv('ARCHER_TOKEN')
    //         ->thenReturn('');

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedTestCommand,
    //             Phunky::setReference(0)
    //         )
    //         ->thenReturn(null);

    //     Phunky::when($this->isolator)
    //         ->passthru(
    //             $expectedCoverallsCommand,
    //             Phunky::setReference(111)
    //         )
    //         ->thenReturn(null);

    //     $exitCode = $this->subject->run($this->input, $this->output->mock());

    //     Phunky::inOrder(
    //         Phunky::verify($this->output)->write('Checking for Coveralls... '),
    //         Phunky::verify($this->coverallsClient)->exists('Vendor', 'package'),
    //         Phunky::verify($this->output)->writeln('enabled.'),
    //         Phunky::verify($this->isolator)->passthru($expectedTestCommand, 255),
    //         Phunky::verify($this->output)->write('Publishing Coveralls data... '),
    //         Phunky::verify($this->isolator)->copy('/path/to/archer/res/coveralls/coveralls.yml', '/path/to/project/.coveralls.yml'),
    //         Phunky::verify($this->isolator)->passthru($expectedCoverallsCommand, 255),
    //         Phunky::verify($this->output)->writeln('failed.')
    //     );

    //     $this->assertSame(111, $exitCode);
    // }
}
