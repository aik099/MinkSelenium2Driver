<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Tests\Driver\Basic\BasicAuthTest;
use Behat\Mink\Tests\Driver\Basic\HeaderTest;
use Behat\Mink\Tests\Driver\Basic\StatusCodeTest;
use Behat\Mink\Tests\Driver\Css\HoverTest;
use Behat\Mink\Tests\Driver\Custom\SeleniumSupportTest;
use Behat\Mink\Tests\Driver\Form\Html5Test;
use Behat\Mink\Tests\Driver\Js\EventsTest;
use Behat\Mink\Tests\Driver\Js\JavascriptTest;

class Selenium2Config extends AbstractConfig
{

    /**
     * @var integer
     */
    protected $seleniumMajorVersion;

    public function __construct()
    {
        $this->seleniumMajorVersion = (int) explode('.', $_SERVER['SELENIUM_VERSION'] ?? '')[0];
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function createDriver(): DriverInterface
    {
        $browser = getenv('WEB_FIXTURES_BROWSER') ?: 'firefox';
        $seleniumHost = $_SERVER['DRIVER_URL'];

        return new Selenium2Driver($browser, null, $seleniumHost);
    }

    public function mapRemoteFilePath($file): string
    {
        if (!isset($_SERVER['TEST_MACHINE_BASE_PATH'])) {
            $webFixturesPath = dirname(__DIR__) . '/vendor/mink/driver-testsuite/web-fixtures';
            $_SERVER['TEST_MACHINE_BASE_PATH'] = realpath($webFixturesPath) . DIRECTORY_SEPARATOR;
        }

        return parent::mapRemoteFilePath($file);
    }

    public function skipMessage($testCase, $test): ?string
    {
        $testCallback = [$testCase, $test];

        if ([Html5Test::class, 'testHtml5Types'] === $testCallback) {
            return <<<TEXT
WebDriver does not support setting value in color inputs.

See https://code.google.com/p/selenium/issues/detail?id=7650.
TEXT;
        }

        if ('Behat\Mink\Tests\Driver\Js\WindowTest' === $testCase
            && (0 === strpos($test, 'testWindowMaximize'))
            && 'true' === getenv('GITHUB_ACTIONS')
        ) {
            return 'Maximizing the window does not work when running the browser in Xvfb.';
        }

        if (BasicAuthTest::class === $testCase) {
            return 'Basic auth is not supported.';
        }

        if (HeaderTest::class === $testCase) {
            return 'Headers are not supported.';
        }

        if (StatusCodeTest::class === $testCase) {
            return 'Checking status code is not supported.';
        }

        if ([JavascriptTest::class, 'testDragDropOntoHiddenItself'] === $testCallback) {
            $browser = $_SERVER['WEB_FIXTURES_BROWSER'] ?? null;

            if ($browser === 'firefox' && $this->getSeleniumMajorVersion() === 2) {
                return 'The Firefox browser compatible with Selenium 2.x does not fully implement drag-n-drop support.';
            }
        }

        // Skip right-clicking tests, when an unsupported Selenium version detected.
        if (([HoverTest::class, 'testRightClickHover'] === $testCallback || [EventsTest::class, 'testRightClick'] === $testCallback)
            && !$this->isRightClickingInSeleniumSupported()
        ) {
            return <<<TEXT
Selenium 3.x does not support right-clicking via JsonWireProtocol.

See https://github.com/SeleniumHQ/selenium/commit/085ceed1f55fbaaa1d419b19c73264415c394905.
TEXT;
        }

        // Skips all tests, except mentioned below, for an unsupported Selenium version.
        if ([SeleniumSupportTest::class, 'testDriverCannotBeUsedInUnsupportedSelenium'] !== $testCallback
            && !$this->isSeleniumVersionSupported()
        ) {
            return 'Does not apply to unsupported Selenium versions.';
        }

        return parent::skipMessage($testCase, $test);
    }

    protected function supportsCss(): bool
    {
        return true;
    }

    public function isRightClickingInSeleniumSupported(): bool
    {
        return $this->getSeleniumMajorVersion() < 3;
    }

    public function isSeleniumVersionSupported(): bool
    {
        return $this->getSeleniumMajorVersion() < 4;
    }

    protected function getSeleniumMajorVersion(): int
    {
        return $this->seleniumMajorVersion;
    }
}
