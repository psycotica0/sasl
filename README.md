# fabiang/sasl

The PHP SASL Authentification Library.

[![Latest Stable Version](https://poser.pugx.org/fabiang/sasl/v/stable.svg)](https://packagist.org/packages/fabiang/sasl) [![Total Downloads](https://poser.pugx.org/fabiang/sasl/downloads.svg)](https://packagist.org/packages/fabiang/sasl) [![Latest Unstable Version](https://poser.pugx.org/fabiang/sasl/v/unstable.svg)](https://packagist.org/packages/fabiang/sasl) [![License](https://poser.pugx.org/fabiang/sasl/license.svg)](https://packagist.org/packages/fabiang/sasl) [![HHVM Status](http://hhvm.h4cc.de/badge/fabiang/sasl.svg)](http://hhvm.h4cc.de/package/fabiang/sasl)
[![Build Status](https://travis-ci.org/fabiang/sasl.svg?branch=master)](https://travis-ci.org/fabiang/sasl) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/fabiang/sasl/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/fabiang/sasl/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/e81e1e30-c545-420a-8a0c-59b60976f54b/mini.png)](https://insight.sensiolabs.com/projects/e81e1e30-c545-420a-8a0c-59b60976f54b) [![Coverage Status](https://img.shields.io/coveralls/fabiang/sasl.svg)](https://coveralls.io/r/fabiang/sasl) [![Dependency Status](https://gemnasium.com/fabiang/sasl.svg)](https://gemnasium.com/fabiang/sasl)

Provides code to generate responses to common SASL mechanisms, including:
* Digest-MD5
* Cram-MD5
* Plain
* Anonymous
* Login (Pseudo mechanism)
* SCRAM

Full refactored version of the the original [Auth_SASL2 Pear package](http://pear.php.net/package/Auth_SASL2/).

## Installation

The easiest way to install fabiang/sasl is by using Composer:

```
curl -sS https://getcomposer.org/installer | php
php composer.phar require fabiang/sasl='1.0.x-dev'
```

## Developing

If you like this library and you want to contribute, make sure the unit tests
and integration tests are running. Composer will help you to install the right
version of PHPUnit and Behat.

```
composer install --dev
```

After that run the unit tests:

```
./vendor/bin/phpunit -c tests
```

The integration tests test the authentication methods against an ejabberd server.
To launch an ejabberd server you can use the provided Vagrant box.
Just [install Vagrant](https://www.vagrantup.com/downloads.html) and run:

```
vagrant up
```

After so minutes you'll have a runnig ejabberd instance inside of a virtual machine.  
Now you can run the integration tests:

```
./vendor/bin/behat -c tests/behat.yml.dist
```

## License

BSD-3-Clause. See the [LICENSE.md](LICENSE.md).
