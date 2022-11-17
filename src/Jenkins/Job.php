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
use stdClass;
use function array_keys;
use function get_object_vars;
use function property_exists;

/**
 * class Job
 *
 * @author inhere
 * @date 2022/11/16
 */
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
     * @param Jenkins $jenkins
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
    public function getParametersDefinitions(): array
    {
        $parameters = [];

        foreach ($this->job->property as $action) {
            if (!property_exists($action, 'parameterDefinitions')) {
                continue;
            }

            foreach ($action->parameterDefinitions as $paramDefinition) {
                $description = property_exists($paramDefinition, 'description')
                    ? $paramDefinition->description
                    : null;
                $paramType   = property_exists($paramDefinition, 'type') ? $paramDefinition->choices : '';

                $default = property_exists($paramDefinition, 'defaultParameterValue')
                && isset($paramDefinition->defaultParameterValue->value)
                    ? $paramDefinition->defaultParameterValue->value
                    : null;
                $choices = property_exists($paramDefinition, 'choices')
                    ? $paramDefinition->choices
                    : null;


                $parameters[$paramDefinition->name] = [
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

    /**
     * @param string $propName
     *
     * @return mixed
     */
    public function getInfo(string $propName = ''): mixed
    {
        if ($propName) {
            if (property_exists($this->job, $propName)) {
                return $this->job->$propName;
            }
            return [];
        }

        return $this->job;
    }

    /**
     * @return array
     */
    public function getKeys(): array
    {
        return array_keys(get_object_vars($this->job));
    }
}
