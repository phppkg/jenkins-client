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
 * class JobQueue
 *
 * @author inhere
 * @date 2022/11/16
 */
class JobQueue
{
    /**
     * @var DataObject
     */
    private DataObject $jobQueue;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param DataObject $jobQueue
     * @param Jenkins   $jenkins
     */
    public function __construct(DataObject $jobQueue, Jenkins $jenkins)
    {
        $this->jobQueue = $jobQueue;
        $this->jenkins  = $jenkins;
    }

    /**
     * @return array
     */
    public function getInputParameters(): array
    {
        $actions = $this->jobQueue->actions;
        if (!isset($actions[0]['parameters'])) {
            return [];
        }

        $parameters = [];
        foreach ($actions[0]['parameters'] as $parameter) {
            $parameters[$parameter['name']] = $parameter['value'];
        }

        return $parameters;
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->jobQueue;
    }

    /**
     * @return string
     */
    public function getJobName(): string
    {
        return $this->jobQueue->task['name'];
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
