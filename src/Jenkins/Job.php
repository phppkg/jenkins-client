<?php declare(strict_types=1);
/**
 * This file is part of phppkg/jenkins-client.
 *
 * @link     https://github.com/inhere
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace PhpPkg\JenkinsClient\Jenkins;

use DOMDocument;
use PhpPkg\JenkinsClient\Jenkins;
use Toolkit\Stdlib\Obj\DataObject;

/**
 * class Job
 *
 * @author inhere
 * @date 2022/11/16
 */
class Job
{
    /**
     * @var DataObject
     */
    private DataObject $job;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param DataObject $job
     * @param Jenkins $jenkins
     */
    public function __construct(DataObject $job, Jenkins $jenkins)
    {
        $this->job = $job;
        $this->jenkins  = $jenkins;
    }

    /**
     * @return Build[]
     */
    public function getBuilds(): array
    {
        $builds = [];
        foreach ($this->job->builds as $build) {
            $builds[] = $this->getJenkinsBuild($build['number']);
        }

        return $builds;
    }

    /**
     * @param int $buildId
     *
     * @return Build
     */
    public function getJenkinsBuild(int $buildId): Build
    {
        return $this->jenkins->getBuild($this->getName(), $buildId);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->job->name;
    }

    /**
     * @return array
     */
    public function getParametersDefinitions(): array
    {
        $parameters = [];

        foreach ($this->job->property as $action) {
            if (!isset($action['parameterDefinitions'])) {
                continue;
            }

            foreach ($action['parameterDefinitions'] as $paramDefinition) {
                $description = $paramDefinition['description'] ?? null;
                $paramType = $paramDefinition['type'] ?? null;

                $default = $paramDefinition['defaultParameterValue']['value'] ?? null;
                $choices = $paramDefinition['choices'] ?? null;

                $parameters[$paramDefinition['name']] = [
                    'description' => $description,
                    'default'     => $default,
                    'type'        => $paramType,
                    'choices'     => $choices,
                ];
            }
        }

        return $parameters;
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return $this->job->color;
    }

    /**
     * @return string
     */
    public function retrieveXmlConfigAsString(): string
    {
        return $this->jenkins->getJobConfig($this->getName());
    }

    /**
     * @return DOMDocument
     */
    public function retrieveXmlConfigAsDomDocument(): DOMDocument
    {
        $document = new DOMDocument;
        $document->loadXML($this->retrieveXmlConfigAsString());

        return $document;
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
     * @return Job
     */
    public function setJenkins(Jenkins $jenkins): Job
    {
        $this->jenkins = $jenkins;

        return $this;
    }

    /**
     * @return Build|null
     */
    public function getLastSuccessfulBuild(): ?Build
    {
        if (null === $this->job->lastSuccessfulBuild) {
            return null;
        }

        return $this->jenkins->getBuild($this->getName(), $this->job->lastSuccessfulBuild['number']);
    }

    /**
     * @return Build|null
     */
    public function getLastBuild(): ?Build
    {
        if (null === $this->job->lastBuild) {
            return null;
        }

        return $this->jenkins->getBuild($this->getName(), $this->job->lastBuild['number']);
    }

    /**
     * @return DataObject
     */
    public function getData(): DataObject
    {
        return $this->job;
    }

    /**
     * @param string $propName
     *
     * @return mixed
     */
    public function getInfo(string $propName = ''): mixed
    {
        if ($propName) {
            return $this->job->get($propName);
        }

        return $this->job;
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return $this->job->getKeys();
    }
}
