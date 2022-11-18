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
use Toolkit\Stdlib\Obj\DataObject;

/**
 * class Executor
 *
 * @author inhere
 * @date 2022/11/16
 */
class Executor
{
    /**
     * @var DataObject
     */
    private DataObject $executor;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @var string
     */
    protected string $computer;

    /**
     * @param DataObject $executor
     * @param string $computer
     * @param Jenkins   $jenkins
     */
    public function __construct(DataObject $executor, string $computer, Jenkins $jenkins)
    {
        $this->executor = $executor;
        $this->computer = $computer;
        $this->jenkins  = $jenkins;
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->executor;
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
     * @return int
     */
    public function getBuildNumber(): int
    {
        $number = 0;
        if (isset($this->executor->currentExecutable)) {
            $number = $this->executor->currentExecutable['number'];
        }

        return $number;
    }

    /**
     * @return string
     */
    public function getBuildUrl(): string
    {
        $url = '';
        if (isset($this->executor->currentExecutable)) {
            $url = $this->executor->currentExecutable['url'];
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
     * @return self
     */
    public function setJenkins(Jenkins $jenkins): static
    {
        $this->jenkins = $jenkins;

        return $this;
    }
}
