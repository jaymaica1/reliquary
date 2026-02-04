### Architectural Design Proposal for Saint Management

To address the two situations (annual new saints from the Vatican and older saints like Saint Peter) while maintaining repository consistency and reducing conflicts, several architectural models are proposed.

---

#### 1. Option A: GitOps-first Hybrid (Recommended)

This model treats the YAML files as the long-term **Source of Truth (Canonical Data)**, while using the database and web forms as a **Working Memory (The Queue)** for data entry and discovery.

*   **Discovery Tool:** A command (`app:discover-vatican`) scrapes the *index* of the Vatican site to find new names/URLs and adds them to the DB as `is_incomplete`.
*   **Biographic Completion:** Admins use web forms to fill in the biography, feast days, and translations.
*   **The Sync Pipeline:** An `app:export-saints` command reads vetted saints and updates `data/saints_info.yaml`.
*   **Pros:** Version controlled, easy to use, handles conflicts via Git.
*   **Cons:** Requires an extra export step.

---

#### 2. Option B: The "Staging Database" (Dual-Schema)

Maintain a clear separation between "Draft/Suggested" saints and "Canonical" saints within the database infrastructure.

*   **Mechanism:** Incoming discoveries from scripts or missing saint suggestions from users go into a `SaintSuggestion` entity.
*   **Workflow:**
    1.  Discovery script populates suggestions.
    2.  Admin reviews and "Promotes" a suggestion to a full `Saint`.
    3.  Promotion triggers an automatic commit/PR to the repository via a Github Action or local script.
*   **Pros:** Public site remains pristine; clear audit trail of who approved what.
*   **Cons:** More complex entity relationship management.

---

#### 3. Option C: Pure File-Based (Static Site Style)

Treat the project as a "Static Site" for saint data.

*   **Mechanism:** No `Saint` table for the primary data. The app reads directly from YAML/JSON files at runtime (with heavy caching).
*   **Workflow:** All additions are made by editing YAML files. The Web UI, if any, is just a "Visual Editor" that saves directly to the filesystem (via a service that can handle `git commit`).
*   **Pros:** Zero database drift; what you see in the code is exactly what is in the app.
*   **Cons:** Performance concerns for very large lists (though solvable with indexing/caching); higher complexity in handling relational data (like Relics pointing to Saints).

---

#### 4. Handling Translations in the Scope

Translations are often the biggest source of "data noise" and merge conflicts.

##### Proposed Translation Strategy: "Locale Partitioning"

Instead of a single `saints_info_pt_BR.yaml`, we should adopt a partitioned structure:

1.  **Core Data (`data/saints/core.yaml`):** Contains only IDs, Vatican URLs, and immutable dates (Canonization/Feast).
2.  **Locale Files (`data/saints/translations/{locale}.yaml`):** Contains the translated name, phrase, abstract, and biography.
3.  **Conflict Resolution:**
    *   **IDs are Primary:** The `id` from the Vatican source is the glue.
    *   **Automated Translation Queue:** When a new saint is discovered in Italian, the system automatically creates "Stubs" in the locale files and marks them as `needs_translation: true`.
    *   **AI Pre-translation:** The export/import process can integrate with an LLM to provide a "First Draft" translation of the Italian biography, which the admin then refines.

---

#### 5. Pros and Cons Comparison (Extended)

| Feature | Option A (Hybrid) | Option B (Staging) | Option C (Pure File) |
| :--- | :--- | :--- | :--- |
| **Ease of Use** | High | High | Medium (needs FS access) |
| **Translation Flow** | Integrated in Form | Staged approval | Manual/Local file edit |
| **Git Conflict Risk** | Low (if exported) | Very Low | High (on single big file) |
| **Data Integrity** | High | Maximum | High |
| **Situations (New/Old)** | Handled via Queue | Handled via Suggestion | Handled via PR |

---

#### 6. Summary of Recommended Approach

I recommend **Option A (Hybrid)** combined with the **Locale Partitioning** strategy. 

*   **For New Saints:** The discovery tool finds them, creates `is_incomplete` entries in the DB.
*   **For Old Saints:** Admins create them via the standard form.
*   **For Translations:** The system manages them in separate files per locale, keyed by the shared ID, allowing for easier multi-person translation without stepping on each other's toes.
