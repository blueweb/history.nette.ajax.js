# History Extension

Adds History API support to nette.ajax.js addon for Nette Framework 2.4!

This repository is a fork of [vojtech-dobes/history.nette.ajax.js](https://github.com/vojtech-dobes/history.nette.ajax.js). All credits to Vojtěch Dobeš.

## Compatibility

| Package version | Nette |
| --------------- | ----- |
| `^2.0`          | Nette 2.4 (this branch) |
| `^3.0`          | Nette 3.0 – 3.1 |
| `^4.0`          | Nette 3.2+, PHP 8.2+ |

The `2.x` branch retains the Nette 2 dependency and application request handling. The fixes to redirect processing and browser history from later releases are backported here without requiring Nette 3 or 4.

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

## Backported fixes

- Preserve the request method, remote address and host when rerunning a redirect.
- Do not expose error-presenter or object parameters in a forwarded redirect URL.
- Read browser history state from the original popstate event (jQuery wrapper compatibility).

## Tests

Install Nette 2.4 dependencies anywhere (e.g. `composer install` in a copy of this package) and run:

```
NETTE_VENDOR_AUTOLOAD=/path/to/vendor/autoload.php php tests/BackportTest.php
NETTE_VENDOR_AUTOLOAD=/path/to/vendor/autoload.php php tests/IntegrationTest.php
node tests/HistoryTest.js
```
