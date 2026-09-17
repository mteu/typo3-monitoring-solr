# Contributing to TYPO3 Monitoring: Apache Solr

Thank you for considering contributing to this project! This guide outlines the process for contributing.

This extension is an add-on for [`EXT:monitoring`](https://github.com/mteu/typo3-monitoring) and follows the same
conventions. Changes to the monitoring system itself — the endpoint, the provider interface, the backend module —
belong in that repository, not here.

## 🚀 Quick Start

1. **Fork** the repository on GitHub
2. **Create** a feature branch: `git checkout -b feature/your-feature-name`
3. **Make** your changes and ensure quality standards
4. **Test** your changes thoroughly
5. **Submit** a pull request

## 📋 Development Workflow

### 1. Set Up Development Environment

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/typo3-monitoring-solr.git
cd typo3-monitoring-solr

# Install dependencies (this also installs the CGL tooling in Tests/CGL)
composer install
```

### 2. Create Feature Branch

```bash
# Create and switch to feature branch
git checkout -b feature/your-feature-name

# Or for bug fixes
git checkout -b bugfix/issue-description
```

### 3. Quality Assurance Commands

**Before submitting any pull request, ensure all quality checks pass:**

#### Testing Commands
```bash
# Run all tests (includes unit + functional)
composer test

# Run only unit tests
composer test:unit

# Run functional tests
composer test:functional
```

The functional tests run against SQLite in memory and need no database service.

#### Testing Against Supported TYPO3 Versions

This extension supports **TYPO3 13.4 & 14.3** on **PHP 8.3, 8.4 and 8.5**. A plain
`composer install` resolves only the highest combination, so `composer test` then
covers just one of the combinations CI gates on. For version-sensitive changes,
verify both lines locally the same way CI does:

```bash
# Test against TYPO3 v13
composer update --with=typo3/cms-core:^13.4
composer test

# Test against TYPO3 v14
composer update --with=typo3/cms-core:^14.3
composer test

# Mirror the "lowest" dependency leg of the CI matrix
composer update --prefer-lowest --with=typo3/cms-core:^13.4
composer test
```

The full PHP × TYPO3 × dependencies matrix is enforced in CI
(`.github/workflows/tests.yaml`).

#### Code Quality (CGL) Commands
```bash
# Check code style compliance
composer lint

# Automatically fix code style issues
composer fix
```

#### Static Code Analysis (SCA) Commands
```bash
# Run static analysis (PHPStan with a 4GB memory limit, plus Rector migration checks)
composer sca
```

> Note: `composer sca` also runs `rector process`, which **applies** migration
> changes to your working tree (it is not a dry run). Commit or stash first if you
> want to review what it changes.

#### Complete Quality Check
```bash
# Run all quality checks in sequence
composer lint && composer sca && composer test
```

### 4. Testing Requirements

- **Unit Tests**: Required for new classes, methods, or provider logic
- **Functional Tests**: Required for anything that depends on the container — service wiring, aliases, or the typed
  extension configuration

## 🔧 Code Standards

### PHP Standards
- Follow **PSR-12** coding standard
- Use **strict types**: `declare(strict_types=1);`
- Add **PHPDoc blocks** with proper type annotations
- Include **copyright headers** in all new files

### Testing Standards
- Use **PHPUnit 11+** attributes (`#[Test]`, `#[CoversClass]`)
- Add `#[CoversClass(YourClass::class)]` to test classes
- Follow existing test patterns and fixture usage
- Ensure tests are deterministic and isolated — no test may contact a real Solr node

### Reading the site configuration

The provider derives everything from `solr_*_read` keys. Two rules hold for any change to that logic:

- A value that is present but unusable is **reported**, never replaced by a default. A default makes the probe test
  something the site does not use, which is the drift this provider exists to catch.
- The probe must resolve connections the way EXT:solr does, including the fallback from a site language to the site
  level. Where the two disagree, EXT:solr is right and this extension has a bug.

## 📤 Submitting Changes

### 1. Commit Guidelines
Use clear, descriptive commit messages. Follow to [TYPO3's Commit Message Rules](https://docs.typo3.org/m/typo3/guide-contributionworkflow/main/en-us/Appendix/CommitMessage.html).
```bash
git commit -m "[TASK] Carry basic auth credentials into the probe"
git commit -m "[BUGFIX] Resolve the core path for EXT:solr 12 site configurations"
git commit -m "[DOCS] Document the indexing error severity"
```

### 2. Push and Create PR
```bash
# Push your feature branch
git push origin feature/your-feature-name

# Create pull request on GitHub with:
# - Clear title describing the change
# - Description of what was changed and why
# - Reference to any related issues
# - Confirmation that all quality checks pass
```

### 3. Pull Request Checklist

Before submitting, ensure:

- [ ] **All tests pass**: `composer test`
- [ ] **Works on both TYPO3 v13 and v14** (test against each, or rely on the CI matrix)
- [ ] **Code style compliant**: `composer lint`
- [ ] **Static analysis clean**: `composer sca`
- [ ] **New features have tests** (unit or functional)
- [ ] **Documentation updated** if needed
- [ ] **Commit messages** are clear and descriptive

## 🧪 Testing New Features

### Required Test Coverage

**For changes to connection resolution:**
- Unit tests covering the site-configuration shape you support, including the language-over-site precedence
- A case for the value being absent, and one for it being present but unusable

**For changes to the probe:**
- Unit tests using the client spy, asserting the URIs that get probed rather than mocking the result

**For Bug Fixes:**
- Regression tests to prevent future occurrences
- Test the specific scenario that was broken

### Test File Locations
- **Unit Tests**: `Tests/Unit/`
- **Functional Tests**: `Tests/Functional/`
- **Test Fixtures**: `Tests/Unit/Fixtures/`

## 🐛 Reporting Issues

When reporting bugs, please include:
- TYPO3 version
- PHP version
- Extension version, plus the `EXT:monitoring` and `EXT:solr` versions
- The relevant `solr_*_read` keys from your site configuration, with hosts and credentials redacted
- Steps to reproduce
- Expected vs actual behavior
- Any relevant error messages
