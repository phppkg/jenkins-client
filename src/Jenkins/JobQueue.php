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
 * class JobQueue
 *
 * @author inhere
 * @date 2022/11/16
 */
class JobQueue
{
    /**
     * @var stdClass
     */
    private stdClass $jobQueue;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param stdClass $jobQueue
     * @param Jenkins   $jenkins
     */
    public function __construct(stdClass $jobQueue, Jenkins $jenkins)
    {
        $this->jobQueue = $jobQueue;
        $this->setJenkins($jenkins);
    }

    /**
     * @return array
     */
    public function getInputParameters(): array
    {
        $parameters = [];

        if (!property_exists($this->jobQueue->actions[0], 'parameters')) {
            return $parameters;
        }

        foreach ($this->jobQueue->actions[0]->parameters as $parameter) {
            $parameters[$parameter->name] = $parameter->value;
        }

        return $parameters;
    }

    /**
     * @return string
     */
    public function getJobName(): string
    {
        return $this->jobQueue->task->name;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->jobQueue->id;
    }

    /**
     * @return void
     */
    public function cancel(): void
    {
        $this->getJenkins()->cancelQueue($this);
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
     * @return JobQueue
     */
    public function setJenkins(Jenkins $jenkins): JobQueue
    {
        $this->jenkins = $jenkins;

        return $this;
    }
}
