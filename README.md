# Switon Log Package

[![Log CI](https://img.shields.io/github/actions/workflow/status/switon-php/log/ci.yml?branch=main&label=Log%20CI)](https://github.com/switon-php/log/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's PSR-3 logger for application services that want automatic categories, structured context, per-category
filtering, and low-setup defaults.

## Highlights

- **Standard logger contract:** `LoggerInterface` is the main logging contract.
- **Automatic category routing:** log categories can be derived from the caller.
- **Structured context:** placeholder interpolation and extra context stay structured.
- **Output backends:** stdout, file, syslog, and memory sinks are available.
- **Category-aware filtering:** `Logger` derives categories from the caller and applies per-category levels.

## Installation

```bash
composer require switon/log
```

## Quick Start

```php
use Psr\Log\LoggerInterface;
use Switon\Core\Attribute\Autowired;

class UserService
{
    #[Autowired] protected LoggerInterface $logger;

    public function login(int $userId): void
    {
        $this->logger->info('User logged in', ['user_id' => $userId]);
    }
}
```

Docs: https://docs.switon.dev/latest/log

## License

MIT.
