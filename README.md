# Archer

[![Build Status]](https://travis-ci.org/IcecaveStudios/archer)
[![Test Coverage]](https://codecov.io/github/IcecaveStudios/archer)
[![SemVer]](http://semver.org)

[build status]: http://img.shields.io/travis/IcecaveStudios/archer/develop.svg?style=flat-square
[test coverage]: https://img.shields.io/codecov/c/github/IcecaveStudios/archer/develop.svg?style=flat-square
[semver]: http://img.shields.io/:semver-1.3.2-brightgreen.svg?style=flat-square

*Archer* is a library for standardizing PHP unit testing, continuous
integration, and documentation behavior across multiple projects using a
[convention-over-configuration] approach. It brings together several high
quality libraries to help improve the quality of a project's test suite and
reporting tools.

The use of *Archer* requires that the host project conforms to a set of
[conventions]. In return, it provides the following benefits:

- Configuration-free, best-practice [unit testing] and [test coverage reports]
  with [PHPUnit] and [Xdebug].
- Configuration-free generation of [API documentation] using [Sami].
- Automated [configuration] of repository and [Travis CI].
- [Build artifact publication] to project [GitHub Pages].
- Configuration-free integration with [Codecov] for excellent test coverage
  metrics.

[api documentation]: #api-documentation
[configuration]: #automated-configuration
[convention-over-configuration]: http://en.wikipedia.org/wiki/Convention_over_configuration
[conventions]: #requirements
[sami]: https://github.com/fabpot/Sami
[unit testing]: #unit-testing

## Requirements

- Projects **must** use [Composer].
- Projects **must** conform to the [expected project layout].
- Projects **must** use a PHP namespace with a vendor and project prefix, for
  example `Icecave\Archer`.
- [PHPUnit] **must** be installed as a dev dependency, or available in the
  user's PATH.
- The [Xdebug] PHP extension is required for [test coverage reports].
- The [openssl] PHP extension is required for [build artifact publication].

[composer]: http://getcomposer.org/
[expected project layout]: #expected-project-layout
[openssl]: http://php.net/openssl

## Getting started

### Expected project layout

*Archer* expects projects to be laid out using the directory structure below:

    .                 # Project / git root
    ├── src/          # PHP source files
    └── test/
        └── suite/    # PHPUnit test suite

### Composer configuration

Add [icecave/archer] to the project's `composer.json` file as a development
dependency:

    composer require icecave/archer:^1 --dev

This will create a new `composer.json` file if it does not exist, and update all
dependencies. The `archer` executable should now be available at
`vendor/bin/archer`:

    vendor/bin/archer --version

[icecave/archer]: https://packagist.org/packages/icecave/archer

### Initializing Archer

Set up the git repository and Travis CI configuration files using the update
command:

    vendor/bin/archer update --authorize

This command will prompt for a GitHub username and password in order to
authorize *Archer* to publish build artifacts from [Travis CI]. For more
information about security, please see the [Security section] of the [Woodhouse]
documentation.

The changes made by *Archer* should now be committed to the repository, and
pushed back to GitHub:

    git add -A
    git commit -m 'Adding Archer integration.'
    git push

And that's it! The project is now set up to use *Archer*.

[security section]: https://github.com/IcecaveStudios/woodhouse#security
[woodhouse]: https://github.com/IcecaveStudios/woodhouse

## Unit testing

*Archer* provides a wrapper around [PHPUnit] for unit testing support. Arguments
and options to this command are passed on to PHPUnit. The test suite can be run
by executing `archer` with no parameters:

    vendor/bin/archer

Running `archer` with no arguments is equivalent to `archer test`.

#### Test command usage

    vendor/bin/archer test                   # canonical form
    vendor/bin/archer                        # shortcut to 'test' (no arguments or options allowed)
    vendor/bin/archer t                      # shortcut to 'test'
    vendor/bin/archer t --stop-on-failure    # arguments are forwarded to PHPUnit

#### Example test command output

```console
$ vendor/bin/archer
Using PHP: /path/to/php
Using PHPUnit: /path/to/phpunit
PHPUnit 4.7.7 by Sebastian Bergmann and contributors.

Runtime:        PHP 5.6.11 with Xdebug 2.3.3
Configuration:  /path/to/project/vendor/icecave/archer/res/phpunit/phpunit.xml

...............................................................  63 / 414 ( 15%)
............................................................... 126 / 414 ( 30%)
............................................................... 189 / 414 ( 45%)
............................................................... 252 / 414 ( 60%)
............................................................... 315 / 414 ( 76%)
............................................................... 378 / 414 ( 91%)
....................................

Time: 1 second, Memory: 14.00Mb

OK (414 tests, 915 assertions)
```

#### Autoloading of test fixture classes

For some tests a test-double is not suitable and a fixture class needs to be
used. For this reason, *Archer* will, at test time only, autoload any classes
that follow the [PSR-0] standard from the `test/src` directory, in addition the
classes normally loaded by Composer.

[psr-0]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md

## Test coverage reports

Test coverage reports provide a useful metric for determining how thoroughly a
project is tested. The test coverage report can be generated by executing
`archer coverage`. The HTML test coverage report will be generated in the
`artifacts/tests/coverage` directory, which can be opened in any web browser.

#### Coverage command usage

    vendor/bin/archer coverage               # canonical form
    vendor/bin/archer c                      # shortcut to 'coverage'
    vendor/bin/archer c --stop-on-failure    # passing arguments to PHPUnit

#### Example coverage command output

```console
$ vendor/bin/archer c
Using PHP: /path/to/php
Using PHPUnit: /path/to/phpunit
PHPUnit 4.7.7 by Sebastian Bergmann and contributors.

Runtime:        PHP 5.6.11 with Xdebug 2.3.3
Configuration:  /path/to/project/vendor/icecave/archer/res/phpunit/phpunit.coverage.xml

...............................................................  63 / 414 ( 15%)
............................................................... 126 / 414 ( 30%)
............................................................... 189 / 414 ( 45%)
............................................................... 252 / 414 ( 60%)
............................................................... 315 / 414 ( 76%)
............................................................... 378 / 414 ( 91%)
....................................

Time: 1 second, Memory: 17.00Mb

OK (414 tests, 915 assertions)

Generating code coverage report in HTML format ... done
```

#### Example test coverage report

![Example Archer coverage report](http://icecavestudios.github.io/archer/doc/img/example-coverage-report.png)

For a live example, see the [test coverage report] for *Archer's* own test
suite.

[test coverage report]: http://icecavestudios.github.io/archer/artifacts/tests/coverage/

#### Codecov integration

*Archer* provides support for [Codecov]. There is nothing to configure; *Archer*
will publish test coverage information to Codecov when a build occurs on
[Travis CI].

See [Archer on Codecov] for an example.

[archer on codecov]: https://codecov.io/github/IcecaveStudios/archer

## API documentation

API documentation provides a useful addition to any project's overall
documentation strategy. Static, searchable API documentation can be generated by
executing `archer documentation`. The API documentation will be generated in the
`artifacts/documentation/api` directory, which can be opened in any web browser.

Note that the search panel of the API documentation uses AJAX, which browsers
often disable for local files. A workaround is to use PHP's
[built-in web server]:

    php -S localhost:8000 -t artifacts/documentation/api

The documentation is now available at [http://localhost:8000/].

[built-in web server]: http://php.net/features.commandline.webserver
[http://localhost:8000/]: http://localhost:8000/

#### Documentation command usage

    vendor/bin/archer documentation    # canonical form
    vendor/bin/archer d                # shortcut to 'documentation'

#### Example API documentation

![Example Archer API documentation](http://icecavestudios.github.io/archer/doc/img/example-api-documentation.png)

For a live example see the [API documentation][pathogen api documentation] for
[Pathogen].

[pathogen]: https://github.com/eloquent/pathogen
[pathogen api documentation]: http://eloquent-software.com/pathogen/artifacts/documentation/api/

## Automated configuration

*Archer* provides a command called `update` to assist in keeping git repository
and [Travis CI] configuration consistent across multiple projects.

The generated configuration ensures that:

- Travis CI knows how to run the tests, build the coverage report, and publish
  build artifacts.
- Travis CI builds against all relevant versions of PHP.
- Travis CI builds are much less likely to fail because of GitHub API
  throttling.
- Test and build artifacts are ignored by Git.
- Archived versions of projects do not include development artifacts like the
  test suite.

#### Update command usage (without artifact publishing support)

    vendor/bin/archer update    # canonical form
    vendor/bin/archer u         # shortcut to 'update'

## Build artifact publication

Wouldn't it be great if everyone could see that 100% test coverage report? Then
it would be obvious how much care and effort went into producing a quality
product. With *Archer*, this is as simple as authorizing a project.

To authorize a project, simply run the `update` command with the `--authorize`
option. The command will prompt for a GitHub username and password, and use
these to create an encrypted configuration file used to publish artifacts from
[Travis CI].

#### Update command usage (with artifact publishing support)

    vendor/bin/archer update --authorize    # canonical form
    vendor/bin/archer u -a                  # shortcut to 'update --authorize'

#### Published artifacts

Once the authorization has been set up, artifacts are published to the project's
`gh-pages` branch under a directory named `artifacts` whenever Travis builds the
repository's [default branch].

The directory structure is as follows:

    artifacts/
    ├── images/         # Status shields/badges, organized by theme
    └── tests/
        └── coverage/   # Test coverage reports

[default branch]: https://help.github.com/articles/setting-the-default-branch-for-a-repository

##### Published test coverage report

Once published, a project's test coverage report is available through
[GitHub Pages]. As an example, *Archer's* own coverage reports are published to
http://icecavestudios.github.io/archer/artifacts/tests/coverage/.

The above URL redirects to a custom domain, but it is still served through
GitHub Pages.

Note that *Archer* will also automatically publish coverage information to
[Codecov]. It is recommended to link to these reports where possible, instead of
the report published by *Archer*.

##### Published test coverage badges

Archer will publish status shields/badges, however this is a legacy feature, and
it is now recommended to use [Shields.io] instead.

[shields.io]: http://shields.io/

## Contact us

- Follow [@IcecaveStudios] on Twitter
- Visit the [Icecave Studios website]
- Join `#icecave` on [irc.freenode.net]

[@icecavestudios]: https://twitter.com/IcecaveStudios
[icecave studios website]: http://icecave.com.au
[irc.freenode.net]: http://webchat.freenode.net?channels=icecave

[build artifact publication]: #build-artifact-publication
[codecov]: https://codecov.io/
[github pages]: http://pages.github.com/
[phpunit]: https://github.com/sebastianbergmann/phpunit
[test coverage reports]: #test-coverage-reports
[travis ci]: https://travis-ci.org/
[xdebug]: http://xdebug.org/
