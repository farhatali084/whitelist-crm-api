# REFLECTION

## 1. What was the hardest part, and why?
The hardest part was time: storing and returning UTC while judging working hours in Asia/Almaty. A slot can be a valid weekday in UTC but already Tuesday night in Almaty (`2030-01-07T19:30Z` is 00:30 local), or Monday in Almaty but still Sunday in UTC. I kept one rule (convert to Almaty, then check the weekday and that the *whole* meeting fits) in one small class, and tested exactly these crossing cases, plus the 17:00 / 17:01 / 17:30 boundary.

## 2. What trade-off did I accept because of the 4-hour limit?
I enforce "no overlapping meetings" in application code, inside a write-locked transaction, instead of with a database constraint. SQLite has no exclusion constraints, and moving to PostgreSQL with Docker would have cost the time I spent on edge cases and tests. I also skipped Docker entirely: I could not test it on my machine, and an untested `docker-compose.yml` is worse than none when the first criterion is "runs strictly by README".

## 3. What would I do first with 8 more hours?
First, PostgreSQL with an exclusion constraint, so the database itself rejects overlapping meetings, plus a `docker compose up` that I can actually test. Then a holidays calendar for Kazakhstan, and list endpoints for meetings and calls, which a real CRM screen will need immediately.

## 4. Where did I use AI, and what did I have to fix in its output?
I used an AI coding assistant (Claude Code) for most of the implementation: the code, the tests and the first drafts of the README. My part was choosing the stack, deciding the ambiguous rules, and checking the results by running the API, not only the tests. Things that had to be corrected:
- It first put a helper function directly in `bootstrap/app.php`. Laravel loads that file again for every test, so the function would be declared twice and crash. It was moved into a class.
- It named a test helper `call()`, which clashes with Laravel's own `TestCase::call()` and stopped the whole test run.
- Its first "concurrency test" fired 10 parallel requests at `php artisan serve`. On Windows that server handles **one request at a time**, so it proved nothing. I redid it with 4 separate server processes sharing one database file.
- When it removed the default migrations, the empty folder disappeared, and the next new files were written into a different project. I noticed it and moved them back.

## 5. Which requirement was ambiguous, and how did I interpret it?
Rule 4, "a company cannot have two active meetings *at the same time*". It can mean overlapping in time, or "only one upcoming meeting at all". I chose overlapping in time, mirroring rule 3 and the words "at the same time". The stricter version is a one-line change, and it is written in the README. A second one is whether `POST /calls` with `meeting_set` should *create* the meeting. I decided the meeting is booked first and the call references it, so all scheduling rules stay in one place.

## Architecture question: two managers press "book" on the same slot at the same moment
**Now:** booking runs "check for overlaps, then insert" in one transaction. SQLite is set to `IMMEDIATE` transactions, so the write lock is taken at `BEGIN`, with a 5-second `busy_timeout`. The second request waits for the first to commit, then sees the new meeting and gets `409 manager_busy`; it cannot pass in between. I tested this with 4 server processes and 12 simultaneous requests for one slot: 1 × 201, 11 × 409, one row in the table. If the lock wait ever times out, the client gets `409 busy_retry` instead of a 500.

**Honest note:** in my local test, even SQLite's default `DEFERRED` mode produced no duplicates, because SQLite allows only one writer at a time anyway. `IMMEDIATE` makes the guarantee explicit instead of relying on that.

**If duplicates still got through under load,** most likely after moving to PostgreSQL or MySQL, where two transactions can both read "no conflict" and both insert, I would make the database the final judge. In PostgreSQL that is an exclusion constraint: `EXCLUDE USING gist (manager_id WITH =, tstzrange(starts_at, ends_at) WITH &&) WHERE (status = 'scheduled')`, and the same for `company_id`. The API would map the constraint error to the same 409. Without such constraints, I would serialise bookings per manager with `SELECT ... FOR UPDATE` on the manager row (and the company row, always locked in the same order to avoid deadlocks). I would also add an idempotency key so a double-clicked button cannot create two bookings, and a periodic check that reports any overlapping meetings so the problem is visible.
