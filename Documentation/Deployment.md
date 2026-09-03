# Deployment checklist

Version 0.1.0 is the initial public release. Site owners still need to complete
the following acceptance checks before going live.

1. Install the compatible TYPO3/maps2 pair from the README. Enable the
   `Maps2 - BayernAtlas` Site Set with its inherited maps2 OpenStreetMap set.
2. Obtain LDBV approval for every staging and production hostname. Check the
   site's CSP for the provider script and iframe at `https://atlas.bayern.de`.
3. Choose the maps2 consent settings deliberately. Its permission is shared by
   OSM, Google Maps and BayernAtlas, not provider-specific. Cover these providers
   in the site's notices and provide a suitable withdrawal mechanism.
4. Follow [Consent behavior and verification](Consent.md), including cache tests
   for a denied visit after an allowed visit. The full-request probe is CLI-only
   and must never run from a public web endpoint.
5. Test actual marker and geometry clicks, AJAX info windows, multiple maps,
   category toggles, mobile layout and resizing. Keep labels enabled for point
   selection, as required by the current BayernAtlas API.
6. Check hidden, deleted and inaccessible POIs. Visitors should receive the
   translated generic error, not maps2's internal diagnostic text.

## What the tests establish

PHP tests cover the record adapter, dimension normalization, FlexForm handling
and real Fluid rendering of the consent branches. JavaScript tests cover the
maps2 AJAX adapter and user-facing errors. The CI matrix checks TYPO3 13.4/maps2
12.2 and TYPO3 14.3/maps2 13.1.

On a local TYPO3 13 installation, full frontend request tests pass for disabled
consent, denied permission, an existing cookie, activation and session-only
permission. A browser smoke test also confirms that denied output has no
BayernAtlas script or iframe, and activation loads multiple maps.

Full TYPO3 14 frontend acceptance remains open. The generic component's
`baFeatureSelect` fixtures are hand-built, not actual iframe recordings. Live
marker/geometry event payload verification also remains open. See the generic
component's [deployment checklist](https://github.com/ElementareTeilchen/bayernatlas-fluid/blob/main/Documentation/Deployment.md).

No demo importer, demo Site Set override or host configuration is included in
this package. The CLI integration probe needs an existing map page supplied by
the test project; it does not create records.
