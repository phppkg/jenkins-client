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

class TestReport
{
    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @var stdClass
     */
    protected stdClass $testReport;

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
     * @param stdClass $testReport
     * @param string $jobName
     * @param int $buildNumber
     */
    public function __construct(Jenkins $jenkins, stdClass $testReport, string $jobName, int $buildNumber)
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
        return json_encode($this->testReport, JSON_THROW_ON_ERROR);
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
     * @return stdClass
     */
    public function getSuite($id): stdClass
    {
        return $this->testReport->suites[$id];
    }

    /**
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
