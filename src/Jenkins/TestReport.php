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
use Toolkit\Stdlib\Json;
use Toolkit\Stdlib\Obj\DataObject;

/**
 * class TestReport
 *
 * @author inhere
 * @date 2022/11/18
 */
class TestReport
{
    protected Jenkins $jenkins;

    protected DataObject $testReport;

    /**
     * @var string
     */
    protected string $jobName;

    /**
     * @var int
     */
    protected int $buildNumber;

    /**
     * __construct
     *
     * @param Jenkins   $jenkins
     * @param DataObject $testReport
     * @param string $jobName
     * @param int $buildNumber
     */
    public function __construct(Jenkins $jenkins, DataObject $testReport, string $jobName, int $buildNumber)
    {
        $this->jenkins     = $jenkins;
        $this->testReport  = $testReport;
        $this->jobName     = $jobName;
        $this->buildNumber = $buildNumber;
    }

    /**
     * @return string
     */
    public function getOriginalTestReport(): string
    {
        return Json::enc($this->testReport);
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->testReport;
    }

    /**
     * @return string
     */
    public function getJobName(): string
    {
        return $this->jobName;
    }

    /**
     * @return int
     */
    public function getBuildNumber(): int
    {
        return $this->buildNumber;
    }

    /**
     * @return float
     */
    public function getDuration(): float
    {
        return $this->testReport->duration;
    }

    /**
     * @return int
     */
    public function getFailCount(): int
    {
        return $this->testReport->failCount;
    }

    /**
     * @return int
     */
    public function getPassCount(): int
    {
        return $this->testReport->passCount;
    }

    /**
     * @return int
     */
    public function getSkipCount(): int
    {
        return $this->testReport->skipCount;
    }

    /**
     * @return array
     */
    public function getSuites(): array
    {
        return $this->testReport->suites;
    }

    /**
     *
     * @param $id
     *
     * @return stdClass
     */
    public function getSuite($id): stdClass
    {
        return $this->testReport->suites[$id];
    }

    /**
     *
     * @param $id
     *
     * @return string
     */
    public function getSuiteStatus($id): string
    {
        $suite  = $this->getSuite($id);
        $status = 'PASSED';
        foreach ($suite->cases as $case) {
            if ($case->status === 'FAILED') {
                $status = 'FAILED';
                break;
            }
        }

        return $status;
    }
}
