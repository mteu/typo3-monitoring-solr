<div align="center">

[![CGL](https://github.com/mteu/typo3-monitoring-solr/actions/workflows/cgl.yaml/badge.svg)](https://github.com/mteu/typo3-monitoring-solr/actions/workflows/cgl.yaml)
[![Tests](https://github.com/mteu/typo3-monitoring-solr/actions/workflows/tests.yaml/badge.svg?branch=main)](https://github.com/mteu/typo3-monitoring-solr/actions/workflows/tests.yaml)


<img src="Resources/Public/Icons/Extension.svg" width="64" height="64" alt="Extension Icon">

# TYPO3 Monitoring: Apache Solr


![TYPO3 Support](https://badgen.net/badge/TYPO3/v13/FF8700?icon=typo3)
![TYPO3 Support](https://badgen.net/badge/TYPO3/v14/FF8700?icon=typo3)
[![PHP Version Require](https://poser.pugx.org/mteu/typo3-monitoring-solr/require/php)](https://packagist.org/packages/mteu/typo3-monitoring-solr)
![Stability](https://typo3-badges.dev/badge/monitoring_solr/stability/badgen.svg)
![Latest version](https://typo3-badges.dev/badge/monitoring_solr/version/badgen.svg)
![Total downloads](https://typo3-badges.dev/badge/monitoring_solr/downloads/badgen.svg)
<!-- Generated with 🧡 at typo3-badges.dev -->

</div>

This package provides the TYPO3 CMS extension `EXT:monitoring_solr`, an add-on for [`EXT:monitoring`](https://github.com/mteu/typo3-monitoring) that reports
the health of the Apache Solr connections declared in your site configuration. It ships separately because obviously not
every installation runs or needs [`EXT:solr`](https://github.com/TYPO3-Solr/ext-solr).

## 🚀 Features

The `Solr` monitoring result aggregates up to four sub-results:

| Sub-result        | Healthy when …                                                                         | Severity when failing |
|-------------------|----------------------------------------------------------------------------------------|-----------------------|
| `Configuration`   | Every `solr_*_read` value present in a site configuration can actually be read.        | `unhealthy`           |
| `Host`            | Every distinct Solr node answers its system-info request (one sub-result per node).    | `unhealthy`           |
| `Cores`           | Every configured core on a reachable node answers an admin ping (one sub-result/core). | `unhealthy`           |
| `Indexing Errors` | No item in the EXT:solr index queue (`tx_solr_indexqueue_item`) recorded an error.     | `degraded` (default)  |

Monitoring targets derive directly from the site configuration, the very same `solr_*_read` keys `EXT:solr` itself uses
at runtime, including the fallback from a site language to the site level. No hard dependency on `EXT:solr`. The
monitoring provider reads the site configuration through TYPO3 core only, and reports itself inactive when the extension
is absent.

## 🔥 Quick Start

### Installation

```bash
composer require mteu/typo3-monitoring-solr
```

That's the whole setup. `EXT:monitoring` discovers the provider through its service tag. That's it.

### Configuration

In contrast to other providers, this one is enabled by default. Installing this extension is the opt-in.
Since Connections are read from the site settings, they're not configured here.
Only the toggle, the probe time-out, and the indexing-error severity:

```php
# config/system/settings.php

return [
    'EXTENSIONS' => [
        'monitoring_solr' => [
            'provider' => [
                'mteu\Monitoring\Solr\SolrProvider' => [
                    'enabled' => true,
                    'timeout' => 5,
                    'indexingErrorSeverity' => 'degraded',
                ],
            ],
        ],
    ],
];
```

### When it reports inactive

The provider stays inactive (rather than falsely healthy) while `EXT:solr` is not installed, or while no site
configures a Solr read connection. Configuration that is present but unusable is the exception. It keeps the provider
active and is reported, because a setup `EXT:solr` cannot read likely counts as a failure.

## 📙 Documentation
Please have a look at the [extension documentation](Documentation/README.md). It covers the site-configuration layout
this provider reads, the `/solr` path normalization, what counts as an unusable value, and notes on TLS and proxies.

## 🤝 Contributing
Contributions are very welcome! Please have a look at the [Contribution Guide](CONTRIBUTING.md). It lays out the
workflow of submitting new features or bugfixes.

## 🔒 Security
Please refer to the [Security Policy](SECURITY.md) if you discover a security vulnerability in
this extension. Be warned, though. I cannot afford bounty. This is a private project.

## 💛 Acknowledgements
This extension exists because of [`EXT:solr`](https://github.com/TYPO3-Solr/ext-solr) and the site-configuration
schema it defines. Reading probe targets from that same source is what keeps this health check from drifting away
from the connections actually in use.

## ⭐ License
This extension is licensed under the [GPL-2.0-or-later](LICENSE.txt) license.

## 💬 Support
For issues and feature requests, please use the [GitHub issue tracker](https://github.com/mteu/typo3-monitoring-solr/issues).
