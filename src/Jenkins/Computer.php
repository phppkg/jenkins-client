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
 * class Computer
 *
 * @author inhere
 * @date 2022/11/16
 */
class Computer
{
    /**
     * @var stdClass
     */
    private stdClass $computer;

    /**
     * @var Jenkins
     */
    private Jenkins $jenkins;

    /**
     * @param stdClass $computer
     * @param Jenkins   $jenkins
     */
    public function __construct(stdClass $computer, Jenkins $jenkins)
    {
        $this->computer = $computer;
        $this->setJenkins($jenkins);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->computer->displayName;
    }

    /**
     *
     * @return bool
     */
    public function isOffline(): bool
    {
        return (bool) $this->computer->offline;
    }

    /**
     *
     * returns null when computer is launching
     * returns \stdClass when computer has been put offline
     *
     * @return null|stdClass
     */
    public function getOfflineCause(): ?stdClass
    {
        return $this->computer->offlineCause;
    }

    /**
     *
     * @return Computer
     */
    public function toggleOffline(): Computer
    {
        $this->getJenkins()->toggleOfflineComputer($this->getName());

        return $this;
    }

    /**
     *
     * @return Computer
     */
    public function delete(): Computer
    {
        $this->getJenkins()
             ->deleteComputer($this->getName());

        return $this;
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
     * @return Computer
     */
    public function setJenkins(Jenkins $jenkins): Computer
    {
        $this->jenkins = $jenkins;

        return $this;
    }

    /**
     * @return string
     */
    public function getConfiguration(): string
    {
        return $this->getJenkins()->getComputerConfiguration($this->getName());
    }
}
