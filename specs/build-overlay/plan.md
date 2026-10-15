# PLAN — SPEC-003 Build Overlay

## Approach

`scripts/build.mjs` + `scripts/lib/{fs-walk,layers,render,env,storage,hash}.mjs`. Pipeline:
`checkManifest -> loadEnv -> collectLayers -> detectCollisions -> renderGenerated -> (--check ? exit : write)
-> syncStorage -> writeBuildInfo`. Collection builds an in-memory map `relPath -> {layer, absPath}`;
collision detection is a pure function of that map (unit-testable with fixtures).

## Decisions

| Decision | Choice | Why |
| -------- | ------ | --- |
| Collision on identical content | still an error | silent duplicates hide intent |
| Stale cleanup | delete except runtime image dirs | build output = pure function of inputs |
| Config source | own templates, not installer | installer is interactive and writes into the docroot |
| Hashing | SHA-256 content compare | mtimes unreliable across Windows/git |
| robots.txt | only overlay exception | store owners edit it per environment |

## Affected files

`scripts/build.mjs`, `scripts/lib/*`, `custom/config/*.tpl`, `.env.example`, tests fixtures in
`scripts/test/fixtures/build/*`.

## Risks

- Core `vendor/` size makes first build slow -> content compare skip; acceptable.
- Reference §1 drifts on core upgrade -> TEST-BLD checks generated configs against a recorded
  list of constants extracted from `install/controller/install/step_3.php`.
