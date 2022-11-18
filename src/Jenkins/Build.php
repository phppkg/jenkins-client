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
 * class Build
 *
 * @author inhere
 * @date 2022/11/16
 */
class Build
{
    /**
     * @var string
     */
    public const FAILURE = 'FAILURE';

    /**
     * @var string
     */
    public const SUCCESS = 'SUCCESS';

    /**
     * @var string
     */
    public const RUNNING = 'RUNNING';

    /**
     * @var string
     */
    public const WAITING = 'WAITING';

    /**
     * @var string
     */
    public const UNSTABLE = 'UNSTABLE';

    /**
     * @var string
     */
    public const ABORTED = 'ABORTED';

    /**
     * @var DataObject
     */
    private DataObject $build;

    /**
     * @var Jenkins
     */
    private Jenkins $jenkins;

    /**
     * @param DataObject $build
     * @param Jenkins $jenkins
     */
    public function __construct(DataObject $build, Jenkins $jenkins)
    {
        $this->build = $build;
        $this->jenkins  = $jenkins;
    }

    /**
     * @return array
     */
    public function getInputParameters(): array
    {
        $actions = $this->build->actions;
        if (!isset($actions[0]['parameters'])) {
            return [];
        }

        // DataObject::new($actions[0]['parameters']);
        $parameters = [];
        foreach ($actions[0]['parameters'] as $parameter) {
            $parameters[$parameter['name']] = $parameter['value'];
        }

        return $parameters;
    }

    /**
     * @return int
     */
    public function getTimestamp(): int
    {
        //division par 1000 => pas de milliseconds
        return (int)($this->build->timestamp / 1000);
    }

    /**
     * @return int
     */
    public function getDuration(): int
    {
        //division par 1000 => pas de milliseconds
        return (int)($this->build->duration / 1000);
    }

    /**
     * @return int
     */
    public function getNumber(): int
    {
        return $this->build->number;
    }

    /**
     * @return null|int
     */
    public function getProgress(): ?int
    {
        $progress = null;
        if (null !== ($executor = $this->getExecutor())) {
            $progress = $executor->getProgress();
        }

        return $progress;
    }

    /**
     * @return int
     */
    public function getEstimatedDuration(): int
    {
        //since version 1.461 estimatedDuration is displayed in jenkins's api
        //we can use it witch is more accurate than calcule ourselves
        //but older versions need to continue to work, so in case of estimated
        //duration is not found we fallback to calcule it.
        if (property_exists($this->build, 'estimatedDuration')) {
            return (int)($this->build->estimatedDuration / 1000);
        }

        $duration = 0;
        $progress = $this->getProgress();
        if (null !== $progress && $progress >= 0) {
            $duration = (int)ceil((time() - $this->getTimestamp()) / ($progress / 100));
        }

        return $duration;
    }

    /**
     * Returns remaining execution time (seconds)
     *
     * @return int|null
     */
    public function getRemainingExecutionTime(): ?int
    {
        $remaining = null;
        if (null !== ($estimatedDuration = $this->getEstimatedDuration())) {
            //be carefull because time from JK server could be different
            //of time from Jenkins server
            //but i didn't find a timestamp given by Jenkins api

            $remaining = $estimatedDuration - (time() - $this->getTimestamp());
        }

        return max(0, $remaining);
    }

    /**
     * @return string
     */
    public function getResult(): string
    {
        return match ($this->build->result) {
            'FAILURE' => self::FAILURE,
            'SUCCESS' => self::SUCCESS,
            'UNSTABLE' => self::UNSTABLE,
            'ABORTED' => self::ABORTED,
            'WAITING' => self::WAITING,
            default => self::RUNNING,
        };
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->build->url;
    }

    /**
     * @return Executor|null
     */
    public function getExecutor(): ?Executor
    {
        if (!$this->isRunning()) {
            return null;
        }

        $runExecutor = null;
        foreach ($this->getJenkins()->getExecutors() as $executor) {
            /** @var Executor $executor */

            if ($this->getUrl() === $executor->getBuildUrl()) {
                $runExecutor = $executor;
            }
        }

        return $runExecutor;
    }

    /**
     * @return bool
     */
    public function isRunning(): bool
    {
        return self::RUNNING === $this->getResult();
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
     * @return Build
     */
    public function setJenkins(Jenkins $jenkins): static
    {
        $this->jenkins = $jenkins;

        return $this;
    }

    public function getBuiltOn()
    {
        return $this->build->builtOn;
    }
}
