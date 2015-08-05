<?php

namespace Icecave\Archer\Console\Command\Travis;

use Eloquent\Phony\Phpunit as x;
use Icecave\Archer\Console\Application;
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
        $this->fileSystem = x\mock('Icecave\Archer\FileSystem\FileSystem');
        $this->isolator = x\mock(Isolator::className());

        $this->application = new Application('/path/to/archer');

        $this->subject = new BuildCommand(
            $this->githubClient->mock(),
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
    }

    public function testConstructorDefaults()
    {
        $this->subject = new BuildCommand();

        $this->assertEquals(new GitHubClient(), $this->subject->githubClient());
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
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 0)->returns();

        $this->assertSame(0, $this->subject->run($this->input, $this->output->mock()));
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
        x\inOrder(
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
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 0)->returns();
        $this->isolator->is_dir('/path/to/project/src')->returns(false);

        $this->assertSame(0, $this->subject->run($this->input, $this->output->mock()));
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
        x\inOrder(
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
        $this->isolator->getenv('TRAVIS_PHP_VERSION')->returns('5.4');
        $this->isolator->passthru->setsArgument(1, 0)->returns();
        $this->isolator->passthru($expectedWoodhouseCommand, '*')->setsArgument(1, 222)->returns();

        $this->assertSame(222, $this->subject->run($this->input, $this->output->mock()));
        x\verify($this->githubClient)
            ->setAuthToken('b1a94b90073382b330f601ef198bb0729b0168aa')
            ->defaultBranch('Vendor', 'package');
        x\inOrder(
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer coverage', 255),
            $this->isolator->passthru->calledWith('/path/to/archer/bin/archer documentation', 255),
            $this->isolator->passthru->calledWith($expectedWoodhouseCommand, 255)
        );
    }
}
