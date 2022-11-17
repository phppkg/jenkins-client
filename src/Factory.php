<?php declare(strict_types=1);
/**
 * This file is part of phppkg/jenkins-client.
 *
 * @link     https://github.com/inhere
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace PhpPkg\JenkinsClient;

/**
 * class Factory
 *
 * @author inhere
 * @date 2022/11/16
 */
class Factory
{
    /**
     * @param string $url
     * @param string $username
     * @param string $apiToken
     *
     * @return Jenkins
     */
    public static function make(string $url, string $username, string $apiToken = ''): Jenkins
    {
        $jk = new Jenkins($url);
        $jk->setUsername($username);
        $jk->setApiToken($apiToken);

        return $jk;
    }
}
