# GLPI Plugin Development — Agent Context

This file is injected into Hermes when working from this directory.
It contains everything learned about this project's setup, conventions, and pitfalls.

## Project Identity

- **Repo**: https://github.com/holamellamoyago/GLPI-YagoOteroMartinez.git
- **Owner**: Yago, Spanish speaker, DAM student (TFC: StaffLink)
- **Purpose**: Custom GLPI plugins for the StaffLink capstone project
- **License**: GPLv3

## GLPI Environment

| Component | Detail |
|-----------|--------|
| Version | **GLPI 11.0.7** |
| Image | `diouxx/glpi:latest` |
| Container | `glpi_server` |
| Network | `default_glpi_network` |
| URL | `http://localhost:81` |
| DB | MySQL 8.0 (`mysql_glpi`), database `glpidb`, user `glpi` / `glpi_password` |
| PHP | 8.3+ (Alpine) |
| CLI | `bin/console` (always use `--allow-superuser` when running as root) |

## Directory Structure

```
GLPI-YagoOteroMartinez/         ← Open THIS folder as project in IDE
├── plugins/                    ← ONLY this subfolder is bind-mounted to Docker
│   └── dashboard/              ← Your GLPI plugin (live editing → instant in container)
│       ├── setup.php
│       ├── hook.php (optional)
│       ├── front/
│       ├── ajax/
│       ├── js/
│       └── css/
├── src/                        ← GLPI core flat copy (for IDE autocomplete)
├── inc/                        ← PSR-4 + legacy bootstrap
├── vendor/                     ← Composer deps
├── config/                     ← DB config
├── ajax/, bin/, css/, front/, templates/, ...
├── AGENTS.md                   ← This file
├── .gitignore                  ← Excluye todo el core de GLPI
└── README.md
```

### Docker Volume Mount

The local folder `C:\Users\Yago\Documents\Programacion\GLPI-YagoOteroMartinez\plugins` is **bind-mounted** to `/var/www/html/glpi/plugins` inside the container. Only the `plugins/` subfolder goes to Docker — the GLPI flat copy stays local for IDE support.

The GLPI core files live inside the container (not on the host). For IDE autocomplete, you can pull a flat copy from the container:

```bash
# Create a flat GLPI copy alongside the plugin (optional, for IDE support)
mkdir ../glpi-flat
for dir in ajax bin config css dependency_injection files front inc install \
           lib locales marketplace public resources routes src templates vendor version; do
    docker cp glpi_server://var/www/html/glpi/$dir/. "../glpi-flat/$dir/"
done
```

## CRITICAL: Flat Copy, Not Subfolder

**Never copy GLPI source into a subdirectory like `_glpi-core/`.** The `include()` paths in plugin files use relative paths like `include('../../../inc/includes.php')` which resolve to `<project_root>/inc/`, NOT `<project_root>/_glpi-core/inc/`. Subfolders break every relative include and the IDE shows false errors.

The flat copy puts `src/`, `inc/`, `vendor/` etc. at the same level as plugin directories. GLPI does NOT try to load these as plugins because they lack `setup.php` + `plugin_version_xxx()`.

## IDE Setup (PHPStorm)

After the flat copy, configure:

1. **Sources Root**: Right-click `src/` → Mark Directory as → Sources Root
   - Enables PSR-4 namespace resolution (`Glpi\Exception\Http` → `src/Glpi/Exception/Http/`)
2. **Include Path**: Settings → PHP → Include Path → add project root folder
   - Enables legacy `include()`/`require()` path resolution

**Both are required.** Sources Root handles modern namespaced classes. Include Path handles legacy `include('../../../inc/includes.php')` patterns.

## GLPI 10 vs 11 — Key Differences

| Aspect | GLPI 10 | GLPI 11 |
|--------|---------|---------|
| Source classes | `inc/` (flat PHP files) | `src/` (namespaced, Symfony-style) |
| `inc/` contents | 200+ class files | Only bootstrap: `includes.php`, `relation.constant.php` |
| Session class | `inc/session.class.php` | `src/Session.php` with `use Glpi\Plugin\Hooks;` |
| Plugin hooks | `inc/hook.class.php` | `src/Glpi/Plugin/Hooks.php`, `src/Glpi/Plugin/HookManager.php` |
| Autoloading | Manual includes | Composer PSR-4 autoloader |
| Version check | Various sources | `version/11.0.x` directory exists |

## Plugin CLI Commands

```bash
# List plugins
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:list --allow-superuser

# Install (run BEFORE activate)
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:install --allow-superuser <name>

# Activate
docker exec glpi_server php //var/www/html/glpi/bin/console plugin:activate --allow-superuser <name>
```

**NEVER run `plugin:install` or `plugin:activate` without explicit user permission.**
These mutate the user's GLPI instance and they react strongly to unsolicited state changes.

## MSYS / git-bash Quirks

- **Double-slash paths** in `docker exec`: use `//var/www/html/glpi/...` to prevent MSYS from translating the path
- **Forward slashes** in `docker run -v`: use `C:/Users/...` not `C:\Users\...`
- **No sed on Windows paths**: `sed -i` mangles `C:\Users\...` into `C:SERS...`. Use Python for path edits
- **PowerShell commands don't work in git-bash**: use POSIX equivalents (`ls`, `grep`, `find`)

## Docker Container Recreation Pattern

When the container needs to be recreated:

1. Extract current settings with `docker inspect`:
   ```bash
   docker inspect glpi_server --format '{{json .HostConfig.NetworkMode}}'
   docker inspect glpi_server --format '{{json .Config.Env}}'
   ```
2. Stop and remove: `docker stop glpi_server && docker rm glpi_server`
3. Recreate with:
   ```bash
   docker run -d \
     --name glpi_server \
     --network default_glpi_network \
     -p 81:80 \
     -v "//c/Users/Yago/Documents/Programacion/GLPI-YagoOteroMartinez/plugins://var/www/html/glpi/plugins" \
     diouxx/glpi:latest
   ```
4. Re-install DB schema:
   ```bash
   docker exec glpi_server php //var/www/html/glpi/bin/console database:configure \
     --db-host=mysql_glpi --db-port=3306 --db-name=glpidb \
     --db-user=glpi --db-password=glpi_password --allow-superuser --no-interaction
   docker exec glpi_server php //var/www/html/glpi/bin/console database:install \
     --allow-superuser --no-interaction
   ```
5. **Ask permission first** — user may decline

## .gitignore Pattern

Everything except plugin directories and project files is excluded. The `.gitignore` lists each GLPI directory and root file explicitly with leading `/`:

```gitignore
/src/
/inc/
/vendor/
/config/
/ajax/
/bin/
/front/
/lib/
... etc
```

When adding a new plugin directory, it will automatically be tracked by git (no need to update `.gitignore`).

## StaffLink TFC Context

- The user is working on their DAM capstone project (TFC = Trabajo de Fin de Ciclo)
- StaffLink integrates Odoo, GLPI, and n8n on-premise
- GLPI plugins are part of the project scope
- Module code is presented as **simulated scaffolding** in the TFC document, not as fully functional production code

## User Preferences

- Spanish speaker, but technical work in English/Spanish mix
- Gets frustrated with over-engineering (like separate reference folders)
- Prefers ONE folder to work in, not multiple
- Does NOT want actions taken without permission (especially install/activate/container lifecycle)
- Responds strongly to unsolicited changes — ask first

## Related Skill

The `glpi-plugin-development` skill has the full playbook including:
- Plugin structure (setup.php, hook.php, JS injection)
- Database operations (`doQuery` vs `queryOrDie`)
- Hook implementations (post_item_form, item_update)
- AJAX endpoint setup
- Common pitfalls table
