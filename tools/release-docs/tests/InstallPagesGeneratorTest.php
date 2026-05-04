<?php

declare(strict_types=1);

namespace OpenEMR\ReleaseDocs\Tests;

use OpenEMR\ReleaseDocs\InstallPagesGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InstallPagesGeneratorTest extends TestCase
{
    private const TEMPLATE_DIR = __DIR__ . '/../templates';
    private const FIXTURE_DIR = __DIR__ . '/fixtures/install-pages';

    public function testRenderInstallMatchesFixtureSnapshot(): void
    {
        $rendered = (new InstallPagesGenerator(self::TEMPLATE_DIR))->renderInstall('8.1.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-install-8.1.0.md', $rendered);
    }

    public function testRenderUpgradeMatchesFixtureSnapshot(): void
    {
        $rendered = (new InstallPagesGenerator(self::TEMPLATE_DIR))->renderUpgrade('8.1.0', '8.0.0');

        self::assertStringEqualsFile(self::FIXTURE_DIR . '/expected-upgrade-8.1.0-from-8.0.0.md', $rendered);
    }

    public function testRenderInstallIsDeterministic(): void
    {
        $generator = new InstallPagesGenerator(self::TEMPLATE_DIR);

        self::assertSame($generator->renderInstall('8.1.0'), $generator->renderInstall('8.1.0'));
    }

    public function testRenderInstallSubstitutesAllVersionPlaceholders(): void
    {
        $rendered = (new InstallPagesGenerator(self::TEMPLATE_DIR))->renderInstall('99.99.99');

        self::assertStringNotContainsString('__VERSION__', $rendered);
        self::assertStringContainsString('99.99.99', $rendered);
    }

    public function testRenderUpgradeIncludesPreviousVersion(): void
    {
        $rendered = (new InstallPagesGenerator(self::TEMPLATE_DIR))->renderUpgrade('8.1.0', '7.0.3');

        self::assertStringContainsString('A working OpenEMR 7.0.3 installation', $rendered);
        self::assertStringNotContainsString('__PREVIOUS_VERSION__', $rendered);
    }

    public function testThrowsWhenTemplateMissing(): void
    {
        $generator = new InstallPagesGenerator('/nonexistent/path');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Template not readable');

        $generator->renderInstall('8.1.0');
    }

    public function testThrowsWhenPlaceholderUnsubstituted(): void
    {
        $tmp = sys_get_temp_dir() . '/install-pages-test-' . bin2hex(random_bytes(4));
        mkdir($tmp);
        file_put_contents($tmp . '/install.md.template', "version: __VERSION__ uses __ORPHAN__ token");

        try {
            $generator = new InstallPagesGenerator($tmp);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsubstituted placeholder');
            $generator->renderInstall('8.1.0');
        } finally {
            unlink($tmp . '/install.md.template');
            rmdir($tmp);
        }
    }
}
