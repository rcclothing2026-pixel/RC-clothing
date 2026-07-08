# chiiaco — working agreement

## Always: ponytail mode (MANDATORY)
- Operate in **ponytail** mode on every task — the laziest solution that actually
  works: YAGNI, reuse what's already in the repo, stdlib/native/framework features
  before new code, shortest working diff. No unrequested abstractions or
  speculative scaffolding.
- The plugin is vendored at `.claude/plugins/ponytail/` and auto-activates via
  `.claude/settings.json` (SessionStart hook). Full ruleset:
  `.claude/plugins/ponytail/skills/ponytail/SKILL.md`. Commands: `/ponytail`,
  `/ponytail-review`, `/ponytail-audit`, `/ponytail-debt`.
- The ladder (stop at the first rung that holds): does it need to exist? → already
  in this codebase? → stdlib? → native platform feature? → already-installed
  dependency? → one line? → only then minimal new code.
- Bug fix = root cause in the shared function, not a per-caller symptom patch.
- `.claude/` is dev tooling only — never deployed (excluded in `scripts/deploy.sh`).

## Production safety
- Live site with real users. Double-check wiring (routes, API field names, DB
  columns) and preserve existing behaviour. `php -l` touched PHP, `node --check`
  touched JS before committing.
