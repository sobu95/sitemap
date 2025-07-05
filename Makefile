.PHONY: lint-frontend format-frontend lint-php lint

lint-frontend:
	cd frontend && npm run lint

format-frontend:
	cd frontend && npm run format

lint-php:
	vendor/bin/phpcs

lint: lint-frontend lint-php
