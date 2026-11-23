# Test Command

Run the PHPUnit automated test suite with Laravel Artisan.

## Usage
- **Run all tests:**
  ```bash
  php artisan test
  ```
- **Run a single test file:**
  ```bash
  php artisan test tests/Feature/EndToEndWorkflowAndSecurityTest.php
  php artisan test tests/Unit/ToothRecordTest.php
  ```
- **Run a specific test method:**
  ```bash
  php artisan test --filter=workflow_01_login_to_dashboard
  ```

## Notes
- Tests run against in-memory SQLite (`:memory:`) as configured in `phpunit.xml`.
- When running in PowerShell or CI, ensure the APP_KEY and testing env are loaded automatically via `phpunit.xml`.
