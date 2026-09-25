# Contributing to AutoCron

[En](#contributing-to-autocron) · [It](#contribuire-ad-autocron)

AutoCron is mostly maintained by one person, so small, focused PRs get merged faster.

## Before opening a PR

1. For non-trivial changes, open an issue first so we can talk about it before you spend time on it.
2. One PR = one change.
3. Rebase on `main` before opening it.
4. These must pass:

```bash
lando phpunit
lando phpstan
lando npm run lint
lando npm run typecheck
lando npm run test
```

## Setup

You need [Lando](https://lando.dev).

```bash
git clone https://github.com/MarcoFabrini/auto-cron.git
cd auto-cron
lando start
lando jwt-keys
lando sf doctrine:migrations:migrate --no-interaction
lando sf doctrine:database:create --env=test --if-not-exists
lando phpunit
```

- App: https://autocron.lndo.site (the frontend rebuilds on its own)
- Dev mail: https://mail.autocron.lndo.site

`compose.yml` is only for deployment, use Lando for development.

## Conventions

### Backend

- PHP 8.5, PSR-12, `declare(strict_types=1);` in every file.
- Attributes, autowiring, `final` controllers.
- Naming: `XxxController`, `XxxService`, `XxxVoter`, `XxxRepository`. Singular entities, plural snake_case tables (`Vehicle` → `vehicles`).
- Every endpoint that works on a single resource goes through the Voter. Lists filter by organization explicitly in the query, no hidden global filters.

### Frontend

Strict TypeScript, Tailwind + shadcn/ui, TanStack Query + Zustand, React Hook Form + Zod.

### Tests

- Functional tests extend `App\Tests\Support\ApiTestCase`, with Foundry fixtures.
- If you add an entity, add its factory too.
- If you touch organization data, add a case to `TenantIsolationTest`: org A must not see org B's data.

## Commits

[Conventional Commits](https://www.conventionalcommits.org), with `closes #N` if you're closing an issue.

```
feat: multi-currency support for expenses
fix: VehicleVoter returned VIEW for expired shares
```

## Where help is welcome

- Translations: files are in `frontend/src/i18n/locales/`, a new language must be registered in `frontend/src/i18n/index.ts`.
- Edge-case tests.
- Documentation.
- Bug reports with steps to reproduce.

## What I won't accept

- SaaS features (payments, plans, subscriptions): AutoCron is self-host only.
- Big rewrites without an issue first.
- Proprietary or AGPL-incompatible dependencies.

## License

By opening a PR you confirm the code is yours or you have the right to contribute it, and that it's released under AGPL-3.0-or-later (see `LICENSE`). There's no CLA.

## Behavior

Be respectful. Toxic behavior, discrimination or spam get you banned from the repository.

---

# Contribuire ad AutoCron

AutoCron lo mantengo praticamente da solo, quindi le PR piccole e mirate passano prima.

## Prima di aprire una PR

1. Per modifiche non banali apri prima un issue, così ne parliamo prima che tu ci perda tempo.
2. Una PR = una modifica.
3. Fai rebase su `main` prima di aprirla.
4. Devono passare:

```bash
lando phpunit
lando phpstan
lando npm run lint
lando npm run typecheck
lando npm run test
```

## Setup

Serve [Lando](https://lando.dev).

```bash
git clone https://github.com/MarcoFabrini/auto-cron.git
cd auto-cron
lando start
lando jwt-keys
lando sf doctrine:migrations:migrate --no-interaction
lando sf doctrine:database:create --env=test --if-not-exists
lando phpunit
```

- App: https://autocron.lndo.site (il frontend si ricompila da solo)
- Email di sviluppo: https://mail.autocron.lndo.site

`compose.yml` serve solo per il deploy, per sviluppare usa Lando.

## Convenzioni

### Backend

- PHP 8.5, PSR-12, `declare(strict_types=1);` in ogni file.
- Attributes, autowiring, controller `final`.
- Nomi: `XxxController`, `XxxService`, `XxxVoter`, `XxxRepository`. Entità al singolare, tabelle al plurale in snake_case (`Vehicle` → `vehicles`).
- Ogni endpoint che lavora su una singola risorsa passa dal Voter. Le liste filtrano per organizzazione in modo esplicito nella query, niente filtri globali nascosti.

### Frontend

TypeScript strict, Tailwind + shadcn/ui, TanStack Query + Zustand, React Hook Form + Zod.

### Test

- I test funzionali estendono `App\Tests\Support\ApiTestCase`, con fixture Foundry.
- Se aggiungi un'entità, aggiungi anche la sua factory.
- Se tocchi dati di un'organizzazione, aggiungi il caso in `TenantIsolationTest`: l'org A non deve vedere i dati dell'org B.

## Commit

[Conventional Commits](https://www.conventionalcommits.org), con `closes #N` se chiudi un issue.

```
feat: supporto multi-valuta sulle spese
fix: VehicleVoter restituiva VIEW per condivisioni scadute
```

## Dove serve una mano

- Traduzioni: i file sono in `frontend/src/i18n/locales/`, la lingua nuova va registrata in `frontend/src/i18n/index.ts`.
- Test sui casi limite.
- Documentazione.
- Bug report con i passi per riprodurre il problema.

## Cosa non accetto

- Funzioni da SaaS (pagamenti, piani, abbonamenti): AutoCron è solo self-host.
- Riscritture grosse senza un issue prima.
- Dipendenze proprietarie o non compatibili con l'AGPL.

## Licenza

Aprendo una PR confermi che il codice è tuo o che hai il diritto di contribuirlo, e che viene rilasciato sotto AGPL-3.0-or-later (vedi `LICENSE`). Non c'è un CLA.

## Comportamento

Sii rispettoso. Comportamenti tossici, discriminazione o spam portano al ban dal repository.
