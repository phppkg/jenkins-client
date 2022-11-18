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
 * class Computer
 *
 * @author inhere
 * @date 2022/11/16
 */
class Computer
{
    /**
     * @var DataObject
     */
    private DataObject $computer;

    /**
     * @var Jenkins
     */
    private Jenkins $jenkins;

    /**
     * @param DataObject $computer
     * @param Jenkins   $jenkins
     */
    public function __construct(DataObject $computer, Jenkins $jenkins)
    {
        $this->computer = $computer;
        $this->jenkins  = $jenkins;
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->computer;
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
     * returns [] when computer is launching
     * returns non-empty when computer has been put offline
     *
     * @return array
     */
    public function getOfflineCause(): array
    {
        return $this->computer->getArray('offlineCause');
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
        $this->jenkins->deleteComputer($this->getName());

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
        return $this->jenkins->getComputerConfig($this->getName());
    }
}
