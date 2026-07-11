# Current task

> Canonical repository: `reactwoo-geo-optimise`

## Problem

Atomic write path that stamps measurement element keys from the blueprint/manifest onto Elementor V3/V4 documents.

## Expected (this pass)

- `RWGA_Elementor_Document_Writer` patch/save `_elementor_data`
- `RWGO_Measurement_Stamper` apply manifest + sync Control→Variant B
- Hook on variant duplicate; Developer Tools sync action
- Atomic widget goal support + typed setting unwrap
- Ship **0.4.73**

## Do not touch

- Full Atomic page construction
- GTM API provisioning / winner policy
- Standalone `reactwoo-geo-ai`
- Unrelated UX reviewer WIP
