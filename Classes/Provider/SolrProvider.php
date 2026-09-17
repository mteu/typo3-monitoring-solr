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

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Result\MonitoringResult;
use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Solr\Configuration\SolrProviderConfiguration;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * SolrProvider.
 *
 * Monitors the Apache Solr connections configured for EXT:solr in the TYPO3 site
 * configuration. It reports four concerns:
 *
 *   1. configuration: Can every `solr_*_read` value actually be read?
 *   2. host: Is each configured Solr node reachable at all?
 *   3. cores: Does every configured core answer a ping?
 *   4. indexing errors: Did the index queue record indexing failures?
 *
 * The connections themselves are read from the site configuration (the same
 * source EXT:solr uses), so the probe never drifts from the production setup.
 *
 * An unreachable host or core is an outage (unhealthy). Indexing errors keep
 * the search backend serving queries and are reported as degraded by default.
 *
 * The provider is disabled by default and only active when EXT:solr is loaded.
 *
 * @internal
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SolrProvider implements MonitoringProvider
{
    /**
     * Number of failing item descriptions included in a reason to keep the
     * output readable on installations with a large index queue.
     */
    private const int SAMPLE_SIZE = 5;

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring_solr/Resources/Private/Language/locallang.be.xlf';

    public function __construct(
        private SolrProviderConfiguration $configuration,
        private SolrConnectionProvider $connectionProvider,
        private SolrClient $client,
        private IndexQueueRepository $indexQueue,
        private LoggerInterface $logger,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function getName(): string
    {
        return 'Solr';
    }

    public function getDescription(): string
    {
        return 'Monitors the Apache Solr connections configured for EXT:solr in the site '
            . 'configuration: whether each Solr node is reachable, whether every configured '
            . 'core answers a ping, and whether the index queue recorded indexing errors. '
            . 'Configuration values EXT:solr cannot use are reported rather than replaced '
            . 'by a default. Inactive when the solr extension is not installed or no site '
            . 'configures a Solr read connection.';
    }

    public function isEnabled(): bool
    {
        return $this->configuration->isEnabled();
    }

    public function isActive(): bool
    {
        // Active when there is something to probe *or* something to complain
        // about. Site configuration that cannot be read yields no connection,
        // and going inactive over it would report a broken Solr setup exactly
        // like an absent one: as silence.
        return ExtensionManagementUtility::isLoaded('solr')
            && !$this->connectionProvider->resolveConnections()->isEmpty();
    }

    public function execute(): Result
    {
        $result = new MonitoringResult($this->getName(), Status::Healthy);

        // Guaranteed non-empty: the handler only executes an active provider,
        // and isActive() requires a connection or a configuration problem.
        $set = $this->connectionProvider->resolveConnections();

        if ($set->problems !== []) {
            $result->addSubResult($this->buildConfigurationResult($set->problems));
        }

        if ($set->connections !== []) {
            $hostReachability = $this->probeHosts($set->connections);

            $result->addSubResult($this->buildHostResult($hostReachability));
            $result->addSubResult($this->buildCoreResult($set->connections, $hostReachability));
        }

        $result->addSubResult($this->checkIndexingErrors());

        if (!$result->isHealthy()) {
            $result->setReason($this->translate('provider.solr.unhealthy'));
        }

        return $result;
    }

    /**
     * A value EXT:solr cannot use is an outage in waiting: it does not fail the
     * probe, it makes the probe test something the site never talks to.
     *
     * @param list<SolrConfigurationProblem> $problems
     */
    private function buildConfigurationResult(array $problems): Result
    {
        $rendered = array_map(
            fn(SolrConfigurationProblem $problem): string => $this->translate(
                'provider.solr.configuration.' . $problem->issue->value,
                $this->escapeHtml($problem->origin),
                $this->escapeHtml($problem->key),
                $this->escapeHtml($problem->value),
            ),
            $problems,
        );

        return new MonitoringResult(
            'Configuration',
            Status::Unhealthy,
            $this->translate(
                count($problems) > 1 ? 'provider.solr.configuration.plural' : 'provider.solr.configuration.singular',
                count($problems),
                $this->renderSampleList($rendered),
            ),
        );
    }

    /**
     * Probes each distinct Solr node once and returns a rootUri => reachable map.
     *
     * @param list<SolrConnection> $connections
     * @return array<string, bool>
     */
    private function probeHosts(array $connections): array
    {
        $reachability = [];

        foreach ($connections as $connection) {
            if (!array_key_exists($connection->rootUri, $reachability)) {
                $reachability[$connection->rootUri] = $this->client->isReachable($connection->hostProbeUri());
            }
        }

        return $reachability;
    }

    /**
     * @param array<string, bool> $hostReachability
     */
    private function buildHostResult(array $hostReachability): Result
    {
        $result = new MonitoringResult('Host', Status::Healthy);

        foreach ($hostReachability as $rootUri => $reachable) {
            $result->addSubResult(
                new MonitoringResult(
                    $rootUri,
                    $reachable ? Status::Healthy : Status::Unhealthy,
                    $this->translate(
                        $reachable ? 'provider.solr.host.reachable' : 'provider.solr.host.unreachable',
                        $this->escapeHtml($rootUri),
                    ),
                ),
            );
        }

        return $result;
    }

    /**
     * @param list<SolrConnection> $connections
     * @param array<string, bool> $hostReachability
     */
    private function buildCoreResult(array $connections, array $hostReachability): Result
    {
        $result = new MonitoringResult('Cores', Status::Healthy);
        $checked = false;

        /** @var array<string, bool> $probed */
        $probed = [];

        foreach ($connections as $connection) {
            $coreProbeUri = $connection->coreProbeUri();

            // No core configured, or the node is already down — the host
            // sub-result covers that outage, so do not double-report it here.
            if ($coreProbeUri === null || ($hostReachability[$connection->rootUri] ?? false) === false) {
                continue;
            }

            $checked = true;
            // Languages sharing a core (the common case once the core is declared
            // site-wide) must not each cost a request, but they keep their own
            // sub-result so the origin stays visible.
            $reachable = $probed[$coreProbeUri] ??= $this->client->isReachable($coreProbeUri);

            $result->addSubResult(
                new MonitoringResult(
                    $connection->label,
                    $reachable ? Status::Healthy : Status::Unhealthy,
                    $this->translate(
                        $reachable ? 'provider.solr.core.reachable' : 'provider.solr.core.unreachable',
                        $this->escapeHtml($connection->core),
                    ),
                ),
            );
        }

        if (!$checked) {
            $result->setReason($this->translate('provider.solr.cores.none'));
        }

        return $result;
    }

    private function checkIndexingErrors(): Result
    {
        try {
            $count = $this->indexQueue->countIndexingErrors();
        } catch (\Throwable $exception) {
            $this->logger->error('Solr index queue query failed.', [
                'exception' => $exception->getMessage(),
            ]);

            return new MonitoringResult(
                'Indexing Errors',
                Status::Unhealthy,
                $this->translate('provider.solr.indexing.queryFailure', $this->escapeHtml($exception->getMessage())),
            );
        }

        if ($count === 0) {
            return new MonitoringResult('Indexing Errors', Status::Healthy, $this->translate('provider.solr.indexing.none'));
        }

        return new MonitoringResult(
            'Indexing Errors',
            $this->configuration->indexingErrorSeverity,
            $this->translate(
                $count > 1 ? 'provider.solr.indexing.plural' : 'provider.solr.indexing.singular',
                $count,
                $this->renderSampleList($this->safeIndexingErrorSample()),
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function safeIndexingErrorSample(): array
    {
        try {
            return array_map(
                $this->escapeHtml(...),
                $this->indexQueue->getIndexingErrorSample(self::SAMPLE_SIZE),
            );
        } catch (\Throwable $exception) {
            $this->logger->warning('Could not fetch Solr indexing error sample.', [
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<string> $sample
     */
    private function renderSampleList(array $sample): string
    {
        if ($sample === []) {
            return '.';
        }

        $items = array_map(
            static fn(string $item): string => '<li>' . $item . '</li>',
            $sample,
        );

        return ':<ul>' . implode('', $items) . '</ul>';
    }

    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5);
    }

    private function translate(string $key, int|float|string ...$args): string
    {
        $label = $this->getLanguageService()->sL(self::LOCALLANG_FILE . ':' . $key);

        return $args === [] ? $label : sprintf($label, ...$args);
    }

    private function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }
}
