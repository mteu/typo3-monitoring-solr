<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring_solr".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace mteu\Monitoring\Solr\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Reads Solr read connections from the TYPO3 site configuration.
 *
 * EXT:solr stores its connection settings as `solr_*_read` keys (scheme, host,
 * port, path, core). Every one of them may be declared globally on the site and
 * overridden per site language; a key absent from the language falls back to the
 * site level. Sourcing the probe targets from there means the monitoring check
 * always reflects the connections actually used in production, with no separate
 * list to keep in sync.
 *
 * Values that are present but unusable are not silently replaced by a default.
 * A default would point the probe at something the production setup does not
 * use — the very drift this provider exists to catch — so they are collected as
 * {@see SolrConfigurationProblem} and reported.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsAlias(SolrConnectionProvider::class)]
final readonly class SiteConfigurationSolrConnectionProvider implements SolrConnectionProvider
{
    private const int DEFAULT_PORT = 8983;

    /**
     * Solr serves below a `/solr` segment, and Solarium — which EXT:solr uses —
     * appends it itself after stripping a configured trailing one. Since EXT:solr
     * 12 the site configuration therefore must not carry it, while older setups
     * still do. Both conventions are normalised to the same URI.
     */
    private const string SOLR_SEGMENT = 'solr';

    /**
     * Whitespace `trim()` leaves behind: everything in the Unicode separator
     * category (a non-breaking space above all) plus the byte order mark.
     */
    private const string EXOTIC_WHITESPACE_PATTERN = '/^[\s\p{Z}\x{FEFF}]+|[\s\p{Z}\x{FEFF}]+$/u';

    public function __construct(
        private SiteFinder $siteFinder,
        private LoggerInterface $logger,
    ) {}

    public function resolveConnections(): SolrConnectionSet
    {
        $connections = [];
        $problems = [];

        foreach ($this->siteFinder->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $language) {
                $connection = $this->buildConnection($site, $language, $problems);

                if ($connection !== null) {
                    $connections[] = $connection;
                }
            }
        }

        return new SolrConnectionSet($connections, $problems);
    }

    /**
     * @param list<SolrConfigurationProblem> $problems
     */
    private function buildConnection(Site $site, SiteLanguage $language, array &$problems): ?SolrConnection
    {
        $origin = $this->describeOrigin($site, $language);
        $configuration = $this->resolveConfiguration($site, $language);

        if (!$this->readBool($configuration, 'solr_enabled_read', $origin, $problems)) {
            return null;
        }

        $host = $this->readString($configuration, 'solr_host_read', '', $origin, $problems);

        if ($host === '') {
            $this->logger->warning('Skipping Solr connection with empty host.', [
                'site' => $site->getIdentifier(),
                'language' => $language->getLanguageId(),
            ]);

            return null;
        }

        $scheme = $this->readString($configuration, 'solr_scheme_read', 'http', $origin, $problems);
        $scheme = $scheme !== '' ? $scheme : 'http';
        $port = $this->readInt($configuration, 'solr_port_read', self::DEFAULT_PORT, $origin, $problems);
        $path = $this->readString($configuration, 'solr_path_read', '', $origin, $problems);
        $core = $this->readString($configuration, 'solr_core_read', '', $origin, $problems);

        $rootUri = sprintf(
            '%s://%s%s%s/%s',
            $scheme,
            $host,
            $port > 0 ? ':' . $port : '',
            $this->normalizePath($path),
            self::SOLR_SEGMENT,
        );

        return new SolrConnection(
            $core !== '' ? $origin . ' (' . $core . ')' : $origin,
            $rootUri,
            $core,
        );
    }

    /**
     * Language configuration on top of the site's, mirroring how EXT:solr resolves
     * a connection property: language first, site level as the fallback.
     *
     * @return array<array-key, mixed>
     */
    private function resolveConfiguration(Site $site, SiteLanguage $language): array
    {
        return array_merge($site->getConfiguration(), $language->toArray());
    }

    /**
     * Strips the `/solr` segment when configured (pre-EXT:solr 12 style) so it can
     * be appended exactly once, and returns the remainder with a leading slash.
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        $segments = $path === '' ? [] : explode('/', $path);

        if ($segments !== [] && end($segments) === self::SOLR_SEGMENT) {
            array_pop($segments);
        }

        return $segments === [] ? '' : '/' . implode('/', $segments);
    }

    /**
     * @return non-empty-string
     */
    private function describeOrigin(Site $site, SiteLanguage $language): string
    {
        return sprintf(
            '%s / %s',
            $site->getIdentifier(),
            $language->getTitle() !== '' ? $language->getTitle() : 'language ' . $language->getLanguageId(),
        );
    }

    /**
     * @param array<array-key, mixed> $configuration
     * @param non-empty-string $key
     * @param non-empty-string $origin
     * @param list<SolrConfigurationProblem> $problems
     */
    private function readBool(array $configuration, string $key, string $origin, array &$problems): bool
    {
        $raw = $configuration[$key] ?? null;

        if ($raw === null) {
            return false;
        }

        if (is_bool($raw)) {
            return $raw;
        }

        $value = $this->readClean($configuration, $key, '', $origin, $problems);

        if ($value === '') {
            return false;
        }

        $boolean = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolean === null) {
            $problems[] = new SolrConfigurationProblem(
                SolrConfigurationIssue::NotBoolean,
                $origin,
                $key,
                $value,
            );

            return false;
        }

        return $boolean;
    }

    /**
     * @param array<array-key, mixed> $configuration
     * @param non-empty-string $key
     * @param non-empty-string $origin
     * @param list<SolrConfigurationProblem> $problems
     */
    private function readInt(array $configuration, string $key, int $default, string $origin, array &$problems): int
    {
        $raw = $configuration[$key] ?? null;

        if ($raw === null) {
            return $default;
        }

        $value = $this->readClean($configuration, $key, '', $origin, $problems);

        if (!is_numeric($value)) {
            $problems[] = new SolrConfigurationProblem(
                SolrConfigurationIssue::NotNumeric,
                $origin,
                $key,
                $value,
            );

            return $default;
        }

        return (int)$value;
    }

    /**
     * @param array<array-key, mixed> $configuration
     * @param non-empty-string $key
     * @param non-empty-string $origin
     * @param list<SolrConfigurationProblem> $problems
     */
    private function readString(array $configuration, string $key, string $default, string $origin, array &$problems): string
    {
        return $this->readClean($configuration, $key, $default, $origin, $problems);
    }

    /**
     * Reads a scalar value and strips the whitespace `trim()` misses, recording a
     * problem when it finds any: EXT:solr does not strip it either and turns such
     * a value into 0 or false, so a probe that quietly cleaned it up would report
     * a connection the site cannot actually use.
     *
     * @param array<array-key, mixed> $configuration
     * @param non-empty-string $key
     * @param non-empty-string $origin
     * @param list<SolrConfigurationProblem> $problems
     */
    private function readClean(array $configuration, string $key, string $default, string $origin, array &$problems): string
    {
        $raw = $configuration[$key] ?? null;

        if (!is_scalar($raw)) {
            return $default;
        }

        $value = (string)$raw;
        $cleaned = preg_replace(self::EXOTIC_WHITESPACE_PATTERN, '', $value) ?? $value;

        if ($cleaned !== trim($value)) {
            $problems[] = new SolrConfigurationProblem(
                SolrConfigurationIssue::InvalidWhitespace,
                $origin,
                $key,
                $value,
            );
        }

        return $cleaned;
    }
}
