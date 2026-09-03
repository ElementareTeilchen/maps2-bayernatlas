# Maps2 BayernAtlas

`maps2_bayernatlas` connects records from
[`jweiland/maps2`](https://github.com/jweiland-net/maps2) to the generic Fluid
component from `elementareteilchen/bayernatlas-fluid`. Editors continue to
manage points, routes, areas and radii in maps2. Each maps2 content element can
use the standard renderer or the BayernAtlas frontend.

## Compatibility

| TYPO3 | maps2 | bayernatlas-fluid | maps2_bayernatlas |
| --- | --- | --- | --- |
| 13.4 LTS | 12.2 | 0.1 | 0.1 |
| 14.3+ | 13.1 | 0.1 | 0.1 |

Shared Fluid files use the `.html` suffix supported by both TYPO3 versions.
Only the version-specific `Show` and `LoadAssets` adapters have an additional
`.fluid.html` variant, which TYPO3 14 resolves first.

The extension supports Composer-based TYPO3 installations. It does not target
Classic mode or publication through the TYPO3 Extension Repository.

## Installation

Configure both GitHub repositories in the TYPO3 root project, then install:

```bash
composer config repositories.bayernatlas-fluid vcs https://github.com/ElementareTeilchen/bayernatlas-fluid
composer config repositories.maps2-bayernatlas vcs https://github.com/ElementareTeilchen/maps2-bayernatlas
composer require elementareteilchen/maps2-bayernatlas:^0.1
```

Composer installs `elementareteilchen/bayernatlas-fluid` as a dependency. The
generic package owns the Fluid component and all map rendering assets.
These VCS entries allow installation without Packagist. Composer ignores
repository declarations in dependencies, so both entries are needed in the
root project. No demo extension or sample database records are installed.

Add the `Maps2 - BayernAtlas` Site Set to the site configuration. The set loads
the maps2 OpenStreetMap set as a compatibility layer. Do not add the Google Maps
set to the same site.

## Configuration

The Site Set defines these settings:

```yaml
settings:
  maps2BayernAtlas.defaultRenderer: bayernatlas
  maps2BayernAtlas.baseLayer: GEORESOURCE_WEB
  maps2BayernAtlas.height: 560px
  maps2BayernAtlas.zoom: 12
  maps2BayernAtlas.showLabels: true
  maps2BayernAtlas.showLayerControl: false
```

`GEORESOURCE_WEB` selects the standard map. Other GeoResource identifiers are
documented by the BayernAtlas project.

The renderer calculates the center of the maps2 coordinate extent before it
creates the BayernAtlas element. The map opens at its final position without a
second view change after loading.

The maps2 content element contains a `Map output` field with these choices:

- site default
- standard maps2 with Google Maps or OpenStreetMap
- BayernAtlas

The separate `BayernAtlas` tab can override the base layer and labels for one
content element. These frontend settings do not change the provider used by
maps2 for the backend editing map and address search.

The map width, map height and zoom from the maps2 content element take precedence
over the BayernAtlas fallbacks. If maps2 provides no `infoWindow` setting, the
AJAX info window remains enabled.

The optional layer control lets visitors switch between the built-in BayernAtlas
base maps. It groups points, routes, areas and radii by their assigned
`sys_category` records. Records without a category appear under `Ohne Kategorie`.
When a record belongs to multiple categories, it remains visible while at least
one of them is enabled. The control can be enabled globally through the Site Set
or for one maps2 content element in the `BayernAtlas` tab.

## Consent

BayernAtlas uses the existing **maps2 consent mechanism**, like OpenStreetMap.
No additional consent extension, cookie or provider setting is required.
Configure it in **Settings > Extension Configuration > maps2 > basic**:

| maps2 setting | Behavior |
| --- | --- |
| `explicitAllowMapProviderRequests = 0` | Load the map immediately. |
| `explicitAllowMapProviderRequests = 1` | Show a notice until maps2 allows provider requests. |
| `explicitAllowMapProviderRequestsBySessionOnly = 1` | With consent enabled, use maps2's session cookie instead of a persistent cookie. |

Before permission, the adapter renders a BayernAtlas notice and local CSS only.
It does not call `baf:map` or register either package's map scripts. The activation
link and the `mapProviderRequestsAllowedForMaps2` cookie are handled by maps2.
Existing permission therefore also unlocks BayernAtlas. It is a **shared map
permission**, not separate consent for OSM, Google Maps and BayernAtlas.

The inherited OpenStreetMap Site Set and its uncached `Overlay` action remain
in place. Standard maps2 content elements retain their original notice; the
BayernAtlas renderer gets its own translated notice. Do not remove the Site Set's
consent condition or replace it with a cookie check inside a shared page cache.

See [Consent behavior and verification](Documentation/Consent.md) for caching,
withdrawal, sitepackage overrides and repeatable tests. This extension does not
change the host site's consent settings automatically.

## Adapter structure

`Resources/Private/Templates/PoiCollection/Show` is an adapter. It passes five
named arguments to `BayernAtlas/Map`:

- content element UID
- maps2 environment
- POI collections
- BayernAtlas configuration
- maps2 info-window setting

The map partial translates maps2 records and settings through
`maps2ba:mapData`. It passes the result to the global `baf:map` Fluid component:

```html
<baf:map id="maps2-{contentElementUid}"
         items="{mapData.items}"
         configuration="{mapData.configuration}"/>
```

The generic package handles markers, geometries, categories and local item
content. This adapter only translates maps2 data and intercepts the
`bayernatlas:item-select` event for the existing maps2 AJAX info window.

See [Maps2 adapter](Documentation/Adapter.md) for the translation and event
flow. See the `elementareteilchen/bayernatlas-fluid` documentation when using
the map without maps2.

See [Deployment checklist](Documentation/Deployment.md) for site-level acceptance
checks and the limits of the automated test coverage.

## Sitepackage overrides

The Site Set adds its Fluid paths with priority `90`. A sitepackage can replace
the map without changing this extension:

```typoscript
plugin.tx_maps2.view {
  templateRootPaths.100 = EXT:site_package/Resources/Private/Extensions/Maps2BayernAtlas/Templates/
  partialRootPaths.100 = EXT:site_package/Resources/Private/Extensions/Maps2BayernAtlas/Partials/
  layoutRootPaths.100 = EXT:site_package/Resources/Private/Extensions/Maps2BayernAtlas/Layouts/
}
```

To replace only the map, provide this file in the sitepackage:

```text
Resources/Private/Extensions/Maps2BayernAtlas/Partials/BayernAtlas/Map.html
```

## Supported maps2 records

- point markers
- routes as line geometries
- areas as polygon geometries
- radii as generated circle polygons
- existing maps2 info-window content through the maps2 AJAX endpoint
- several maps on one page

## Current limitations

- custom maps2 marker images are not passed to the BayernAtlas component
- the maps2 backend editing map remains OpenStreetMap
- the initial viewport uses the configured zoom and the center of all records
- marker clustering is unavailable until the BayernAtlas web component offers
  a native clustering API
- the current BayernAtlas API requires labels for selectable point markers;
  with labels disabled, points do not open an info window

None of these limitations requires a change to the maps2 database schema.

## Beta and domain access

The BayernAtlas web component is in beta. The LDBV currently restricts its use
to approved domains. Ask the LDBV to allow every staging and production hostname
before deployment.

The site's Content Security Policy must allow `https://atlas.bayern.de` for the
component script and its map requests. The required directives depend on the
site's existing policy.

The site's privacy information and consent notices must cover all providers
unlocked by the shared maps2 permission. BayernAtlas loads both a script and a
cross-origin iframe from `atlas.bayern.de`.

## Development checks

Run the JavaScript tests from the extension directory:

```bash
npm test
```

Install test dependencies, run PHPUnit and validate the package:

```bash
TYPO3_SKIP_ASSET_PUBLISH=1 composer install
composer test:php
composer validate --strict
find Classes Tests -name '*.php' -type f -print0 | xargs -0 -n1 php -l
```

The checkout declares the generic component's VCS repository, so these commands
also work outside a TYPO3 project. GitHub Actions tests the supported TYPO3/maps2
pairs and runs JavaScript tests against the exported package without demo files.
The environment flag skips site asset publication in the standalone checkout.
Do not use it when installing into a real TYPO3 site.

In a checkout below a TYPO3 root project, `Tests/bootstrap.php` can instead use
the root Composer autoloader with a local PHPUnit PHAR:

```bash
php /path/to/phpunit.phar --configuration phpunit.xml.dist
```

## License

GPL-2.0-or-later.
