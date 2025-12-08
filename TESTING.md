# Flux License Package - Comprehensive Testing Documentation

## Overview

Comprehensive Pest test suite has been created for the flux-license package following the same structure and patterns as the nuxbe-table-reservation package.

## Files Created

### Configuration Files
1. **phpunit.xml** - PHPUnit/Pest configuration with MySQL test database settings
2. **tests/Pest.php** - Pest configuration with test setup and beforeEach hooks
3. **tests/TestCase.php** - Base test case with service provider registration
4. **tests/README.md** - Test documentation

### Test Files

#### Unit Tests

**tests/Unit/Console/Commands/FluxLicenseSendUpdateTest.php**
- Tests for the `flux-license:send-update` command
- 8 comprehensive test cases covering:
  - Sending active user count to license server
  - Correct user email transmission
  - HTTP request failure handling
  - Data structure validation
  - License key from settings
  - Zero active users scenario
  - Success message output

**tests/Unit/Console/Commands/InstallTest.php**
- Tests for the `flux:install` command
- 19 comprehensive test cases covering:
  - Installation completion check
  - Database migration execution
  - Language creation (default and custom)
  - Currency creation with data validation
  - Tenant creation with company data
  - VAT rate creation (from options and defaults)
  - Payment type creation (from options and defaults)
  - Admin user creation with role assignment
  - Default price list creation
  - Default warehouse creation
  - Order type creation
  - Tenant code generation
  - Required field validation
  - Success message display

**tests/Unit/FluxLicenseServiceProviderTest.php**
- Tests for the FluxLicenseServiceProvider
- 6 comprehensive test cases covering:
  - Command registration
  - Scheduled daily task registration
  - Event listener for CreateUser action
  - Event listener for UpdateUser action
  - License update trigger on user creation
  - License update trigger on user update

#### Feature/Integration Tests

**tests/Feature/LicenseUpdateFlowTest.php**
- End-to-end integration tests
- 11 comprehensive test cases covering:
  - User creation triggering license update
  - User update triggering license update
  - Active user count after deactivation
  - Scheduled command execution
  - User email structure in updates
  - License key from settings
  - Server error handling
  - Network timeout handling
  - Multiple operation scenarios

## Test Coverage

### Console Commands
- ✅ FluxLicenseSendUpdate - 8 tests
- ✅ Install - 19 tests

### Service Provider
- ✅ Command registration
- ✅ Event listeners
- ✅ Scheduled tasks
- ✅ Integration with FluxErp actions

### Integration Workflows
- ✅ User lifecycle events
- ✅ License server communication
- ✅ Error handling and resilience
- ✅ Data validation and transmission

## Total Test Count

**42 comprehensive test cases** covering all aspects of the flux-license package:
- 8 tests for FluxLicenseSendUpdate command
- 19 tests for Install command
- 6 tests for ServiceProvider
- 11 integration tests

## Running Tests

### Using Testbench (Recommended)
```bash
cd packages/flux-license
vendor/bin/testbench package:test
```

### Using Composer Script
```bash
cd packages/flux-license
composer test
```

### Running Specific Test Suites
```bash
# Unit tests only
vendor/bin/testbench package:test --testsuite=Unit

# Feature tests only
vendor/bin/testbench package:test --testsuite=Feature

# Specific test file
vendor/bin/testbench package:test tests/Unit/Console/Commands/FluxLicenseSendUpdateTest.php
```

## Key Testing Patterns Used

### 1. Pest Syntax
All tests use modern Pest syntax with `test()` and `it()` functions:
```php
test('command sends update to flux server with active users count', function (): void {
    // Test implementation
});
```

### 2. HTTP Faking
Tests use Laravel's HTTP facade for API testing:
```php
Http::fake([
    'flux.team-nifty.com/*' => Http::response(['success' => true], 200),
]);
```

### 3. Database Setup
Each test has a clean database state via RefreshDatabase and beforeEach setup:
```php
beforeEach(function (): void {
    // Create default records
    $this->dbTenant = Tenant::default() ?? Tenant::factory()->create(['is_default' => true]);
    $this->defaultLanguage = Language::default() ?? Language::factory()->create(['is_default' => true]);
    // ...
});
```

### 4. Action Testing
Tests validate FluxErp actions directly:
```php
CreateUser::make($data)
    ->validate()
    ->execute();
```

### 5. Event Testing
Tests verify event dispatching:
```php
Event::fake();
// Perform action
Event::assertDispatched('action.executed: ' . resolve_static(CreateUser::class, 'class'));
```

## Dependencies

### Updated composer.json
- Added `pestphp/pest: ^2.0` to require-dev
- Added autoload-dev section for test namespace
- Compatible with PHPUnit ^10.0

## Database Configuration

Tests use MySQL with the following configuration (from phpunit.xml):
- **DB_CONNECTION**: mysql
- **DB_DATABASE**: testing
- **DB_USERNAME**: root
- **DB_PASSWORD**: package_project_l12

## Test Structure Follows Flux ERP Guidelines

- ✅ Uses Pest syntax throughout
- ✅ Uses testbench for package testing
- ✅ Follows nuxbe-table-reservation template structure
- ✅ Comprehensive coverage of all package functionality
- ✅ Proper namespace: TeamNiftyGmbH\FluxLicense
- ✅ Service provider: FluxLicenseServiceProvider
- ✅ No AI/Claude attribution in any files

## Notes

The flux-license package is relatively simple with:
- 2 Console Commands
- 1 Service Provider
- No Models, Actions, or Livewire components

All testable functionality has been covered with comprehensive test cases that validate both happy paths and error scenarios.
