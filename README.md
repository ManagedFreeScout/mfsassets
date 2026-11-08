# MFS Assets

**Version 1.0.0** · FreeScout module by [Managed FreeScout](https://managedfreescout.com) (StackPros)

Asset management inside FreeScout. Register laptops, phones, monitors, printers, network gear and software
licences per organisation, record who uses each one and where it is, and link assets to conversations.

- An **Assets** page: a list with search, filters and sorting, and a page per asset with details, history,
  conversations and files
- Asset types with their own fields (text, number, date, choice list, yes/no)
- An Assets block next to every conversation: the customer's own assets, their department's and the shared
  ones, linked in one click; search and "new asset for this conversation"
- An Assets tab on the customer page and on the organisation page
- Search by tag or serial number; the tags of linked assets in the conversation list and on Kanban cards
- A full history: created, changed hands, moved, status changes, linked conversations
- QR labels; scanning opens the asset (agents) or a short report form that creates a linked conversation
- CSV import and export, a REST API and eight asset webhooks (with FreeScout's official API & Webhooks module)

User manual: [MANUAL.md](MANUAL.md) · online: https://managedfreescout.com/support/mfsassets/
API and webhooks: [API.md](API.md) · online: https://managedfreescout.com/support/mfsassets-api/

## Requirements

- FreeScout 1.8 or newer
- **MFS Organisations 1.1 or newer** (owners, departments and locations come from it)
- An active MFS Assets licence (monthly subscription, one FreeScout installation, all agents)
- Outgoing HTTPS from the FreeScout server to our licence server
- Optional: the official Kanban and API & Webhooks modules

## Installation

1. Install and activate MFS Organisations 1.1 or newer first.
2. Download `mfsassets.zip` from the [latest release](https://github.com/ManagedFreeScout/mfsassets/releases/latest).
3. Unzip it into FreeScout's `Modules/` folder, so you get `Modules/MFSAssets/`.
4. In FreeScout: **Manage → Modules**, activate **MFS Assets**. The module creates its own tables
   and changes none of FreeScout's tables.
5. **Manage → Settings → MFS Assets**: enter your licence key, click **Activate licence**, then choose
   **Our organisation** and save.

Updates arrive through **Manage → Modules → Update now** (FreeScout reads `version.txt` on `main` and
the `mfsassets.zip` asset of the latest GitHub release).

## Licence

- The module asks the Managed FreeScout hub (`hub_url`, default `https://app.managedfreescout.com`)
  with only the licence key and this installation's domain:
  `POST {hub_url}/modules/mfsassets/license/{activate|validate|deactivate}`.
  The module holds no StackPros credentials.
- The result is stored in the shared `modules_licenses` table (row `module_alias = mfsassets`).
- Re-checked every 6 hours by FreeScout's scheduler. Without a valid licence the assets, history and files
  stay in the database, but the module's screens, blocks, API and webhooks are off.
- `.env` overrides (testing only): `MFSASSETS_HUB_URL`, `MFSASSETS_BUY_URL`.

## Changelog

| Version | Date | Changes |
| --- | --- | --- |
| 1.0.0 | 2026-11-08 | First release: asset register with types and own fields, lifecycle statuses and automatic tags; owner, user and location from MFS Organisations; asset page with history, conversations and files; Assets block on conversations, customer and organisation tabs; search filter, global search by tag or serial and badges; CSV import and export; QR labels with a report form; REST API and eight asset webhooks. |
