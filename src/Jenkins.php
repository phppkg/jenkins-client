<?php declare(strict_types=1);
/**
 * This file is part of phppkg/jenkins-client.
 *
 * @link     https://github.com/inhere
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace PhpPkg\JenkinsClient;

use DomDocument;
use InvalidArgumentException;
use PhpPkg\JenkinsClient\Jenkins\Job;
use RuntimeException;
use stdClass;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;

/**
 * class Jenkins
 *
 * @author inhere
 * @date 2022/11/16
 */
class Jenkins
{
    /**
     * @var string
     */
    private string $baseUrl;

    /**
     * @var null
     */
    private $jenkins = null;

    /**
     * Whether or not to retrieve and send anti-CSRF crumb tokens
     * with each request
     *
     * Defaults to false for backwards compatibility
     *
     * @var boolean
     */
    private bool $crumbsEnabled = false;

    /**
     * The anti-CSRF crumb to use for each request
     *
     * Set when crumbs are enabled, by requesting a new crumb from Jenkins
     *
     * @var string
     */
    private string $crumb;

    /**
     * The header to use for sending anti-CSRF crumbs
     *
     * Set when crumbs are enabled, by requesting a new crumb from Jenkins
     *
     * @var string
     */
    private string $crumbRequestField;

    /**
     * @param string $baseUrl
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Enable the use of anti-CSRF crumbs on requests
     *
     * @return void
     */
    public function enableCrumbs(): void
    {
        $this->crumbsEnabled = true;

        $crumbResult = $this->requestCrumb();

        if (!$crumbResult || !is_object($crumbResult)) {
            $this->crumbsEnabled = false;

            return;
        }

        $this->crumb             = $crumbResult->crumb;
        $this->crumbRequestField = $crumbResult->crumbRequestField;
    }

    /**
     * Disable the use of anti-CSRF crumbs on requests
     *
     * @return void
     */
    public function disableCrumbs(): void
    {
        $this->crumbsEnabled = false;
    }

    /**
     * Get the status of anti-CSRF crumbs
     *
     * @return boolean Whether or not crumbs have been enabled
     */
    public function areCrumbsEnabled(): bool
    {
        return $this->crumbsEnabled;
    }

    public function requestCrumb(): stdClass
    {
        $url = sprintf('%s/crumbIssuer/api/json', $this->baseUrl);

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $ret = curl_exec($curl);

        $this->validateCurl($curl, 'Error getting csrf crumb');

        $crumbResult = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);

        if (!$crumbResult instanceof stdClass) {
            throw new RuntimeException('Error during json_decode of csrf crumb');
        }

        return $crumbResult;
    }

    public function getCrumbHeader(): string
    {
        return "$this->crumbRequestField: $this->crumb";
    }

    /**
     * @return boolean
     */
    public function isAvailable(): bool
    {
        $curl = curl_init($this->baseUrl . '/api/json');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($curl);

        if (curl_errno($curl)) {
            return false;
        }

        try {
            $this->getQueue();
        } catch (RuntimeException $e) {
            //en cours de lancement de jenkins, on devrait passer par là
            return false;
        }

        return true;
    }

    /**
     * @return void
     * @throws RuntimeException
     */
    private function initialize(): void
    {
        if (null !== $this->jenkins) {
            return;
        }

        $curl = curl_init($this->baseUrl . '/api/json');

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error during getting list of jobs on %s', $this->baseUrl));

        $this->jenkins = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);
        if (!$this->jenkins instanceof stdClass) {
            throw new RuntimeException('Error during json_decode');
        }
    }

    /**
     * @throws RuntimeException
     * @return array
     */
    public function getAllJobs(): array
    {
        $this->initialize();

        $jobs = [];
        foreach ($this->jenkins->jobs as $job) {
            $jobs[$job->name] = [
                'name' => $job->name
            ];
        }

        return $jobs;
    }

    /**
     * @return Jenkins\Job[]
     */
    public function getJobs(): array
    {
        $this->initialize();

        $jobs = [];
        foreach ($this->jenkins->jobs as $job) {
            $jobs[$job->name] = $this->getJob($job->name);
        }

        return $jobs;
    }

    /**
     * @param string $computer
     *
     * @return array
     * @throws RuntimeException
     */
    public function getExecutors(string $computer = '(master)'): array
    {
        $this->initialize();

        $executors = [];
        for ($i = 0; $i < $this->jenkins->numExecutors; $i++) {
            $url  = sprintf('%s/computer/%s/executors/%s/api/json', $this->baseUrl, $computer, $i);
            $curl = curl_init($url);

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $ret = curl_exec($curl);

            $this->validateCurl(
                $curl,
                sprintf('Error during getting information for executors[%s@%s] on %s', $i, $computer, $this->baseUrl)
            );

            $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);
            if (!$infos instanceof stdClass) {
                throw new RuntimeException('Error during json_decode');
            }

            $executors[] = new Jenkins\Executor($infos, $computer, $this);
        }

        return $executors;
    }

    /**
     * @param       $jobName
     * @param array $parameters
     *
     * @return bool
     * @internal param array $extraParameters
     *
     */
    public function launchJob($jobName, array $parameters = []): bool
    {
        if (0 === count($parameters)) {
            $url = sprintf('%s/job/%s/build', $this->baseUrl, $jobName);
        } else {
            $url = sprintf('%s/job/%s/buildWithParameters', $this->baseUrl, $jobName);
        }

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($parameters));

        $headers = [];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error trying to launch job "%s" (%s)', $jobName, $url));

        return true;
    }

    /**
     * @param string $jobName
     *
     * @return bool|Job
     * @throws RuntimeException
     */
    public function getJob(string $jobName): bool|Job
    {
        $url  = sprintf('%s/job/%s/api/json', $this->baseUrl, $jobName);
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $response_info = curl_getinfo($curl);

        if (200 !== (int)$response_info['http_code']) {
            return false;
        }

        $this->validateCurl(
            $curl,
            sprintf('Error during getting information for job %s on %s', $jobName, $this->baseUrl)
        );

        $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);
        if (!$infos instanceof stdClass) {
            throw new RuntimeException('Error during json_decode');
        }

        return new Jenkins\Job($infos, $this);
    }

    /**
     * @param string $jobName
     *
     * @return void
     */
    public function deleteJob(string $jobName): void
    {
        $url  = sprintf('%s/job/%s/doDelete', $this->baseUrl, $jobName);
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);

        $headers = [];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $ret = curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error deleting job %s on %s', $jobName, $this->baseUrl));
    }

    /**
     * @return Jenkins\Queue
     * @throws RuntimeException
     */
    public function getQueue(): Jenkins\Queue
    {
        $url  = sprintf('%s/queue/api/json', $this->baseUrl);
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error during getting information for queue on %s', $this->baseUrl));

        $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);
        if (!$infos instanceof stdClass) {
            throw new RuntimeException('Error during json_decode');
        }

        return new Jenkins\Queue($infos, $this);
    }

    /**
     * @return Jenkins\View[]
     */
    public function getViews(): array
    {
        $this->initialize();

        $views = [];
        foreach ($this->jenkins->views as $view) {
            $views[] = $this->getView($view->name);
        }

        return $views;
    }

    /**
     * @return Jenkins\View|null
     */
    public function getPrimaryView(): ?Jenkins\View
    {
        $this->initialize();
        $primaryView = null;

        if (property_exists($this->jenkins, 'primaryView')) {
            $primaryView = $this->getView($this->jenkins->primaryView->name);
        }

        return $primaryView;
    }

    /**
     * @param string $viewName
     *
     * @return Jenkins\View
     * @throws RuntimeException
     */
    public function getView(string $viewName): Jenkins\View
    {
        $url  = sprintf('%s/view/%s/api/json', $this->baseUrl, rawurlencode($viewName));
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $this->validateCurl(
            $curl,
            sprintf('Error during getting information for view %s on %s', $viewName, $this->baseUrl)
        );

        $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);
        if (!$infos instanceof stdClass) {
            throw new RuntimeException('Error during json_decode');
        }

        return new Jenkins\View($infos, $this);
    }

    /**
     * @param        $job
     * @param        $buildId
     * @param string $tree
     *
     * @return Jenkins\Build
     * @throws RuntimeException
     */
    public function getBuild($job, $buildId, string $tree = 'actions[parameters,parameters[name,value]],result,duration,timestamp,number,url,estimatedDuration,builtOn'): ?Jenkins\Build
    {
        if ($tree !== null) {
            $tree = sprintf('?tree=%s', $tree);
        }
        $url  = sprintf('%s/job/%s/%d/api/json%s', $this->baseUrl, $job, $buildId, $tree);
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $this->validateCurl(
            $curl,
            sprintf('Error during getting information for build %s#%d on %s', $job, $buildId, $this->baseUrl)
        );

        $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);

        if (!$infos instanceof stdClass) {
            return null;
        }

        return new Jenkins\Build($infos, $this);
    }

    /**
     * @param string $job
     * @param int $buildId
     *
     * @return null|string
     */
    public function getUrlBuild(string $job, int $buildId): ?string
    {
        return (null === $buildId) ?
            $this->getUrlJob($job)
            : sprintf('%s/job/%s/%d', $this->baseUrl, $job, $buildId);
    }

    /**
     * @param string $computerName
     *
     * @return Jenkins\Computer
     * @throws RuntimeException
     */
    public function getComputer(string $computerName): ?Jenkins\Computer
    {
        $url  = sprintf('%s/computer/%s/api/json', $this->baseUrl, $computerName);
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $this->validateCurl(
            $curl,
            sprintf('Error during getting information for computer %s on %s', $computerName, $this->baseUrl)
        );

        $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);

        if (!$infos instanceof stdClass) {
            return null;
        }

        return new Jenkins\Computer($infos, $this);
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $job
     *
     * @return string
     */
    public function getUrlJob(string $job): string
    {
        return sprintf('%s/job/%s', $this->baseUrl, $job);
    }

    /**
     * getUrlView
     *
     * @param string $view
     *
     * @return string
     */
    public function getUrlView(string $view): string
    {
        return sprintf('%s/view/%s', $this->baseUrl, $view);
    }

    /**
     * @param string $jobname
     *
     * @return string
     *
     * @throws RuntimeException
     *@deprecated use getJobConfig instead
     *
     */
    public function retrieveXmlConfigAsString(string $jobname): string
    {
        return $this->getJobConfig($jobname);
    }

    /**
     * @param string $jobname
     * @param DomDocument $document
     *
     * @deprecated use setJobConfig instead
     */
    public function setConfigFromDomDocument(string $jobname, DomDocument $document): void
    {
        $this->setJobConfig($jobname, $document->saveXML());
    }

    /**
     * @param string $jobname
     * @param string $xmlConfiguration
     *
     * @throws InvalidArgumentException
     */
    public function createJob(string $jobname, string $xmlConfiguration): void
    {
        $url  = sprintf('%s/createItem?name=%s', $this->baseUrl, $jobname);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);

        curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlConfiguration);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $headers = ['Content-Type: text/xml'];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);

        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
            throw new InvalidArgumentException(sprintf('Job %s already exists', $jobname));
        }
        if (curl_errno($curl)) {
            throw new RuntimeException(sprintf('Error creating job %s', $jobname));
        }
    }

    /**
     * @param string $jobname
     * @param        $configuration
     *
     * @internal param string $document
     */
    public function setJobConfig(string $jobname, $configuration): void
    {
        $url  = sprintf('%s/job/%s/config.xml', $this->baseUrl, $jobname);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $configuration);

        $headers = ['Content-Type: text/xml'];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error during setting configuration for job %s', $jobname));
    }

    /**
     * @param string $jobname
     *
     * @return string
     */
    public function getJobConfig(string $jobname): string
    {
        $url  = sprintf('%s/job/%s/config.xml', $this->baseUrl, $jobname);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error during getting configuration for job %s', $jobname));

        return $ret;
    }

    /**
     * @param Jenkins\Executor $executor
     *
     * @throws RuntimeException
     */
    public function stopExecutor(Jenkins\Executor $executor): void
    {
        $url = sprintf(
            '%s/computer/%s/executors/%s/stop',
            $this->baseUrl,
            $executor->getComputer(),
            $executor->getNumber()
        );

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);

        $headers = [];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_exec($curl);

        $this->validateCurl(
            $curl,
            sprintf('Error during stopping executor #%s', $executor->getNumber())
        );
    }

    /**
     * @param Jenkins\JobQueue $queue
     *
     * @throws RuntimeException
     * @return void
     */
    public function cancelQueue(Jenkins\JobQueue $queue): void
    {
        $url = sprintf('%s/queue/item/%s/cancelQueue', $this->baseUrl, $queue->getId());

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);

        $headers = [];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_exec($curl);

        $this->validateCurl(
            $curl,
            sprintf('Error during stopping job queue #%s', $queue->getId())
        );
    }

    /**
     * @param string $computerName
     *
     * @return void
     *@throws RuntimeException
     */
    public function toggleOfflineComputer(string $computerName): void
    {
        $url  = sprintf('%s/computer/%s/toggleOffline', $this->baseUrl, $computerName);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);

        $headers = [];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error marking %s offline', $computerName));
    }

    /**
     * @param string $computerName
     *
     * @return void
     *@throws RuntimeException
     */
    public function deleteComputer(string $computerName): void
    {
        $url  = sprintf('%s/computer/%s/doDelete', $this->baseUrl, $computerName);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);

        $headers = [];

        if ($this->areCrumbsEnabled()) {
            $headers[] = $this->getCrumbHeader();
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error deleting %s', $computerName));
    }

    /**
     * @param string $jobname
     * @param string $buildNumber
     *
     * @return string
     */
    public function getConsoleTextBuild(string $jobname, string $buildNumber): string
    {
        $url  = sprintf('%s/job/%s/%s/consoleText', $this->baseUrl, $jobname, $buildNumber);
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        return curl_exec($curl);
    }

    /**
     * @param string $jobName
     * @param        $buildId
     *
     * @return array|Jenkins\TestReport
     * @internal param string $buildNumber
     *
     */
    public function getTestReport(string $jobName, $buildId): array|Jenkins\TestReport
    {
        $url  = sprintf('%s/job/%s/%d/testReport/api/json', $this->baseUrl, $jobName, $buildId);
        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($curl);

        $errorMessage = sprintf(
            'Error during getting information for build %s#%d on %s',
            $jobName,
            $buildId,
            $this->baseUrl
        );

        $this->validateCurl(
            $curl,
            $errorMessage
        );

        $infos = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);

        if (!$infos instanceof stdClass) {
            throw new RuntimeException($errorMessage);
        }

        return new Jenkins\TestReport($this, $infos, $jobName, $buildId);
    }

    /**
     * Returns the content of a page according to the jenkins base url.
     * Useful if you use jenkins plugins that provides specific APIs.
     * (e.g. "/cloud/ec2-us-east-1/provision")
     *
     * @param string $uri
     * @param array  $curlOptions
     *
     * @return string
     */
    public function execute(string $uri, array $curlOptions): string
    {
        $url  = $this->baseUrl . '/' . $uri;
        $curl = curl_init($url);
        curl_setopt_array($curl, $curlOptions);
        $ret = curl_exec($curl);

        $this->validateCurl($curl, sprintf('Error calling "%s"', $url));

        return $ret;
    }

    /**
     * @return Jenkins\Computer[]
     */
    public function getComputers(): array
    {
        $return = $this->execute(
            '/computer/api/json',
            [
                CURLOPT_RETURNTRANSFER => 1,
            ]
        );
        $infos  = json_decode($return, false, 512, JSON_THROW_ON_ERROR);
        if (!$infos instanceof stdClass) {
            throw new RuntimeException('Error during json_decode');
        }
        $computers = [];
        foreach ($infos->computer as $computer) {
            $computers[] = $this->getComputer($computer->displayName);
        }

        return $computers;
    }

    /**
     * @param string $computerName
     *
     * @return string
     */
    public function getComputerConfiguration(string $computerName): string
    {
        return $this->execute(sprintf('/computer/%s/config.xml', $computerName), [CURLOPT_RETURNTRANSFER => 1,]);
    }

    /**
     * Validate curl_error() and http_code in a cURL request
     *
     * @param $curl
     * @param $errorMessage
     */
    private function validateCurl($curl, $errorMessage): void
    {
        if (curl_errno($curl)) {
            throw new RuntimeException($errorMessage);
        }
        $info = curl_getinfo($curl);

        if ($info['http_code'] === 403) {
            throw new RuntimeException(sprintf('Access Denied [HTTP status code 403] to %s"', $info['url']));
        }
    }
}
