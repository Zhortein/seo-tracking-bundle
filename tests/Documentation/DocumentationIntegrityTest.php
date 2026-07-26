<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Documentation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Zhortein\SeoTrackingBundle\DependencyInjection\Configuration;

final class DocumentationIntegrityTest extends TestCase
{
    private const array REQUIRED_GUIDES = [
        'docs/index.md',
        'docs/quick-start.md',
        'docs/configuration.md',
        'docs/cookbook.md',
        'docs/data-model.md',
        'docs/custom-entities.md',
        'docs/easylyse-forwarding.md',
    ];

    public function testRequiredGuidesExist(): void
    {
        foreach (self::REQUIRED_GUIDES as $path) {
            self::assertFileExists($this->repositoryRoot().'/'.$path);
        }
    }

    public function testDocumentationIndexLinksEveryGuide(): void
    {
        $root = $this->repositoryRoot();
        $index = $this->read($root.'/docs/index.md');

        foreach ($this->documentationFiles() as $file) {
            if ($file === $root.'/docs/index.md') {
                continue;
            }

            $relativePath = substr($file, strlen($root.'/docs/'));
            self::assertStringContainsString(
                sprintf('(%s)', $relativePath),
                $index,
                sprintf('%s is missing from docs/index.md.', $relativePath),
            );
        }
    }

    public function testEveryLocalMarkdownLinkResolves(): void
    {
        foreach ($this->markdownFiles() as $file) {
            $markdown = $this->read($file);
            preg_match_all('/\[[^\]]*]\(([^)\s]+)(?:\s+["\'][^)]*)?\)/', $markdown, $matches);

            /** @var list<string> $targets */
            $targets = $matches[1] ?? [];
            foreach ($targets as $target) {
                if ('' === $target
                    || str_starts_with($target, '#')
                    || 1 === preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
                    continue;
                }

                $path = rawurldecode(explode('#', $target, 2)[0]);
                self::assertFileExists(
                    dirname($file).'/'.$path,
                    sprintf('Broken local Markdown link "%s" in %s.', $target, $file),
                );
            }
        }
    }

    public function testConfigurationReferenceCoversEveryPublicKey(): void
    {
        $reference = $this->read($this->repositoryRoot().'/docs/configuration.md');
        $configuration = (new Processor())->processConfiguration(new Configuration(), []);

        foreach ($this->flattenConfiguration($configuration) as $key) {
            self::assertStringContainsString(
                sprintf('`%s`', $key),
                $reference,
                sprintf('The public configuration key "%s" is undocumented.', $key),
            );
        }
    }

    public function testQuickStartContainsExecutableVerificationSteps(): void
    {
        $quickStart = $this->read($this->repositoryRoot().'/docs/quick-start.md');

        foreach ([
            'composer require zhortein/seo-tracking-bundle',
            'php bin/console debug:router seo_tracking_page_call',
            'php bin/console make:migration',
            'php bin/console doctrine:migrations:migrate',
            "seo_tracking('generic')",
            'php bin/console asset-map:compile',
            'seo_tracking_statistics()',
        ] as $expected) {
            self::assertStringContainsString($expected, $quickStart);
        }
    }

    /**
     * @return list<string>
     */
    private function markdownFiles(): array
    {
        $root = $this->repositoryRoot();
        $files = [
            $root.'/README.md',
            $root.'/CHANGELOG.md',
            $root.'/CONTRIBUTING.md',
            $root.'/FEATURE_IDEAS.md',
            ...$this->documentationFiles(),
        ];
        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function documentationFiles(): array
    {
        $root = $this->repositoryRoot();
        $files = [
            ...(glob($root.'/docs/*.md') ?: []),
            ...(glob($root.'/docs/releases/*.md') ?: []),
        ];
        sort($files);

        return $files;
    }

    private function repositoryRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return list<string>
     */
    private function flattenConfiguration(array $configuration, string $prefix = ''): array
    {
        $keys = [];
        foreach ($configuration as $key => $value) {
            $path = '' === $prefix ? $key : $prefix.'.'.$key;
            if (is_array($value)) {
                array_push($keys, ...$this->flattenConfiguration($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
