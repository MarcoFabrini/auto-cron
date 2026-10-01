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
- `TRUSTED_PROXIES` defaults to `REMOTE_ADDR` (set in the container entrypoint): the direct peer is trusted to report the client IP. That is right while the app port stays bound to `127.0.0.1` or a private network (the default in `compose.yml`). If you publish it on `0.0.0.0`, anyone can forge `X-Forwarded-For` and sidestep the per-IP rate limits: set `TRUSTED_PROXIES` to your proxy's IP/CIDR (e.g. Cloudflare ranges) instead.
- Update regularly, after a backup: `docker compose exec api php bin/console app:backup:create && docker compose pull && docker compose up -d`. Pin `AUTOCRON_TAG` to a version if you don't want `latest`.
- Copy the backups in `./autocron/backups` (and your `.env`) to another machine. Don't copy `./autocron/db` while the stack is running.

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
- `TRUSTED_PROXIES` di default vale `REMOTE_ADDR` (impostato nell'entrypoint del container): ci si fida del peer diretto per l'IP del client. Va bene finché la porta dell'app resta legata a `127.0.0.1` o a una rete privata (il default di `compose.yml`). Se la pubblichi su `0.0.0.0`, chiunque può falsificare `X-Forwarded-For` e aggirare i limiti per IP: imposta `TRUSTED_PROXIES` a IP/CIDR del tuo proxy (es. gli intervalli Cloudflare).
- Aggiorna spesso, dopo un backup: `docker compose exec api php bin/console app:backup:create && docker compose pull && docker compose up -d`. Fissa `AUTOCRON_TAG` a una versione se non vuoi `latest`.
- Copia i backup di `./autocron/backups` (e il tuo `.env`) su un'altra macchina. Non copiare `./autocron/db` a stack acceso.
