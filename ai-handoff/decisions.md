# Decisions — ReactWoo Geo Optimise

| Date | Decision | Rationale |
|------|----------|-----------|
| — | Experiments/CRO in this plugin | Core owns assignment event contracts (phase 6) |
| — | No duplicate visitor detection | Use Core hooks + REST `/capabilities` |
| — | File handoff for cross-tool debug | `ai-handoff/` + suite workflow doc in Geo Core |

## AI handoff defaults

- CSV export and assignment bugs: check `RWGO_Stats`, experiment CPT, Core assignment hooks before new abstractions.
