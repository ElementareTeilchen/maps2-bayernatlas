# Shared maps2 consent

## Ownership

The adapter delegates permission to maps2. `bayernatlas-fluid` stays independent
of maps2 and of consent-management products such as `dp_cookieconsent`.

The existing maps2 options are the only switches:

- `explicitAllowMapProviderRequests`: require permission before displaying maps.
- `explicitAllowMapProviderRequestsBySessionOnly`: use a session cookie when
  permission is required. Otherwise maps2 controls the persistent cookie lifetime.

The website's configuration is not changed by installing this adapter.

## Request flow

1. The inherited OpenStreetMap Site Set evaluates maps2's permission condition.
   Without permission, maps2 selects its uncached `Overlay` action.
2. The adapter's `PoiCollection/Overlay.html` chooses a notice for the selected
   frontend renderer. Standard maps2 delegates to its original `Maps2/Overlay`.
   BayernAtlas uses `BayernAtlas/Consent` with an LDBV notice.
3. The activation link comes from maps2's `requestUriForOverlay` ViewHelper.
   It reloads the page with the existing maps2 activation parameter. The maps2
   middleware sets `mapProviderRequestsAllowedForMaps2` using its cookie policy.
4. The normal `Show` action invokes `BayernAtlas/Map`. That partial additionally
   checks maps2's `isRequestToMapProviderAllowed` before invoking the generic
   component or registering map scripts.

Denied output contains local CSS only. It reserves the configured map dimensions
with normalized CSS units. Multiple notices share one asset identifier.
After permission, the existing map asset identifiers also prevent duplicate
downloads when several maps are displayed.

## Shared permission and withdrawal

This is not per-provider consent. A permission originally granted for OSM also
allows BayernAtlas, and activation from a BayernAtlas notice also allows other
maps2 providers on that site. Site owners must ensure their notices describe
the providers actually covered. The adapter's default notice explicitly mentions
the shared permission, but does not rewrite upstream OSM/Google notices.

There is no new consent store, consent manager or withdrawal button. Sites using
an external consent manager should follow maps2's integration guidance. This
adapter does not enable or reconfigure that manager.

To withdraw the native shared permission, the site must remove the maps2 cookie
with its original path/domain and reload a URL **without the activation query
parameter**. Otherwise that parameter grants permission again. Cookie removal
alone does not unload an already running iframe. For sites requiring a visitor-
facing withdrawal control, that control remains site integration work.

The inherited TypoScript condition and uncached overlay are part of the cache
boundary. A direct render of the adapter partial from an unrelated cached plugin
does not acquire that boundary automatically. Do not rely on its Fluid check
alone for a cache shared between visitors with different permission states.

## Customizing the notice

Override `BayernAtlas/Consent.html` through the maps2 `partialRootPaths` to adapt
the text, privacy link or appearance. Its explicit arguments are
`contentElementUid`, `environment` and `configuration`. Do not include map assets
or the generic component in the denied branch.

The shared `.html` overlay and consent partial work on TYPO3 13 and 14. Local
external CSS needs no version-specific nonce attribute; the site's CSP must
allow that local stylesheet.

## Automated checks

`Tests/Unit/Consent/RenderingTest.php` renders the real Fluid branches with the
real maps2 permission ViewHelper. It covers disabled consent, first visits,
activation parameters, saved and unrelated cookies, removed permission,
numeric dimensions, the standard OSM fallback and multiple maps. Framework
edges such as the asset collector and map component are fakes. These tests ran
against both TYPO3 13/maps2 12.2 and TYPO3 14/maps2 13.1 dependencies.

`Tests/Integration/ConsentRequest.php` performs a full HTTP application call
inside an existing local TYPO3 installation. It needs a page with at least one
BayernAtlas maps2 content element and the adapter Site Set. It does not create
demo records or change configuration files. It changes the two maps2 options
only in its process, before TYPO3 constructs configuration-dependent services.
Its configuration manager rejects writes, including automatic synchronization
of extension defaults. If that is needed, prepare the local installation first
and rerun the probe; do not remove the write protection.
It does exercise the installation's normal caches. Run it only locally.

Example from a DDEV project, replacing the URL with your existing map page:

```bash
ddev exec env TYPO3_TEST_ROOT=/var/www/html \
  php packagesDev/maps2_bayernatlas/Tests/Integration/ConsentRequest.php \
  denied 'https://example.ddev.site/map.html'
```

Run `denied`, `saved`, then `denied` again to check cache separation after
permission is removed. Run `disabled` to check the disabled switch. For `grant`
and `session`, use the activation URL returned by `denied`, including its query
parameters and the site origin. Each scenario prints JSON and exits nonzero on
failure. It checks map markup, script registration and the native cookie headers.

All five scenarios passed on local TYPO3 13 with multiple BayernAtlas maps and
one standard OSM map. The integration probe checks server output, not browser
network traffic or the remote iframe. A full TYPO3 14 HTTP/browser run and the
site's final privacy/withdrawal integration remain deployment checks.
