<?php declare(strict_types=1);
/**
 * This file is part of phppkg/jenkins-client.
 *
 * @link     https://github.com/inhere
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace PhpPkg\JenkinsClient;

use Toolkit\Stdlib\Obj\AbstractObj;

/**
 * class JenkinsConfig
 *
 * @author inhere
 * @date 2022/11/16
 */
class JenkinsConfig extends AbstractObj
{
    /**
     * @var string
     */
    public string $hostUrl = '';

    /**
     * Jenkins username
     *
     * @var string
     */
    public string $username = '';

    /**
     * Jenkins user password
     *
     * @var string
     */
    public string $password = '';

    /**
     * @var string
     */
    public string $apiToken = '';

    /**
     * @param string $jobName
     *
     * @return string
     */
    public function jobPageUrl(string $jobName): string
    {
        return $this->hostUrl . '/job/' . $jobName;
    }

    /**
     * @param string $viewName
     *
     * @return string
     */
    public function viewPageUrl(string $viewName): string
    {
        return $this->hostUrl . '/view/' . $viewName;
    }

}
