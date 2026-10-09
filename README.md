# j0nixRSS

A single PHP file that fetches RSS and Atom feeds and the latest videos of YouTube channels server-side and returns them as JSON, so a web page can show them without CORS problems or a third-party feed API. It replaces the Google Feed API, which Google shut down.

Supported formats: RSS 2.0, RSS 1.0 (RDF) and Atom. YouTube channels are read through their Atom feeds.

## Requirements

- PHP 7.0 or later
- PHP extensions `curl` and `SimpleXML` (on Debian/Ubuntu: `php-curl` and `php-xml`)

## Setup

Copy `j0nixRSS.php` to a directory served by PHP, then configure two lists at the top of the file. Either list can be empty.

`$RSS_URLS` is the reading list: feed name => RSS or Atom feed URL.

```php
$RSS_URLS = array(
	"Slashdot" => "http://rss.slashdot.org/Slashdot/slashdot",
	"Linux Today" => "https://www.linuxtoday.com/feed/"
);
```

`$YOUTUBE_CHANNELS` is the YouTube list: channel name => channel ID.

```php
$YOUTUBE_CHANNELS = array(
	"Jeff Geerling" => "UCR-DXc1voovS8nhAvccRZhg",
	"The Linux Experiment" => "UC5UAwBUum7CPN5buc-_N1Fw"
);
```

The channel ID starts with `UC` and is not the `@handle`. To find it, open the channel page, view the page source and search for `channel_id=`; the page links its own feed as `https://www.youtube.com/feeds/videos.xml?channel_id=<ID>`.

The keys are the names clients request. A name may appear in both lists, because clients request each list with its own parameter. The script only fetches URLs built from these lists, so it cannot be used as an open proxy.

Defaults, set at the top of the file:

| Variable | Default | Meaning |
|---|---|---|
| `$LIMIT` | `15` | Maximum number of items returned |
| `$TRUNCATE` | `0` | Maximum description length in characters; `0` disables truncation |

To try it locally:

```sh
php -S localhost:8000
curl 'http://localhost:8000/j0nixRSS.php?rss=Slashdot&limit=2&truncate=50'
curl 'http://localhost:8000/j0nixRSS.php?youtube=Jeff%20Geerling&limit=2&truncate=50'
```

## API

### List feeds

`GET j0nixRSS.php`

Returns both lists and the defaults:

```json
{
    "about": "https://github.com/j0nix/j0nixRSS",
    "truncate": 0,
    "limit": 15,
    "rss": {
        "Slashdot": "http://rss.slashdot.org/Slashdot/slashdot",
        "Linux Today": "https://www.linuxtoday.com/feed/"
    },
    "youtube": {
        "Jeff Geerling": "UCR-DXc1voovS8nhAvccRZhg",
        "The Linux Experiment": "UC5UAwBUum7CPN5buc-_N1Fw"
    }
}
```

### Get a feed

`GET j0nixRSS.php?rss=<name>&limit=<n>&truncate=<n>`

`GET j0nixRSS.php?youtube=<name>&limit=<n>&truncate=<n>`

| Parameter | Required | Meaning |
|---|---|---|
| `rss` | one of `rss` or `youtube` | Feed name, exactly as in `$RSS_URLS` (case-sensitive, URL-encoded) |
| `youtube` | one of `rss` or `youtube` | Channel name, exactly as in `$YOUTUBE_CHANNELS` (case-sensitive, URL-encoded) |
| `limit` | no | Maximum number of items; overrides `$LIMIT` |
| `truncate` | no | Maximum description length in characters; overrides `$TRUNCATE` |

Response for a feed:

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

Response for a YouTube channel; each item also has a `thumbnail`:

```json
{
    "channel": "Jeff Geerling",
    "link": "https://www.youtube.com/channel/UCR-DXc1voovS8nhAvccRZhg",
    "description": "",
    "lastBuildDate": "",
    "item": [
        {
            "title": "I can't afford RAM, so I'm upgrading my 1989 Mac instead",
            "pubDate": "2026-10-03T16:08:42+00:00",
            "link": "https://www.youtube.com/watch?v=-vtFNuPM5zY",
            "description": "From your Mac Mini media server to that Raspberry Pi running who-knows-what,...",
            "thumbnail": "https://i2.ytimg.com/vi/-vtFNuPM5zY/hqdefault.jpg"
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
| `item[].pubDate` | `pubDate`, else `dc:date` (RSS 1.0); Atom `published`, else `updated`; empty if the feed has none |
| `item[].link` | Item URL (Atom: `<link rel="alternate">`, falling back to `<id>`) |
| `item[].description` | Item description (Atom: `summary`, else `media:description`) with HTML tags removed |
| `item[].thumbnail` | YouTube only: video thumbnail URL from `media:thumbnail` |

## Behaviour

- **Sorting:** items are sorted newest first before `limit` is applied, because some feeds are not in date order. Undated items go last.
- **Dates** are passed through as the feed writes them, either RFC 822 (`Tue, 06 Oct 2026 07:31:48 -0400`) or ISO 8601 (`2026-10-06T19:00:00+00:00`). JavaScript's `new Date()` parses both.
- **Truncation** cuts at the last word boundary within the limit and appends `...`.
- **HTML:** tags are stripped from descriptions, but HTML entities such as `&amp;` remain, and titles are passed through unchanged. Insert feed text into a page as text (`textContent`), not as HTML, and decode entities if needed.
- **YouTube:** a channel request reads two feeds. The channel feed holds the 15 latest uploads, Shorts and livestreams included. The channel's `UULF` playlist feed holds its 15 latest regular videos, without Shorts or livestreams. The script merges both, removes Shorts and duplicates by video ID, and returns the newest items up to `limit`, so a channel that posts many Shorts still returns a full list. The channel feed stays the primary source, because the playlist leaves out livestreams and can lag behind new uploads. If the playlist feed fails, the channel feed is used alone.
- **Thumbnails** are 480×360 (4:3); crop them to 16:9 to remove the letterbox bars, for example with CSS `aspect-ratio: 16 / 9; object-fit: cover`.
- **Unknown feed names** return the feed list instead of an error.
- **Errors:** a feed that cannot be used returns `{"error": "<reason>"}`. The HTTP status is always 200, so check for `error` in the response. The reasons are:

  | `error` | Cause |
  |---|---|
  | `Fetch failed: <curl error>` | Connection, DNS or TLS failure, or a timeout |
  | `Feed returned HTTP <status>` | The source answered with status 400 or higher |
  | `Cannot parse xml` | The response is not XML; `xml` holds its first 200 characters |
  | `Not an RSS or Atom feed` | The response is XML or HTML but not RSS or Atom, such as a maintenance page |
- **Fetching:** every request fetches the feed from the source (two feeds for a YouTube channel); nothing is cached. The script sends a browser User-Agent, because some sites block plain curl, follows redirects, and times out after 10 seconds connecting or 60 seconds in total.

## Example

`example.htm` is a tabbed reader in plain JavaScript with no dependencies. It shows a row of tabs for the reading list and one for YouTube, leaves out a list that has no entries, and shows thumbnails for YouTube videos. It expects `j0nixRSS.php` in the same directory. To try it, run `php -S localhost:8000` in this repository and open <http://localhost:8000/example.htm>.

The example inserts feed text with `textContent` and only follows `http`/`https` links, so a malicious feed cannot inject HTML or script into the page.

[zweet.net](https://zweet.net) uses j0nixRSS for its reading list and YouTube list.

## License

Public domain; see [LICENSE](LICENSE).
