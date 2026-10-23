# Rominas — Git Workflow

- **Work and commit on `dev`** in `rominas-api`, `rominas-admin`, `rominas-academy` and `rominas-voting`. `main` is the release line; the maintainer merges `dev` into `main` and pushes. (`rominas-deployment` is still worked on `main`.) Check `git branch --show-current` before committing.
- **Commit messages carry only the substance:** a subject line, then what changed and why. No closing recap ("tests pass", "docs updated") and no trailers or metadata (`Co-Authored-By`, session links).
- A change that touches several repos (say the API and the academy) is committed in each repo separately, and they are deployed together; say so in the module's `docs/` chapter when the order matters (for example "run `migrate`, deploy the API and the academy together").
- The frontends are separate Next.js repos next to this one, each with its own `CLAUDE.md` to read before working there. Their checks are `pnpm exec tsc --noEmit` and `pnpm lint`; there is no automated frontend testing, so a UI change comes with a short manual test procedure instead.
