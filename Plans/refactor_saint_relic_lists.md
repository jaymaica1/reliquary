# Plan: Refactor Saint Relic Lists to Unified Standard

## Context
Currently, the `RelicController` contains two endpoints dedicated to rendering relic lists for a specific saint:
- `app_saint_relics_desktop` (rendering `_relic_list_desktop.html.twig`)
- `app_saint_relics_mobile` (rendering `_relic_list_mobile.html.twig`)

These templates were restored as a stop-gap measure because they were missed during the general refactor that unified other relic lists into `relic/index.html.twig`.

## Objective
Consolidate the saint-specific relic lists into a single, responsive view that follows the current project standard, eliminating the need for separate desktop/mobile templates and controller actions.

## Steps

1. **Analyze `relic/index.html.twig`**:
   - Understand how the unified responsive grid is implemented.
   - Identify if it can be reused for the saint-specific list.

2. **Update `RelicController`**:
   - Create a unified `app_saint_relics` route (or reuse/refactor existing ones).
   - Ensure it passes the same variables as the main index (`pagination`, `filter`, `relic_degrees`, etc.).

3. **Refactor Templates**:
   - Either use `relic/index.html.twig` directly with a custom title/context.
   - Or create a new `relic/_list.html.twig` fragment that `index.html.twig` and the saint-specific view can both include.

4. **Update `saint/show.html.twig`**:
   - Update the Turbo Frame or link that points to `app_saint_relics_desktop/mobile` to point to the new unified route.

5. **Cleanup**:
   - Delete `templates/relic/_relic_list_desktop.html.twig`.
   - Delete `templates/relic/_relic_list_mobile.html.twig`.
   - Remove `saintRelicsDesktop` and `saintRelicsMobile` actions from `RelicController`.

## Verification
- Visit a Saint's show page on both Desktop and Mobile.
- Ensure the relics list renders correctly using the unified responsive style.
- Confirm pagination and filters (if applicable) work as expected.
