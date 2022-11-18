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

class Queue
{
    /**
     * @var DataObject
     */
    private DataObject $queue;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param DataObject $queue
     * @param Jenkins $jenkins
     */
    public function __construct(DataObject $queue, Jenkins $jenkins)
    {
        $this->queue = $queue;
        $this->jenkins  = $jenkins;
    }

    /**
     * @return array
     */
    public function getJobQueues(): array
    {
        $jobs = [];

        foreach ($this->queue->items as $item) {
            $jobs[] = new JobQueue(DataObject::new($item), $this->jenkins);
        }

        return $jobs;
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->queue;
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
     * @return Queue
     */
    public function setJenkins(Jenkins $jenkins): Queue
    {
        $this->jenkins = $jenkins;

        return $this;
    }
}
