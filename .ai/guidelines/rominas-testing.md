# Rominas — Testing

- Read `.claude/skills/testing-best-practices/` (`SKILL.md` and `rules/*.md`) before writing tests. Conventions in the neighbouring test files win over the skill.
- **Feature tests first.** Prove a change through the HTTP layer with a Pest feature test in `tests/Feature`. Write a unit test only for pure logic worth pinning on its own (like `ScoreCalculatorTest`), and never a unit test and a feature test for the same behaviour.
- **No tests for database seeders.** Check a seeder with Pint, PHPStan and a manual `db:seed --class=…` run.
- Assert the status explicitly (`assertStatus(201)`, not `assertOk()`), and cover the unhappy paths: validation errors, 401, 403, 404, 422.
- Assert the user-facing message text where it is part of the contract, and that a refused request changed nothing.
- Build data with factories and their named states (`Member::factory()->founding()->create()`); add a state rather than passing raw attributes repeatedly.
- Freeze or move time with `travelTo()` / `freezeTime()` and assert known literal values; never recompute an expectation with the code under test.
- Tests never send real e-mail: `Tests\TestCase` points the SMTP mailer at the array transport, and `phpunit.xml` forces `MAIL_DELIVERY_TRANSPORT=smtp` with an empty `BREVO_API_KEY`. Keep it that way.
- Run the narrowest tests that cover the change while developing, and the full suite (`php artisan test --compact`) before merging `dev` into `main`.
