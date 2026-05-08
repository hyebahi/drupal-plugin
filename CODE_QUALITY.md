# Code Quality Checks

Quick reference for running Drupal best practice checks using Docker.

## First Time Setup

```bash
make docker-setup
```

This installs Composer and all dev dependencies (PHPCS, Drupal Check, etc.) in the Docker environment.

## Running Checks

### All Checks
```bash
make all-checks
```

### Individual Checks
```bash
make phpcs              # PHP Code Sniffer (Drupal coding standards)
make drupal-check       # Drupal Check (deprecated code, best practices)
make security-audit     # Composer security audit
```

### Get Help
```bash
make help
```

### Interactive Shell
```bash
make shell
```

## What Each Check Does

- **PHPCS** - Enforces Drupal coding standards on PHP files
- **Drupal Check** - Detects deprecated code and violations of Drupal best practices
- **Security Audit** - Checks for known security vulnerabilities in Composer dependencies

## CI/CD

All checks run automatically in GitLab CI on merge requests and commits to `main`/`develop` branches.

See `.gitlab-ci.yml` for details.
