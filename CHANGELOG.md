# [1.25.0](https://github.com/CesarScur/reliquary/compare/v1.24.0...v1.25.0) (2025-07-23)


### Features

* **workflow:** implement relic approval workflow ([c9eaa9a](https://github.com/CesarScur/reliquary/commit/c9eaa9a2edb0bcb878a309c99213626f77f79f6c))

# [1.24.0](https://github.com/CesarScur/reliquary/compare/v1.23.0...v1.24.0) (2025-07-23)


### Bug Fixes

* **enum:** update translation for VENERATION in CanonicalStatus ([c0c653e](https://github.com/CesarScur/reliquary/commit/c0c653e01454766e3c69759bf247b5c0915f4f5f))


### Features

* **admin:** manage incomplete saints in admin panel ([601d7bd](https://github.com/CesarScur/reliquary/commit/601d7bde3004679bfff69d1a8724fd985f5a77e8))
* **filter:** add canonical status filter for saints listing ([2a05a06](https://github.com/CesarScur/reliquary/commit/2a05a06c6421ef519f35c9e63df5e69fa1fb3936))

# [1.23.0](https://github.com/CesarScur/reliquary/compare/v1.22.0...v1.23.0) (2025-07-23)


### Features

* **form:** add dynamic saint creation in Relic form ([b2aa1c3](https://github.com/CesarScur/reliquary/commit/b2aa1c3897adfd042191b4c256bc11c1d0b50190))
* **form:** translate saint autocomplete placeholder text ([3d7e945](https://github.com/CesarScur/reliquary/commit/3d7e945e7ffe88dac12f2c1413dffcf68d778039))
* **images:** add uploader tracking for relic and user images ([67c1c11](https://github.com/CesarScur/reliquary/commit/67c1c11c7295dc40edccba972cb8e569efd7685a))

# [1.22.0](https://github.com/CesarScur/reliquary/compare/v1.21.0...v1.22.0) (2025-07-22)


### Features

* **profile:** implement user profile management with image handling ([e9e1572](https://github.com/CesarScur/reliquary/commit/e9e15727cb3ac4dae19544de9b9965c28ba652b9))

# [1.21.0](https://github.com/CesarScur/reliquary/compare/v1.20.0...v1.21.0) (2025-07-22)


### Features

* **compose:** add volumes for image uploads and temporary files ([7214dc0](https://github.com/CesarScur/reliquary/commit/7214dc0c78a9e05c8e9d455457d4c3620f36072c))

# [1.20.0](https://github.com/CesarScur/reliquary/compare/v1.19.0...v1.20.0) (2025-07-22)


### Features

* **semantic-release:** integrate GitHub plugin for enhanced release automation ([13bb07b](https://github.com/CesarScur/reliquary/commit/13bb07b3f71aa728ca8ad74db5b8dfb617179158))

# [1.19.0](https://github.com/CesarScur/reliquary/compare/v1.18.0...v1.19.0) (2025-07-22)


### Features

* **controllers:** add success flash messages for CRUD operations ([e88af3e](https://github.com/CesarScur/reliquary/commit/e88af3e1d92c2515d73475337155f60c1513f374))
* **relic:** add description field with autocomplete support ([eb18797](https://github.com/CesarScur/reliquary/commit/eb18797692de29a549f059e182de4f750e90de69))

# [1.18.0](https://github.com/CesarScur/reliquary/compare/v1.17.0...v1.18.0) (2025-07-21)


### Features

* **forms:** enhance password validation rules for registration and change password forms ([73482fe](https://github.com/CesarScur/reliquary/commit/73482fe588d4d7a9765c94cf139dee61678a6b40))
* **relic:** introduce relic degree classification and enhance entity management ([acfe0e1](https://github.com/CesarScur/reliquary/commit/acfe0e1ff2af96474ffd912a844b5ee68f4d0127))
* **templates:** enhance relic details layout and image presentation ([587dec9](https://github.com/CesarScur/reliquary/commit/587dec92ab0757891d0ef31c619c1fce36ae2ccd))

# [1.17.0](https://github.com/CesarScur/reliquary/compare/v1.16.0...v1.17.0) (2025-07-21)


### Bug Fixes

* **map:** resolve marker icon issues in Leaflet setup ([03f5998](https://github.com/CesarScur/reliquary/commit/03f5998e061197742a1564794c9f6501d76c62ee))


### Features

* **images:** add image upload functionality for relics ([df23fa5](https://github.com/CesarScur/reliquary/commit/df23fa5d644d9b1503bd7aa5c20b5626844be5fd))
* **images:** enable image removal for relics with visual feedback ([e721d27](https://github.com/CesarScur/reliquary/commit/e721d279dc0167bd4b7c80cafb710d9db21c065e))
* **security:** restrict relic management actions to admin users ([a7c1886](https://github.com/CesarScur/reliquary/commit/a7c1886c532d521d8c7802114b96a91b2f543afd))
* **templates:** add fallback image for relics with no associated images ([1e8ff68](https://github.com/CesarScur/reliquary/commit/1e8ff6835cb9df7c507339127bfefbc0a5633a99))
* **templates:** display thumbnails for relics in lists and improve image presentation ([779e2fd](https://github.com/CesarScur/reliquary/commit/779e2fd4585861b33e1b2a6c0f6f271afd2348c0))
* **templates:** improve relic list design and image presentation ([331e49c](https://github.com/CesarScur/reliquary/commit/331e49cbff294fc3631b090976b5630b5051c1fd))
* **templates:** replace static relic list with responsive Turbo Frames ([e2b51bc](https://github.com/CesarScur/reliquary/commit/e2b51bc175a7bcd2be19b1cb4f5d96a28461b764))

# [1.16.0](https://github.com/CesarScur/reliquary/compare/v1.15.0...v1.16.0) (2025-07-19)


### Features

* **enum:** introduce `CanonicalStatus` enum and integrate with saints functionality ([2b30d71](https://github.com/CesarScur/reliquary/commit/2b30d716cb10ec2a2a0a3f1bacbf253dd41002b0))
* **i18n:** enhance saint translations with new fields and improved descriptions ([e3803f6](https://github.com/CesarScur/reliquary/commit/e3803f68e3bfbea23e75676b2a2d8b58b520fdc6))
* **templates:** add reusable header and enhance saint details template ([d7868e9](https://github.com/CesarScur/reliquary/commit/d7868e94dc882ab53a432bf7e70bbed6c6260007))

# [1.15.0](https://github.com/CesarScur/reliquary/compare/v1.14.0...v1.15.0) (2025-07-19)


### Features

* **forms:** add password visibility toggle to forms ([67309a0](https://github.com/CesarScur/reliquary/commit/67309a0f4eb183a70345b6cfc07cfe49c969e13d))

# [1.14.0](https://github.com/CesarScur/reliquary/compare/v1.13.0...v1.14.0) (2025-07-19)


### Features

* **admin:** add password reset button and modal in user management ([c5a9954](https://github.com/CesarScur/reliquary/commit/c5a9954546f067cf6af72b19b345c81c86a0a8ca))
* **templates:** enhance password reset templates with improved UI styling ([fc0e3e8](https://github.com/CesarScur/reliquary/commit/fc0e3e8408528a4309162342ff7430dbbab75c84))

# [1.13.0](https://github.com/CesarScur/reliquary/compare/v1.12.0...v1.13.0) (2025-07-19)


### Features

* **auth:** implement password reset functionality ([5963366](https://github.com/CesarScur/reliquary/commit/5963366fff5d9c1ac9aeb2d956f39bd85cfa25a3))
* **deployment:** automate database migrations via container entrypoint ([be21e65](https://github.com/CesarScur/reliquary/commit/be21e653ed6cbb79124aff8f5d1b6f9e2dca8b87))
* **i18n:** enhance translations and update templates for multilingual support ([9847718](https://github.com/CesarScur/reliquary/commit/9847718b050e55365e9a2e04926f3fcd05fa2858))

# [1.12.0](https://github.com/CesarScur/reliquary/compare/v1.11.2...v1.12.0) (2025-07-19)


### Features

* **admin:** add translation status viewer for missing translations ([65b80be](https://github.com/CesarScur/reliquary/commit/65b80bebbf3f4da6a3b90f7680da39eaa06cc091))
* **i18n:** add locale switching and multi-language support ([5ec2b3c](https://github.com/CesarScur/reliquary/commit/5ec2b3c1a5b41a194ba4c0addc89064faac6b324))
* **i18n:** replace Spanish and French with Portuguese Brazil support ([28fb1a9](https://github.com/CesarScur/reliquary/commit/28fb1a9b47c12e0054fa74e71059412bb87457b1))
* **i18n:** restructure translations into domain-specific files ([46c6957](https://github.com/CesarScur/reliquary/commit/46c6957cf02c81c99c58dfc11651e619f9d9ba66))
* **templates:** refactor relic templates to use shared header partial ([f7e3136](https://github.com/CesarScur/reliquary/commit/f7e3136be077e30efccc69dcc7bda447e2cd6224))


### Reverts

* Revert "feat(templates): enhance Turbo integration for relic-related templates" ([89c6896](https://github.com/CesarScur/reliquary/commit/89c6896c21a3de5d764ccdc16b7c0d9a9e75c584))

## [1.11.2](https://github.com/CesarScur/reliquary/compare/v1.11.1...v1.11.2) (2025-07-17)


### Bug Fixes

* **templates:** remove unused title header from relic list mobile view ([6d65e6e](https://github.com/CesarScur/reliquary/commit/6d65e6ef75f236ffbb1da617fa58c832bf7e2e0a))

## [1.11.1](https://github.com/CesarScur/reliquary/compare/v1.11.0...v1.11.1) (2025-07-17)


### Bug Fixes

* **docker:** update Dockerfile path for Apache build step ([75e7865](https://github.com/CesarScur/reliquary/commit/75e7865f8a3e11a3d4acdc1ac160ffc98e819df6))

# [1.11.0](https://github.com/CesarScur/reliquary/compare/v1.10.0...v1.11.0) (2025-07-17)


### Features

* **docker:** refactor Docker setup to replace nginx with Apache ([8215497](https://github.com/CesarScur/reliquary/commit/8215497863b43f95f3b4441856955dfc81eb5df9))
* **templates:** add responsive relic list with desktop and mobile views ([bda4c02](https://github.com/CesarScur/reliquary/commit/bda4c025ebdfcf043c419f0b132dc09532dbe549))
* **templates:** enhance relic list design with improved table styling ([ae13a9b](https://github.com/CesarScur/reliquary/commit/ae13a9b41c5a81db0c76b37f8b9c784383bf299c))
* **templates:** enhance Turbo integration for relic-related templates ([54199e5](https://github.com/CesarScur/reliquary/commit/54199e5393bfe78d5c5624e19ef5ecc15037ed0d))
* **templates:** remove admin-only "Users" link from navigation menu ([da43056](https://github.com/CesarScur/reliquary/commit/da43056a64bd64488b645c170808a474a62e7e05))

# [1.10.0](https://github.com/CesarScur/reliquary/compare/v1.9.0...v1.10.0) (2025-07-17)


### Features

* **docker:** add new session volume to production compose file ([6647417](https://github.com/CesarScur/reliquary/commit/6647417a1375092ccad76d70f3e393d996479797))
* **templates:** redesign email confirmation template with improved styling ([46bc268](https://github.com/CesarScur/reliquary/commit/46bc2687b767b79ac13ff20568c8f1f1f092fc27))
* **templates:** redesign email confirmation template with improved styling ([5af510b](https://github.com/CesarScur/reliquary/commit/5af510bc64f5afecbde78deecdbaf8201014aee7))

# [1.9.0](https://github.com/CesarScur/reliquary/compare/v1.8.0...v1.9.0) (2025-07-17)


### Bug Fixes

* **mailer:** update email sender address in registration confirmation ([91b629b](https://github.com/CesarScur/reliquary/commit/91b629b3b4364bc9e048c6634e1caa00b453c142))


### Features

* **project:** update project metadata in composer.json ([4732c36](https://github.com/CesarScur/reliquary/commit/4732c36dfecefafb6af36e210f606f187f706d9f))
* **twig:** add `LogFormatterExtension` for log formatting in templates ([8045599](https://github.com/CesarScur/reliquary/commit/8045599c41a15e99ec48e4c2ecb559cea65599db))

# [1.8.0](https://github.com/CesarScur/reliquary/compare/v1.7.0...v1.8.0) (2025-07-16)


### Features

* **mailer:** Upgrade symfony to 7.2 and add mailtrap ([297ba3e](https://github.com/CesarScur/reliquary/commit/297ba3e38b219224e55592c0ae44a781ad0ffeef))


### Reverts

* Revert "feat(mailer): add Mailtrap integration and update dependencies" ([04845f4](https://github.com/CesarScur/reliquary/commit/04845f490fbab9442070da7a815516926d59f873))

# [1.7.0](https://github.com/CesarScur/reliquary/compare/v1.6.2...v1.7.0) (2025-07-16)


### Features

* **mailer:** add Mailtrap integration and update dependencies ([5b82904](https://github.com/CesarScur/reliquary/commit/5b82904a4c3fc2d155ac1a3cb6958e353bc7a30f))

## [1.6.2](https://github.com/CesarScur/reliquary/compare/v1.6.1...v1.6.2) (2025-07-16)


### Bug Fixes

* **user:** rename `setVerified` to `setIsVerified` ([3656ec9](https://github.com/CesarScur/reliquary/commit/3656ec960176f7ca084c73379003ad1f174c0a40))

## [1.6.1](https://github.com/CesarScur/reliquary/compare/v1.6.0...v1.6.1) (2025-07-15)


### Bug Fixes

* **logging:** optimize Monolog configuration and enhance log handling ([df8f689](https://github.com/CesarScur/reliquary/commit/df8f689b9017f16d28c191f49ff3e4451cc83ddc))

# [1.6.0](https://github.com/CesarScur/reliquary/compare/v1.5.0...v1.6.0) (2025-07-15)


### Bug Fixes

* **dev:** set default URI for development environment ([168e4cc](https://github.com/CesarScur/reliquary/commit/168e4cc30e0dbe0df6200ef209087052e34e6b40))


### Features

* **errors:** add custom error pages and debug error triggers ([796a4b1](https://github.com/CesarScur/reliquary/commit/796a4b10e3a90dcbd46f6a8658c27613d0a943f8))
* **logging:** add admin log viewer interface ([94cc128](https://github.com/CesarScur/reliquary/commit/94cc128cceab32b5aefc381ce05cc267e472ddb7))
* **logging:** integrate Monolog for enhanced logging capabilities ([29d55a0](https://github.com/CesarScur/reliquary/commit/29d55a02e23c48302db447ec4c51d44a7a44f72b))
* **users:** add user management functionality for admins ([4f93b32](https://github.com/CesarScur/reliquary/commit/4f93b324331f45eac4ea4a3c76e827ca0ccf676b))

# [1.5.0](https://github.com/CesarScur/reliquary/compare/v1.4.0...v1.5.0) (2025-07-15)


### Features

* **apache:** configure domain and SSL for production ([3665f22](https://github.com/CesarScur/reliquary/commit/3665f22818d49e07d52ce433ca52b31821066c78))

# [1.4.0](https://github.com/CesarScur/reliquary/compare/v1.3.0...v1.4.0) (2025-07-15)


### Features

* **security:** enforce HTTPS and secure cookies ([46a3063](https://github.com/CesarScur/reliquary/commit/46a306385a9f733a38b24a553f8cb3298fcba0f0))

# [1.3.0](https://github.com/CesarScur/reliquary/compare/v1.2.0...v1.3.0) (2025-07-15)


### Features

* **home:** optimize layout for mobile and desktop views ([df6e8f1](https://github.com/CesarScur/reliquary/commit/df6e8f1ade129e4195a096abaa94451775070249))
* **relics:** redesign relics page for responsiveness ([5c597c7](https://github.com/CesarScur/reliquary/commit/5c597c70336782581d0abc2e6ac559ece2447ba2))

# [1.2.0](https://github.com/CesarScur/reliquary/compare/v1.1.1...v1.2.0) (2025-07-15)


### Bug Fixes

* **navbar:** hide search bar on small screens ([c5f8664](https://github.com/CesarScur/reliquary/commit/c5f86644549fd50b857e91e3537b53860e41a989))


### Features

* **navbar:** improve mobile responsiveness and accessibility ([2d027d7](https://github.com/CesarScur/reliquary/commit/2d027d73765a99f2f4f5d55624c5d0f9c26f6042))

## [1.1.1](https://github.com/CesarScur/reliquary/compare/v1.1.0...v1.1.1) (2025-07-15)


### Bug Fixes

* **map:** adjust no relics message position to bottom left ([1fd5db5](https://github.com/CesarScur/reliquary/commit/1fd5db58702f2594f7a44d55194dbe2e4794515a))
* **release:** adjust semantic-release order and enhance VERSION file management ([7ac8244](https://github.com/CesarScur/reliquary/commit/7ac8244e7319b00d7a854cd570ced6b1cb598f11))

# [1.1.0](https://github.com/CesarScur/reliquary/compare/v1.0.0...v1.1.0) (2025-07-14)


### Features

* **deployment:** integrate Watchtower for automated production updates ([6dd8613](https://github.com/CesarScur/reliquary/commit/6dd861306484d08e604b17466af3dabdd08ac342))

# 1.0.0 (2025-07-13)


### Features

* **ci:** enhance GitHub Actions workflow with Node.js setup and semantic release dependencies ([70582f1](https://github.com/CesarScur/reliquary/commit/70582f14059bfb059b5197b73c0a3435cab52445))
* **docker:** add asset map compilation step to production Dockerfile ([1d4b791](https://github.com/CesarScur/reliquary/commit/1d4b79187b7125f22e10eaee5c410f8034447d3b))
* **versioning:** implement semantic release configuration for automated versioning ([a807c0e](https://github.com/CesarScur/reliquary/commit/a807c0eabf0ad316e28ac32142938a5f7cb43721))
* **versioning:** integrate dynamic versioning system across application ([96805f1](https://github.com/CesarScur/reliquary/commit/96805f118052e7bc93e4fc28d9a6cd88df677848))
