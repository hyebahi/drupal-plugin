# Drupal Cookie Consent Plugin

This repository contains Drupal modules for cookie consent management integration, including TrustArc cookie consent banner integration.

## Modules

- **trustarc** - Provides TrustArc Cookie Consent Manager (CCM) integration
- **cookiebot** - Provides Cookiebot integration (additional module)
- **js_cookie** - Provides JavaScript Cookie library integration (dependency)

## Installation

```bash
git clone https://github.com/hyebahi-trustarc/drupal-plugin.git
cd drupal-plugin
docker compose up -d
```

Access the application via http://localhost:8080

## Code Quality & Testing

### Quick Start

All code quality checks are run via Docker. First-time setup:

```bash
make docker-setup
```

Run all checks:

```bash
make all-checks
```

Or run individual checks:

```bash
make phpcs              # PHP Code Sniffer (Drupal coding standards)
make security-audit     # Composer dependency security audit
make drupal-check       # drupal-check + phpstan in temporary Drupal root
```

### Code Quality Standards

The **trustarc** module is tested against:

- **PHPCS (PHP Code Sniffer)** - Enforces Drupal coding standards
- **Security Audit** - Checks for known vulnerabilities in dependencies
- **GitLab CI** - Enforced automated checks on merge requests and commits

### Current Status

✅ **All Checks Passing:**
- 0 Errors
- 0 Warnings
- 0 Security vulnerabilities

### Available Commands

```bash
make help               # Show all available commands
make docker-setup      # Install dev dependencies
make phpcs             # Run PHP Code Sniffer
make security-audit    # Run security audit
make all-checks        # Run all checks
make shell             # Interactive Docker shell
```

### Development

For local development or manual testing, you can use the interactive shell:

```bash
make shell
```

This opens a bash shell in the Docker environment where you can run commands like:

```bash
# In the shell:
cd trustarc
./vendor/bin/phpcs --standard=phpcs.xml --colors src/
./vendor/bin/phpcbf --standard=phpcs.xml src/  # Auto-fix issues
composer audit
```

### CI/CD Pipeline

The enforced checks that run via GitLab CI are:

- PHPCS
- Composer security audit

These checks run on:
- Merge requests
- Commits to `main` branch
- Commits to `develop` branch

`make drupal-check` builds a temporary Composer-based Drupal root inside Docker and runs both `drupal-check` and `phpstan` against `trustarc`.

See `.gitlab-ci.yml` in the trustarc module for configuration.

## Project Structure

```
drupal-plugin/
├── trustarc/                    # TrustArc module (main focus)
│   ├── src/                     # PHP source code
│   │   └── Form/
│   │       └── TrustarcSettingsForm.php
│   ├── config/                  # Configuration schemas
│   ├── composer.json            # Dependencies (with dev tools)
│   ├── .eslintrc.json           # ESLint config
│   ├── .gitlab-ci.yml           # CI/CD pipeline
│   └── README.md                # Module documentation
├── cookiebot/                   # Cookiebot module
├── phpcs.xml                    # PHP Code Sniffer config (shared)
├── phpstan.neon                 # PHPStan config (shared)
├── js_cookie/                   # JS Cookie library wrapper
├── docker-compose.yaml          # Docker setup
├── Dockerfile                   # Custom PHP/Composer image
├── Makefile                     # Code quality commands
├── CODE_QUALITY.md              # Detailed code quality documentation
└── README.md                    # This file
```

## Documentation

- [CODE_QUALITY.md](CODE_QUALITY.md) - Detailed code quality checks documentation
- [trustarc/README.md](trustarc/README.md) - TrustArc module documentation

## Contributors

- Haissam Yebahi
- Felipe Brito
- TrustArc

## Contributing

When submitting pull requests:

1. Ensure all code quality checks pass: `make all-checks`
2. Fix any PHPCS violations: `./vendor/bin/phpcbf --standard=phpcs.xml src/`
3. Keep code dependencies up to date: `composer audit`

## License

See individual module LICENSE files for licensing information.

