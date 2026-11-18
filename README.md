# Whitelist CRM API (Task B — Software Developer)

A REST API for a call centre that phones companies and books meetings for managers. No UI.
Built with **PHP 8.2+ / Laravel 12 / SQLite**.

## Run from scratch

Requirements: **PHP 8.2+** with the `pdo_sqlite`, `mbstring`, `openssl` and `fileinfo` extensions, and **Composer 2**. Docker is not needed.

```bash
git clone https://github.com/farhatali084/whitelist-crm-api.git
cd whitelist-crm-api
composer start
```

`composer start` is the single command. It:
1. copies `.env.example` to `.env` if there is no `.env`
2. installs dependencies
3. generates the app key
4. creates `database/database.sqlite`
5. runs the migrations and seeds three managers (ids 1, 2, 3)
6. starts the server

The API is then at **http://127.0.0.1:8000**. If port 8000 is busy, Laravel picks the next free port and prints it, so use the URL shown in the terminal.

Stop the server with `Ctrl+C`. Running `composer start` again keeps the data. To start with an empty database, delete `database/database.sqlite` first.

### Tests

```bash
composer test          # or: php artisan test
```

The tests use an in-memory SQLite database and a frozen clock (2030-01-01), so they never touch your data.

### Example requests

[requests.http](requests.http) has 20 requests covering every endpoint and every kind of refusal. Open it in VS Code (REST Client extension) or PhpStorm and click "Send Request". If the server is not on port 8000, change `@base` at the top of the file. The same requests with curl:

```bash
curl -X POST http://127.0.0.1:8000/companies -H "Content-Type: application/json" \
  -d '{"name":"Digital Solutions","bin":"180540012343","phone":"+7 701 555 44 33"}'
curl -X POST http://127.0.0.1:8000/meetings -H "Content-Type: application/json" \
  -d '{"company_id":1,"manager_id":1,"starts_at":"2030-01-07T05:00:00Z"}'
curl "http://127.0.0.1:8000/managers/1/schedule?date=2030-01-07"
```

### Environment variables

All are in `.env.example` and all are optional.

| Variable | Default | Meaning |
|---|---|---|
| `CRM_TIMEZONE` | `Asia/Almaty` | Timezone in which working hours are checked |
| `CRM_WORKDAY_START` / `CRM_WORKDAY_END` | `09:00` / `18:00` | Working hours |
| `CRM_MEETING_MINUTES` | `60` | Meeting length |
| `DB_DATABASE` | `database/database.sqlite` | SQLite file |
| `DB_TRANSACTION_MODE` | `IMMEDIATE` | SQLite transaction mode (see "Concurrent bookings") |

## Endpoints

All times in requests and responses are **UTC, ISO 8601** (`2030-01-07T05:00:00Z`). Working hours are checked in **Asia/Almaty** (UTC+5), so 10:00 in Almaty is `05:00Z`.

| Method | Path | What it does | Success |
|---|---|---|---|
| POST | `/companies` | Add a company: `name`, `bin` (12 digits, unique), `phone` | 201 |
| GET | `/companies?status=&page=&per_page=` | List companies, filtered by status (`new`, `no_answer`, `refused`, `meeting_set`), paginated (`per_page` 1–100, default 20) | 200 |
| POST | `/calls` | Log a call: `company_id`, `manager_id`, `result` (`no_answer`, `refused`, `meeting_set`), `meeting_id` (required for `meeting_set`), optional `comment`, `called_at` | 201 |
| POST | `/meetings` | Book a meeting: `company_id`, `manager_id`, `starts_at`. The end is always start + 60 min | 201 |
| GET | `/meetings/{id}` | One meeting with its full history | 200 |
| PATCH | `/meetings/{id}` | Reschedule: `starts_at` and/or `manager_id`. Cancel: `{"status":"cancelled"}` | 200 |
| GET | `/managers/{id}/schedule?date=YYYY-MM-DD` | A manager's active meetings on that **Almaty** calendar day, sorted | 200 |

A meeting looks like this:

```json
{"data": {
  "id": 1, "company_id": 1, "manager_id": 1, "status": "scheduled",
  "starts_at": "2030-01-07T10:00:00Z", "ends_at": "2030-01-07T11:00:00Z",
  "original_starts_at": "2030-01-07T05:00:00Z",
  "booked_at": "2030-01-01T09:12:44Z", "cancelled_at": null,
  "history": [
    {"type": "created", "at": "2030-01-01T09:12:44Z", "new_starts_at": "2030-01-07T05:00:00Z", "new_manager_id": 1, ...},
    {"type": "rescheduled", "at": "2030-01-02T08:00:00Z", "old_starts_at": "2030-01-07T05:00:00Z", "new_starts_at": "2030-01-07T10:00:00Z", ...}
  ]
}}
```

### Errors

Every refusal has the same shape and a specific code. A 500 only happens for a real bug.

```json
{"error": {"code": "manager_busy", "message": "Manager 1 already has a meeting at this time.",
           "details": {"conflicting_meeting": {"id": 1, "starts_at": "2030-01-07T05:00:00Z", ...}}}}
```

| HTTP | `code` | When |
|---|---|---|
| 422 | `validation_failed` | Missing or invalid field, unknown `company_id`/`manager_id` in the body, time without a timezone, `meeting_set` without `meeting_id` (`details` lists every field) |
| 422 | `outside_working_hours` | Not Mon–Fri, or the meeting does not fit completely in 09:00–18:00 Almaty |
| 422 | `starts_in_past` | Booking or moving a meeting into the past |
| 422 | `meeting_company_mismatch` / `meeting_not_active` / `meeting_already_linked` | `meeting_set` call pointing to another company's meeting, a cancelled meeting, or a meeting already linked to a call |
| 409 | `manager_busy` | The manager already has an overlapping active meeting |
| 409 | `company_busy` | The company already has an overlapping active meeting (with any manager) |
| 409 | `meeting_cancelled` / `meeting_started` | Changing a cancelled meeting, or one that has already started |
| 409 | `busy_retry` | Database lock wait timed out under extreme concurrency; safe to retry |
| 404 | `not_found` | Unknown id in the URL, or unknown path |
| 405 | `method_not_allowed` | Wrong HTTP method |

**Why 422 vs 409:** 422 means the request is wrong on its own, so sending it again will always fail. 409 means it conflicts with the current data, so the same request could succeed after something changes (for example, the other meeting is cancelled).

## Business rules and how they are enforced

All meeting rules live in one class, [app/Services/MeetingScheduler.php](app/Services/MeetingScheduler.php). Working hours are a separate pure class, [app/Support/WorkingHours.php](app/Support/WorkingHours.php).

| # | Rule | Implementation |
|---|---|---|
| 1 | Meeting is 60 minutes | The client sends only `starts_at`; `ends_at = starts_at + CRM_MEETING_MINUTES`. |
| 2 | Mon–Fri 09:00–18:00 Asia/Almaty, must fit completely | `WorkingHours::violation()` converts UTC to Almaty, checks the weekday, and checks `start >= 09:00` and `end <= 18:00`. A 17:00 start is allowed (ends 18:00); 17:01 and 17:30 are refused. |
| 3 | No overlapping meetings per manager; 10–11 and 11–12 is fine | Overlap is `existing.start < new.end AND existing.end > new.start`, with strict `<`/`>`, so touching meetings don't conflict. Only `scheduled` meetings count. |
| 4 | No two active meetings of one company at the same time, even with different managers | The same overlap check, by `company_id`. |
| 5 | `meeting_set` call without a created meeting is a validation error | `meeting_id` is required for `meeting_set` (and forbidden for other results). It must exist, belong to the same company, be active, and not already be linked to another call. |
| 6 | Rescheduling keeps history | A reschedule changes the same meeting (same id), but `original_starts_at` and `booked_at` never change, and every create, reschedule and cancel adds a row to `meeting_events` with the old and new time and manager. |
| 7 | Correct HTTP code and clear message, not 500 | One error handler in [bootstrap/app.php](bootstrap/app.php) turns every expected failure into the JSON shape above. |

## Decisions on ambiguous points

1. **Rule 4, "two active meetings at the same time".** I read it as *overlapping in time*, the same as rule 3 but per company: a company may have a meeting on Monday and another on Wednesday. The stricter reading, "at most one upcoming meeting per company", would be a one-line change in `assertBookable`.
2. **Order of call and meeting.** The manager books the meeting first (`POST /meetings`), then logs the call with that `meeting_id`. `POST /calls` never creates a meeting itself. This keeps the meeting rules in one place, and it is what makes rule 5 checkable.
3. **Company status** (`GET /companies?status=`) is `new` until the first call, and afterwards the result of the **latest** call. Cancelling a meeting does not change it.
4. **Input times.** The assignment says "accept UTC". I accept any ISO 8601 time with an **explicit** zone (`Z` or `+05:00`) and convert it to UTC. A time **without** a zone (`2030-01-07T10:00:00`) is rejected, because it is ambiguous: is it Almaty or UTC? Responses are always `Z`.
5. **"Originally scheduled"** can mean *the first time slot* or *the moment it was booked*. Both are returned: `original_starts_at` and `booked_at`, plus the full history.
6. **Schedule `date`** is an Almaty calendar day, because that is the manager's working day. It returns only active (not cancelled) meetings.
7. **No meetings in the past.** A meeting that has already started cannot be moved or cancelled. A cancelled meeting is final.
8. **BIN** must be 12 digits and unique. I do not verify the check digit; the BIN in the LLM task's sample document would fail it.
9. **Managers** have no endpoint (not in the assignment); three are seeded.
10. **Not handled:** public holidays (e.g. Nauryz), lunch breaks, different hours per manager.

## Concurrent bookings

`book()` and `reschedule()` run "check for overlaps, then insert" inside **one transaction**. SQLite is configured with `transaction_mode = IMMEDIATE`, which takes the write lock at `BEGIN`, and `busy_timeout = 5000`. A second booking for the same slot therefore waits until the first one commits, then sees it and gets `409 manager_busy`.

I checked this against 4 server processes sharing one database file: 12 simultaneous requests for one slot gave **1 × 201, 11 × 409, and exactly one row**. What I would do on a server database is in [REFLECTION.md](REFLECTION.md).

## Project structure

```
app/
  Services/MeetingScheduler.php    all meeting rules (book, reschedule, cancel, overlap checks)
  Support/WorkingHours.php         working-hours rule (pure, unit-tested)
  Support/Time.php                 UTC parsing / ISO formatting
  Rules/IsoDateTime.php            validation: ISO 8601 with a timezone
  Http/Controllers/                thin: validate -> call service -> resource
  Http/Requests/                   input validation per endpoint
  Http/Resources/                  JSON output shape
  Models/                          Company, Manager, Meeting, MeetingEvent, Call
  Exceptions/ApiException.php      422 / 409 refusals with an error code
bootstrap/app.php                  routing (no /api prefix) + JSON error handler
config/crm.php                     timezone, hours, duration
database/migrations/               schema (5 tables)
tests/Feature, tests/Unit          39 tests
requests.http                      example requests
```

## Tests

`composer test` runs 39 tests (267 assertions):

- **MeetingOverlapTest** (required):
  - same manager at 10:30 and 09:30 against a 10:00 meeting → 409
  - back-to-back 09:00 / 10:00 / 11:00 → all allowed
  - same company with a different manager → 409
  - a cancelled meeting frees its slot
  - a failed reschedule leaves the meeting unchanged
  - moving a meeting by 30 minutes doesn't conflict with itself
- **WorkingHoursTest** (required), 11 boundary cases:
  - 09:00 ok, 08:59 refused
  - **17:00 ok, 17:01 and 17:30 refused**
  - 18:00, Saturday and Sunday refused
  - UTC times that fall on a different day in Almaty
  - `+05:00` input is converted to UTC
  - a reschedule is held to the same hours
- **CallTest:** rule 5 in all its forms, and the company status update.
- **MeetingHistoryTest:** original slot and every step are kept, cancel is final, a started meeting can't be moved, the PATCH body must say what to change.
- **ErrorHandlingTest:** 404 / 405 / 422 shapes, malformed times, wrong JSON types, booking in the past.
- **CompanyAndScheduleTest:** duplicate or invalid BIN, filter and pagination, schedule for one Almaty day.
- **Unit WorkingHoursTest:** the rule without a database.

## Done / not done

**Done:**
- all 6 required endpoints, plus `GET /meetings/{id}` to see history
- all 7 business rules, each with tests
- one JSON error format
- a concurrency-safe booking transaction
- one-command start, `requests.http`, and the 39 tests listed above

**Not done:**
- **Docker:** not tested, because Docker is not installed on my machine, and an untested `docker-compose.yml` would break "runs strictly by README".
- No holidays calendar.
- No endpoints to list calls or meetings, or to edit or delete companies (not required).
- No OpenAPI spec (`requests.http` instead).
- The overlap rule is enforced by application code inside a transaction, not by a database constraint (see REFLECTION.md).
