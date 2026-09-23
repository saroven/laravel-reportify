# Contributing to Laravel Reportify

Thank you for contributing to **Laravel Reportify**! To maintain quality and stability across the codebase, please review the following guidelines before submitting issues or pull requests.

---

## 🛠 Local Development Setup

1. **Fork and clone** the repository:
   ```bash
   git clone https://github.com/<your-username>/laravel-reportify.git
   cd laravel-reportify
   ```

2. **Install Composer dependencies**:
   ```bash
   composer install
   ```

3. **Verify the test suite**:
   ```bash
   vendor/bin/pest
   ```

---

## 🌿 Branching Strategy

- **`main`**: Active production-ready branch. All pull requests should target `main`.
- Create descriptive branch names from `main`:
  - `feat/feature-name` for new capabilities
  - `fix/issue-description` for bug fixes
  - `docs/documentation-update` for documentation changes

---

## 🧪 Coding & Testing Standards

- **Code Style**: Adhere to PSR-12 and standard Laravel conventions.
- **Strict Types**: Always include `declare(strict_types=1);` at the top of new PHP files.
- **Type Declarations**: Use explicit parameter types, return types, and typed properties throughout.
- **Pest Testing**: 
  - Every bug fix must include a reproducing test case that passes with your fix.
  - Every new feature must include comprehensive unit and/or feature tests.
  - Run the entire test suite before submitting:
    ```bash
    vendor/bin/pest
    ```

---

## 📥 Pull Request Checklist

Before opening a pull request, ensure:

1. [ ] All Pest tests pass locally (`vendor/bin/pest`).
2. [ ] Your code does not introduce unnecessary comments, debug code, or unused imports.
3. [ ] Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (e.g. `feat:`, `fix:`, `docs:`, `test:`, `refactor:`).
4. [ ] Documentation in `README.md` is updated if your change modifies configuration, public API methods, or behavior.
5. [ ] Your PR description clearly explains the **problem solved**, **changes made**, and **testing performed**.

---

## 🔒 Security Vulnerabilities

If you discover a security vulnerability within Laravel Reportify, please do not disclose it publicly via GitHub Issues. Instead, report it privately through GitHub Security Advisories or contact the maintainer directly.
