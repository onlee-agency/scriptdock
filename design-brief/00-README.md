# ScriptDock design brief: how to use this folder

Everything needed to design ScriptDock's complete admin UI in Claude Design.

## Files
| File | What it is |
|---|---|
| `PROMPT.md` | The kickoff prompt and 7 follow-up phase prompts. Start here. |
| `01-features-and-constraints.md` | Every feature, screen, field and state the plugin has today; WordPress design constraints; the new targeting concept; confirmed decisions |
| `02-brand-guidelines.md` | Brand: colours (sampled from the reference and checked for accessibility), type, shapes, icons, motion, dark code theme, WordPress fit |
| `03-screens-and-flows.md` | Brief for every screen (S01–S21), user flows, responsive deliverables |
| `04-design-system.md` | Token names, component list with variants and states, accessibility, hand-off rules |
| `05-sample-data.md` | Realistic content (Lumen Coffee Roasters) for all mockups |
| `brand-reference.webp` | The style reference (inspiration only, not to be copied) |

## Set-up in Claude Design
1. Create a new project, e.g. **"ScriptDock: WordPress plugin UI"**.
2. Upload these files: `01`–`05` and `brand-reference.webp`. `PROMPT.md` and this README don't need uploading.
3. Paste the **Kickoff prompt** from `PROMPT.md`. Claude Design will produce Phase 1 (brand foundations and component library) and stop.
4. Review Phase 1. Ask for changes, then paste the Phase 2 prompt, and so on up to Phase 8.
5. When a phase is approved, say so, so later phases keep it as the reference.

## After the designs are done
Share the result with Claude Code (exported HTML/CSS, a share link, or screenshots) and ask it to implement the redesign in the plugin. The token names (`--sd-…`) and component names (`sd-…`) in the brief are the ones the implementation will use, so the hand-off maps one to one.

## Decisions already made
Name **ScriptDock** · brand from the reference (ember orange, ink, pills, rounded cards, line art with an orange dot) · fully custom snippet list · page scripts through a block-editor button, a sidebar and a full-screen modal · branded system with bundled fonts (Plus Jakarta Sans, JetBrains Mono) · dark mode for the code editor only in v1 · the entire product in scope.
