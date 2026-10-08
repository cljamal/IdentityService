---
name: testing-best-practices
description: "Test design and review for IdentityService, preserving its Actions + Events architecture, client isolation and token contracts. Use when selecting coverage, naming or structuring tests, choosing assertions or test data, isolating dependencies, testing HTTP or security boundaries, improving suite performance, or reviewing test value. Use framework guidance or search-docs for Pest and PHPUnit syntax."
license: MIT
metadata:
  author: laravel
  customized_for: IdentityService
---

# Testing Best Practices

This skill provides rules for designing Laravel tests. Each rule file explains what to do and why. Use `search-docs` for Laravel API syntax. Consult documentation matching the installed PHPUnit version at `https://phpunit.de/documentation.html` for PHPUnit API syntax.
This project uses PHPUnit. Follow the corresponding guidance in each rule.

## Consistency First

Read CLAUDE.md, the Laravel skill's architecture.md and nearby tests before choosing syntax and organization.

This local override follows IdentityService's PHPUnit suite: behavior groups in Feature/Auth,
focused components in Unit/Auth, RefreshDatabase with SQLite :memory:, ActsAsClient,
selective event fakes and FakeOtpRepository. Generic advice must preserve these conventions,
synchronous role assignment, OTP delivery boundaries and refresh-token security effects.

A pattern repeated throughout the project is a convention, and project conventions take precedence over this skill. Follow them and give new tests the same structure.

These rules govern the tests you write now. An existing test that follows a project convention is not defective merely because it conflicts with this skill. Do not delete or rewrite it. If the convention has drawbacks, explain them and let the user decide.

## What to Test

Read this section before you write a test.

- Test observable behavior and application contracts. A test must pass after an implementation change if the behavior stays the same.
- Cover changed behavior and applicable high-value failure modes. Choose cases that detect distinct defects; do not add tests that merely mirror branches or declarations.
- Exercise declarations through behavior instead of repeating their text.
- Leave framework behavior to framework tests. Testing project configuration is not testing the framework. A constrained relationship, cast, scope, or validation rule belongs to this project.
- Keep every test that can detect a distinct defect. When two tests detect the same defect, trim the higher-layer test to one case and report the duplication. Do not delete an existing test.
- Use feature tests for HTTP and persistence boundaries. Focused component tests under Unit may extend Tests\TestCase and use the container, facades or contract mocks when required; they need not be pure PHP. Keep their dependencies minimal.
- Write a feature test for every behavior reachable through a request. Real-browser tests require `laravel/dusk` and a browser download, neither of which this project installs. Mention the package only if the user asks for a real-browser test.
- Use the test tools that the project installs. Add a new test dependency, plugin, or browser only after the user asks for it.

## How to Apply

1. Read the code under test. Read the tests in the same directory. Identify every decision in the code.
2. Select every applicable branch in the rule index. Read every selected rule file.
3. Trace the full Action, strategy, guard and repository flow. Report actual defects; a controller without a Form Request, an Action delegating validation, or ownership enforced without a policy is not itself defective. Test the intended contract without endorsing an established security defect.
4. Write the tests. Run the smallest set of tests that covers the change. The tests must pass.
5. Check every applicable item in `rules/review.md` and every selected rule file. Resolve every mismatch before completion.

## Rule Index

Most changes need more than one rule file.

| Subject | Rule File |
| --- | --- |
| Test framework features that may already do the work | [`rules/finding-features.md`](rules/finding-features.md) |
| File layout, test names, and groups | [`rules/naming.md`](rules/naming.md) |
| Arrange-act-assert and choosing the correct assertion | [`rules/assertions.md`](rules/assertions.md) |
| Endpoint coverage, authentication, authorization, tenant isolation, validation, and browser tests | [`rules/endpoint-tests.md`](rules/endpoint-tests.md) |
| Factories, test data ownership, and repeated input values | [`rules/test-data.md`](rules/test-data.md) |
| Fakes, mocks, outbound HTTP, time, randomness, and databases | [`rules/isolation.md`](rules/isolation.md) |
| Escaping, injection, cross-tenant access, and privilege checks | [`rules/security.md`](rules/security.md) |
| Environment and CI settings for a slow suite | [`rules/performance.md`](rules/performance.md) |
| Reviewing a test or suite | [`rules/review.md`](rules/review.md) |
