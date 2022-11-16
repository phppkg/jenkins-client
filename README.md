# Jenkins client

[![License](https://img.shields.io/github/license/phppkg/jenkins-client?style=flat-square)](LICENSE)
[![Php Version](https://img.shields.io/packagist/php-v/phppkg/jenkins-client?maxAge=2592000)](https://packagist.org/packages/phppkg/jenkins-client)
[![GitHub tag (latest SemVer)](https://img.shields.io/github/tag/phppkg/jenkins-client)](https://github.com/phppkg/jenkins-client)
[![Actions Status](https://github.com/phppkg/jenkins-client/workflows/Unit-Tests/badge.svg)](https://github.com/phppkg/jenkins-client/actions)

`jenkins-client` is a set of classes designed to interact with Jenkins CI using its API.

> `phppkg/jenkins-client` is inspired from the https://github.com/jenkins-khan/jenkins-php-api

## Install

- Required PHP 8.0+

**composer**

```bash
composer require phppkg/jenkins-client
```

## Usage

Before anything, you need to instantiate the client :

```php
$jenkins = new \PhpPkg\JenkinsClient\Jenkins('http://host.org:8080');
```

If your Jenkins needs authentication, you need to pass a URL like this : `'http://user:token@host.org:8080'`.

Here are some examples of how to use it:

### Get the color of the job

```php
    $job = $jenkins->getJob("dev2-pull");
    vdump($job->getColor());
    //string(4) "blue"
```

### Launch a Job

```php
    $job = $jenkins->launchJob("clone-deploy");
    vdump($job);
    // bool(true) if successful or throws a RuntimeException
```

### List the jobs of a given view

```php
    $view = $jenkins->getView('madb_deploy');
    foreach ($view->getJobs() as $job) {
      var_dump($job->getName());
    }
    //string(13) "altlinux-pull"
    //string(8) "dev-pull"
    //string(9) "dev2-pull"
    //string(11) "fedora-pull"
```

### List builds and their status

```php
    $job = $jenkins->getJob('dev2-pull');
    foreach ($job->getBuilds() as $build) {
      var_dump($build->getNumber());
      var_dump($build->getResult());
    }
    //int(122)
    //string(7) "SUCCESS"
    //int(121)
    //string(7) "FAILURE"
```

### Check if Jenkins is available

```php
    var_dump($jenkins->isAvailable());
    //bool(true);
```

For more information, see the [Jenkins API](https://wiki.jenkins-ci.org/display/JENKINS/Remote+access+API).

## License

[MIT](LICENSE)
