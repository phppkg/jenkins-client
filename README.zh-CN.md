# Jenkins client

[![License](https://img.shields.io/github/license/phppkg/jenkins-client?style=flat-square)](LICENSE)
[![Php Version](https://img.shields.io/packagist/php-v/phppkg/jenkins-client?maxAge=2592000)](https://packagist.org/packages/phppkg/jenkins-client)
[![GitHub tag (latest SemVer)](https://img.shields.io/github/tag/phppkg/jenkins-client)](https://github.com/phppkg/jenkins-client)
[![Unit Tests](https://github.com/phppkg/jenkins-client/actions/workflows/php.yml/badge.svg)](https://github.com/phppkg/jenkins-client/actions/workflows/php.yml)
[![Deploy Pages](https://github.com/phppkg/jenkins-client/actions/workflows/static.yml/badge.svg)](https://github.com/phppkg/jenkins-client/actions/workflows/static.yml)

> **[EN README](README.md)**

`jenkins-client` - 简单方便的使用 API 与 Jenkins CI 进行交互。

> `phppkg/jenkins-client` is inspired from the https://github.com/jenkins-khan/jenkins-php-api

## 安装

- Required PHP 8.0+

**composer**

```bash
composer require phppkg/jenkins-client
```

## 开始使用

在进行任何操作之前，您需要实例化客户端:

```php
$jenkins = new \PhpPkg\JenkinsClient\Jenkins('http://host.org:8080');
```

如果你的 Jenkins 需要身份验证，你需要传递这样的 URL: `http://user:token@host.org:8080`.

Simple example - sending "String Parameters":

```shell
curl JENKINS_URL/job/JOB_NAME/buildWithParameters \
--user USER:TOKEN \
--data id=123 --data verbosity=high
```

Another example - sending a "File Parameter":

```shell
curl JENKINS_URL/job/JOB_NAME/buildWithParameters \
--user USER:PASSWORD \
--form FILE_LOCATION_AS_SET_IN_JENKINS=@PATH_TO_FILE
```

Here are some examples of how to use it:

### 获取Job的颜色

```php
    $job = $jenkins->getJob("dev2-pull");
    vdump($job->getColor());
    //string(4) "blue"
```

### 启动 Job

```php
    $job = $jenkins->launchJob("clone-deploy");
    vdump($job);
    // bool(true) if successful or throws a RuntimeException
```

### 列出给定视图的Jobs

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

### 列出构建及其状态

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

### 检查 Jenkins 是否可用

```php
    var_dump($jenkins->isAvailable());
    //bool(true);
```

For more information, see the [Jenkins API](https://wiki.jenkins-ci.org/display/JENKINS/Remote+access+API).

## License

[MIT](LICENSE)
