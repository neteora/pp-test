# PostPickr

Pubblicazione programmata sui social con generazione testo via LLM (progetto "gioco", non per produzione).

    backend/    Symfony 7.4 / PHP 8.3: API + worker (un solo codebase, un solo container `app`)
    frontend/   React + Vite, servito da Apache
    docker-compose.yml   app, frontend, mysql  (+ storage media S3-compatibile esterno)

## Avvio

    # 1. Segreti: LLM_API_KEY, S3_* in backend/.env.local (vedi backend/.env)
    docker compose up -d --build mysql
    # 2. Passo esplicito, una volta (e a ogni deploy con nuove migrazioni): migrazioni + tabelle della coda
    docker compose run --rm app sh docker/init.sh
    # 3. Tutto il resto
    docker compose up -d

API su http://localhost:8095, interfaccia su http://localhost:8096.

## Dentro il container `app` (supervisord)

| processo     | comando                                                                |
|--------------|------------------------------------------------------------------------|
| `api`        | Apache + mod_php                                                       |
| `worker`     | `messenger:consume async scheduler_default publish_instagram`          |
| `worker-llm` | `messenger:consume llm`                                                |

Flusso: lo Scheduler emette un tick al minuto (con lock su MySQL) → i post `pending` scaduti vengono
distribuiti in chunk su `async` → claim atomico `pending→queued` → coda `publish_<piattaforma>` →
`queued→publishing` → `published | failed`. Il passaggio `draft→pending` avviene solo con una richiesta
autenticata dell'utente (`POST /api/posts/{id}/approve`).

Deploy: leggi readme all'interno delle directory backend e frontend

Dopo un deploy: `php bin/console messenger:stop-workers` (supervisord li riavvia con il codice nuovo).

