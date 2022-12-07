# HttpHeader Plugin

This Joomla Plugin implements an UI Layer for the HTTP Security headers so everyone can set and configure them from the backend.

## Features

This Joomla Plugin helps you to set the following HTTP Security Headers.
- [Strict-Transport-Security](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Strict-Transport-Security)
- [Content-Security-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [Content-Security-Policy-Report-Only](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP#Testing_your_policy)
- [X-Frame-Options](https://developer.mozilla.org/de/docs/Web/HTTP/Headers/X-Frame-Options)
- [X-XSS-Protection](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-XSS-Protection)
- [X-Content-Type-Options](https://developer.mozilla.org/de/docs/Web/HTTP/Headers/X-Content-Type-Options)
- [Referrer-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Referrer-Policy)
- [Expect-CT](https://developer.mozilla.org/de/docs/Web/HTTP/Headers/Expect-CT)
- [Feature-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Feature-Policy)
- [Permissions-Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Feature-Policy)

This plugin also comes with some easy defaults for:
- X-Frame-Options
- X-XSS-Protection
- X-Content-Type-Options
- Referrer-Policy

**Note**: If you have configured some HTTP Security Headers **directly on the server**, then this Plugin might create double entries.

Check the output of your HTTP headers after configuring this HTTP Security Headers Plugin. In Google Chrome: Inspect > Network > the output under Headers).
In this Plugin you can disable the settings that cause double entries. Also check the Console of your browser for possible errors.

## Configuration

### Initial setup the plugin

- [Download the latest version of the plugin](https://github.com/zero-24/plg_system_httpheader/releases/latest)
- Install the plugin using `Upload & Install`
- Enable the plugin `System - HttpHeader` form the plugin manager

Now the inital setup is completed and you can start configure the headers.

### Default Headers

Please note that by default the following headers und values are set:
```
X-Frame-Options: SAMEORIGIN
```
More Infos: https://scotthelme.co.uk/hardening-your-http-response-headers/#x-frame-options
```
X-XSS-Protection: 1; mode=block
```
More Infos: https://scotthelme.co.uk/hardening-your-http-response-headers/#x-xss-protection
```
X-Content-Type-Options: nosniff
```
More Infos: https://scotthelme.co.uk/hardening-your-http-response-headers/#x-content-type-options
```
Referrer-Policy: no-referrer-when-downgrade
```
More Infos: https://scotthelme.co.uk/a-new-security-header-referrer-policy/

You can allways choose to disable or change the value for one of those by changing the plugin configuration.

### Option descriptions

#### Force HTTP Header

Using this you can set different values from the default ones and also force headers. The supported headers are:
- Strict-Transport-Security
- Content-Security-Policy
- Content-Security-Policy-Report-Only
- X-Frame-Options
- X-XSS-Protection
- X-Content-Type-Options
- Referrer-Policy
- Expect-CT
- Feature-Policy
- Cross-Origin-Opener-Policy
- Permissions-Policy

Here you can also decide whether the header is applyed only to the frontend and or only the backed or both sites.

#### HTTP Strict Transport Security (HSTS)

This option activates 'Strict Transport Security' and allows the configuration of the value of that header including `Include subdomains`, `Maximum registration time (max-age)` and `Preload`.

HSTS means that your domain can no longer be called without HTTPS. Once added to the preload list, this is **not easy to undo**. Domains can be removed, but it takes months for users to make a change with a browser update. This option is very important to prevent ['man-in-the-middle attacks'](https://en.wikipedia.org/wiki/Man-in-the-middle_attack), so it should be activated in any case, but only if you are sure that HTTPS is fully supported for the domain and all subdomains in the long run! The value for 'maximum registration time' must be set to 63072000 (2 years) for recording.

#### Content Security Policy (CSP)

With this option the `Content-Security-Policy` rule can be set individually including an dedicated subform for the the different directives as well as setting the rules in `Report-Only` mode.

## Update Server

Please note that my update server only supports the latest version running the latest version of Joomla and atleast PHP 7.0.
Any other plugin version I may have added to the download section don't get updates using the update server.

## Issues / Pull Requests

You have found an Issue, have a question or you would like to suggest changes regarding this extension?
[Open an issue in this repo](https://github.com/zero-24/plg_system_httpheader/issues/new) or submit a pull request with the proposed changes.

## Translations

You want to translate this extension to your own language? Check out my [Crowdin Page for my Extensions](https://joomla.crowdin.com/zero-24) for more details. Feel free to [open an issue here](https://github.com/zero-24/plg_system_httpheader/issues/new) on any question that comes up.

This plugin is translated into the following languages:
- de-DE by @zero-24
- en-GB by @zero-24 & @brianteeman
- fr-FR by @Sandra97 & @YGomiero
- it-IT by @jeckodevelopment
- nl-NL by @pe7er

## Beyond this repo

This plugin has been included in the Joomla Core ([joomla/joomla-cms#18301](https://github.com/joomla/joomla-cms/pull/18301)) and will be part of the upcomming 4.0 Release. Please note that the core the plugin has been renamed to plg_system_httpheaders (extra `s`) and extended by the new com_csp component for to core distribution.

## Special Thanks

David Jardin - @snipersister - https://www.djumla.de/ & Yves Hoppe - @yvesh - https://compojoom.com/

For giving me the inspiration for the plugin and their feedback on the actual implementation. Thanks :+1:

## Joomla! Extensions Directory (JED)

This plugin can also been found in the Joomla! Extensions Directory: [HTTPHeader by zero24](https://extensions.joomla.org/extension/httpheader/)

## Release steps

- Update the version within the XMLs
- `build/build.sh`
- `git commit -am 'prepare release HttpHeader 1.0.x'`
- `git tag -s '1.0.x' -m 'HttpHeader 1.0.x'`
- `git push origin --tags`
- create the release on GitHub
- `git push origin master`

## Crowdin

### Upload new strings

`crowdin upload sources`

### Download translations

`crowdin download --skip-untranslated-files --ignore-match`
