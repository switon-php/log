# Switon Log Package Test Fixtures

This directory contains PSR-4 test fixtures used across Log package tests.

## File Organization

- Top-level `*.php`: one class or interface per file under `Switon\\Log\\Tests\\Fixtures`
- Keep domain, service, transport, and test-double fixtures as separate files
- Prefer single-declaration fixture files over aggregate loaders

## Fixture Groups

### Mock Appenders

- `MockAppender` - Collects log entries for testing
- `FailingAppender` - Throws exception for error testing

### Mock Event Dispatchers

- `MockEventDispatcher` - Collects dispatched events for testing
- `FailingEventDispatcher` - Throws exception for error testing

### Test Utilities

- `LogEntryFactory` - Factory for creating test log entries
- `LoggerEventFactory` - Factory for creating test logger events

### Domain Layer

- `User` (entity) - User domain model
- `Order` (entity) - Order domain model

### Infrastructure Layer

- `UserRepositoryInterface` (interface)
- `UserRepository` → implements `UserRepositoryInterface`, uses `LoggerInterface`
- `PaymentServiceInterface` (interface)
- `PaymentService` → implements `PaymentServiceInterface`, uses `LoggerInterface`
- `EmailServiceInterface` (interface)
- `EmailService` → implements `EmailServiceInterface`, uses `LoggerInterface`

### Service Layer

- `UserService` → depends on `UserRepositoryInterface`, `EmailServiceInterface`, `LoggerInterface`
- `OrderService` → depends on `UserRepositoryInterface`, `PaymentServiceInterface`, `LoggerInterface`

### HTTP Layer

- `RequestInterface` (interface)
- `Request` → implements `RequestInterface`
- `ResponseInterface` (interface)
- `Response` → implements `ResponseInterface`
- `UserController` → depends on `UserService`, `RequestInterface`, `ResponseInterface`, `LoggerInterface`

### Custom Appenders

- `MemoryAppender` - In-memory appender for testing
- `FilteringAppender` - Level-filtering appender for testing

## Notes

- All classes follow typical application architecture patterns (Domain → Service → Infrastructure)
- Classes are designed to test real-world logging scenarios
- Mock classes provide controlled environments for testing
- Error conditions are tested through `Failing*` classes
- Fixture files are organized by responsibility instead of aggregate include files
