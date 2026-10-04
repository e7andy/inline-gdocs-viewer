# Live test resources

What the resources used by `tests/e2e/live-google.spec.js` must contain. See "Live tests" in the main README for the variables that point to them.

## Test sheet (`IGSV_LIVE_SHEET`)

Share it with **Anyone with the link (Viewer)**.

- **First tab:** paste [sheet-first-tab.tsv](sheet-first-tab.tsv) into cell A1. Then color the cell backgrounds (**Fill color** in the toolbar):
  - the header row, A1:D1: **light gray 1** (`#d9d9d9`);
  - the Ninjas row, A3:D3: **light green 3** (`#d9ead3`).

  Leave every other cell without a fill color.
- **Second tab:** paste [sheet-second-tab.tsv](sheet-second-tab.tsv) into cell A1.

## Private sheet (`IGSV_LIVE_PRIVATE_SHEET`)

Any sheet with **General access: Restricted**.

## Web app (`IGSV_LIVE_WEBAPP`)

[webapp.gs](webapp.gs), deployed with **Deploy > New deployment > Web app**, **Execute as: Me**, **Who has access: Anyone**. Use the `/exec` URL.
