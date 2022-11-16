<?php declare(strict_types=1);
/**
 * This file is part of phppkg/jenkins-client.
 *
 * @link     https://github.com/inhere
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace PhpPkg\JenkinsClient\Jenkins;

use PhpPkg\JenkinsClient\Jenkins;
use stdClass;

/**
 * class Executor
 *
 * @author inhere
 * @date 2022/11/16
 */
class Executor
{
    /**
     * @var stdClass
     */
    private stdClass $executor;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @var string
     */
    protected string $computer;

    /**
     * @param stdClass $executor
     * @param string $computer
     * @param Jenkins   $jenkins
     */
    public function __construct(stdClass $executor, string $computer, Jenkins $jenkins)
    {
        $this->executor = $executor;
        $this->computer = $computer;
        $this->setJenkins($jenkins);
    }

    /**
     * @return string
     */
    public function getComputer(): string
    {
        return $this->computer;
    }

    /**
     * @return int
     */
    public function getProgress(): int
    {
        return $this->executor->progress;
    }

    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->executor->number;
    }

    /**
     * @return int|null
     */
    public function getBuildNumber(): ?int
    {
        $number = null;
        if (isset($this->executor->currentExecutable)) {
            $number = $this->executor->currentExecutable->number;
        }

        return $number;
    }

    /**
     * @return null|string
     */
    public function getBuildUrl(): ?string
    {
        $url = null;
        if (isset($this->executor->currentExecutable)) {
            $url = $this->executor->currentExecutable->url;
        }

        return $url;
    }

    /**
     * @return void
     */
    public function stop(): void
    {
        $this->getJenkins()->stopExecutor($this);
    }

    /**
     * @return Jenkins
     */
    public function getJenkins(): Jenkins
    {
        return $this->jenkins;
    }

    /**
     * @param Jenkins $jenkins
     *
     * @return Executor|Job
     */
    public function setJenkins(Jenkins $jenkins): Job|static
    {
        $this->jenkins = $jenkins;

        return $this;
    }
}
