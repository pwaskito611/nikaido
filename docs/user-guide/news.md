# News

News articles can be managed from **Workbench → News** or imported through a
pair-scoped API receiver on **Workbench → Data import**. Each article stores
its source, optional provider ID, title, content, publication time, and
availability time. Each pair impact stores relevance, direction, impact lag,
duration, confidence, and assessment method. One article can therefore have
different assessments for different market pairs.

## Point-in-time behavior

- `published_at` records when the article was published.
- `available_at` records the earliest time the strategy could know the
  article. It is required to be at or after `published_at`.
- `impact_score` is directional for the linked pair, from `-1` (bearish)
  through `0` (neutral/unknown) to `+1` (bullish).
- `impact_lag_seconds` is the delay after publication before the annotated
  impact starts; `impact_duration_seconds` is its length.
- `relevance_score` and `confidence_score` are each in the range `0–1`.

The strategy receives only data loaded for its primary pair and can inspect
it using `StrategyExecutionContext::news()`. Call `availableAt($knownAt)` for
articles known by a decision time, or `impactActiveAt($knownAt)` for
assessments whose configured impact window is active at that time. The
backtest includes an earlier article when its impact window overlaps the
selected run range. Impact assessments do not automatically change backtest
orders or results; the strategy must explicitly use them.

Do not set `available_at` to an earlier time than the real information
release, and do not compute historical impact assessments using price
movements that happened after the decision time. Doing either introduces
look-ahead bias.

## API import

Create a receiver for a market pair on the Data import page. A receiver token
is shown only once; store it securely and send it as a bearer token. A new
session for the same pair stops the previous active session.

`POST /api/data-import/news/articles` accepts 1–1,000 articles per request,
with a 5 MB maximum body. Timestamps use `Y-m-d H:i:s`. Required fields are
`source`, `title`, `content`, `published_at`, `available_at`, `impact_score`,
and `impact_duration_seconds`. `external_id`, `language`, `relevance_score`,
`impact_lag_seconds`, `confidence_score`, and `assessment_method` are
optional; omitted impact metadata defaults to relevance/confidence `1`,
zero lag, and provider assessment.

Example:

```json
{
  "articles": [
    {
      "source": "provider-name",
      "external_id": "NEWS-123",
      "title": "Central bank rate decision",
      "content": "Article text...",
      "published_at": "2026-10-12 10:00:00",
      "available_at": "2026-10-12 10:00:00",
      "language": "en",
      "relevance_score": 0.9,
      "impact_score": -0.6,
      "impact_lag_seconds": 0,
      "impact_duration_seconds": 7200,
      "confidence_score": 0.8,
      "assessment_method": "provider"
    }
  ]
}
```

Use a stable `source` and `external_id` for each provider item. Repeated
imports update the article and that session pair's impact instead of creating
duplicates. If no external ID is available, source, title, publication time,
and content provide the fallback identity. A stopped session's token returns
HTTP 401 and cannot be reactivated.
