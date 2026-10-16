# devnull mobile

A native iOS and Android app for [devnull](https://dev-null.sh), the social network for developers drawn as a git log. It's built with [NativePHP Mobile](https://nativephp.com) v4, so the UI is fully native and the app logic is Laravel.

## How signing in works

There are no passwords or tokens. On first launch the app makes its own ed25519 SSH key and keeps the secret half in the Keychain. Every request to devnull is signed with it.

Sign in with GitHub opens dev-null.sh in the system's sign-in browser, which shares Safari's cookies, so if you're already signed in there you only tap Approve. dev-null.sh approves this phone's key for your account and sends the browser back to `devnull://auth/callback` with a code. The app trades the code for your account with a signed request, using PKCE to prove it started the sign-in.

## The API

The app talks to the signed `/api/v1` from [nunomaduro/dev-null.sh#130](https://github.com/nunomaduro/dev-null.sh/pull/130), which isn't merged yet. Until it is, point the app at a devnull instance running that branch.

## Setup

```sh
composer install
cp .env.example .env
php artisan key:generate
```

`nativephp/mobile-secure-storage` is a paid plugin from `plugins.nativephp.com`, so `composer install` needs your NativePHP plugin licence.

For now the app tracks the `feat/custom-icons` branches of `nativephp/mobile` and `nativephp/mobile-ui`, which add custom SVG icons. Composer pulls them straight from GitHub. That lasts until [NativePHP/mobile-air#544](https://github.com/NativePHP/mobile-air/pull/544) and [NativePHP/mobile-ui#132](https://github.com/NativePHP/mobile-ui/pull/132) are released.

### Without a plugin licence

```sh
composer setup:free
```

This installs everything except the Secure Storage plugin, from a copy of `composer.json` and `composer.lock` that it writes to `composer.free.*`. To install again later, run `COMPOSER=composer.free.json composer install`.

Build the same way, since `native:run` runs its own `composer install` on a copy of the app:

```sh
COMPOSER=composer.free.json php artisan native:run ios
```

Without the plugin, the app keeps its key in a file encrypted with `APP_KEY` instead of in the Keychain. That's fine for a simulator, but don't ship it. On a real phone the app works this out for itself and refuses to make a key. `DEVNULL_KEY_STORE` (`keychain` or `file`) overrides the choice, so `file` makes it use the file on a phone too.

These can be set in `.env`:

- `DEVNULL_URL` is the devnull instance to talk to. It defaults to production.
- `NATIVEPHP_DEEPLINK_SCHEME` must be `devnull`, as in `.env.example`. NativePHP registers the scheme from it at build time, and its sign-in browser reads it from the bundled `.env` to know when dev-null.sh has sent the browser back.

## Running it

```sh
php artisan native:run ios
```

If CocoaPods fails with "Unicode Normalization not appropriate for ASCII-8BIT", run it with `LANG=en_US.UTF-8 LC_ALL=en_US.UTF-8`.

## Tests

```sh
php artisan test
```

CI needs a `COMPOSER_AUTH` repository secret with your `plugins.nativephp.com` credentials.

## License

MIT. Geist and Geist Mono are under the SIL Open Font License, see `resources/fonts/OFL.txt`. The icons in `resources/icons` are from [Lucide](https://lucide.dev), as on the web, under the ISC License, see `resources/icons/LICENSE`.
