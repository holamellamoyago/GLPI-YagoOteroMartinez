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

## GLPI 11 Menu System (menu_toadd)

The `menu_toadd` hook in `plugin_init_xxx()` registers plugin entries in the left sidebar menu.
**GLPI 11 processes two different formats**, and using the wrong one silently drops your menu.

### Format 1: Add to EXISTING section (string value)

```php
// Adds PluginDashboardDashboard inside the "Setup" (config) section
$PLUGIN_HOOKS['menu_toadd']['dashboard'] = ['config' => 'PluginDashboardDashboard'];
//                                           ^^^^^^^^^^^^^ string value = class name
```

The key (`config`) must match a section from `src/Html.php:getMenuInfos()`: `assets`, `helpdesk`, `management`, `tools`, `plugins`, `admin`, `config`, `preference`.

**Pitfall**: `isset($menu[$key])` check at line 1389 — if the section key doesn't exist in the base menu, the entry is **silently dropped**. Using a string value with a new section name will NEVER work.

### Format 2: Create a NEW top-level section (array value)

```php
// Creates a brand new top-level menu section called 'mydashboard'
$PLUGIN_HOOKS['menu_toadd']['dashboard'] = ['mydashboard' => ['PluginDashboardDashboard']];
//                                           ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^ array value
```

With an array value, PHP auto-creates `$menu['mydashboard']` (bypasses the `isset` check). The new section needs:

| Requirement | Source |
|-------------|--------|
| **Title** | `getMenuContent()` → `'title'` |
| **Default page** | `getMenuContent()` → `'page'` |
| **Icon** | Static method `getIcon()` on the class (NOT from `getMenuContent()`) |

### When getMenuContent() is called

After the hook processes `types[]`, GLPI calls `$type::getMenuContent()` for each registered class (line 1402-1424). The returned array populates the section:

```php
static function getMenuContent(): array|false {
    return [
        'title' => __('Dashboard', 'dashboard'),  // Menu label
        'page'  => '/plugins/dashboard/front/dashboard.php',  // Link target
    ];
}
```

Return `false` to hide the menu entry conditionally (e.g., based on permissions).

### Html::header() must match the section key

```php
Html::header($title, $url, $section, $item);
//                          ^^^^^^^^^ section key from menu_toadd
//                                    ^^^^^^ item to highlight in submenu
```

- `$section`: The same identifier used as key in `menu_toadd` (e.g., `'mydashboard'`, `'config'`)
- `$item`: Identifier to highlight the active sub-item (for simple plugins, the plugin name is fine)

**Using the wrong section in header() causes breadcrumb and sidebar highlighting to misbehave.**

## Translation function __()

```php
__('Dashboard', 'dashboard')
//  ^^^^^^^^^^^^^^  text in English (default)
//                 ^^^^^^^^^^^  text domain (plugin name)
```

- First arg: default English string
- Second arg: text domain (typically the plugin name) — prevents collisions between plugins
- Translations stored in `plugins/<name>/locales/es_ES.php` (e.g., `$LANG['dashboard']['Dashboard'] = 'Panel de control';`)

## Hooks in GLPI

Hooks are extension points where GLPI notifies plugins that something is happening.
Defined as constants in `src/Glpi/Plugin/Hooks.php`. Registered in `plugin_init_xxx()` via `$PLUGIN_HOOKS`.

| Hook | Constant | Purpose |
|------|----------|---------|
| `menu_toadd` | `Hooks::MENU_TOADD` | Add entries to sidebar menu |
| `csrf_compliant` | - | Mark plugin pages as CSRF-safe |
| `post_item_form` | `Hooks::POST_ITEM_FORM` | Inject content into item forms |
| `item_add` | `Hooks::ITEM_ADD` | React when an item is created |
| `pre_item_update` | `Hooks::PRE_ITEM_UPDATE` | Intercept before item update |
| `dashboard_cards` | `Hooks::DASHBOARD_CARDS` | Add cards to GLPI dashboard |
| `dashboard_filters` | `Hooks::DASHBOARD_FILTERS` | Add filters to GLPI dashboard |

## Frontend UI Widgets (replaces raw echo HTML)

GLPI 11 provides a rich set of PHP helpers and widgets. Avoid raw `echo '<div>...'` in `front/` pages.

### Page Structure

| Method | Purpose |
|--------|---------|
| `Html::header($title, $url, $section, $item)` | Full page header with sidebar, toolbar, breadcrumb |
| `Html::footer()` | Closes page, loads deferred JS |
| `Html::popHeader()` / `popFooter()` | For pages opened inside a Bootstrap modal |

### Form Components

| Method | Purpose |
|--------|---------|
| `Html::input($name, $options)` | `<input>` tag (type defaults to `text`, class `form-control`) |
| `Html::hidden($name, $options)` | `<input type="hidden">` |
| `Html::submit($caption, $options)` | `<button type="submit">` with optional icon/confirmation |
| `Html::textarea($options)` | Rich textarea with optional TinyMCE editor |
| `Html::showDateField($name, $options)` | Flatpickr date picker (single, range, multiple) |
| `Html::showDateTimeField($name, $options)` | Flatpickr date+time picker |
| `Html::showColorField($name, $options)` | Native `<input type="color">` |
| `Html::showCheckbox($options)` | Single checkbox with label and tooltip |
| `Html::showCheckboxMatrix($cols, $rows, $options)` | Matrix of checkboxes with row/col toggles |
| `Html::closeForm($display)` | Closes a `<form>` with CSRF token |
| `Html::requestRefresh()` | Meta tag to auto-refresh page |

### CRITICAL: Echo vs Return en los helpers Html

La mayoría de los helpers Html usan **`return`**, NO `echo`. Si los llamas sin `echo`, no producen salida visible — no hay error, simplemente no se ve nada.

| Método | ¿Echo o return? | Uso correcto |
|--------|-----------------|--------------|
| `Html::textarea($opts)` | **echo** (display=true por defecto) | `Html::textarea([...])` |
| `Html::submit($caption, $opts)` | **return** | `echo Html::submit('...', [...])` |
| `Html::link($text, $url, $opts)` | **return** | `echo Html::link('...', '#', [...])` |
| `Html::input($name, $opts)` | **return** | `echo Html::input('...', [...])` |
| `Html::hidden($name, $opts)` | **return** | `echo Html::hidden('...', [...])` |
| `Html::select($name, $values, $opts)` | **return** | `echo Html::select(...)` |
| `Html::closeForm($display)` | **echo** (display=true por defecto) | `Html::closeForm()` |
| `Html::showSimpleForm(...)` | **echo** | `Html::showSimpleForm(...)` |
| `Html::getSimpleForm(...)` | **return** | `echo Html::getSimpleForm(...)` |
| `Html::scriptBlock($js)` | **echo** | `Html::scriptBlock('...')` |
| `Html::script($url, $opts)` | **echo** | `Html::script('/path/file.js')` |
| `Html::css($url)` | **echo** | `Html::css('/path/file.css')` |

**Cómo detectarlo**: busca en `src/Html.php` — si el método termina con `return sprintf(...)`, necesita `echo`. Si tiene `echo $out; return true`, no necesita `echo`. O usa `ob_start(); Metodo(); $out = ob_get_clean();` — si `$out` está vacío, el método usa `return`.

### Dropdowns (select2 searchable combos)

| Method | Purpose |
|--------|---------|
| `Dropdown::show('User', $options)` | Generic dropdown for any itemtype with Ajax search |
| `Dropdown::showFromArray($name, $elements, $options)` | Dropdown from PHP associative array |
| `Dropdown::showYesNo($name, $value)` | Yes/No radio-style dropdown |
| `Dropdown::showNumber($name, $options)` | Numeric dropdown |
| `Dropdown::showHours($name, $options)` | Hour dropdown (0-24) |
| `Dropdown::showFrequency($name, $value)` | Frequency selector |
| `Dropdown::showLanguages()` | Language dropdown |
| `Dropdown::showItemTypes()` | Itemtype selector (Computer, Monitor, etc.) |
| `Dropdown::showGlobalSwitch()` | On/Off global switch |

### Dashboard Widgets (`\Glpi\Dashboard\Widget`)

All are `public static function`. Each returns HTML for a card. Available in `src/Glpi/Dashboard/Widget.php`.

| Method | Type |
|--------|------|
| `Widget::bigNumber(...)` | Big number card with label, icon, color, clickable URL |
| `Widget::summaryNumber(...)` | Collection of multiple big numbers |
| `Widget::multipleNumber(...)` | Side-by-side number cards with gradients |
| `Widget::pie(...)` | ECharts pie chart |
| `Widget::donut(...)` | ECharts donut chart |
| `Widget::halfPie(...)` / `halfDonut(...)` | Semi-circular variants |
| `Widget::simpleBar(...)` | Vertical bar chart |
| `Widget::simpleHbar(...)` | Horizontal bar chart |
| `Widget::multipleBars(...)` / `multipleHBars(...)` | Grouped bar series |
| `Widget::stackedBars(...)` / `stackedHBars(...)` | Stacked bar charts |
| `Widget::simpleLine(...)` | Single series line chart |
| `Widget::simpleArea(...)` | Single series area chart |
| `Widget::multipleLines(...)` / `multipleAreas(...)` | Multi-series line/area |
| `Widget::markdown(...)` | Editable Markdown card with TinyMCE |
| `Widget::searchShowList(...)` | Embedded search result table inside a card |

### Data Tables

| Method | Purpose |
|--------|---------|
| `Search::showList('Computer', $params)` | Full data table with pagination, sort, massive actions, export |
| `CommonDBTM::displayList($itemtype, $params)` | Simplified list display |
| `Html::printPager($start, $total, $baseurl)` | Pagination controls |

### Modals, Tabs & Tooltips

| Method | Purpose |
|--------|---------|
| `Ajax::createIframeModalWindow($id, $url, $options)` | Bootstrap modal with iframe (adds `_in_modal=1` → auto popHeader) |
| `Ajax::createModalWindow($id, $url, $options)` | Bootstrap modal loading content via Ajax |
| `Ajax::createTabs($divId, $contentId, $tabs, $type, $ID)` | Ajax-loaded tab panel |
| `Html::showToolTip($content, $options)` | qTip2 tooltip on hover/click |
| `Html::redefineAlert()` / `redefineConfirm()` | Replace native alert/confirm with styled GLPI dialogs |

### Twig Templates

Twig 3 engine at `src/Glpi/Application/View/TemplateRenderer.php`. Plugin templates auto-discovered in `<plugin>/templates/`.

```php
echo TemplateRenderer::getInstance()->render('@dashboard/my_template.html.twig', $vars);
```

Built-in extensions: `__('...')`, `csrf_token()`, `session('key')`, `config('k')`, `getTypeName()`, `getLink()`, `path('route')`.

### JS Library Loader

```php
Html::requireJs('charts');    // ECharts
Html::requireJs('flatpickr'); // Date picker
Html::requireJs('tinymce');   // Rich text editor
Html::requireJs('gridstack'); // Dashboard grid
Html::requireJs('fileupload');// jQuery File Upload
Html::requireJs('clipboard'); // Clipboard.js
Html::requireJs('sortable');  // SortableJS
Html::requireJs('leaflet');   // Maps
Html::requireJs('fullcalendar');
Html::requireJs('rateit');    // Star rating
Html::requireJs('masonry');   // Masonry layout
Html::requireJs('fuzzy');     // Fuzzy search
Html::requireJs('photoswipe');// Image gallery
```

### Progress Bars

| Method | Purpose |
|--------|---------|
| `Html::getProgressBar($percentage, $label)` | Static Bootstrap progress bar |
| `Html::progress($max, $value, $params)` | Accessible progress bar with tooltip |

### JavaScript Helpers

```php
Html::scriptBlock('JS code here');           // Inline <script>
Html::script('/path/to/file.js');            // <script src="...">
Html::css('/path/to/file.css');              // <link rel="stylesheet">
Html::scss('/plugins/mine/scss/file.scss');  // Compiles SCSS on the fly
Html::getCoreVariablesForJavascript();       // Expose CFG_GLPI to JS
```

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
