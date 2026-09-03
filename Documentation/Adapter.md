# Maps2 adapter

`maps2_bayernatlas` connects maps2 with the generic `baf:map` Fluid component
from `elementareteilchen/bayernatlas-fluid`.

## Record translation

The adapter translates maps2 records to generic map items:

| maps2 collection type | Generic item type |
| --- | --- |
| `Point` | `point` |
| `Route` | `line` |
| `Area` | `polygon` |
| `Radius` | `circle` |

Maps2 `sys_category` records become generic categories. The adapter also maps
the maps2 width, height, zoom and fallback center to the generic map
configuration.

## Info window

The generic map dispatches `bayernatlas:item-select` when a visitor selects an
item. The maps2 JavaScript adapter cancels the generic local-content behavior
and requests the existing maps2 AJAX endpoint. It then passes the returned HTML
back to the generic information panel.

When the maps2 `infoWindow` setting is disabled, the adapter does not intercept
the event.

## Fluid flow

`PoiCollection/Show` passes five explicit arguments to `BayernAtlas/Map`. The
partial calls `maps2ba:mapData` to translate the records and configuration. It
then renders the generic component:

```html
<baf:map id="maps2-{contentElementUid}"
         items="{mapData.items}"
         configuration="{mapData.configuration}"/>
```

The adapter partial remains replaceable through the maps2 `partialRootPaths`.
