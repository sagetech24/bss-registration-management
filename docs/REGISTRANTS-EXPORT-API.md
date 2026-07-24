# Registrants Export API

Secured JSON API for exporting **v2** event registrants (`event_registrant`) to Google Sheets via Apps Script. This replaces the legacy open scrape at `script/google4.php` (which only reads `bss_registrant`).

| | |
|---|---|
| **Action** | `export-event-registrants` |
| **Method** | `GET` |
| **Auth** | Bearer token (`Authorization` header) |
| **Content-Type** | `application/json` |
| **Source tables** | `event_registrant` + `event_registration` (+ package label from promotions / pricing snapshot) |

Production base URL (typical):

```
https://www.biblesociety.sg/registration-v2/
```

Local (MAMP example):

```
http://localhost:8888/BSS/registration-manager/
```

Apps Script **cannot** call localhost. Use a publicly reachable host for Google triggers.

---

## 1. Setup

### 1.1 Export token (`wp-config.php`)

Add a dedicated token (preferred). Do **not** commit real values.

```php
define('RM_EXPORT_API_TOKEN', '…long random secret…');
```

If `RM_EXPORT_API_TOKEN` is empty/undefined, the API falls back to `BSS_API_BEARER_TOKEN`.

A dedicated export token can be rotated without breaking BSS REST calls.

### 1.2 Schema: `event_registrant.reported`

Incremental sync (`mode=unreported`) needs the `reported` column.

- Auto-installed on Registration Manager bootstrap (`migrations/004_event_registrant_reported.sql` via `schema-install.php`)
- Or apply the migration manually in MySQL

Load any RM page once after deploy so the column is created if missing.

### 1.3 Event requirements

- Event must exist and resolve by `event_code` (program code)
- Event must use **v2** registration (`event_registrant` path)
- Cancelled registrant rows are never exported

---

## 2. Endpoint

```
GET {base}/?action=export-event-registrants
    &event_code={programCode}
    &mode=unreported|all
    &package_filter=all|individual|{promotion_id}
    &addon_filter=no-addon|include|addon-only
```

### Example

```
https://www.biblesociety.sg/registration-v2/?action=export-event-registrants&event_code=LET2601&mode=unreported&addon_filter=no-addon
```

### Authentication

Required header:

```
Authorization: Bearer <RM_EXPORT_API_TOKEN>
Accept: application/json
```

| Result | Meaning |
|--------|---------|
| `401` + `{ "ok": false, "error": "Unauthorized." }` | Missing/invalid/empty token |
| `200` + `{ "ok": true, ... }` | Success |
| `200` + `{ "ok": false, "error": "…" }` | Soft failure (bad event code, not v2, etc.) |

There is **no** WordPress login redirect. Apps Script must use the Bearer header (not cookies).

---

## 3. Query parameters

| Param | Required | Default | Description |
|-------|----------|---------|-------------|
| `action` | yes | — | Must be `export-event-registrants` |
| `event_code` | yes | — | Event program code (same role as legacy google4 `tbl`) |
| `mode` | no | `unreported` | Sync mode (see below) |
| `package_filter` | no | `all` | `all`, `individual`, or numeric `event_promotions.id` (**id**, not slug) |
| `addon_filter` | no | `no-addon` | Role filter (see below) |

### `mode`

| Value | Behavior |
|-------|----------|
| `unreported` | Returns only rows with `reported = 0`, then marks those IDs `reported = 1` (google4-style pull-once). Use for scheduled append sync. |
| `all` | Returns all non-cancelled rows. Does **not** change `reported`. Use for one-time full rebuilds. |

### `addon_filter`

| Value | Returns |
|-------|---------|
| `no-addon` | `primary` + `member` only (default) |
| `include` | `primary` + `member` + `addon` (guests) |
| `addon-only` | `addon` guests only |

Aliases: `exclude` → `no-addon`; `with-addon` / `all` → `include`; `guests` → `addon-only`.

Legacy: `include_addons=0|1` maps to `no-addon` / `include`.

### `package_filter`

| Value | Meaning |
|-------|---------|
| `all` | Every package |
| `individual` | Registrations with no package (`event_promotion_id` null) |
| `{digits}` | That promotion id only (e.g. `7`) |

Slugs such as `couple-promo` are **not** valid here; they fall back to `all`.

---

## 4. Response shape

Success payload includes **both** shapes:

```json
{
  "ok": true,
  "error": "",
  "event": {
    "event_id": 123,
    "event_code": "LET2601",
    "title": "Event Title"
  },
  "mode": "unreported",
  "package_filter": "all",
  "addon_filter": "no-addon",
  "registrant_rows": [ /* flat rows for one sheet */ ],
  "packages": [
    {
      "key": "individual",
      "label": "Individual",
      "people_count": 2,
      "registration_count": 2,
      "registrants": [ /* same row objects */ ]
    }
  ],
  "summary": {
    "total_people": 2,
    "paid_count": 2,
    "pending_count": 0,
    "total_revenue": 100
  }
}
```

- Use `registrant_rows` for a single spreadsheet
- Use `packages[]` for **one spreadsheet (or tab) per package**

### Registrant row columns (stable order)

1. `created_at` — date registered  
2. `order_number`  
3. `role`  
4. `full_name`  
5. `title`  
6. `christian_name`  
7. `given_name`  
8. `family_name`  
9. `certificate_name`  
10. `email`  
11. `contact`  
12. `nric`  
13. `address1`  
14. `address2`  
15. `postcode`  
16. `church_name`  
17. `package_label`  
18. `payment_status`  
19. `payment_option`  
20. `payment_request_id`  
21. `amount`  
22. `total_amount`  
23. `status`  
24. …any custom form fields (from `custom_responses`), appended after the fixed keys  

### Fields intentionally omitted

- `registrant_id`
- `registration_id`
- `member_index`
- `package_slug`
- `event_promotion_id`
- `email_sent`
- `confirmation_number`

Package buckets also omit `event_promotion_id` / `slug`; they use `key` + `label` + counts + `registrants`.

---

## 5. Testing with Postman

1. Method: **GET**
2. URL: `{base}/?action=export-event-registrants&event_code=YOUR_CODE&mode=all`
3. Authorization → Type **Bearer Token** → paste `RM_EXPORT_API_TOKEN` (no `Bearer ` prefix in the token field)
4. Optional header: `Accept: application/json`
5. Use `mode=all` while exploring so you do not mark rows reported by accident
6. Confirm `ok: true` and inspect `registrant_rows` / `packages`

---

## 6. Google Apps Script integration

### 6.1 Store the token (Script Properties)

1. Open the Apps Script project  
2. **Project Settings** (gear) → **Script properties**  
3. Add:
   - Property: `RM_EXPORT_API_TOKEN`
   - Value: same secret as `wp-config.php` (no `Bearer ` prefix)  
4. Save  

Never hard-code the token in source.

### 6.2 Modes vs spreadsheet behavior

| Apps Script `mode` | Spreadsheet behavior |
|--------------------|----------------------|
| `unreported` | **Append** new rows only. API marks them reported. Use for triggers. |
| `all` | **Full rebuild**: clear sheet, rewrite headers + all rows. Manual only. |

If the script always calls `sheet.clear()`, every run will overwrite — even when the API returns only new rows. Clear **only** when `mode === 'all'`.

### 6.3 Recommended sync script (per-package spreadsheets)

```javascript
function syncRegistrantsByPackage() {
  var programCode = 'D6F2027'; // your event program code
  var token = PropertiesService.getScriptProperties().getProperty('RM_EXPORT_API_TOKEN');
  if (!token) {
    throw new Error('RM_EXPORT_API_TOKEN is missing in Script Properties');
  }

  // Trigger / scheduled sync: 'unreported'
  // Manual full rebuild: 'all'
  var mode = 'unreported';

  // Options: 'no-addon' (default), 'include', 'addon-only'
  // no-addon (dafault) - primary + member only
  // include - primary + member + addon (guests)
  // addon-only - addon only
  var addonFilter = 'no-addon';

  var url = 'https://www.biblesociety.sg/registration-v2/'
    + '?action=export-event-registrants'
    + '&event_code=' + encodeURIComponent(programCode)
    + '&mode=' + encodeURIComponent(mode)
    + '&addon_filter=' + encodeURIComponent(addonFilter);

  var response = UrlFetchApp.fetch(url, {
    method: 'get',
    headers: {
      Authorization: 'Bearer ' + token,
      Accept: 'application/json'
    },
    muteHttpExceptions: true
  });

  var payload = JSON.parse(response.getContentText());
  Logger.log('mode=' + mode + ' HTTP ' + response.getResponseCode());
  if (!payload.ok) {
    throw new Error(payload.error || 'Export failed');
  }

  var rowsAll = payload.registrant_rows || [];
  if (mode === 'unreported' && !rowsAll.length) {
    Logger.log('No new registrants. Skipping.');
    return;
  }

  var headers = rowsAll.length ? Object.keys(rowsAll[0]) : null;
  var eventTitle = (payload.event && payload.event.title) || programCode;
  var packages = payload.packages || [];

  var props = PropertiesService.getScriptProperties();
  var mapKey = 'PACKAGE_SHEETS_' + programCode;
  var idMap = {};
  try {
    idMap = JSON.parse(props.getProperty(mapKey) || '{}');
  } catch (e) {
    idMap = {};
  }

  packages.forEach(function (pkg) {
    var rows = pkg.registrants || [];
    if (!rows.length) {
      return;
    }

    var packageKey = String(pkg.key || 'individual');
    var packageLabel = pkg.label || packageKey;

    var ss = null;
    if (idMap[packageKey]) {
      try {
        ss = SpreadsheetApp.openById(idMap[packageKey]);
      } catch (e) {
        delete idMap[packageKey];
        ss = null;
      }
    }
    if (!ss) {
      ss = SpreadsheetApp.create(eventTitle + ' — ' + packageLabel);
      idMap[packageKey] = ss.getId();
      Logger.log('Created spreadsheet: ' + ss.getUrl());
    }

    var sheet = ss.getSheets()[0];
    sheet.setName(packageLabel.substring(0, 90));
    var colHeaders = headers || Object.keys(rows[0]);

    if (mode === 'all') {
      sheet.clear();
      sheet.getRange(1, 1, 1, colHeaders.length).setValues([colHeaders]);
      var rebuild = rows.map(function (row) {
        return colHeaders.map(function (h) {
          var v = row[h];
          return v === null || v === undefined ? '' : v;
        });
      });
      sheet.getRange(2, 1, rebuild.length, colHeaders.length).setValues(rebuild);
      Logger.log(packageLabel + ': REBUILT ' + rebuild.length + ' rows');
      return;
    }

    // Incremental: never clear — append only
    if (sheet.getLastRow() === 0) {
      sheet.getRange(1, 1, 1, colHeaders.length).setValues([colHeaders]);
    } else {
      colHeaders = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    }

    var orderCol = colHeaders.indexOf('order_number');
    var existing = {};
    if (orderCol >= 0 && sheet.getLastRow() > 1) {
      sheet.getRange(2, orderCol + 1, sheet.getLastRow() - 1, 1).getValues().forEach(function (r) {
        if (r[0] !== '' && r[0] != null) {
          existing[String(r[0])] = true;
        }
      });
    }

    var values = [];
    rows.forEach(function (row) {
      var on = row.order_number != null ? String(row.order_number) : '';
      if (on && existing[on]) {
        return;
      }
      values.push(colHeaders.map(function (h) {
        var v = row[h];
        return v === null || v === undefined ? '' : v;
      }));
    });

    if (!values.length) {
      Logger.log(packageLabel + ': nothing new to append');
      return;
    }

    var startRow = Math.max(sheet.getLastRow() + 1, 2);
    sheet.getRange(startRow, 1, values.length, colHeaders.length).setValues(values);
    Logger.log(packageLabel + ': APPENDED ' + values.length + ' row(s) at row ' + startRow);
  });

  props.setProperty(mapKey, JSON.stringify(idMap));
}

/** Clear stored spreadsheet IDs after deleting Drive files manually */
function resetPackageSpreadsheetMap() {
  var programCode = 'LET2601';
  PropertiesService.getScriptProperties().deleteProperty('PACKAGE_SHEETS_' + programCode);
  Logger.log('Cleared PACKAGE_SHEETS_' + programCode);
}
```

### 6.4 Time-driven trigger

1. Apps Script → **Triggers** → Add trigger  
2. Function: `syncRegistrantsByPackage`  
3. Event source: Time-driven (e.g. every 5–15 minutes; every minute is usually unnecessary)  
4. Ensure the function keeps `mode = 'unreported'`  

After a successful pull, later runs should log `No new registrants. Skipping.` until someone new registers.

### 6.5 After changing API columns

1. Set `mode = 'all'` once  
2. Run manually (rebuilds headers: `created_at`, `order_number`, …)  
3. Set `mode = 'unreported'` again for the trigger  

---

## 7. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| `Unauthorized.` | Token missing/wrong, or Script Property not set | Set `RM_EXPORT_API_TOKEN` in `wp-config.php` and Script Properties; send `Authorization: Bearer …` |
| `RM_EXPORT_API_TOKEN is missing in Script Properties` | Property not created | Add Script Property exactly named `RM_EXPORT_API_TOKEN` |
| Event not found / not v2 | Wrong `event_code` or legacy-only event | Use a v2 event program code |
| Every trigger **overwrites** the sheet | Script calls `sheet.clear()` or uses `mode=all` | Use `unreported` + append-only path; clear only for `all` |
| Trigger returns same people every time | `mode=all`, or `reported` not updating on server | Use `unreported`; confirm migration 004 is installed on that environment |
| Deleted spreadsheets not recreated | Stale IDs in Script Properties | Run `resetPackageSpreadsheetMap()` or rely on openById failure + recreate |
| Headers still old after API change | Script reuses existing header row | Run once with `mode=all` and `sheet.clear()` |
| Apps Script cannot reach API | Using `localhost` | Point URL at public production/staging host |

---

## 8. Related code

| Path | Role |
|------|------|
| `index.php` | Routes `action=export-event-registrants` |
| `includes/auth.php` | Bearer token check |
| `includes/registrant-export-service.php` | Build response, mark reported |
| `includes/request.php` | `mode`, `addon_filter`, `package_filter` |
| `includes/schema-install.php` | Install `reported` column |
| `migrations/004_event_registrant_reported.sql` | DDL for export cursor |

Legacy (unchanged): `script/google4.php` for old `bss_registrant` events only.
