.PHONY: help docker-setup phpcs drupal-check security-audit all-checks

help:
	@echo "Drupal Plugin Code Quality Commands"
	@echo "===================================="
	@echo ""
	@echo "Setup:"
	@echo "  make docker-setup          Install dev dependencies in Docker"
	@echo ""
	@echo "Code Quality Checks:"
	@echo "  make phpcs                 Run PHP Code Sniffer (Drupal standards)"
	@echo "  make drupal-check          Run drupal-check + phpstan in temp Drupal root"
	@echo "  make security-audit        Run Composer security audit"
	@echo "  make all-checks            Run all checks"
	@echo ""
	@echo "Usage:"
	@echo "  make docker-setup    # Run this first"
	@echo "  make all-checks      # Run all quality checks"

# Setup Docker with dev dependencies
docker-setup:
	@echo "Setting up Docker environment with dev dependencies..."
	docker-compose run --rm code-quality bash -c "cd trustarc && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && composer install"
	@echo "✓ Docker setup complete!"

# Run PHP Code Sniffer
phpcs:
	@echo "Running PHP Code Sniffer..."
	-docker-compose run --rm code-quality bash -c "cd trustarc && ./vendor/bin/phpcs --standard=../phpcs.xml --colors src/"

# Run Drupal Check + PHPStan in a temporary Composer-based Drupal root.
drupal-check:
	@echo "Running drupal-check and phpstan in temporary Drupal root..."
	docker-compose run --rm code-quality bash -lc "set -euo pipefail; rm -rf /tmp/drupal-review; composer create-project drupal/recommended-project:^10.2 /tmp/drupal-review --no-interaction --no-progress; cd /tmp/drupal-review; mkdir -p web/modules/contrib/trustarc; (cd /workspace/trustarc && tar --exclude=vendor --exclude=composer.lock -cf - .) | (cd web/modules/contrib/trustarc && tar -xf -); cp /workspace/phpstan.neon web/modules/contrib/trustarc/; composer require --dev mglaman/drupal-check phpstan/phpstan --no-interaction --no-progress -W; ./vendor/bin/drupal-check --memory-limit=1G --deprecations --analysis web/modules/contrib/trustarc; ./vendor/bin/phpstan analyse web/modules/contrib/trustarc/src web/modules/contrib/trustarc/trustarc.module web/modules/contrib/trustarc/trustarc.install -c web/modules/contrib/trustarc/phpstan.neon --memory-limit=1G --no-progress"

# Run Security Audit
security-audit:
	@echo "Running Security Audit..."
	docker-compose run --rm code-quality bash -c "cd trustarc && composer audit"

# Run all checks
all-checks: phpcs drupal-check security-audit
	@echo ""
	@echo "✓ All checks completed!"

# Interactive shell for manual testing
shell:
	docker-compose run --rm code-quality bash
