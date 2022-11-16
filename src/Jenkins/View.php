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

class View
{
    /**
     * @var stdClass
     */
    private stdClass $view;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param stdClass $view
     * @param Jenkins   $jenkins
     */
    public function __construct(stdClass $view, Jenkins $jenkins)
    {
        $this->view    = $view;
        $this->jenkins = $jenkins;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->view->name;
    }

    /**
     * @return string
     */
    public function getDescription(): ?string
    {
        return $this->view->description ?? null;
    }

    /**
     * @return string
     */
    public function getURL(): ?string
    {
        return $this->view->url ?? null;
    }

    /**
     * @return Job[]
     */
    public function getJobs(): array
    {
        $jobs = [];

        foreach ($this->view->jobs as $job) {
            $jobs[] = $this->jenkins->getJob($job->name);
        }

        return $jobs;
    }

    /**
     * getColor
     *
     * @return string
     */
    public function getColor(): string
    {
        $color = 'blue';
        foreach ($this->view->jobs as $job) {
            if ($this->getColorPriority($job->color) > $this->getColorPriority($color)) {
                $color = $job->color;
            }
        }

        return $color;
    }

    /**
     * getColorPriority
     *
     * @param string $color
     *
     * @return int
     */
    protected function getColorPriority(string $color): ?int
    {
        switch ($color) {
            default:
                return 999;
            case 'red_anime':
                return 11;
            case 'red':
                return 10;
            case 'yellow_anime':
                return 6;
            case 'yellow':
                return 5;
            case 'blue_anime':
                return 2;
            case 'blue':
                return 1;
            case 'disabled':
                return 0;
        }
    }
}
