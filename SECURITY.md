# Security Policy

[En](#security-policy) · [It](#policy-di-sicurezza)

AutoCron is a personal project I work on in my spare time. It comes with no warranty (see `LICENSE`) and no guaranteed support.

## Supported versions

Only the latest commit on `main`. Older versions don't get fixes.

## Reporting a vulnerability

Please don't open a public issue. Report it privately through [GitHub](https://github.com/MarcoFabrini/auto-cron/security/advisories/new) (Security → Report a vulnerability), with steps to reproduce and, if you have one, a fix.

I'll look at it when I can and fix it if possible, but I can't promise response times or deadlines. A PR with the fix is the fastest way to get it solved.

## Not considered vulnerabilities

- DoS through sheer traffic volume.
- Insecure configuration of your own instance.
- Dependency vulnerabilities already fixed upstream: open a PR with the version bump.

## Hardening your instance

Running your instance safely is up to you. A few basics:

- Generate `APP_SECRET`, `JWT_PASSPHRASE` and `MARIADB_PASSWORD` with `openssl rand -hex 32`, and `chmod 600 .env`.
- `TRUSTED_PROXIES` defaults to `REMOTE_ADDR` automatically (set in the container entrypoint) — override in `.env` only if you're behind an external proxy with known IPs (e.g. Cloudflare).
- Update regularly: `docker compose pull && docker compose up -d`.
- Copy the backups in `./autocron/backups` to another machine.

---

# Policy di sicurezza

AutoCron è un progetto personale che porto avanti nel tempo libero. Non ha garanzie (vedi `LICENSE`) né supporto garantito.

## Versioni supportate

Solo l'ultimo commit su `main`. Le versioni precedenti non ricevono correzioni.

## Segnalare una vulnerabilità

Non aprire un issue pubblico. Segnalala in privato tramite [GitHub](https://github.com/MarcoFabrini/auto-cron/security/advisories/new) (Security → Report a vulnerability), con i passi per riprodurla e, se ce l'hai, una fix.

La guardo quando posso e la correggo se possibile, ma non garantisco tempi di risposta né scadenze. Una PR con la correzione è il modo più veloce per risolvere.

## Cosa non è una vulnerabilità

- DoS tramite traffico massiccio.
- Configurazioni insicure della tua istanza.
- Vulnerabilità di dipendenze già corrette upstream: apri una PR con l'aggiornamento.

## Mettere in sicurezza l'istanza

La sicurezza della tua istanza è responsabilità tua. Le basi:

- Genera `APP_SECRET`, `JWT_PASSPHRASE` e `MARIADB_PASSWORD` con `openssl rand -hex 32`, e fai `chmod 600 .env`.
- `TRUSTED_PROXIES` di default vale già `REMOTE_ADDR` (impostato nell'entrypoint del container) — override in `.env` solo se sei dietro un proxy esterno con IP noti (es. Cloudflare).
- Aggiorna spesso: `docker compose pull && docker compose up -d`.
- Copia i backup di `./autocron/backups` su un'altra macchina.
