# SECTF Recon

A Laravel app for HackFest 2026 SECTF Phase 1 (passive OSINT). Claude Code connects to it as a remote MCP server and files findings with sources; you watch a live dashboard and export a print-ready, bilingual dossier.

## Set it up (for you or for an AI doing it for you)

Prerequisites: **Docker with the compose plugin**. Nothing else; there is no PHP or Node on the host. On Windows, run from WSL with Docker Desktop's WSL integration on. Optional: Tailscale, if other people or machines need to reach it.

```bash
git clone <this repo> sectf && cd sectf
scripts/bootstrap.sh "Your Name" you@example.com
```

That one command creates `.env`, builds the image, installs dependencies, generates the app key and OAuth keys, builds the front end, migrates, seeds the 67-flag catalogue and creates your login. The generated password is printed once. It is safe to re-run.

Then open <http://localhost:8080>, log in, and follow [docs/CONTESTANT.md](docs/CONTESTANT.md) to connect Claude Code and run the research.

## Getting the dossier out

The dossier is written as **PDF and HTML, in English and French** (four files) into:

```
dossier-exports/<target-name>/        inside the project folder
```

On Windows with WSL that folder is `\\wsl.localhost\Ubuntu\home\<you>\...\dossier-exports\<target-name>`; the app shows the exact path.

Three ways to produce it, all write the same files and all tell you where they went:

- **Dashboard:** *Export dossier* in the sidebar. A window shows the folder path (with a Copy button) and opens any file.
- **Terminal:** `scripts/dossier.sh` prints the paths, then asks `[p] open the PDF  [f] open the folder  [Enter] nothing`.
- **Claude:** say *"export the dossier"*. It calls `export_dossier`, tells you the folder and file names, asks whether to open the PDF or the folder, and suggests what to strengthen next.

The English dossier is all English and the French one all French. For that, Claude files text in English and adds an `fr` version on every finding, number, person and pretext; anything missing a French version is tagged "English only" in the French dossier and listed in the export's suggestions.

The dossier expects at least **5 pretexts**. Each lists its primary flags and the **flags it can naturally pivot to** with the bridge line, and the pretexts section shows which flags no pretext reaches yet. Phone numbers carry a kind (head office, customer service, full store, distribution centre, repair service, ...) so you can see when every number comes from the same type of place.

## What you can ask Claude to do

Once Claude Code is connected (see [docs/CONTESTANT.md](docs/CONTESTANT.md)) you steer it in plain language. Every answer is filed against your target with its source URLs, so the dashboard and dossier fill in as it works. Examples:

**Kick off a target**
> Create a target for *IKEA Canada* (https://www.ikea.com/ca/en/), select it, and give me a one-paragraph plan: which open flags are worth the most for the effort, and where you will look first.

**Work the catalogue by value**
> List the open flags sorted by points. Take the five cheapest-to-answer ones, research them with passive sources only, and file a finding for each, including your confidence and the exact quote that supports it.

**Go after one flag deeply**
> Flag 04-05 (third-party security guard company). Check job postings, press, permits and procurement notices. File what you find even if the confidence is low, and say what would raise it.

**Build the call sheet**
> Find every public phone number for this company: head office, stores, customer service, press. File each with the page it appears on, note hours and conflicts between sources, and do not dial anything.

**Map people, job facts only**
> Who runs HR, IT and security? File name, title, department and a public profile URL for each. Nothing personal.

**Prepare Phase 2**
> Draft two pretexts for the national line aimed at flags 02-04 and 03-09. Use only numbers already on the call sheet, give each a persona, an opener, the questions mapped to flags, and a polite exit.

**Audit before you submit**
> Show every flag marked found with a confidence under 0.7 or only one source. Re-check those sources, and list what is still open and why.

**Catch up and avoid duplicates**
> Run `get_updates` and tell me what my teammate's Claude filed since the last time I looked. Don't redo anything that is already covered.

**Widen the call sheet**
> Our numbers are all corporate. Find public numbers for full-size stores in Québec, a distribution centre, customer service and a repair line. Skip small commission-based formats: staff there have no time to talk. File each with its kind, plus a French label.

**Wrap up**
> Export the dossier, tell me where the PDF and folder are, and list the three gaps most worth another hour.

### If setup fails

| Symptom | Fix |
|---|---|
| `docker: command not found` in WSL | Docker Desktop → Settings → Resources → WSL integration → enable your distro |
| Port 8080/8081/54329 in use | Set `APP_PORT`, `REVERB_HOST_PORT`, `DB_HOST_PORT` in `.env`, then `docker compose up -d` |
| Blank page / missing styles | `docker compose logs assets`; the first build takes a few minutes |
| Lost the password | `docker compose exec app php artisan sectf:user "Your Name" you@example.com --reset` |

## Everyday commands

```bash
docker compose up -d                                    # start (app :8080, reverb :8081)
docker compose exec app php artisan test                # tests
docker compose exec app vendor/bin/pint --dirty         # formatting
docker compose run --rm assets npm run typecheck        # front-end types
docker compose exec app php artisan sectf:user "Name" email@example.com   # add a contestant (no public registration)
```

## More

- [docs/CONTESTANT.md](docs/CONTESTANT.md): connect Claude Code, run Phase 1, export the dossier
- [CLAUDE.md](CLAUDE.md): architecture rules and conventions for contributors (and their Claudes)
