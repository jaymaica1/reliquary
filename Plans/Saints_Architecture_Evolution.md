# Implementation Plan: Database-First Saint Management

This plan outlines the iterative transition from a YAML-based saint management system to a Database-first architecture with regular backups.

## Phase 1: Database Hardening & Initial Cleanup
**Goal:** Establish the Database as the Single Source of Truth (SSOT) and remove immediate architectural debt.

1.  **Schema Migration:**
    *   Remove the obsolete `file` field from the `Saint` entity (legacy YAML path).
    *   Migrate `biography` and `abstract` fields to the `SaintTranslation` entity to allow full multi-language support.
    *   (canceled) Evaluate and potentially migrate `image_link` to the `SaintImage` system (we decide to keep the reference for reference).
2.  **Data Consolidation:**
    *   (canceled) Run a final, comprehensive import of all data from `data/saints_info.yaml` and its translation files into the database (prod alread has the most up to date data).
    *   Translate the missing saints from the legacy YAML format into the new DB schema.
    *   Import all saint translations from the legacy YAML format into the new DB schema.
    *   Verify data integrity (ensure biographies, abstracts, and metadata are correctly persisted in their respective translation tables).
3.  **Entity Cleanup:**
    *   Remove the `file` property and its getter/setter from `src/Entity/Saint.php`.
    *   Update `src/Form/SaintType.php` to remove the `file` field.

**Deployment Outcome:** A cleaner database schema and a verified, complete dataset in the DB.

---

## Phase 2: Administrative Workflow & UI Polish
**Goal:** Provide a full-featured interface for managing saints without needing manual file edits.

1.  **Vetting Dashboard (Discovery Hub):**
    *   Enhance the existing `AdminIncompleteSaintsController` to provide a clear workflow for "Draft" saints.
    *   Add "Approve/Complete" actions that mark a saint as ready for public view.
    *   Allow admins to discard incomplete saints – relics of said saint must be moved or deleted.
2.  **Enhanced Saint Editor:**
    *   Ensure all fields (biography, abstract, feast days) are fully editable via the `SaintType` form.
    *   (Optional) Add a simple translation toggle or sub-form for managing `SaintTranslation` entities.
3.  **Search & Filtering:**
    *   Improve the `Saint` index in the admin area with robust search (name) and filters (canonical status, incomplete status).

**Deployment Outcome:** Admins can manage the entire lifecycle of a saint (creation, discovery, vetting, editing) through the Web UI.

---

## Phase 3: Automated Vatican Discovery Service
**Goal:** Seamlessly integrate new saints from the Vatican site into the system.

1.  **Discovery Command:**
    *   Refactor `ScrapeVaticanCommand` to `app:discover-vatican`.
    *   Instead of writing to a file, it should:
        1. Crawl the Vatican's index of celebrations/canonizations.
        2. Check for existence in the database (by URL or Name).
        3. Insert new entries as `is_incomplete = true`.
2.  **Admin Notifications:**
    *   Show a badge or notification in the Admin Dashboard when new saints are discovered and awaiting vetting.

**Deployment Outcome:** The system automatically stays up-to-date with Vatican releases, requiring only human verification.

---

## Phase 4: Legacy Code & Asset Removal
**Goal:** Complete the cleanup by removing all obsolete components.

1.  **Command Removal:**
    *   Delete `src/Command/ImportSaintsCommand.php`.
    *   Delete `src/Command/ImportSaintTranslationsCommand.php`.
2.  **Controller & Template Cleanup:**
    *   Delete `src/Controller/AdminImportController.php`.
    *   Remove associated templates in `templates/admin/import/`.
3.  **Data Cleanup:**
    *   Delete `data/saints_info.yaml` and all `data/saints_info_*.yaml` files.
    *   Remove any legacy import scripts.

**Deployment Outcome:** A significantly reduced codebase, faster project search, and no confusion about where the "Source of Truth" lies.

---

## Phase 5: Backup Strategy & Automation
**Goal:** Ensure data durability without Git-based storage.

1.  **Simple Backup Command:**
    *   Create a lightweight command `app:backup-db` that generates a timestamped `.sql` dump of the database.
2.  **Documentation:**
    *   Update `docs/maintenance.md` with instructions on how to restore from a backup and the frequency of automated backups in production.

**Deployment Outcome:** Reliable recovery path for the primary data source.
