# PostPickr

Pubblicazione programmata sui social con generazione testo via LLM (progetto "gioco", non per produzione).

    backend/    Symfony 7.4 / PHP 8.3: API + worker (un solo codebase, un solo container `app`)
    frontend/   React + Vite, servito da Apache
    docker-compose.yml   app, frontend, mysql  (media su disco in backend/public/uploads)

## Avvio

    # 1. Segreti: LLM_API_KEY in backend/.env.local (vedi backend/.env)
    docker compose up -d --build mysql
    # 2. Passo esplicito, una volta (e a ogni deploy con nuove migrazioni): migrazioni + tabelle della coda
    docker compose run --rm app sh docker/init.sh
    # 3. Tutto il resto
    docker compose up -d

API su http://localhost:8080, interfaccia su http://localhost:8081.

## Pubblicazione in locale: tunnel

Instagram scarica l'immagine dal suo URL pubblico: `localhost` non e' raggiungibile. In locale serve un tunnel
verso la porta dell'API (lasciare il terminale aperto finche' si pubblica):

    docker run --rm --network host cloudflare/cloudflared tunnel --url http://localhost:8095

Il comando stampa un indirizzo `https://<nome>.trycloudflare.com`, che cambia a ogni riavvio del tunnel. Poi:

    # backend/.env.local
    MEDIA_PUBLIC_BASE_URL=https://<nome>.trycloudflare.com/uploads

    docker compose up -d --force-recreate app

Le immagini caricate prima della modifica hanno salvato il vecchio indirizzo: vanno ricaricate.

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

Dopo un deploy: `php bin/console messenger:stop-workers` (supervisord li riavvia con il codice nuovo).

## Aggiungere una piattaforma
1. Una classe `SocialPublisherInterface` in `backend/src/Social/` (autoconfigurata).
2. Un transport `publish_<platform>` in `config/packages/messenger.yaml` e il suo nome nel comando di `worker` in `docker/supervisord.conf`.