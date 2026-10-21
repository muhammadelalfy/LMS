# ADR 0001: PluginStore must stop mutating shared process-wide state at install/uninstall time

- **Status:** Proposed
- **Date:** 2026-10-19
- **Owner:** Architecture (Mudarisy)
- **Related:** `ENGINEERING_CONSTITUTION.md` §6 (Modular Monolith and Module Ownership), §7 (Multi-Tenancy),
  §9 (database tenancy mapping ADR requirement), §23 (Security), §27.6 (ADRs for significant decisions)

## Context

A constitution-compliance audit of `Modules/PluginStore` found that `install()`/`uninstall()` physically
mutate state that is shared by **every school on the same running application**, while recording the
"installed" fact in each school's own tenant database. Reading the actual code confirms and sharpens the
audit's finding:

**Tenancy model actually in use** (`config/tenancy.php`,
`Modules/Tenancy/Services/SchoolProvisioner.php`): `stancl/tenancy` v3 with `DatabaseTenancyBootstrapper` —
**database-per-school**. Each school has its own SQLite file (PostgreSQL in `[BUILD]`), and tenant tables
(including PluginStore's `plugin_products`, `plugin_purchases`, `installed_modules` — see
`Modules/PluginStore/Database/Migrations/2026_08_17_112400_create_plugin_store_tables.php`) are created inside
every school's own database, because `tenancy.database.migration_parameters` globs
`Modules/*/Database/Migrations` and stancl runs that glob per tenant. So `InstalledModule` rows genuinely are
per-school, as the audit and the constitution's module table (§6: "PluginStore: module catalogue, per-school
installs") describe.

**What is not tenant-scoped, and cannot be made so by this mechanism:**

1. **The module registry is one JSON file for the whole deployment.** `config/modules.php` sets
   `'activator' => 'file'` with `'statuses-file' => base_path('modules_statuses.json')`. That file
   (`modules_statuses.json`) lists every module's enabled/disabled flag for the **entire
   application process**, not per request, not per tenant. `PluginInstaller::install()` calls
   `Module::find($plugin->module_name)?->enable()`, which writes to this single shared file. There is no
   tenant dimension in `nwidart/laravel-modules`'s activator at all.
2. **The module's code lives in one shared directory.** `base_path('Modules/<name>')` is the Composer-autoloaded,
   framework-root `Modules/` folder — one filesystem location for the one running codebase. `PluginInstaller`
   does `File::moveDirectory($moduleRoot, $destination)` into it, and `uninstall()` in
   `PluginStoreController::uninstall()` does `File::deleteDirectory($path)` on it. This directory is **not**
   in `tenancy.filesystem.disks` (only `local` and `public` storage disks are tenant-suffixed by
   `FilesystemTenancyBootstrapper`); `Modules/` was never meant to be tenant-scoped by the tenancy package.
3. **Consequence, restated precisely:** one school's admin installing a plugin (a) physically deploys that
   module's PHP code into the shared app, and (b) flips the shared `modules_statuses.json` flag that controls
   whether that module's `ServiceProvider` (routes, event listeners, bound services) is loaded for **every
   request to every school**, not just their own. Uninstalling physically deletes that code out from under
   every other school whose own tenant DB still says `installed_modules.status = 'installed'` — those schools
   now have a dangling DB record pointing at a module whose routes 404 and whose providers are gone, with no
   error raised to them.
4. **This is more severe than a state-consistency bug — it is close to a platform-wide code-execution /
   privilege-escalation hole, not just a data-isolation one.** The install flow extracts an admin-uploaded ZIP
   (`PluginInstaller::install()`, via `ZipArchive`) and the extracted code becomes a `ServiceProvider` that
   `nwidart/laravel-modules` will boot for the whole process once enabled — i.e. **any single school's
   `admin` role** (an ordinary tenant-level role per §23, not a platform operator) can cause arbitrary PHP to
   run inside the one shared application process that serves every other school. §23 requires "uploads …
   never executed"; here an upload becomes a booted service provider. This reclassifies the finding from "a
   tenant isolation bug" to "a tenant isolation bug that is also an unauthenticated-at-the-platform-level
   remote code execution path," which raises the urgency above a normal [BUILD]-backlog item.
5. **A secondary correctness problem even ignoring security:** under the constitution's target stack
   (Octane, §4/§24, horizontal scaling to N stateless instances), `base_path('Modules/')` and
   `modules_statuses.json` are **local to one instance's disk** unless put on shared/synced storage, and
   Octane workers cache booted providers/routes across requests — so "install" would not even reliably take
   effect for *any* school without a worker/instance restart across the whole fleet. This independently
   violates §24 ("No local state on production instances … instances are replaceable").
6. **Scope check against §6:** PluginStore is listed `[NOW]`, and its per-school *install record* is already
   correctly tenant-scoped, matching §6's framing. The violation is specifically in the physical
   code-deployment and module-activation mechanism, not in `InstalledModule`'s data model, which does not need
   to change.

## Decision

**Decouple per-tenant feature activation from physical code deployment. Stop all runtime filesystem mutation
of `Modules/` and all runtime writes to the shared module activator. `PluginStore.install()`/`uninstall()`
become pure per-tenant entitlement toggles against data that already ships with every release.**

Concretely:

1. **All catalogue module code ships with every deploy**, reviewed and released the same way `Core`,
   `Students`, `Attendance`, etc. are — through the normal CI/CD pipeline (§25), not through an admin-triggered
   runtime upload. `Modules/<name>` for every catalogue plugin is present on disk in every environment from
   the moment it is released, exactly like today's `[NOW]` modules.
2. **The module stays registered and enabled for the whole application, always** (`modules_statuses.json`
   stops being written to at runtime; it simply lists every shipped module as enabled, the way it already does
   for `Core`/`Auth`/etc. today). Its `ServiceProvider`, routes and listeners always boot. This removes the
   shared-state race entirely — there is nothing left for one tenant's action to flip for every tenant,
   because there is no longer a global on/off switch tied to a tenant action.
3. **Tenant-level gating moves to the authorization layer**, reading the already-correct, already-tenant-scoped
   `InstalledModule` row in the current school's own database:
   - A new, small `EnsureModuleInstalled` middleware/policy check (PluginStore module) that queries
     `InstalledModule::where('module_name', $name)->where('status', 'installed')->exists()` inside the current
     tenant context and 403s with an Arabic message otherwise — the same place other modules already enforce
     plan limits (§7.7).
   - Every plugin module's own routes/controllers call this check (or a `CheckModuleEnabled('name')` route
     middleware) rather than relying on "does the provider exist in this process" as the gate.
   - `PluginStoreController::install()`/`uninstall()` become: authorize (admin, purchased — unchanged) →
     `DB::transaction` → upsert/update the `InstalledModule` row's `status` in the tenant DB → commit → done.
     No `ZipArchive`, no `File::moveDirectory`/`deleteDirectory` on `Modules/`, no `Module::find()->enable()`/
     `disable()`, no `Artisan::call('optimize:clear')` triggered by a tenant action.
4. **The ZIP-upload-and-extract capability is removed from the tenant-facing `install()` path entirely.**
   If Mudarisy has an actual business requirement for third-party-authored, independently-releasable plugin
   code (as opposed to first-party features gated by plan/purchase), that is a materially larger problem —
   sandboxed execution, static/security review pipeline, resource isolation, a trust boundary around arbitrary
   PHP from a non-platform-operator actor — and is explicitly **out of scope here**. It must get its own ADR
   and must not be solved by writing into the shared `Modules/` directory or the shared activator file,
   regardless of mechanism (see Alternative (c) below, rejected for now per §28 and the Final Rule).
5. **`PluginProduct`/catalogue data** (name, description, price, `module_name`) stays exactly as it is now —
   it already describes *which already-shipped module* a purchase/install maps to; nothing about the catalogue
   model needs to change.

This keeps `InstalledModule` as the single source of truth for "does this school have this feature," matches
§6's existing framing ("per-school installs") without any schema change, and removes every cross-tenant
side-effect identified above. It is strictly simpler than today's code (no ZIP handling, no filesystem
move/delete, no backup/rollback-on-failure dance in `PluginInstaller`), which satisfies the constitution's
Final Rule directly: the simpler, tenant-safe solution wins.

## Alternatives Considered

### (a) Ship-all-code, per-tenant flag gate — **chosen** (see Decision)
*Tradeoffs:* Requires every catalogue plugin's code to go through the normal release pipeline before any
school can use it — a school cannot get a brand-new plugin without Mudarisy shipping a deploy first. This is
an acceptable, in fact desirable, constraint: it means plugin code is reviewed, tested and versioned exactly
like every other module (§6, §25 CI gates), with no new trust boundary. It also means "installing" is instant
and synchronous (a DB flag flip) instead of a slow, failure-prone filesystem operation — better UX, strictly
fewer failure modes, no background `optimize:clear`/cache-busting needed. Enabling an optional feature only
for schools that purchased it is now purely an authorization concern, which is exactly what §23 already
requires everywhere else.

### (b) A genuinely per-tenant module registry/filesystem namespace
Give each tenant its own copy of `Modules/<name>` (e.g. under a tenant-suffixed path already covered by
`FilesystemTenancyBootstrapper`) and a per-tenant module activator (a custom activator keyed by school id
instead of one shared `modules_statuses.json`), so one school's install/uninstall truly cannot touch another's.

*Tradeoffs, why rejected:* `nwidart/laravel-modules` discovers and boots providers once per application
bootstrap (composer autoload + one `modules_statuses.json`), not per request; making it tenant-aware would mean
either (i) re-resolving and re-registering a different set of service providers/routes on every single request
after tenancy is initialized — expensive, fights route caching, and is exactly the kind of per-request
dynamic-bootstrapping complexity §8/§28 warn against — or (ii) running genuinely separate
application processes/deployments per school, which is a disguised step toward per-tenant services and
conflicts with "Modular Monolith … do not begin with unrestricted microservices" (§2, §28) for a problem that
does not need it. It also multiplies disk usage and deployment complexity linearly with school count and still
does not solve problem (4) above (arbitrary uploaded code executing with the school's own privileges is safer
than with everyone's, but it is still uploaded, unreviewed PHP executing in production) — it shrinks the blast
radius but does not remove the underlying unsafe capability. Rejected: more complex than the problem requires,
and does not fully close the security gap raised in Context point 4.

### (c) Keep dynamic runtime plugin code, but isolate it properly (sandboxed execution / per-school worker or container)
Run uploaded plugin code in an isolated execution context (separate process, container, or restricted runtime)
per school, so a malicious or buggy upload cannot affect other tenants or the core app.

*Tradeoffs, why rejected (for now):* This is the only alternative that would preserve today's "admin uploads a
ZIP at runtime" product capability safely. It requires infrastructure Mudarisy does not have a measured need
for yet — container/process-per-tenant orchestration, a code review/signing pipeline for uploaded plugins,
resource quotas — i.e. exactly the unrestricted-microservice/Kubernetes-style complexity §28 says not to adopt
without a measured requirement. If "schools can ship their own plugin code independently of Mudarisy's release
cycle" turns out to be a real, prioritized business requirement, it deserves its own ADR weighing this
properly; it should not be backed into as a side effect of fixing today's isolation bug.

### (d) Minimal patch: keep physical install/uninstall, just serialize and scope better
Add a global lock around install/uninstall, and/or stop deleting on uninstall (soft-disable only), to reduce
the chance of concurrent corruption, while keeping one shared `Modules/` directory and one shared activator.

*Tradeoffs, why rejected:* This treats the symptom, not the cause. Even perfectly serialized, one school's
install still globally enables the module and deploys its code for every other school (problem (3)), and the
RCE-adjacent exposure in point (4) is untouched — any tenant admin can still cause arbitrary uploaded PHP to
boot in the shared process, just no longer racily. Rejected as insufficient against §7's "isolation is
absolute."

## Consequences

- **Positive:** Closes the cross-tenant data/availability bug and the arbitrary-code-execution exposure in the
  same change. `PluginInstaller`'s ZIP/filesystem/backup logic and the `Module::enable()/disable()` calls are
  deleted outright — net reduction in code and failure modes. Matches §6 and §7 with no schema migration
  (`InstalledModule` keeps its shape and meaning). Compatible with the `[BUILD]` Octane/N-instances target
  (§24) with no extra work, since there is no runtime filesystem/registry mutation left to be instance-local.
- **Negative / follow-up work required:**
  - Every existing catalogue plugin's code must already be present in the deployed `Modules/` tree and listed
    (enabled) in `modules_statuses.json` at release time; this changes the plugin release process (plugin code
    ships via the normal CI/CD pipeline, not via admin upload) — this is a product/process change, not just a
    code change, and should be communicated to whoever manages the plugin catalogue today.
  - `PluginStoreController`/`PluginInstaller`/routes/tests need rewriting to match the new, simpler flow; this
    ADR intentionally stops short of writing that code (ADR only, per task scope) — implementation is a
    follow-up backend-engineer task against this decision, including a migration note for any already-"installed"
    modules in existing school databases (their `InstalledModule.status` stays authoritative and needs no
    backfill, since the module code for every existing catalogue entry must already be on disk under this
    model).
  - If arbitrary third-party plugin code upload is later confirmed as a real requirement, it needs its own ADR
    building on Alternative (c) or an equivalent, not a reopening of this one.
- **Risk if not done:** every additional PluginStore install/uninstall in production remains a live
  cross-tenant availability bug and a standing RCE-adjacent path exploitable by any single school's `admin`
  role against the whole platform.
