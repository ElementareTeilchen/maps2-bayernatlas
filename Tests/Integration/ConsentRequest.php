<?php

declare(strict_types=1);

/**
 * CLI-only HTTP integration probe for an existing local TYPO3 map page.
 * Set TYPO3_TEST_ROOT to its project root; pass MODE and the full page URL.
 * Run each scenario in a fresh process. No configuration file or record changes.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

$mode = $argv[1] ?? '';
$url = $argv[2] ?? '';
$projectRoot = realpath(getenv('TYPO3_TEST_ROOT') ?: '');
$parts = parse_url($url);

if (
    !$projectRoot
    || !is_file($projectRoot . '/vendor/autoload.php')
    || !in_array($mode, ['disabled', 'denied', 'saved', 'grant', 'session'], true)
    || !is_array($parts)
    || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
    || empty($parts['host'])
) {
    fwrite(STDERR, "Set TYPO3_TEST_ROOT and pass disabled|denied|saved|grant|session and a local map page URL.\n");
    exit(1);
}

parse_str($parts['query'] ?? '', $_GET);
if (in_array($mode, ['grant', 'session'], true) && (int)($_GET['tx_maps2_maps2']['mapProviderRequestsAllowedForMaps2'] ?? 0) !== 1) {
    fwrite(STDERR, "For grant/session, use the actual activation URL returned by the denied scenario.\n");
    exit(1);
}

$_COOKIE = $mode === 'saved' ? ['mapProviderRequestsAllowedForMaps2' => '1'] : [];
$https = $parts['scheme'] === 'https';
$_SERVER = array_merge($_SERVER, [
    'HTTP_HOST' => $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''),
    'SERVER_NAME' => $parts['host'],
    'SERVER_PORT' => $parts['port'] ?? ($https ? 443 : 80),
    'HTTPS' => $https ? 'on' : 'off',
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''),
    'QUERY_STRING' => $parts['query'] ?? '',
    'SCRIPT_NAME' => '/index.php',
    'SCRIPT_FILENAME' => $projectRoot . '/public/index.php',
    'DOCUMENT_ROOT' => $projectRoot . '/public',
    'REMOTE_ADDR' => '127.0.0.1',
]);

putenv('TYPO3_PATH_ROOT=' . $projectRoot . '/public');
putenv('TYPO3_PATH_APP=' . $projectRoot);
$loader = require $projectRoot . '/vendor/autoload.php';
\TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::run(0, \TYPO3\CMS\Core\Core\SystemEnvironmentBuilder::REQUESTTYPE_FE);

// Override before service construction, including when system caches are cold.
// Abort rather than persisting the test values if TYPO3 synchronizes extension defaults.
class ConsentRequestConfigurationManager extends \TYPO3\CMS\Core\Configuration\ConfigurationManager
{
    public function writeLocalConfiguration(array $configuration)
    {
        throw new \RuntimeException('The consent probe must not write the host configuration.');
    }

    public function writeAdditionalConfiguration(array $additionalConfigurationLines)
    {
        throw new \RuntimeException('The consent probe must not write additional host configuration.');
    }
}

class ConsentRequestBootstrap extends \TYPO3\CMS\Core\Core\Bootstrap
{
    public static string $mode;

    public static function createConfigurationManager(): \TYPO3\CMS\Core\Configuration\ConfigurationManager
    {
        return new ConsentRequestConfigurationManager();
    }

    protected static function populateLocalConfiguration(\TYPO3\CMS\Core\Configuration\ConfigurationManager $configurationManager)
    {
        parent::populateLocalConfiguration($configurationManager);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['maps2']['explicitAllowMapProviderRequests'] = self::$mode === 'disabled' ? '0' : '1';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['maps2']['explicitAllowMapProviderRequestsBySessionOnly'] = self::$mode === 'session' ? '1' : '0';
    }
}

ConsentRequestBootstrap::$mode = $mode;
$container = ConsentRequestBootstrap::init($loader);
$request = \TYPO3\CMS\Core\Http\ServerRequestFactory::fromGlobals()->withCookieParams($_COOKIE)->withQueryParams($_GET);
$response = $container->get(\TYPO3\CMS\Core\Http\Application::class)->handle($request);
$html = (string)$response->getBody();
preg_match_all('/href="([^\"]*mapProviderRequestsAllowedForMaps2[^\"]*)"/', $html, $links);
$cookies = array_values(array_filter(
    $response->getHeader('Set-Cookie'),
    static fn(string $cookie): bool => str_starts_with($cookie, 'mapProviderRequestsAllowedForMaps2='),
));
$allowed = $mode !== 'denied';
$result = [
    'mode' => $mode,
    'status' => $response->getStatusCode(),
    'requiresConsent' => $container->get(\JWeiland\Maps2\Configuration\ExtConf::class)->getExplicitAllowMapProviderRequests(),
    'maps' => substr_count($html, 'class="bayernatlas-fluid"'),
    'notices' => substr_count($html, 'class="maps2-bayernatlas-consent"'),
    'componentScripts' => substr_count($html, 'src="https://atlas.bayern.de/wc.js"'),
    'mapModule' => str_contains($html, 'BayernAtlas.js'),
    'adapterModule' => str_contains($html, 'Maps2Adapter.js'),
    'localStyles' => str_contains($html, '/Css/Consent.css'),
    'activationUrls' => array_values(array_unique(array_map('html_entity_decode', $links[1]))),
    'consentCookies' => $cookies,
];
$result['passed'] = $result['status'] === 200
    && $result['requiresConsent'] === ($mode !== 'disabled')
    && ($result['maps'] > 0) === $allowed
    && ($result['notices'] > 0) === !$allowed
    && $result['componentScripts'] === ($allowed ? 1 : 0)
    && $result['mapModule'] === $allowed
    && $result['adapterModule'] === $allowed;

if (!$allowed) {
    $result['passed'] = $result['passed'] && $result['localStyles'] && $result['activationUrls'] !== [] && $cookies === [];
} elseif ($mode === 'disabled') {
    $result['passed'] = $result['passed'] && $cookies === [];
} else {
    $hasExpiry = isset($cookies[0]) && stripos($cookies[0], 'expires=') !== false;
    $result['passed'] = $result['passed'] && count($cookies) === 1 && $hasExpiry === ($mode !== 'session');
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
exit($result['passed'] ? 0 : 1);
