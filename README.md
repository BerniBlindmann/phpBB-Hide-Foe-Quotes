# Hide Foe Quotes

A phpBB 3.3 extension that hides quotes written by users on the viewer's ignore
list.

phpBB's built-in ignore feature only filters whole posts, matching on
`poster_id`. When somebody you are ignoring gets quoted by a third user, their
text still shows up in full. This extension closes that gap: the quote block is
replaced with a short notice instead.

The replacement happens **server side, at render time**. The hidden text never
reaches the browser, so it cannot be read by viewing the page source or with
developer tools.

## Requirements

* phpBB 3.3.0 – 3.3.x (developed and tested against 3.3.17)
* PHP 7.2 or newer

No database changes. The extension creates no tables, no columns and no config
entries. It only reads from `phpbb_zebra` and `phpbb_users`.

## Installation

1. Download the latest release and unzip it.
2. Copy the `hrole` folder into your board's `ext/` directory, so that
   `ext/hrole/foequotes/composer.json` exists.
3. Go to **ACP → Customise → Manage extensions**, find *Hide Foe Quotes* and
   click **Enable**.
4. Go to **ACP → General → Purge the cache**.

Step 4 matters. phpBB compiles the text renderer and caches it; until the cache
is purged, the old renderer stays in use and nothing appears to happen.

## Uninstallation

**ACP → Manage extensions → Disable**, purge the cache, then delete the
`ext/hrole/foequotes` directory. Nothing is left behind.

## What it covers

* Quotes carrying a `user_id` attribute — everything produced by the Quote
  button since phpBB 3.2. Matched by user ID, so renames do not matter.
* Older or hand-typed `[quote="Name"]` tags — matched by username as a
  fallback. Exact matches only: a foe named `Bert` does not hide a quote
  attributed to `Bertram`.
* Nested quotes. A foe quote inside a normal quote removes only the inner part.
  A normal quote inside a foe quote is removed along with everything else.
* Posts, private messages, topic review and the posting preview.

## What it does not cover

* Text copied verbatim without a `[quote]` tag. There is nothing to detect.
* Hand-typed quotes where the name is spelled differently, or quotes of users
  who have since been renamed. Affects the username fallback only, never the ID
  match.
* Notifications when a foe quotes you. That lives in
  `phpbb/notification/type/quote.php` and is a separate concern.
* Whole posts written by a foe. phpBB already handles those.

Note that phpBB does not let users add administrators or moderators to their
ignore list in the first place, so the extension does not need to special-case
staff.

## How it works

Since phpBB 3.2, posts are stored as XML and rendered on every page view, which
means the quoted author can be evaluated per viewer at display time.

The extension hooks three events:

| Event | Purpose |
| --- | --- |
| `core.text_formatter_s9e_configure_after` | Wraps the `QUOTE` template in an `xsl:choose` that swaps the quote for a notice when the quoted user is a foe. |
| `core.text_formatter_s9e_renderer_setup` | Feeds the current viewer's foes list into the renderer as template parameters. |
| `core.text_formatter_s9e_render_before` | The same, repeated before each render, because the renderer service can be constructed before `user_setup` has run. |

The style's own quote markup is preserved verbatim inside the `xsl:otherwise`
branch, so normal quotes render byte-for-byte identically to a board without
the extension. The original template is re-wrapped as an `UnsafeTemplate`,
matching how phpBB's own factory registers it.

One extra database query per page view for logged-in users, cached for the
duration of the request. Guests trigger no query at all. The compiled renderer
template is cached by phpBB.

## Changing the displayed text

The notice text lives in the `FOE_QUOTE_HIDDEN` key of
`language/<iso>/common.php`:

```php
$lang = array_merge($lang, array(
	'FOE_QUOTE_HIDDEN' => 'A quote from a member you are ignoring was hidden.',
));
```

Edit the string, save the file as UTF-8 without BOM, and purge the cache. No
code changes are required.

If you would rather not edit a shipped file, so that updates cannot overwrite
your change, override the key from your own small extension instead.

## Adding a translation

Only the one string needs translating.

1. Find the ISO code your board's language pack uses. It is the folder name in
   phpBB's own `language/` directory, and you can confirm it with:

   ```sql
   SELECT lang_iso, lang_local_name FROM phpbb_lang;
   ```

2. Copy `language/en` to `language/<iso>`, for example `language/fr`.
3. Translate the `FOE_QUOTE_HIDDEN` value. Leave the array key, the file header
   and the `IN_PHPBB` guard untouched.
4. Save as **UTF-8 without BOM** with LF line endings. If the translation
   contains an apostrophe, escape it for the PHP single-quoted string:
   `'Une citation d\'un membre …'`.
5. Purge the cache, then switch your account to that language to check it.

If phpBB finds no folder matching the board language, it falls back to English.
An unused language folder is harmless, it is simply never loaded.

Pull requests with translations are welcome. Please submit one language per
pull request and state which language pack ISO code it targets.

### Included translations

| ISO | Language |
| --- | --- |
| `en` | English |
| `de` | German (informal, *du*) |
| `de_x_sie` | German (formal, *Sie*) |

The German wording follows the official German language pack, which describes a
foe in `POST_BY_FOE` as "einem von dir ignorierten Mitglied".

## Styling

The placeholder is emitted as:

```html
<blockquote class="uncited foequotes-hidden"><div><em>…</em></div></blockquote>
```

The `uncited` class makes it look like a native prosilver quote box. Use
`foequotes-hidden` to style it however you like.

## Support

Please report bugs on the issue tracker. Support questions are best asked in
the extension's topic at phpBB.com.

## License

[GNU General Public License v2.0](license.txt)
