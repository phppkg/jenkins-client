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
use Throwable;
use Toolkit\FsUtil\File;
use Toolkit\Stdlib\Helper\Assert;
use Toolkit\Stdlib\Obj;
use Toolkit\Stdlib\Obj\DataObject;
use function explode;
use function is_file;
use function md5;
use function sprintf;

/**
 * class Jenkins
 *
 * @author inhere
 * @date 2022/11/16
 */
class Jenkins // extends AbstractObj
{
    use JenkinsCrumbTrait;

    /**
     * Jenkins server host URL
     *
     * @var string
     */
    private string $baseUrl;

    /**
     * Jenkins username
     *
     * @var string
     */
    public string $username = '';

    /**
     * Jenkins user password
     *
     * - usage: https://USER:PASSWORD@some.com/api/json
     *
     * @var string
     */
    public string $password = '';

    /**
     * Jenkins user token.
     *
     * - usage: https://USER:TOKEN@some.com/api/json
     *
     * @var string
     */
    public string $apiToken = '';

    /**
     * @var bool cache jenkins info
     */
    public bool $enableCache = false;

    /**
     * @var string cache dir
     */
    public string $cacheDir = '';

    /**
     * @var DataObject|null all jenkins info by /api/json
     */
    private DataObject|null $jenkins = null;

    /**
     * @var AbstractClient|null
     */
    private ?AbstractClient $httpClient = null;

    /**
     * @param string $baseUrl Jenkins server host URL
     * @param array $config = [
     *     'enableCache' => false,
     *     'cacheDir' => '',
     *     'username' => '',
     *     'apiToken' => '',
     *     'password' => '',
     *  ]
     */
    public static function new(string $baseUrl, array $config = []): self
    {
        return new self($baseUrl, $config);
    }

    /**
     * @param string $baseUrl Jenkins server host URL
     * @param array $config = [
     *     'enableCache' => false,
     *     'cacheDir' => '',
     *     'username' => '',
     *     'apiToken' => '',
     *     'password' => '',
     *  ]
     */
    public function __construct(string $baseUrl, array $config = [])
    {
        $this->baseUrl = $baseUrl;

        if ($config) {
            Obj::init($this, $config);
        }
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
    public function refreshCache(): void
    {
        $this->loadJenkinsInfo();
    }

    /**
     * @return void
     */
    private function initialize(): void
    {
        if (null !== $this->jenkins) {
            return;
        }

        if ($this->enableCache && $this->cacheDir) {
            $cacheFile = $this->getCacheFile();

            if (is_file($cacheFile)) {
                $this->jenkins = DataObject::fromJson(File::readAll($cacheFile));
                return;
            }
        }

        $this->loadJenkinsInfo();
    }

    private function loadJenkinsInfo(): void
    {
        $url = $this->buildUrl('/api/json');
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException('Error on get list of jobs');
        }

        $this->jenkins = $cli->getDataObject();
        if ($this->jenkins->isEmpty()) {
            throw new RuntimeException('Error during json_decode the /api/json data');
        }

        if ($this->enableCache && $this->cacheDir) {
            File::mkdirSave($this->getCacheFile(), $this->jenkins->toString());
        }
    }

    /**
     * @return string
     */
    public function getCacheFile(): string
    {
        $filename = md5($this->baseUrl . $this->username) . '.json';

        return File::join($this->cacheDir, $filename);
    }

    /**
     * @return array
     */
    public function getAllJobs(): array
    {
        $this->initialize();

        // vdump(json_encode($this->jenkins));

        $jobs = [];
        foreach ($this->jenkins->jobs as $job) {
            $jobName = $job['name'];
            // add
            $jobs[$jobName] = [
                'name' => $jobName,
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
            $jobName = $job['name'];

            $jobs[$jobName] = $this->getJob($jobName);
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
            $url = $this->buildUrl('/computer/%s/executors/%s/api/json', $computer, $i);
            $cli = $this->getHttpClient()->get($url);

            if (!$cli->isSuccess()) {
                throw new RuntimeException(sprintf('Error on get information for executors[%s@%s]', $i, $computer));
            }

            $infos = $cli->getDataObject();

            $executors[] = new Jenkins\Executor($infos, $computer, $this);
        }

        return $executors;
    }

    /**
     * trigger hook build like use gitlab
     *
     * @param string $jobName
     * @param array $hookData
     *
     * @return bool
     */
    public function triggerHook(string $jobName, array $hookData = []): bool
    {
        $url = $this->buildUrl('/project/%s', $jobName);
        $cli = $this->getHttpClient()->post($url, $hookData);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error trying to launch job "%s"(%s)', $jobName, $url));
        }

        return true;
    }

    /**
     * trigger job build, can with parameters
     *
     * @param string $jobName
     * @param array $parameters
     *
     * @return bool
     */
    public function triggerBuild(string $jobName, array $parameters = []): bool
    {
        return $this->launchJob($jobName, $parameters);
    }

    /**
     * launch job build, can with parameters
     *
     * @param string $jobName
     * @param array $parameters
     *
     * @return bool
     */
    public function launchJob(string $jobName, array $parameters = []): bool
    {
        if (0 === count($parameters)) {
            $url = $this->buildUrl('/job/%s/build', $jobName);
        } else {
            $url = $this->buildUrl('/job/%s/buildWithParameters', $jobName);
        }

        $headers = [];
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $cli = $this->getHttpClient()->post($url, $parameters, $headers);

        if (!$cli->isSuccess()) {
            // $respStr = $cli->getResponseBody();
            throw new RuntimeException(sprintf('Error trying to launch job "%s"', $jobName));
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
        $url = $this->buildUrl('/job/%s/api/json', $jobName);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for job %s', $jobName));
        }

        $infos = $cli->getDataObject();

        return new Jenkins\Job($infos, $this);
    }

    /**
     * Get job build params definitions
     *
     * @param string $jobName
     * @param string $tree
     *
     * @return DataObject
     */
    public function getJobParams(string $jobName, string $tree = ''): DataObject
    {
        $tree = $tree ?: 'property[parameterDefinitions[description,name,type,choices,defaultParameterValue]]';
        $url  = $this->buildUrl('/job/%s/api/json?tree=%s', $jobName, $tree);
        $cli  = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for job %s', $jobName));
        }

        $data = $cli->getArrayData();

        $params = [];
        if (isset($data['property'])) {
            $params = Job::fmtBuildParams($data['property']);
        }

        return DataObject::new($params);
    }

    /**
     * @param string $jobName
     *
     * @return void
     */
    public function deleteJob(string $jobName): void
    {
        $headers = [];
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/job/%s/doDelete', $jobName);
        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error deleting job %s', $jobName));
        }
    }

    /**
     * @return Jenkins\Queue
     */
    public function getQueue(): Jenkins\Queue
    {
        $url = $this->buildUrl('/queue/api/json');
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException('Error on get information for queues');
        }

        $infos = $cli->getDataObject();

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
        $url = $this->buildUrl('/view/%s/api/json', rawurlencode($viewName));
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for view %s', $viewName));
        }

        $infos = $cli->getDataObject();

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

        $url = $this->buildUrl('/job/%s/%d/api/json%s', $job, $buildId, $tree);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for build %s#%d', $job, $buildId));
        }

        return new Jenkins\Build($cli->getDataObject(), $this);
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
            : $this->buildUrl('/job/%s/%d', $job, $buildId);
    }

    /**
     * @param string $computerName
     *
     * @return Jenkins\Computer
     */
    public function getComputer(string $computerName): Jenkins\Computer
    {
        $url = $this->buildUrl('/computer/%s/api/json', $computerName);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for computer %s', $computerName));
        }

        return new Jenkins\Computer($cli->getDataObject(), $this);
    }

    /**
     * @return string
     */
    public function getBaseUrl(): string
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
        return $this->buildUrl('/job/%s', $jobName);
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
        return $this->buildUrl('/view/%s', $view);
    }

    /**
     * @param string $jobName
     * @param string $xmlConfiguration
     */
    public function createJob(string $jobName, string $xmlConfiguration): void
    {
        $headers = ['Content-Type' => 'text/xml'];
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/createItem?name=%s', $jobName);
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
        $url = $this->buildUrl('/createItem?mode=copy&name=%s&from=%s', $jobName, $fromJob);
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
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/job/%s/config.xml', $jobName);
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
        $url = $this->buildUrl('/job/%s/config.xml', $jobName);
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
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/computer/%s/executors/%s/stop', $executor->getComputer(), $executor->getNumber());
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
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/queue/item/%s/cancelQueue', $queue->getId());
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
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/computer/%s/toggleOffline', $computerName);
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
        if ($this->isCrumbsEnabled()) {
            $headers = $this->getCrumbHeaders();
        }

        $url = $this->buildUrl('/computer/%s/doDelete', $computerName);
        $cli = $this->getHttpClient()->post($url, null, $headers);

        if (!$cli->isSuccess()) {
            throw new RuntimeException("Error on deleting computer $computerName");
        }
    }

    /**
     * @param string $jobName
     * @param int $buildNumber
     *
     * @return string
     */
    public function getBuildConsoleText(string $jobName, int $buildNumber): string
    {
        $url = $this->buildUrl('/job/%s/%d/consoleText', $jobName, $buildNumber);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get %s build#%d console text information', $jobName, $buildNumber));
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
        $url = $this->buildUrl('/job/%s/%d/testReport/api/json', $jobName, $buildId);
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException(sprintf('Error on get information for build %s#%d', $jobName, $buildId));
        }

        $infos = $cli->getDataObject();

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

        $infos = $cli->getDataObject();

        $computers = [];
        foreach ($infos->computer as $computer) {
            $computers[] = $this->getComputer($computer['displayName']);
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
        if ($this->username) {
            $tokenOrPasswd = $this->apiToken ?: $this->password;
            [$prefix, $host] = explode('://', $this->baseUrl, 2);

            return sprintf('%s://%s:%s@%s%s', $prefix, $this->username, $tokenOrPasswd, $host, $apiPath);
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
                // 'headers' => $this->apiToken ? ['token' => $this->apiToken] : [],
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
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @param bool $enableCache
     */
    public function setEnableCache(bool $enableCache): void
    {
        $this->enableCache = $enableCache;
    }

    /**
     * @param string $cacheDir
     */
    public function setCacheDir(string $cacheDir): void
    {
        $this->cacheDir = $cacheDir;
    }

}
