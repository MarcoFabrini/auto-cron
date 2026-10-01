# AutoCron

[![License: AGPL-3.0](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)

[En](#En) · [It](#It)

---

## En

Self-hosted vehicle management (PWA): maintenance, fuel logs, expenses, reminders and vehicle sharing with your family. Your data, 100% self-hosted.

### Installation

**Requirements:** Docker + Docker Compose.

```bash
curl -O https://raw.githubusercontent.com/MarcoFabrini/auto-cron/main/compose.yml
curl -O https://raw.githubusercontent.com/MarcoFabrini/auto-cron/main/.env
```

Open `.env`, set `DOMAIN` and generate the 3 secrets (DB password, `APP_SECRET`, `JWT_PASSPHRASE`):

```bash
openssl rand -hex 32
```

Start:

```bash
docker compose up -d
```

The app is available at `http://127.0.0.1:8080`. Check: `curl http://127.0.0.1:8080/api/health`

The **first user** to register becomes the instance admin; after that, registration is closed and other users can only join by invitation (Settings → Members).

### Configuration

- **Email:** set `MAILER_DSN` in `.env`. Without it, no emails are sent.
- **Web Push:** from Settings → Notifications (admin only).

### HTTPS (reverse proxy)

The app only speaks plain HTTP. To expose it publicly, create a `compose.override.yml` next to `compose.yml` (example for Traefik with an existing external `proxy` network):

```yaml
services:
  api:
    ports: !reset []
    networks:
      - default
      - proxy
    labels:
      traefik.enable: "true"
      traefik.docker.network: "proxy"
      traefik.http.routers.autocron.rule: "Host(`${DOMAIN}`)"
      traefik.http.routers.autocron.entrypoints: "https"
      traefik.http.routers.autocron.tls.certresolver: "letsencrypt"
      traefik.http.services.autocron.loadbalancer.server.port: "80"

networks:
  proxy:
    external: true
```

Then run `docker compose up -d` again. Using another proxy (nginx, Caddy, Cloudflare Tunnel)? Skip the override and point it at `127.0.0.1:8080`.

### Updating

```bash
docker compose exec api php bin/console app:backup:create   # backup first
docker compose pull && docker compose up -d
```

DB migrations run automatically on startup, so an update can change the schema: take a backup first. To stay on a known version set `AUTOCRON_TAG` in `.env` to a release without the `v` (e.g. `0.1.2`, or `0.1` for its latest patch) instead of the default `latest`, which follows the newest release.

### Backup

Data (DB, attachments, keys) lives in `./autocron/`, but **don't copy `./autocron/db` while the stack is running**: a live copy of a database directory can be inconsistent. A nightly job writes a DB dump and an uploads archive to `./autocron/backups` (readable only by the app user): copy those, plus your `.env`, to another machine. To copy the whole folder, stop the stack first (`docker compose down`).

### Settings worth knowing

| Variable | Default | Meaning |
|---|---|---|
| `TRUSTED_PROXIES` | `REMOTE_ADDR` | Trust the direct peer (your reverse proxy) for `X-Forwarded-For`. Safe only while the port stays on `127.0.0.1`/a private network; if you expose it on `0.0.0.0`, set your proxy's IP/CIDR. |
| `APP_TIMEZONE` | `Europe/Rome` | Calendar timezone for reminder due dates ("today"). |

### Development and contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Security vulnerabilities: [SECURITY.md](SECURITY.md) (do not open public issues).

### License

[AGPL-3.0-or-later](LICENSE)

---

## It

Gestione veicoli self-hosted (PWA): manutenzioni, rifornimenti, spese, scadenze e condivisione in famiglia. Dati tuoi, 100% self-hosted.

### Installazione

**Requisiti:** Docker + Docker Compose.

```bash
curl -O https://raw.githubusercontent.com/MarcoFabrini/auto-cron/main/compose.yml
curl -O https://raw.githubusercontent.com/MarcoFabrini/auto-cron/main/.env
```

Apri `.env`, imposta `DOMAIN` e genera i 3 secret (password DB, `APP_SECRET`, `JWT_PASSPHRASE`):

```bash
openssl rand -hex 32
```

Avvia:

```bash
docker compose up -d
```

L'app è su `http://127.0.0.1:8080`. Verifica: `curl http://127.0.0.1:8080/api/health`

Il **primo utente** che si registra diventa admin dell'istanza; poi la registrazione si chiude e gli altri entrano solo su invito (Impostazioni → Membri).

### Configurazione

- **Email:** imposta `MAILER_DSN` in `.env`. Senza, le email non vengono inviate.
- **Web Push:** da Impostazioni → Notifiche (solo admin).

### HTTPS (reverse proxy)

L'app parla solo HTTP. Per esporla pubblicamente crea un `compose.override.yml` accanto a `compose.yml` (esempio per Traefik con una rete esterna `proxy` già esistente):

```yaml
services:
  api:
    ports: !reset []
    networks:
      - default
      - proxy
    labels:
      traefik.enable: "true"
      traefik.docker.network: "proxy"
      traefik.http.routers.autocron.rule: "Host(`${DOMAIN}`)"
      traefik.http.routers.autocron.entrypoints: "https"
      traefik.http.routers.autocron.tls.certresolver: "letsencrypt"
      traefik.http.services.autocron.loadbalancer.server.port: "80"

networks:
  proxy:
    external: true
```

Poi rilancia `docker compose up -d`. Usi un altro proxy (nginx, Caddy, Cloudflare Tunnel)? Salta l'override e puntalo a `127.0.0.1:8080`.

### Aggiornamento

```bash
docker compose exec api php bin/console app:backup:create   # prima un backup
docker compose pull && docker compose up -d
```

Le migrazioni DB girano da sole all'avvio, quindi un aggiornamento può cambiare lo schema: fai prima un backup. Per restare su una versione precisa imposta `AUTOCRON_TAG` in `.env` a una release senza la `v` (es. `0.1.2`, oppure `0.1` per la sua ultima patch) invece del default `latest`, che segue l'ultima release.

### Backup

I dati (DB, allegati, chiavi) sono in `./autocron/`, ma **non copiare `./autocron/db` a stack acceso**: la copia a caldo di una cartella di database può essere incoerente. Un job notturno scrive un dump del DB e un archivio degli upload in `./autocron/backups` (leggibile solo dall'utente dell'app): copia quelli, più il tuo `.env`, su un'altra macchina. Per copiare l'intera cartella ferma prima lo stack (`docker compose down`).

### Impostazioni da conoscere

| Variabile | Default | Significato |
|---|---|---|
| `TRUSTED_PROXIES` | `REMOTE_ADDR` | Si fida del peer diretto (il tuo reverse proxy) per `X-Forwarded-For`. Sicuro solo finché la porta resta su `127.0.0.1`/rete privata; se la esponi su `0.0.0.0` imposta IP/CIDR del proxy. |
| `APP_TIMEZONE` | `Europe/Rome` | Fuso di calendario per le scadenze dei promemoria ("oggi"). |

### Sviluppo e contributi

Vedi [CONTRIBUTING.md](CONTRIBUTING.md). Vulnerabilità: [SECURITY.md](SECURITY.md) (non aprire issue pubbliche).

### Licenza

[AGPL-3.0-or-later](LICENSE)
