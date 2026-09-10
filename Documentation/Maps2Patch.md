# maps2 info-window patch

maps2 13.1.0 cannot render its standard info window on TYPO3 14. The BayernAtlas
renderer requests that info window through the maps2 AJAX endpoint, so both
renderers are affected. maps2 12.2 on TYPO3 13 ships the template at its default
path and does not need the patch.

## Cause

- The site setting `maps2.infoWindowContent.templatePath` defaults to
  `EXT:maps2/Resources/Private/Templates/InfoWindowContent.html`. maps2 13 only
  ships `InfoWindowContent.fluid.html`.
- The partial renders editorial content with `f:transform.html`. Its link
  resolution reads `$GLOBALS['TYPO3_REQUEST']`, which is not set while the maps2
  info-window middleware renders the view. `f:format.html` uses the request from
  the rendering context instead.

The fix is proposed upstream in
[jweiland-net/maps2#378](https://github.com/jweiland-net/maps2/pull/378). Remove
the patch once a maps2 release contains it.

## Apply the patch

This extension cannot patch maps2 for you: Composer applies patches only from
the root project. Require `cweagans/composer-patches` in the TYPO3 root project:

```bash
composer require cweagans/composer-patches:^1.7
```

Save the patch as `patches/jweiland/maps2/fixInfoWindowContent.patch`:

```diff
diff --git a/Configuration/Sets/Maps2/settings.definitions.yaml b/Configuration/Sets/Maps2/settings.definitions.yaml
--- a/Configuration/Sets/Maps2/settings.definitions.yaml
+++ b/Configuration/Sets/Maps2/settings.definitions.yaml
@@ -35,7 +35,7 @@ settings:
     category: 'Maps2.templates.infoWindowContent'
     description: 'If you click on a marker (POI) a little info window will appear. With this setting you can change the template to your own needs.'
     type: string
-    default: 'EXT:maps2/Resources/Private/Templates/InfoWindowContent.html'
+    default: 'EXT:maps2/Resources/Private/Templates/InfoWindowContent.fluid.html'
   maps2.infoWindowContent.imageHeight:
     label: 'Image Height'
     category: 'Maps2.templates.infoWindowContent'
diff --git a/Resources/Private/Partials/InfoWindowContent.fluid.html b/Resources/Private/Partials/InfoWindowContent.fluid.html
--- a/Resources/Private/Partials/InfoWindowContent.fluid.html
+++ b/Resources/Private/Partials/InfoWindowContent.fluid.html
@@ -33,5 +33,5 @@
 <f:if condition="{poiCollection.info_window_content}">
     <br/>
-    <div class="infoWindowContent">{poiCollection.info_window_content -> f:transform.html()}</div>
+    <div class="infoWindowContent">{poiCollection.info_window_content -> f:format.html()}</div>
 </f:if>
 </html>
```

Register it in the root `composer.json`:

```json
{
    "extra": {
        "patches": {
            "jweiland/maps2": {
                "Fix info window template path and link rendering on TYPO3 14": "patches/jweiland/maps2/fixInfoWindowContent.patch"
            }
        }
    }
}
```

Then reinstall maps2 so Composer applies the patch:

```bash
composer reinstall jweiland/maps2
```

A site that already sets `maps2.infoWindowContent.templatePath` to an existing
template still needs the second hunk, unless its own template avoids
`f:transform.html`.

## Verify

Open a page with a maps2 map and select a point that has info-window content
with a link. The info window should show the title, address and content, and
the link should point to the target page.
