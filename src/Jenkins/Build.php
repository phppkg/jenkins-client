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
     * @var stdClass
     */
    private stdClass $build;

    /**
     * @var Jenkins
     */
    private Jenkins $jenkins;

    /**
     * @param stdClass $build
     * @param Jenkins   $jenkins
     */
    public function __construct(stdClass $build, Jenkins $jenkins)
    {
        $this->build = $build;
        $this->setJenkins($jenkins);
    }

    /**
     * @return array
     */
    public function getInputParameters(): array
    {
        $parameters = [];

        if (!property_exists($this->build->actions[0], 'parameters')) {
            return $parameters;
        }

        foreach ($this->build->actions[0]->parameters as $parameter) {
            $parameters[$parameter->name] = $parameter->value;
        }

        return $parameters;
    }

    /**
     * @return int
     */
    public function getTimestamp(): float|int
    {
        //division par 1000 => pas de millisecondes
        return $this->build->timestamp / 1000;
    }

    /**
     * @return int
     */
    public function getDuration(): float|int
    {
        //division par 1000 => pas de millisecondes
        return $this->build->duration / 1000;
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
     * @return float|null
     */
    public function getEstimatedDuration(): float|int|null
    {
        //since version 1.461 estimatedDuration is displayed in jenkins's api
        //we can use it witch is more accurate than calcule ourselves
        //but older versions need to continue to work, so in case of estimated
        //duration is not found we fallback to calcule it.
        if (property_exists($this->build, 'estimatedDuration')) {
            return $this->build->estimatedDuration / 1000;
        }

        $duration = null;
        $progress = $this->getProgress();
        if (null !== $progress && $progress >= 0) {
            $duration = ceil((time() - $this->getTimestamp()) / ($progress / 100));
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
     * @return null|string
     */
    public function getResult(): ?string
    {
        $result = match ($this->build->result) {
            'FAILURE' => self::FAILURE,
            'SUCCESS' => self::SUCCESS,
            'UNSTABLE' => self::UNSTABLE,
            'ABORTED' => self::ABORTED,
            'WAITING' => self::WAITING,
            default => self::RUNNING,
        };

        return $result;
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
     * @return Build|Job
     */
    public function setJenkins(Jenkins $jenkins): Job|static
    {
        $this->jenkins = $jenkins;

        return $this;
    }

    public function getBuiltOn()
    {
        return $this->build->builtOn;
    }
}
