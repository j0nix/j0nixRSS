# j0nixRSS

A single PHP file that fetches RSS and Atom feeds server-side and returns them as JSON, so a web page can show feeds without CORS problems or a third-party feed API. It replaces the Google Feed API, which Google shut down.

Supported formats: RSS 2.0, RSS 1.0 (RDF) and Atom.

## Requirements

- PHP 7.0 or later
- PHP extensions `curl` and `SimpleXML` (on Debian/Ubuntu: `php-curl` and `php-xml`)

## Setup

Copy `j0nixRSS.php` to a directory served by PHP, then list the feeds it may fetch in `$RSS_URLS`:

```php
$RSS_URLS = array(
	"Slashdot" => "http://rss.slashdot.org/Slashdot/slashdot",
	"Linux Today" => "https://www.linuxtoday.com/feed/"
);
```

The key is the feed name clients request; the value is the feed URL. The script only fetches URLs in this list, so it cannot be used as an open proxy.

Defaults, set at the top of the file:

| Variable | Default | Meaning |
|---|---|---|
| `$LIMIT` | `15` | Maximum number of items returned |
| `$TRUNCATE` | `0` | Maximum description length in characters; `0` disables truncation |

To try it locally:

```sh
php -S localhost:8000
curl 'http://localhost:8000/j0nixRSS.php?rss=Slashdot&limit=2&truncate=50'
```

## API

### List feeds

`GET j0nixRSS.php`

Returns the configured feeds and defaults:

```json
{
    "about": "https://github.com/j0nix/j0nixRSS",
    "truncate": 0,
    "limit": 15,
    "rss": {
        "Slashdot": "http://rss.slashdot.org/Slashdot/slashdot",
        "Linux Today": "https://www.linuxtoday.com/feed/"
    }
}
```

### Get a feed

`GET j0nixRSS.php?rss=<name>&limit=<n>&truncate=<n>`

| Parameter | Required | Meaning |
|---|---|---|
| `rss` | yes | Feed name, exactly as in `$RSS_URLS` (case-sensitive, URL-encoded) |
| `limit` | no | Maximum number of items; overrides `$LIMIT` |
| `truncate` | no | Maximum description length in characters; overrides `$TRUNCATE` |

Response:

```json
{
    "channel": "Slashdot: Linux",
    "link": "https://linux.slashdot.org/",
    "description": "News for nerds, stuff that matters",
    "lastBuildDate": "",
    "item": [
        {
            "title": "IBM and Red Hat Find More Than 400 New Vulnerabilities In Popular Java Code",
            "pubDate": "2026-10-06T19:00:00+00:00",
            "link": "https://it.slashdot.org/story/26/10/06/1854244/...",
            "description": "IBM and Red Hat say their AI-powered Lightwell initiative..."
        }
    ]
}
```

| Field | Source |
|---|---|
| `channel`, `description` | Feed title and description (Atom: `title`, `subtitle`) |
| `link` | Site URL (Atom: `<link rel="alternate">`) |
| `lastBuildDate` | RSS `lastBuildDate`, Atom `updated`; often empty |
| `item[].title` | Item title |
| `item[].pubDate` | `pubDate`, else `dc:date` (RSS 1.0), Atom `updated`; empty if the feed has none |
| `item[].link` | Item URL (Atom: `<link rel="alternate">`, falling back to `<id>`) |
| `item[].description` | Item description (Atom: `summary`) with HTML tags removed |

## Behaviour

- **Sorting:** items are sorted newest first before `limit` is applied, because some feeds are not in date order. Undated items go last.
- **Dates** are passed through as the feed writes them, either RFC 822 (`Tue, 06 Oct 2026 07:31:48 -0400`) or ISO 8601 (`2026-10-06T19:00:00+00:00`). JavaScript's `new Date()` parses both.
- **Truncation** cuts at the last word boundary within the limit and appends `...`.
- **HTML:** tags are stripped from descriptions, but HTML entities such as `&amp;` remain, and titles are passed through unchanged. Insert feed text into a page as text (`textContent`), not as HTML, and decode entities if needed.
- **Unknown feed names** return the feed list instead of an error.
- **Errors:** if the feed cannot be fetched or parsed, the response is `{"error": "Cannot parse xml", "xml": "<first 200 characters>..."}`. The HTTP status is always 200, so check for `channel` or `error` in the response.
- **Fetching:** every request fetches the feed from the source; nothing is cached. The script sends a browser User-Agent, because some sites block plain curl, follows redirects, and times out after 10 seconds connecting or 60 seconds in total.

## Example

`example.htm` is a tabbed reader built with jQuery UI. It expects the script at `j0nixRSS/j0nixRSS.php` relative to the page, and its tab icon and loading spinner (`img/s_rss.png`, `img/loading.gif`) are not included in this repository.

[zweet.net](https://zweet.net) uses j0nixRSS for its reading list.

## License

Public domain; see [LICENSE](LICENSE).
