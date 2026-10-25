# backend/AGENTS.md

Rules for writing code under `backend/`. The reasons behind them are in `docs/adr/0008-backend-layers.md` and `docs/adr/0011-sanctum-api-token.md`; do not copy them here.

## Commands

PHP runs in the container: `docker compose exec app <cmd>`

- Test: `./vendor/bin/pest [--filter Name]`
- Static analysis: `./vendor/bin/phpstan analyse`
- Format: `./vendor/bin/pint` (`--test` to check only)

## Layers

```
app/
  Http/                     Controllers/<Feature>/, Requests/<Feature>/, Resources/
  UseCases/<Feature>/       one class per use case, e.g. UseCases/Auth/RegisterUserUseCase.php
  Domain/<Aggregate>/       entities, repository interfaces, domain exceptions, e.g. Domain/User/
  Domain/Shared/            interfaces used across aggregates, e.g. PasswordHasherInterface
  Infrastructure/Eloquent/  Models/ and Repositories/ (Eloquent<Name>Repository)
  Infrastructure/<Concern>/ other implementations of Domain interfaces, e.g. Hashing/
```

Dependencies point `Http → UseCases → Domain ← Infrastructure`. `tests/Arch/LayersTest.php` enforces the following; run it after adding a class.

- Domain uses only plain PHP: no `Illuminate`, no other layer.
- UseCases use only Domain: no `Illuminate`.
- Eloquent and `DB` appear only in `App\Infrastructure`, `Database\*` (factories, seeders), and tests.
- `App\Infrastructure` is referenced only from `App\Providers` (bindings), `Database\*`, and tests.
- UseCase class names end with `UseCase`. UseCases and controllers expose only `__construct` and `__invoke`.

## Per layer

**Http**
- One controller per route. It validates input with a FormRequest, calls one UseCase, and returns an API Resource.
- Pass scalars to the UseCase (`$request->string('x')->toString()`), and pass the current user as `(string) Auth::id()`. Do not use `Auth::user()`.
- Catch domain exceptions and convert them to HTTP errors here: field errors as `ValidationException::withMessages` (422), others as 403/404.
- FormRequest checks format only. Checks that need the DB (`unique`, `exists`) go in the UseCase.
- Resources take a Domain entity (`@property Entity $resource`). Dates are `format(DateTimeInterface::ATOM)`.

**UseCases**
- `final readonly class`, dependencies injected through the constructor as Domain interfaces.
- Authorization: compare the user ID with the entity's owner. Not permitted → throw a domain exception that Http maps to 403. A resource whose existence must stay hidden (for example another user's draft) → treat as not found (404).
- Wrap multi-write operations in a transaction through a Domain interface, not `DB::transaction`.
- Document thrown domain exceptions with `@throws`.

**Domain**
- Entities are `final readonly class` with public constructor properties. Dates are `DateTimeImmutable`.
- Domain exceptions are `final class X extends DomainException {}`, named after the condition (`EmailAlreadyTaken`, `UserNotFound`).
- When a UseCase needs a Laravel feature (hashing, clock, queue), define an interface here and implement it in Infrastructure.

**Infrastructure**
- Repositories convert models to entities in a `private static function toEntity()` and never return a model.
- Convert DB errors that carry domain meaning into domain exceptions (for example `UniqueConstraintViolationException` → `EmailAlreadyTaken`).
- Register every binding in `AppServiceProvider::$bindings`.
- Models generate IDs with `HasUuids` (UUIDv7). Do not let the DB generate them.

Not available because of the layering: route model binding, Policies, `Auth::user()`, FormRequest `unique`/`exists`, `LengthAwarePaginator`. For pagination, define a result type with items and total in Domain.

## Tests

- `tests/Feature/<Feature>/`: HTTP through the real stack with `RefreshDatabase` (set in `tests/Pest.php`). Write one test per scenario step and exception in the spec; assert status code, JSON, and DB state.
- `tests/Unit/UseCases/<Feature>/`: UseCases with in-memory doubles of Domain interfaces, defined as anonymous classes in `beforeEach`.
- Doubles follow the test-double rule in the root `AGENTS.md`.
- `tests/Arch/`: layer rules. Add a rule here when a new convention can be checked mechanically.
