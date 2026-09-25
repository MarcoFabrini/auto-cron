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
docker compose pull && docker compose up -d
```

DB migrations run automatically on startup.

### Backup

All data (DB, attachments, keys) lives in `./autocron/`: a `tar` or `rsync` of that folder is enough.

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
docker compose pull && docker compose up -d
```

Le migrazioni DB girano da sole all'avvio.

### Backup

Tutti i dati (DB, allegati, chiavi) sono in `./autocron/`: basta un `tar` o `rsync` di quella cartella.

### Sviluppo e contributi

Vedi [CONTRIBUTING.md](CONTRIBUTING.md). Vulnerabilità: [SECURITY.md](SECURITY.md) (non aprire issue pubbliche).

### Licenza

[AGPL-3.0-or-later](LICENSE)
