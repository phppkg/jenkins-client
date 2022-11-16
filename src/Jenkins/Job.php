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
use RuntimeException;
use stdClass;

class Job
{
    /**
     * @var stdClass
     */
    private stdClass $job;

    /**
     * @var Jenkins
     */
    protected Jenkins $jenkins;

    /**
     * @param stdClass $job
     * @param Jenkins   $jenkins
     */
    public function __construct(stdClass $job, Jenkins $jenkins)
    {
        $this->job = $job;

        $this->setJenkins($jenkins);
    }

    /**
     * @return Build[]
     */
    public function getBuilds(): array
    {
        $builds = [];
        foreach ($this->job->builds as $build) {
            $builds[] = $this->getJenkinsBuild($build->number);
        }

        return $builds;
    }

    /**
     * @param int $buildId
     *
     * @return Build
     * @throws RuntimeException
     */
    public function getJenkinsBuild(int $buildId): Build
    {
        return $this->getJenkins()->getBuild($this->getName(), $buildId);
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
    public function getParametersDefinition(): array
    {
        $parameters = [];

        foreach ($this->job->actions as $action) {
            if (!property_exists($action, 'parameterDefinitions')) {
                continue;
            }

            foreach ($action->parameterDefinitions as $parameterDefinition) {
                $default     = property_exists($parameterDefinition, 'defaultParameterValue')
                               && isset($parameterDefinition->defaultParameterValue->value)
                    ? $parameterDefinition->defaultParameterValue->value
                    : null;
                $description = property_exists($parameterDefinition, 'description')
                    ? $parameterDefinition->description
                    : null;
                $choices     = property_exists($parameterDefinition, 'choices')
                    ? $parameterDefinition->choices
                    : null;

                $parameters[$parameterDefinition->name] = [
                    'default'     => $default,
                    'choices'     => $choices,
                    'description' => $description,
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
     *
     * @throws RuntimeException
     */
    public function retrieveXmlConfigAsString(): string
    {
        return $this->jenkins->retrieveXmlConfigAsString($this->getName());
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

        return $this->getJenkins()->getBuild($this->getName(), $this->job->lastSuccessfulBuild->number);
    }

    /**
     * @return Build|null
     */
    public function getLastBuild(): ?Build
    {
        if (null === $this->job->lastBuild) {
            return null;
        }

        return $this->getJenkins()->getBuild($this->getName(), $this->job->lastBuild->number);
    }
}
