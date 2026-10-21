<!-- antd-cli setup start -->
## Ant Design CLI MCP

When working with Ant Design in this repository, use the configured `antd` MCP server before writing component code:

- Use `antd_info` for component props, defaults, common props, and native HTML element hints.
- Use `antd_doc` when you need the full component API documentation.
- Use `antd_demo` before generating non-trivial usage examples.
- Use `antd_token` and `antd_design_md` for theme, token, and design-language work.
- Use `antd_semantic` when customizing `classNames` or `styles` slots.
- Use `antd_changelog` for version migration or API-diff questions.

Use the shared Ant Design skill at `.agents/skills/antd/SKILL.md` for CLI fallback guidance and project-local agent instructions.

<!-- antd-cli setup end -->

## Engineering Standards & Agent Skills

This repository integrates production-grade engineering skills from [Addy Osmani's agent-skills](https://skills.addy.ie/) located in `.agents/skills/` and `.agents/references/`.

For every implementation task, agents must follow the corresponding workflow skill:

1. **Phase 1: Define & Plan**:
   - `spec-driven-development` & `planning-and-task-breakdown`: Define explicit acceptance criteria and break work into verifiable units before coding.
   - `interview-me`: Clarify underspecified requirements with the user before assuming.
   - `source-driven-development`: Ground decisions in authoritative documentation (Laravel 10, Inertia.js, React 18, Ant Design v5).
   - `using-agent-skills`: Surface assumptions early, manage confusion actively, resist sycophancy, and maintain strict scope discipline.

2. **Phase 2: Build & Harden**:
   - `incremental-implementation`: Build in thin, vertical slices. Test and verify each slice before moving to the next.
   - `frontend-ui-engineering`: Maintain design system fidelity, WCAG 2.1 AA accessibility, responsive layouts, and token-based theming.
   - `api-and-interface-design`: Enforce typed/validated contracts, clean error structures, and predictable responses.
   - `security-and-hardening`: Enforce RBAC authorization gates, sanitize inputs, parameterize queries, and follow OWASP Top 10 guidelines.
   - `doubt-driven-development`: Adversarially cross-examine non-trivial architectural and security decisions.

3. **Phase 3: Verify & Review**:
   - `test-driven-development`: Verify behavior using automated tests before claiming completion.
   - `debugging-and-error-recovery`: Reproduce -> Localize -> Fix -> Guard with regression tests.
   - `code-review-and-quality`: Conduct multi-axis verification before proposing PRs or marking tasks done.
   - `code-simplification`: Remove accidental complexity and dead branches while strictly preserving behavior.
   - `references/definition-of-done.md`: All unit/feature tests must pass in the Docker container (`laravel_app`), Vite build must compile without errors, and no regressions may be introduced.

