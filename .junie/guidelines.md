# Development Guidelines for Reliquary Project

This document provides an index to essential information for developers working on the Reliquary project.

## Table of Contents

1. [Introduction](docs/introduction.md)
2. [Build/Configuration Instructions](docs/build-configuration.md)
   - Docker Setup
   - Environment Configuration
3. [Testing Information](docs/testing.md)
   - Testing Setup
   - Running Tests
   - Creating Tests
   - Test Database
4. [Code Style and Development Practices](docs/code-style.md)
   - Project Structure
   - Coding Standards
   - Git Workflow
   - Symfony Best Practices
   - Front-end Architecture
   - Templates
   - Database Management
   - Data Import
5. [Translation System](docs/translation-system.md)
6. [Workflow System](docs/workflow-system.md)
7. [External Integrations](docs/external-integrations.md)
   - OpenStreetMap Integration
   - Image Management System
8. [Implementation Planning](docs/implementation-planning.md)
9. [Production Configuration](docs/production-configuration.md)
   - Docker Production Setup
   - Platform.sh Deployment
   - SSL/TLS Configuration
10. [Version Management and CI Workflow](docs/version-management.md)
    - Semantic Versioning
    - CI Workflow
11. [Maintenance](docs/maintenance.md)
    - Updating the Application
    - Monitoring and Troubleshooting
12. [Common Issues](docs/common-issues.md)

## AI Execution Guidelines

To ensure the environment consistency, the following rules MUST be followed by the AI when executing commands:

- **PHP Commands**: Always use `docker compose exec app php` instead of local `php`.
- **Composer Commands**: Always use `docker compose exec app composer` instead of local `composer`.
- **Symfony Console**: Always use `docker compose exec app bin/console` instead of local `bin/console`.
- **Other Container Commands**: For any other tool required that is part of the application stack, use `docker compose exec app <command>`.

## How to Use This Documentation

Each link in the table of contents leads to a detailed document about that specific topic. If you're new to the project, we recommend reading through all the documentation in order. If you're looking for specific information, use the table of contents to navigate to the relevant section.

## Contributing to Documentation

If you find any issues or have suggestions for improving this documentation, please update the relevant files and submit a pull request.
