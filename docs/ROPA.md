# Record of Processing Activities (ROPA)

This document serves as a record of personal data processing activities for the Reliquary project, as required by GDPR Article 30.

## 1. Data Controller
**Name:** Reliquary Project Team  
**Contact:** https://github.com/reliquary/project/issues  

## 2. Purposes of Processing and Legal Basis

| Activity | Data Categories | Purpose | Legal Basis (GDPR) |
| :--- | :--- | :--- | :--- |
| User Registration | Username, Email, Password | Account creation and management | Art. 6(1)(b) - Performance of a contract |
| Service Access | IP Address, Login Timestamp | Security, session management, rate limiting | Art. 6(1)(f) - Legitimate interest (Security) |
| Relic Submission | Username, Contributor Name (optional), Geolocation (optional) | Attributing contributions, mapping relics | Art. 6(1)(a) - Consent |
| Usage Analytics | Anonymized IP, Page Views | Improving service performance and features | Art. 6(1)(a) - Consent (Preferences/Analytics) |
| Contact Form | Name, Email, IP Address | Handling user inquiries | Art. 6(1)(b) - Performance of a contract |

## 3. Categories of Data Subjects
- Registered Users
- Website Visitors
- Contributors

## 4. Categories of Personal Data
- **Identity Data:** Username, Email.
- **Technical Data:** IP Address, Browser type, Version, Timezone, Location (if consented).
- **Usage Data:** Interaction logs with the platform.

## 5. Data Recipients and Third-Party Services
- **Hosting Provider:** Local or Cloud-based (see deployment docs).
- **Map Providers:** OpenStreetMap & Leaflet (No PII shared directly, but IP address is visible to their servers upon tile request).

## 6. Data Retention and Erasure

| Data Category | Retention Period | Action |
| :--- | :--- | :--- |
| User Account | Until account deletion or 2 years of inactivity | Full deletion (Cascade) |
| IP Addresses (Logs) | 30 days | Anonymization (automated via `app:gdpr:cleanup`) |
| Geolocation Data | Until user opts-out or account deletion | Deletion |
| Relic Submissions | Indefinitely (as public record) | Linked to "System" or anonymized if user deleted |

## 7. Technical and Organizational Security Measures
- HTTPS/TLS encryption for all traffic.
- Password hashing using Argon2.
- CSRF protection on forms.
- Regular automated cleanup of sensitive data (IPs).
- Role-based access control (RBAC).

---
*Last Updated: 2026-01-26*
