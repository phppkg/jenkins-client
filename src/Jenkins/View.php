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
 * class View
 *
 * @author inhere
 * @date 2022/11/16
 */
class View
{
    /**
     * @var DataObject
     */
    private DataObject $view;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param DataObject $view
     * @param Jenkins   $jenkins
     */
    public function __construct(DataObject $view, Jenkins $jenkins)
    {
        $this->view    = $view;
        $this->jenkins = $jenkins;
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->view;
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
    public function getDescription(): string
    {
        return $this->view->getString('description');
    }

    /**
     * @return string
     */
    public function getURL(): string
    {
        return $this->view->getString('url');
    }

    /**
     * @return Job[]
     */
    public function getJobs(): array
    {
        $jobs = [];

        foreach ($this->view->jobs as $job) {
            $jobs[] = $this->jenkins->getJob($job['name']);
        }

        return $jobs;
    }

    /**
     * get color
     *
     * @return string
     */
    public function getColor(): string
    {
        $color = 'blue';
        foreach ($this->view->jobs as $job) {
            if ($this->getColorPriority($job['color']) > $this->getColorPriority($color)) {
                $color = $job['color'];
            }
        }

        return $color;
    }

    /**
     * get Color Priority
     *
     * @param string $color
     *
     * @return int
     */
    protected function getColorPriority(string $color): int
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
