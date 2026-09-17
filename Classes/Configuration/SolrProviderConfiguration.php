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

namespace mteu\Monitoring\Solr\Configuration;

use mteu\Monitoring\Configuration\Provider\ProviderConfiguration;
use mteu\Monitoring\Result\Status;
use mteu\TypedExtConf\Attribute\ExtConfProperty;
use mteu\TypedExtConf\Attribute\ExtensionConfig;

/**
 * SolrProviderConfiguration.
 *
 * The Solr connections themselves (host, port, core, …) are not configured here:
 * they are read from the TYPO3 site configuration, the very same source EXT:solr
 * uses at runtime. This keeps the health probe and the production configuration
 * from drifting apart. Only the toggle, the probe timeout and the indexing-error
 * severity live here.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[ExtensionConfig(extensionKey: 'monitoring_solr')]
final readonly class SolrProviderConfiguration implements ProviderConfiguration
{
    public function __construct(
        /**
         * Installing this extension is itself the opt-in, so the provider is on
         * by default — unlike a provider bundled with EXT:monitoring, which every
         * installation would carry whether or not it runs Solr.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Solr\\SolrProvider.enabled')]
        private bool $enabled = true,

        /**
         * Maximum time (in seconds) to wait for a host or a core to respond
         * before it is treated as unreachable.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Solr\\SolrProvider.timeout')]
        public int $timeout = 5,

        /**
         * Severity reported when the index queue contains indexing errors.
         * Defaults to degraded: indexing errors are attention-worthy but, unlike
         * an unreachable host or core, do not take the search backend offline.
         */
        #[ExtConfProperty(path: 'provider.mteu\\Monitoring\\Solr\\SolrProvider.indexingErrorSeverity')]
        public Status $indexingErrorSeverity = Status::Degraded,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
