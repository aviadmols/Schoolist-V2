# Performance measurement checklist

Use this checklist to verify response time, query count, and memory before/after performance changes.

## Before/after metrics to record

For each of these endpoints, record:

| Metric | How to measure |
|--------|----------------|
| **Query count** | Enable query log (see below) and count queries per request |
| **Response time (ms)** | Browser DevTools Network tab, or server-side logging |
| **Peak memory** | `memory_get_peak_usage(true)` at end of request or via middleware |

### Endpoints to measure

- **GET /class/{id}** — classroom page (ClassroomShowController)
- **GET /dashboard** — dashboard (DashboardController, requires `classroom.context`)

## Enabling query logging

Temporarily in `AppServiceProvider::boot()` (or a middleware):

```php
if (app()->environment('local') && config('app.debug')) {
    \Illuminate\Support\Facades\DB::listen(function ($query) {
        \Illuminate\Support\Facades\Log::channel('single')->debug($query->sql);
    });
}
```

Alternatively, use Laravel Debugbar or Telescope if installed.

## Production runtime checks

Run these in production (or staging) after deploy:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- **OPcache:** Keep enabled; hot paths avoid dynamic includes.
- **Containers:** Services (TemplateRenderer, TimetableService, etc.) are resolved once per request; no change needed unless profiling shows container as hotspot.

## Expected impact after recent fixes

- **Classroom page (Fix 1):** Cache key includes date → correct data after midnight; one fewer query on cache miss (eager load `timetableFile`).
- **Dashboard (Fix 2):** Timetable + announcements + timetable_image cached per classroom per day for 120s → fewer DB hits on repeated loads.
- **Announcements (Fix 3):** Composite index `(classroom_id, occurs_on_date)` → faster feed query when filtering by classroom and date range (run `php artisan migrate` to apply).
