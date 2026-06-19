# History Extension

Adds History API support to nette.ajax.js addon for Nette Framework!

This repo is a fork of [vojtech-dobes/history.nette.ajax.js](https://github.com/vojtech-dobes/history.nette.ajax.js) package with additional support for Nette 3.

All credits go to Vojtěch. Thank you!

## Compatibility

| Package version | Nette |
| --------------- | ----- |
| `^4.0`          | `nette/application` **3.2+** (readonly `Application`), `nette/http` 3.3+, PHP 8.2+ |
| `^3.0`          | Nette 3.0 – 3.1 |

**4.0.0 is a compatibility release for the readonly `Application` era.** Since `nette/application` 3.2 the `Application::$httpRequest` property is a promoted `readonly` property, so the previous reflection-based request swapping no longer works. The redirect re-run now uses the public `Application::processRequest()` API and is wrapped in the same lifecycle as `Application::run()` (`onStartup` / `onShutdown`, and `onError` on failure) so it behaves consistently with a normal request.

> **Note:** because the redirect re-run fires `onStartup` / `onShutdown` again within the same HTTP request, handlers bound to these events must be idempotent (this was already the case with the previous `run()`-based implementation).

## Installation

1. Link `client-side/history.ajax.js` after `nette.ajax.js`.
2. Load PHP files with Composer: `blueweb/nette-ajax-history`
3. Register config extension in your configuration:

```
extensions:
	ajaxHistory: Blueweb\NetteAjax\HistoryExtension
```

## Usage

Write your application as normal. All redirects and forwards will be properly handled.

To correctly update UI, use snippets. If you plan to ajaxify whole application, consider adding this snippet to your `beforeRender()` method in `BasePresenter`.

```php
if ($this->isAjax()) {
	$this->invalidateControl('title');
	$this->invalidateControl('content');
}
```

And `app/@layout.latte` might be upgraded accordingly:

```html
<title n:inner-snippet="title">...</title>
```

```html
{snippet content}
	{include content}
{/snippet}
```

### Disabling

If you want specific links or forms exclude from scope of this addon (not make them update the URL), you can use `data-ajax-off` feature of *nette.ajax.js*.

```html
<a class="ajax" data-ajax-off="history">
```

## UI Caching

Extension will automatically cache your UI and restore it on *Back* and *Forward* buttons without communication with server. If you wish to call server on every *Back* and *Forward*, turn caching off.

```js
$.nette.ext('history').cache = false;
```

## Changelog

### 4.0.0

- **BREAKING:** requires `nette/application` 3.2+ (readonly `Application`). Not compatible with Nette < 3.2.
- Replaced reflection-based `Application::$httpRequest` swapping with the public `Application::processRequest()` API (the property is now `readonly` and cannot be mutated).
- Redirect re-run is wrapped in the same lifecycle events as `Application::run()` (`onStartup` / `onShutdown`, `onError` on failure).
- Fixed `Nette\Http\Request` construction for `nette/http` 3.3 (non-nullable `array` / `string` constructor arguments).
- Declared previously transitive dependencies explicitly: `nette/routing`, `nette/utils`.
- Removed the legacy Nette 2.x `Nette\Config\CompilerExtension` class alias.
