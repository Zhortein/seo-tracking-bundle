# 🤝 Contributing to SeoTrackingBundle

First off, thank you for considering contributing to this bundle! 🚀  
Your ideas, fixes, and improvements help make the project better for everyone.

---

## 📋 Table of contents

- [Code of conduct](#-code-of-conduct)
- [How to contribute](#-how-to-contribute)
- [Development setup](#-development-setup)
- [Submitting a pull request](#-submitting-a-pull-request)
- [Code style](#-code-style)
- [Tests](#-tests)
- [Feature ideas](#-feature-ideas)

---

## 📜 Code of conduct

This project follows a simple rule:  
**Be respectful, constructive, and kind.** 🙏

---

## 🛠️ How to contribute

You can help in many ways:

- 📥 Report bugs via [GitHub issues](../../issues)
- 💡 Suggest features (see [`FEATURE_IDEAS.md`](./FEATURE_IDEAS.md))
- 🧪 Write or improve tests
- 🧼 Refactor code or improve performance
- 📚 Improve documentation or add examples

---

## 💻 Development setup

```bash
# Clone the repository
git clone https://github.com/zhortein/seo-tracking-bundle.git
cd seo-tracking-bundle

# Install dependencies
make installdeps

# Update dependencies
make updatedeps

# Run PHPStan
make phpstan

# Run CS Fixer
make csfixer

# Run the test suite
make test 
```

## 🔀 Submitting a pull request
1. Fork the repository
2. Create your feature branch:
```bash
git checkout -b feature/my-awesome-change
```
3. Commit your changes (see below for style guidelines)
4. Push to your branch and open a PR against `main`
   
Please include:
* A clear description of what the PR does
* Any related issue or feature request (use Fixes #xx)
* Screenshots or examples if it's a visual/UI change

## 🧹 Code style
This bundle follows Symfony's coding standards.

Before pushing your changes, please run:
```bash
make csfixer
make phpstan
```
If any error is listed by PHPStan, please correct before pushing your changes !

If you add Twig templates, make sure to follow:
* [Symfony UX & Twig Best Practices](https://symfony.com/doc/current/templates.html)

## ✅ Tests
All new features or fixes must include relevant tests.
Tests live in the tests/ directory and follow the PHPUnit naming convention.

To run tests:
```bash
make test
```

## 🧠 Feature ideas
Check the [FEATURE_IDEAS.md](./FEATURE_IDEAS.md) file for inspiration, open discussions, or things we plan to add later. 
Feel free to open a PR to suggest one of them 🚀

---- 

Thank you again for your contribution! 🙌
— The SeoTrackingBundle Maintainers