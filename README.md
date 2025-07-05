# Sitemap Checker

This project includes a PHP backend and a React frontend.

## API Endpoints

The React frontend communicates with a few simple PHP endpoints located in the `api/` directory. All endpoints require the user to be authenticated (the same session as the PHP application).

### `api/getDomains.php`
Returns the list of domains belonging to the logged in user along with the two most recent check results.

**Response example**
```json
{
  "domains": [
    {
      "id": 1,
      "domain": "https://example.com/sitemap.xml",
      "created_at": "2023-10-01 12:00:00",
      "last_check": 120,
      "previous_check": 110
    }
  ]
}
```

### `api/getDomainHistory.php?id=DOMAIN_ID`
Returns history of sitemap checks for the specified domain (only if it belongs to the logged in user).

**Response example**
```json
{
  "history": [
    {
      "checked_at": "2023-10-01 12:00:00",
      "result": 120
    }
  ]
}
```

The React application should send requests to these endpoints to obtain domain lists and check history data.


## Code Style

This project uses **PHP_CodeSniffer** to enforce the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard. The configuration is stored in `phpcs.xml` at the project root.

### Installation

Install PHP_CodeSniffer as a development dependency:

```bash
composer require --dev squizlabs/php_codesniffer
```

### Usage

Run the following command from the project root to check all PHP files:

```bash
vendor/bin/phpcs
```

PHP_CodeSniffer will automatically use the rules defined in `phpcs.xml`.


## Makefile

Common tasks can be run via `make` commands in the project root.

Run the frontend linter:

```bash
make lint-frontend
```

Format frontend files:

```bash
make format-frontend
```

Check all PHP files with PHP_CodeSniffer:

```bash
make lint-php
```

Run both linters at once:

```bash
make lint
```

