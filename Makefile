.PHONY: help docker-setup phpcs eslint prettier cspell drupal-check security-audit test-unit test-kernel all-checks

help:
	@echo "Drupal Plugin Code Quality Commands"
	@echo "===================================="
	@echo ""
	@echo "Setup:"
	@echo "  make docker-setup          Install dev dependencies in Docker"
	@echo ""
	@echo "Code Quality Checks:"
	@echo "  make phpcs                 Run PHP Code Sniffer (Drupal + DrupalPractice)"
	@echo "  make eslint                Run ESLint on JS files"
	@echo "  make prettier              Run Prettier format check on JS files"
	@echo "  make cspell                Run cspell spell check"
	@echo "  make drupal-check          Run drupal-check + phpstan in temp Drupal root"
	@echo "  make security-audit        Run Composer security audit"
	@echo "  make test-unit             Run PHPUnit Unit tests (no database required)"
	@echo "  make test-kernel           Run PHPUnit Kernel tests (requires running DB)"
	@echo "  make all-checks            Run all checks (matches GitLab CI)"
	@echo ""
	@echo "Usage:"
	@echo "  make docker-setup    # Run this first"
	@echo "  make all-checks      # Run all quality checks"

# Setup Docker with dev dependencies (PHP + Node)
docker-setup:
	@echo "Setting up Docker environment with dev dependencies..."
	docker-compose build code-quality
	docker-compose run --rm code-quality bash -c "cd trustarc && composer install"
	docker-compose run --rm code-quality bash -c "npm install"
	@echo "✓ Docker setup complete!"

# Run PHP Code Sniffer (Drupal + DrupalPractice standards)
phpcs:
	@echo "Running PHP Code Sniffer..."
	docker-compose run --rm code-quality bash -c "cd trustarc && ./vendor/bin/phpcs --standard=../phpcs.xml --colors src/ trustarc.module trustarc.install"

# Run ESLint on JS files
eslint:
	@echo "Running ESLint..."
	docker-compose run --rm code-quality bash -c "./node_modules/.bin/eslint trustarc/js/ --color"

# Run Prettier format check on JS files
prettier:
	@echo "Running Prettier..."
	docker-compose run --rm code-quality bash -c "./node_modules/.bin/prettier --check trustarc/js/"

# Run cspell spell check
cspell:
	@echo "Running cspell..."
	docker-compose run --rm code-quality bash -c "./node_modules/.bin/cspell lint --config cspell.json --no-progress"

# Run Drupal Check + PHPStan in a temporary Composer-based Drupal root.
drupal-check:
	@echo "Running drupal-check and phpstan in temporary Drupal root..."
	docker-compose run --rm code-quality bash -lc "set -euo pipefail; rm -rf /tmp/drupal-review; composer create-project drupal/recommended-project:^10.2 /tmp/drupal-review --no-interaction --no-progress; cd /tmp/drupal-review; mkdir -p web/modules/contrib/trustarc; (cd /workspace/trustarc && tar --exclude=vendor --exclude=composer.lock -cf - .) | (cd web/modules/contrib/trustarc && tar -xf -); cp /workspace/phpstan.neon web/modules/contrib/trustarc/; composer require --dev mglaman/drupal-check phpstan/phpstan --no-interaction --no-progress -W; ./vendor/bin/drupal-check --memory-limit=1G --deprecations --analysis web/modules/contrib/trustarc/src web/modules/contrib/trustarc/trustarc.module web/modules/contrib/trustarc/trustarc.install; ./vendor/bin/phpstan analyse web/modules/contrib/trustarc/src web/modules/contrib/trustarc/trustarc.module web/modules/contrib/trustarc/trustarc.install -c web/modules/contrib/trustarc/phpstan.neon --memory-limit=1G --no-progress"

# Run PHPUnit Unit tests (no database needed)
test-unit:
	@echo "Running PHPUnit Unit tests..."
	docker-compose run --rm code-quality bash -lc "set -euo pipefail; \
		rm -rf /tmp/drupal-test; \
		composer create-project drupal/recommended-project:^10.2 /tmp/drupal-test --no-interaction --no-progress; \
		cd /tmp/drupal-test; \
		mkdir -p web/modules/contrib/trustarc; \
		(cd /workspace/trustarc && tar --exclude=vendor --exclude=composer.lock -cf - .) | (cd web/modules/contrib/trustarc && tar -xf -); \
		composer require --dev drupal/core-dev --no-interaction --no-progress -W; \
		cp web/core/phpunit.xml.dist web/core/phpunit.xml; \
		./vendor/bin/phpunit web/modules/contrib/trustarc/tests/src/Unit/ --configuration web/core/phpunit.xml --testdox"

# Run PHPUnit Kernel tests (requires docker-compose up -d db drupal first)
test-kernel:
	@echo "Running PHPUnit Kernel tests (requires DB)..."
	docker-compose run --rm \
		-e SIMPLETEST_DB=mysql://drupal:drupal@db/drupal \
		-e SIMPLETEST_BASE_URL=http://drupal \
		code-quality bash -lc "set -euo pipefail; \
		rm -rf /tmp/drupal-kernel-test; \
		composer create-project drupal/recommended-project:^10.2 /tmp/drupal-kernel-test --no-interaction --no-progress; \
		cd /tmp/drupal-kernel-test; \
		mkdir -p web/modules/contrib/trustarc; \
		(cd /workspace/trustarc && tar --exclude=vendor --exclude=composer.lock -cf - .) | (cd web/modules/contrib/trustarc && tar -xf -); \
		composer require --dev drupal/core-dev --no-interaction --no-progress -W; \
		cp web/core/phpunit.xml.dist web/core/phpunit.xml; \
		./vendor/bin/phpunit web/modules/contrib/trustarc/tests/src/Kernel/ --configuration /tmp/drupal-kernel-test/web/core/phpunit.xml --testdox"

# Run Security Audit
security-audit:
	@echo "Running Security Audit..."
	docker-compose run --rm code-quality bash -c "cd trustarc && composer audit"

# Run all checks — mirrors the GitLab CI (DrupalCI) pipeline
all-checks: phpcs eslint prettier cspell drupal-check security-audit test-unit
	@echo ""
	@echo "✓ All checks completed!"

# Interactive shell for manual testing
shell:
	docker-compose run --rm code-quality bash
