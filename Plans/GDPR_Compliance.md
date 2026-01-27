# GDPR Compliance Implementation Plan

This document outlines the steps required to achieve full GDPR compliance for the Reliquary project.

## 1. Consent Management (Cookies & Tracking)
### Current Status
- Basic banner exists.
- Single "Accept" button sets one cookie for 365 days.
- No categorization.

### Tasks
- [✓] **Refine Cookie Categorization:**
    - Essential: Session, CSRF, Security.
    - Analytics: Visitor statistics (if implemented).
    - Preferences: Language, UI themes.
- [✓] **Granular Consent UI:**
    - Update `_cookie_banner.html.twig` to include a "Settings" or "Preferences" button.
    - Implement a modal or expanded view allowing users to toggle non-essential categories.
- [✓] **Conditional Script Loading:**
    - Ensure non-essential scripts only load *after* consent is granted for their specific category.
    - Implemented conditional loading for Leaflet maps and Geolocation based on "Preferences" consent.
    - Implemented conditional Server-side Access Logging based on "Analytics" consent (with fallback for critical routes).
- [✓] **Consent Logging:**
    - Maintain an anonymous log of when consent was given/withdrawn (for accountability).
    - Implemented anonymous logging via AccessLogService and GDPRController.

## 2. Data Subject Rights (User Control)
### Current Status
- Users can update their username/email.
- Data export and account deletion implemented.
- Geolocation storage opt-out implemented.

### Tasks
- [✓] **Right to Access & Portability (Data Export):**
    - Implement a service to aggregate all user-related data (Profile, Relics created, Images uploaded, Geolocation history).
    - Create a secure route `/profile/export` to download this data in JSON/CSV format.
- [✓] **Right to Erasure (Account Deletion):**
    - Implement a "Delete My Account" feature in the user profile.
    - Ensure "Cascade Delete" or anonymization is correctly handled for:
        - Relics (Should they be deleted or assigned to a "System" user?).
        - Images.
        - Geolocation logs.
- [✓] **Right to Object/Restict Processing:**
    - Add toggles in user settings to opt-out of specific data processing (e.g., "Do not store my geolocation").

## 3. Data Minimization & Privacy by Design
### Current Status
- Geolocation is stored in the `User` entity.
- IP addresses and technical data are collected (as per Privacy Policy).
- Explicit Geolocation Consent implemented (requires "Preferences" category).
- Automated cleanup command implemented (`app:gdpr:cleanup`).

### Tasks
- [✓] **Explicit Geolocation Consent:**
    - Before calling browser Geolocation APIs, show a specific prompt explaining *why* it's needed and *how long* it's stored.
- [✓] **Data Retention Policy (Automated Cleanup):**
    - Create a Symfony Command to:
        - Anonymize IP addresses in logs older than 30 days.
        - Delete inactive users (e.g., no login for 2 years).
- ❌ **Anonymization of Public Contributions:**
    - Allow users to submit relics "Anonymously" (linked to their account for moderation, but name hidden from public view).
    - No! This is a terrible idea. We want to keep track of who contributed what.
  
## 4. Documentation & Accountability
### Current Status
- Legal documentation updated with contact info and third-party services.
- Record of Processing Activities (ROPA) created.
- PII awareness added to submission form.

### Tasks
- [✓] **Legal Review & Update:**
    - Update `translations/legal.en.yaml` with accurate contact information for the Data Controller.
    - Specify exact third-party services used (e.g., Map providers, AI providers).
- [✓] **Data Mapping (Internal Document):**
    - Create a "Record of Processing Activities" (ROPA) listing:
        - What data is collected.
        - Legal basis (Consent, Contract, Legitimate Interest).
        - Storage location and duration.
- [✓] **Privacy Notice for Contributors:**
    - Update the Relic Submission form to include a checkbox: "I understand my contribution will be public and I have removed PII from images/descriptions."

## 5. Technical Security
### Tasks
- [ ] **Audit Sensitive Data:**
    - Ensure no personal data is inadvertently leaked into system logs or error reports (e.g., Sentry, Monolog).
- [ ] **Encryption at Rest:**
    - Verify that the database and backups are encrypted if they contain sensitive PII.

---
*Created on: 2026-01-12*
*Status: Initial Plan*
