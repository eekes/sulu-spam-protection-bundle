# Working in this bundle

@CONTRIBUTING.md

That file is the single source of truth for the layout of the bundle, its conventions, the code
style, and what is expected of tests. There are no separate rules for agents.

Two things worth repeating because they are easy to get wrong:

- The test application in `tests/Application` deliberately registers **no Sulu bundles**. Services
  gated on `SuluAdminBundle` or `SuluFormBundle` being in `kernel.bundles` are not in the container
  during tests; build them by hand with the real collaborators instead of reaching for a mock.
- Never gate on `hasExtension()` inside `load()`: the container it gets holds no extensions, so the
  check always fails and the services quietly disappear.
- PHPStan runs at level max. Fix the cause — never add a baseline, an ignore comment, or a cast to
  make an error go away.
