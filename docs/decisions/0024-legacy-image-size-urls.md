# 0024: Upgraded sites keep the 1.x URLs of their image sizes

- Status: accepted
- Date: 2026-11-05
- Amends: [0008](0008-media-references.md) (the medias module)

## Context

In 1.1, `Folklore\Entities\ImageSize` was a size of `image.sizes`: its URL was the 1.x URL of the size (`Image::url($path, ['large'])`, `photo-filters(large).jpg`) on `app.image_url`, and its dimensions were computed from the size's definition (`width` and `height` as they are, `maxWidth` and `maxHeight` within the original's dimensions). Front ends read these URLs from the API, and caches and CDNs are keyed on them.

In 2.0, an image size is a `laravel-image` 2 preset, and its URL is the preset's 2.x URL, which only `Route::image()` serves ([#162](https://github.com/folkloreinc/laravel-folklore/issues/162)). `laravel-image`'s upgrade (`image:upgrade`) leaves a site with `Route::imageLegacy()` alone, which serves 1.x URLs; its guide moves sites to presets and `Route::image()` later, behind a CDN. So every image size of an upgraded site changed URL to one that isn't served, and sites whose API must stay identical through the upgrade copied 1.1's `ImageSize` into their own code.

`site.medias.resource` already keeps the 1.1 shape of `MediaResource` for upgraded sites. A shape needs nothing else; a URL needs a route that serves it, and new projects have no legacy route.

Laravel-image reads `image.sizes` as presets while `image.presets` isn't set, but `image:upgrade` writes `image.presets` and keeps `image.sizes` only when the site's code reads it: neither key tells an upgraded site from a new one.

## Decision

- **A setting, `site.medias.image_urls`**: `presets` (the default) or `legacy`.
  - `presets`: one size per preset, with the 2.x URL of its variant and its exact dimensions, as in the earlier alphas. For new projects, and sites that serve `Route::image()`.
  - `legacy`: the sizes of 1.1. `Image::sizes()` gives one size per entry of `image.sizes`, in their order (`original` included), or per preset when `image.sizes` isn't set. A size's URL is its 1.x URL, made by `laravel-image`'s `V1Codec` with the site's 1.x template, on `app.image_url` (or `app.url`) as 1.1 did: the image's filters, the size's name unless it is `original`, and the format, as a `.webp` extension unless the original has it. An SVG keeps its URL. Its dimensions are computed from its definition in `image.sizes` as 1.1 did, and its type from its format; a size without a definition there (a preset) takes its variant's dimensions. `Route::imageLegacy()` serves these URLs: it reads a named filter as the preset of that name.
- **The default is `presets`**, unlike `site.medias.resource`: a 1.x URL is only served by the legacy route, which new projects don't have. Upgraded sites get `legacy` from the upgrade, as they get `medias.reference_key`:
  - `folklore:upgrade --check` reports, as to do, a site whose image sizes give presets' URLs and that has no `Route::image()`, with the lines of its 1.x sizes (`image.sizes`) and of its legacy image routes;
  - `folklore:upgrade` sets `site.medias.image_urls` to `legacy` for such a site, when it uses the medias module.
- `new ImageSize($image, $name, $format)` keeps its signature: in the legacy mode, the name is the id of a size of `image.sizes`, or of a preset.

## Consequences

- An upgraded site keeps the URLs and dimensions of its image sizes, so its front ends and caches keep working, and it drops its copy of 1.1's `ImageSize`. It keeps `image.sizes` in `config/image.php` while it is in the legacy mode, for the list of its sizes and their dimensions.
- A site moves to presets when it serves `Route::image()`: it sets `site.medias.image_urls` to `presets`, and its sizes change URL then, once, with the CDN in front.
- The legacy mode reads `image.sizes` and `app.image_url`, two 1.x keys, until 3.0, which removes it with the other 1.x shapes.
