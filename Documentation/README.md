# Solr Monitoring Provider

Monitors the [Apache Solr](https://github.com/TYPO3-Solr/ext-solr) connections configured for your TYPO3 instance and reports their health on the
monitoring endpoint. A search backend can fail in several independent ways:
- a Solr node can be down,
- a single core can be missing,
- or indexing can quietly start failing while queries still work.

This provider reports these as distinct concerns.

The provider is enabled by default and inactive unless the `apache-solr-for-typo3/solr` extension is installed,
and at least one site configures a Solr read connection.

Without one (e.g. before any site configuration or root page exists) there
is nothing to probe, so the provider reports inactive rather than a misleading "healthy". Configuration that is present
but unusable is the exception. It keeps the provider active and is reported, because a setup `EXT:solr` cannot read is a failure, not an absence.

## No connection configuration to maintain

The provider reads the read connections straight from the TYPO3 site configuration. Those are the same
`solr_*_read` settings (scheme, host, port, path, core) that EXT:solr itself uses
at runtime, configured in the *Sites* backend module or in `config/sites/<site>/config.yaml`.

Every setting may be declared once for the whole site and overridden per site
language. A key absent from the language falls back to the site level. Both halves of this layout are read:

```yaml
solr_enabled_read: true
solr_scheme_read: https
solr_port_read: 8443
solr_path_read: /
languages:
  - title: English
    solr_host_read: solr-host-1
    solr_core_read: core_en
  - title: German
    solr_host_read: solr-host-2
    solr_core_read: core_de
```

Because the probe and EXT:solr read from the same source, the health check can
never drift away from the connections actually in use in production. Add a
language, point a site at a different Solr node, disable a read connection — the
monitoring check follows automatically.

## What it checks

`execute()` returns one aggregate result (`Solr`) composed of up to four
sub-results. The aggregate is healthy only when all checks pass.

| Sub-result        | Healthy when …                                                                         | Severity when failing |
|-------------------|----------------------------------------------------------------------------------------|-----------------------|
| `Configuration`   | Every `solr_*_read` value present in a site configuration can actually be read.        | `unhealthy`           |
| `Host`            | Every distinct Solr node answers its system-info request (one sub-result per node).    | `unhealthy`           |
| `Cores`           | Every configured core on a reachable node answers an admin ping (one sub-result/core). | `unhealthy`           |
| `Indexing Errors` | No item in the EXT:solr index queue (`tx_solr_indexqueue_item`) recorded an error.     | `degraded` (default)  |

> [!NOTE]
> - The `Configuration` sub-result only appears when there is something to report.
> - Nodes are de-duplicated, so several cores sharing a node are probed against the same host once. Languages resolving to the same core probe it once too, while keeping one sub-result each.
> - When a node is unreachable its cores are not probed separately — the `Host` sub-result already reports that outage.
> - The indexing-errors reason lists up to 5 affected items (`item_type:item_uid`).
> - Reachability is probed over HTTP via TYPO3's request factory; a connection failure, timeout, or non-200 status all count as unreachable.

## Unusable values are reported, not defaulted

A value that is present but cannot be read is never replaced by default.
A default would point the probe at something the site does not use. This would be turning the
health check green while TYPO3 cannot talk to Solr at all. Three cases are reported:

| Reported when                                                                              | Why it matters                                                                                           |
|--------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| A value is padded with whitespace `trim()` does not strip (a non-breaking space above all) | EXT:solr does not strip it either: `solr_port_read` casts to `0` and the endpoint is rejected as invalid |
| `solr_port_read` is not a number                                                           | Same outcome — no usable endpoint                                                                        |
| `solr_enabled_read` is not a boolean                                                       | The connection is dropped entirely, which would otherwise look like "no Solr configured"                 |

### The `/solr` path segment

Solr serves below a `/solr` segment, and Solarium appends
it itself after stripping a configured trailing one. Since EXT:solr v12 the site
configuration therefore must not carry it (`solr_path_read: /`), while older
setups still do. Both spellings are normalised to the same probe URI, so neither
convention produces a false alarm:

| `solr_path_read` | Probed root URI                |
|------------------|--------------------------------|
| `/`, empty       | `http://solr:8983/solr`        |
| `/solr/`         | `http://solr:8983/solr`        |
| `/search/`       | `http://solr:8983/search/solr` |

## Configuration

Only the toggle, the probe time-out, and the indexing-error severity are
configured here. Again, the connections come from the site configuration. Set via
Extension Configuration (`monitoring_solr`) or `config/system/settings.php`:

```php
return [
    'EXTENSIONS' => [
        'monitoring_solr' => [
            'provider' => [
                'mteu\\Monitoring\\Solr\\SolrProvider' => [
                    'enabled' => true,
                    'timeout' => 5,
                    'indexingErrorSeverity' => 'degraded',
                ],
            ],
        ],
    ],
];
```

| Setting                 | Default    | Description                                                                                                                                                            |
|-------------------------|------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `enabled`               | `true`     | Enables the provider. Installing this extension is itself the opt-in, so it is on by default. Set `false` to keep it installed but silent. :shrug:                     |
| `timeout`               | `5`        | Maximum time (seconds) to wait for a host or a core before it is treated as unreachable.                                                                               |
| `indexingErrorSeverity` | `degraded` | Status reported when the index queue contains indexing errors: `degraded` (visible on the endpoint at HTTP 200, no notification) or `unhealthy` (an outage, HTTP 503). |

Indexing errors default to `degraded` because the search backend keeps serving
queries while indexing is broken — it is attention-worthy but not, on its own, an
outage. Pair it with `reportDispatcher.notifyFrom = degraded` (see
[Reporters](https://github.com/mteu/ext-monitoring-src/blob/main/Documentation/Reporters.md)) if you want degradation to page.

## Example output

```json
{
  "status": "unhealthy",
  "services": {
    "Solr": {
      "status": "unhealthy",
      "subResults": {
        "Host": {
          "status": "healthy",
          "subResults": {
            "http://localhost:8983/solr": { "status": "healthy" }
          }
        },
        "Cores": {
          "status": "unhealthy",
          "subResults": {
            "main / English (core_en)": { "status": "healthy" },
            "main / German (core_de)": { "status": "unhealthy" }
          }
        },
        "Indexing Errors": { "status": "degraded" }
      }
    }
  }
}
```

> [!NOTE]
> The full breakdown (reasons and the affected index queue items) is available in the TYPO3 backend module.

## TLS and proxies

Probes go through TYPO3's request factory, so everything under
`$GLOBALS['TYPO3_CONF_VARS']['HTTP']` applies — including `verify` for a custom
CA bundle, which is what a Solr node behind an internal certificate authority
needs. Mind that a globally configured `proxy` applies as well. If Solr is
reachable on an internal network only, add its host to `no_proxy`, or every probe
is routed through the proxy and times out.
