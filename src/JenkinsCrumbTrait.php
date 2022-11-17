<?php declare(strict_types=1);

namespace PhpPkg\JenkinsClient;

use RuntimeException;
use stdClass;
use function is_object;

/**
 * trait JenkinsCrumbTrait
 *
 * @author inhere
 * @date 2022/11/17
 */
trait JenkinsCrumbTrait
{

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
    private string $crumbValue = '';

    /**
     * The header to use for sending anti-CSRF crumbs
     *
     * Set when crumbs are enabled, by requesting a new crumb from Jenkins
     *
     * @var string
     */
    private string $crumbField = '';

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

        $this->crumbValue = $crumbResult->crumb;
        $this->crumbField = $crumbResult->crumbRequestField;
    }

    /**
     * @return stdClass
     */
    public function requestCrumb(): stdClass
    {
        $url = $this->buildUrl('/crumbIssuer/api/json');
        $cli = $this->getHttpClient()->get($url);

        if (!$cli->isSuccess()) {
            throw new RuntimeException('Error on get csrf crumb');
        }

        // {
        //  "_class":"hudson.security.csrf.DefaultCrumbIssuer",
        //  "crumb":"03a97f9f8d0071148f82da4d057790f9",
        //  "crumbRequestField":"Jenkins-Crumb"
        // }
        return $cli->getJsonObject();
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
    public function isCrumbsEnabled(): bool
    {
        return $this->crumbsEnabled;
    }

    /**
     * @return array
     */
    public function getCrumbHeaders(): array
    {
        return $this->crumbsEnabled ? [$this->crumbField => $this->crumbValue] : [];
    }

}