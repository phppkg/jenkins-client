<?php declare(strict_types=1);
/**
 * This file is part of phppkg/jenkins-client.
 *
 * @link     https://github.com/inhere
 * @author   https://github.com/inhere
 * @license  MIT
 */

namespace PhpPkg\JenkinsClient;

use PhpPkg\Http\Client\AbstractClient;
use PhpPkg\Http\Client\Client;
use PhpPkg\JenkinsClient\Jenkins\Job;
use RuntimeException;
use stdClass;
use Throwable;
use Toolkit\Stdlib\Helper\Assert;
use function explode;
use function sprintf;

/**
 * class Jenkins
 *
 * @author inhere
 * @date 2022/11/16
 */
class Jenkins // extends AbstractObj
{
    /**
     * @var string
     */
    private string $baseUrl;

    /**
     * Jenkins username
     *
     * @var string
     */
    private string $username = '';

    /**
     * Jenkins user password
     *
     * @var string
     */
    private string $password = '';

    /**
     * @var string
     */
    private string $apiToken = '';

    /**
     * @var null all jenkins info by /api/json
     */
    private $jenkins = null;

    /**
     * @var AbstractClient|null
     */
    private ?AbstractClient $httpClient;

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
    private string $crumb = '';

    /**
     * The header to use for sending anti-CSRF crumbs
     *
     * Set when crumbs are enabled, by requesting a new crumb from Jenkins
     *
     * @var string
     */
    private string $crumbRequestField = '';

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
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException('Error on get csrf crumb');
        }

        return $cli->getJsonObject();
    }

    public function getCrumbHeaders(): array
    {
        return [$this->crumbRequestField => $this->crumb];
    }

    /**
     * @return boolean
     */
    public function isAvailable(): bool
    {
        try {
            $this->getQueue();
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @return void
     */
    private function initialize(): void
    {
        if (null !== $this->jenkins) {
            return;
        }

        $url = $this->baseUrl . '/api/json';
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get list of jobs on %s', $this->baseUrl));
        }

        $this->jenkins = $cli->getJsonObject();
        if (!$this->jenkins instanceof stdClass) {
            throw new RuntimeException('Error during json_decode the /api/json data');
        }
    }

    /**
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
     */
    public function getExecutors(string $computer = '(master)'): array
    {
        $this->initialize();

        $executors = [];
        for ($i = 0; $i < $this->jenkins->numExecutors; $i++) {
            $url = sprintf('%s/computer/%s/executors/%s/api/json', $this->baseUrl, $computer, $i);
            $cli = $this->getHttpClient()->get($url);

            if (!$cli->isSuccess()) {
                throw new RuntimeException(sprintf('Error on get information for executors[%s@%s] on %s', $i, $computer, $this->baseUrl));
            }

            $infos = $cli->getJsonObject();

            $executors[] = new Jenkins\Executor($infos, $computer, $this);
        }

        return $executors;
    }

    /**
     * @param string $jobName
     * @param array $parameters
     *
     * @return bool
     */
    public function launchJob(string $jobName, array $parameters = []): bool
    {
        if (0 === count($parameters)) {
            $url = sprintf('%s/job/%s/build', $this->baseUrl, $jobName);
        } else {
            $url = sprintf('%s/job/%s/buildWithParameters', $this->baseUrl, $jobName);
        }

        $headers = [];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $cli = $this->getHttpClient()->post($url, $parameters, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error trying to launch job "%s"(%s)', $jobName, $url));
        }

        return true;
    }

    /**
     * @param string $jobName
     *
     * @return Job
     */
    public function getJob(string $jobName): Job
    {
        $url = sprintf('%s/job/%s/api/json', $this->baseUrl, $jobName);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for job %s on %s', $jobName, $this->baseUrl));
        }

        $infos = $cli->getJsonObject();

        return new Jenkins\Job($infos, $this);
    }

    /**
     * @param string $jobName
     *
     * @return void
     */
    public function deleteJob(string $jobName): void
    {
        $headers = [];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = sprintf('%s/job/%s/doDelete', $this->baseUrl, $jobName);
        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error deleting job %s on %s', $jobName, $this->baseUrl));
        }
    }

    /**
     * @return Jenkins\Queue
     */
    public function getQueue(): Jenkins\Queue
    {
        $url = sprintf('%s/queue/api/json', $this->baseUrl);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for queue on %s', $this->baseUrl));
        }

        $infos = $cli->getJsonObject();

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
     */
    public function getView(string $viewName): Jenkins\View
    {
        $url = sprintf('%s/view/%s/api/json', $this->baseUrl, rawurlencode($viewName));
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for view %s on %s', $viewName, $this->baseUrl));
        }

        $infos = $cli->getJsonObject();

        return new Jenkins\View($infos, $this);
    }

    /**
     * @param string $job
     * @param int $buildId
     * @param string $tree
     *
     * @return Jenkins\Build
     */
    public function getBuild(
        string $job,
        int $buildId,
        string $tree = 'actions[parameters,parameters[name,value]],result,duration,timestamp,number,url,estimatedDuration,builtOn'
    ): Jenkins\Build {
        if ($tree !== '') {
            $tree = sprintf('?tree=%s', $tree);
        }

        $url = sprintf('%s/job/%s/%d/api/json%s', $this->baseUrl, $job, $buildId, $tree);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for build %s#%d on %s', $job, $buildId, $this->baseUrl));
        }

        $infos = $cli->getJsonObject();

        return new Jenkins\Build($infos, $this);
    }

    /**
     * @param string $job
     * @param int $buildId
     *
     * @return string
     */
    public function getBuildUrl(string $job, int $buildId): string
    {
        return $buildId < 1 ?
            $this->getJobUrl($job)
            : sprintf('%s/job/%s/%d', $this->baseUrl, $job, $buildId);
    }

    /**
     * @param string $computerName
     *
     * @return Jenkins\Computer
     */
    public function getComputer(string $computerName): Jenkins\Computer
    {
        $url = sprintf('%s/computer/%s/api/json', $this->baseUrl, $computerName);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for computer %s', $computerName));
        }

        $infos = $cli->getJsonObject();
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
     * @param string $jobName
     *
     * @return string
     */
    public function getJobUrl(string $jobName): string
    {
        return sprintf('%s/job/%s', $this->baseUrl, $jobName);
    }

    /**
     * get View Url
     *
     * @param string $view
     *
     * @return string
     */
    public function getViewUrl(string $view): string
    {
        return sprintf('%s/view/%s', $this->baseUrl, $view);
    }

    /**
     * @param string $jobName
     * @param string $xmlConfiguration
     */
    public function createJob(string $jobName, string $xmlConfiguration): void
    {
        $headers = ['Content-Type' => 'text/xml'];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = sprintf('%s/createItem?name=%s', $this->baseUrl, $jobName);
        $cli = $this->getHttpClient()->post($url, $xmlConfiguration, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('The job %s already exists', $jobName));
        }
        // if (curl_errno($curl)) {
        // throw new RuntimeException(sprintf('Error creating job %s', $jobName));
        // }
    }

    /**
     * To copy a job, send a POST request to this URL with three query parameters name=NEWJOBNAME&mode=copy&from=FROMJOBNAME
     *
     * @param string $jobName new job name
     * @param string $fromJob from job name
     */
    public function createJobByCopy(string $jobName, string $fromJob): void
    {
        $url = sprintf('%s/createItem?mode=copy&name=%s&from=%s', $this->baseUrl, $jobName, $fromJob);
        $cli = $this->getHttpClient()->post($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('The job %s already exists', $jobName));
        }
    }

    /**
     * @param string $jobName
     * @param string $configuration
     */
    public function setJobConfig(string $jobName, string $configuration): void
    {
        $headers = ['Content-Type' => 'text/xml'];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = sprintf('%s/job/%s/config.xml', $this->baseUrl, $jobName);
        $cli = $this->getHttpClient()->post($url, $configuration, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error during setting configuration for job %s', $jobName));
        }
    }

    /**
     * @param string $jobName
     *
     * @return string
     */
    public function getJobConfig(string $jobName): string
    {
        $url = sprintf('%s/job/%s/config.xml', $this->baseUrl, $jobName);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get configuration for job %s', $jobName));
        }

        return $cli->getResponseBody();
    }

    /**
     * @param Jenkins\Executor $executor
     */
    public function stopExecutor(Jenkins\Executor $executor): void
    {
        $headers = [];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = sprintf(
            '%s/computer/%s/executors/%s/stop',
            $this->baseUrl,
            $executor->getComputer(),
            $executor->getNumber()
        );

        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error during stopping executor #%s', $executor->getNumber()));
        }
    }

    /**
     * @param Jenkins\JobQueue $queue
     *
     * @return void
     */
    public function cancelQueue(Jenkins\JobQueue $queue): void
    {
        $headers = [];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('%s/queue/item/%s/cancelQueue', $queue->getId());
        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error during stopping job queue #%s', $queue->getId()));
        }
    }

    /**
     * @param string $computerName
     *
     * @return void
     */
    public function toggleOfflineComputer(string $computerName): void
    {
        $headers = [];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('%s/computer/%s/toggleOffline', $computerName);
        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException("Error on marking $computerName offline");
        }
    }

    /**
     * @param string $computerName
     *
     * @return void
     */
    public function deleteComputer(string $computerName): void
    {
        $headers = [];
        if ($this->areCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('%s/computer/%s/doDelete', $computerName);
        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException("Error on deleting computer $computerName");
        }
    }

    /**
     * @param string $jobName
     * @param string $buildNumber
     *
     * @return string
     */
    public function getConsoleTextBuild(string $jobName, string $buildNumber): string
    {
        $url = $this->buildUrl('%s/job/%s/%s/consoleText', $jobName, $buildNumber);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException('Error on get computers information');
        }

        return $cli->getResponseBody();
    }

    /**
     * @param string $jobName
     * @param int $buildId
     *
     * @return Jenkins\TestReport
     */
    public function getTestReport(string $jobName, int $buildId): Jenkins\TestReport
    {
        $url = $this->buildUrl('%s/job/%s/%d/testReport/api/json', $jobName, $buildId);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for build %s#%d on %s',
                $jobName,
                $buildId,
                $this->baseUrl));
        }

        $infos = $cli->getJsonObject();

        return new Jenkins\TestReport($this, $infos, $jobName, $buildId);
    }

    /**
     * @return Jenkins\Computer[]
     */
    public function getComputers(): array
    {
        $url = $this->buildUrl('/computer/api/json');
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException('Error on get computers information');
        }

        $infos = $cli->getJsonObject();

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
    public function getComputerConfig(string $computerName): string
    {
        $url = $this->buildUrl('/computer/%s/config.xml', $computerName);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get computer "%s" configuration', $computerName));
        }

        return $cli->getResponseBody();
    }

    /**
     * @param string $pathFmt
     * @param mixed ...$args
     *
     * @return string
     */
    public function buildUrl(string $pathFmt, ...$args): string
    {
        $apiPath = $args ? sprintf($pathFmt, ...$args) : $pathFmt;
        Assert::notEmpty($this->baseUrl, 'jenkins base url cannot be empty');

        // with auth http://user:token@host.org:8080
        if ($this->username && $this->password) {
            [$prefix, $host] = explode('://', $this->baseUrl, 2);

            return sprintf('%s://%s:%s@%s%s', $prefix, $this->username, $this->password, $host, $apiPath);
        }

        return $this->baseUrl . $apiPath;
    }

    /**
     * @return AbstractClient
     */
    public function getHttpClient(): AbstractClient
    {
        if (!$this->httpClient) {
            $this->httpClient = Client::factory([
                'baseUrl' => $this->baseUrl,
                'headers' => $this->apiToken ? ['token' => $this->apiToken] : [],
            ]);
        }

        return $this->httpClient;
    }

    /**
     * @param AbstractClient $httpClient
     */
    public function setHttpClient(AbstractClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @return string
     */
    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    /**
     * @param string $apiToken
     */
    public function setApiToken(string $apiToken): void
    {
        $this->apiToken = $apiToken;
    }

    /**
     * @param string $username
     * @param string $passwd
     *
     * @return void
     */
    public function setUserAuth(string $username, string $passwd): void
    {
        $this->setUsername($username);
        $this->setPassword($passwd);
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

}
