# Art Caravan PH Inventory Management System

## Whole-System Test Checklist

Use this checklist for staging acceptance testing, release regression, or a production smoke test. Do not use live customer or payment data in a non-production environment.

### Test run record

| Field | Value |
|---|---|
| Build / commit | |
| Environment and URL | |
| Database engine | |
| Browser(s) and version(s) | |
| Desktop / tablet / mobile sizes | |
| Queue worker running | Yes / No |
| Tester(s) | |
| Test start | |
| Test finish | |
| Overall result | Pass / Conditional pass / Fail |

### Result and evidence rules

- Mark each item `[x]` only after checking the visible result and the saved data or stock balance behind it.
- Record defects as `DEFECT-ID – short description`, with screenshot, user role, hub, channel, order/reference number, time, and reproduction steps.
- Use `N/A – reason` only when a feature is intentionally excluded from the tested deployment.
- For every stock-changing test, capture the before and after values for Good, Damaged, Total Physical, and each affected channel allocation.
- For money-changing tests, capture subtotal, discount, shipping, amount due, amount paid/collected, refund, and replacement balance.
- Recheck a failed test after its fix and run the related end-to-end flow again, not only the failed screen.

## 1. Test preparation and controlled data

- [ ] Confirm the environment is isolated from production or that the approved production smoke-test limits are documented.
- [ ] Record the current commit/build and database engine.
- [ ] Confirm `APP_DEBUG=false` when testing production-like behavior.
- [ ] Confirm HTTPS, secure cookies, correct application URL, mail configuration, storage link, and writable storage/cache directories.
- [ ] Confirm all migrations are applied and no unexpected migration is pending.
- [ ] Confirm the normal queue worker and the dedicated `inventory` / `product-files` worker are running.
- [ ] Confirm the scheduler is configured and its most recent execution succeeded.
- [ ] Run `php artisan inventory:health` and attach the output.
- [ ] Check `php artisan queue:failed`; resolve or document every failed job before testing.
- [ ] Create or identify one active Head Office and at least two active regular Store Hubs.
- [ ] Create or identify one inactive Store Hub for visibility/access testing.
- [ ] Create test users for Admin, Inventory Staff, Sales Associate, and Sales/Marketing Staff.
- [ ] Give one Sales Associate a single branch and another Sales Associate multiple designated branches.
- [ ] Give Inventory Staff and Sales/Marketing Staff controlled channel assignments, including a user with no assigned channel.
- [ ] Prepare products with short numeric IDs, leading-zero IDs/barcodes, long barcodes, mixed-case searchable descriptions, and inactive products.
- [ ] Prepare stock states: Good only, Damaged only, mixed Good/Damaged, zero stock, fully allocated stock, and partially allocated stock.
- [ ] Prepare channel allocations for Online, Wholesale, Shopee, Lazada, TikTok, and Walk-In/unallocated capacity.
- [ ] Prepare customers with similar names to test lookup disambiguation.
- [ ] Prepare valid and invalid CSV/XLSX files, including duplicate rows, scientific notation, commas, quotes, multiline text, blank quantities, and 1,500+ rows.
- [ ] Record the starting inventory and financial control totals before any end-to-end flow.

## 2. Authentication, session, and account security

- [ ] Root URL redirects to Login and protected pages redirect unauthenticated users to Login.
- [ ] Valid username/email and password sign in successfully and land on the correct dashboard.
- [ ] Invalid credentials show a safe error without revealing whether the account exists.
- [ ] Five failed sign-in attempts lock the account for 15 minutes.
- [ ] A locked account cannot sign in with the correct password until the lock expires.
- [ ] An inactive staff account cannot sign in or continue using an existing session.
- [ ] Logout ends the session and browser Back cannot reopen protected content.
- [ ] Fifteen minutes of inactivity signs the user out; active use refreshes the activity timestamp.
- [ ] Password reset request, valid-token reset, expired/invalid token, and reused token behave correctly.
- [ ] Password update requires the current password and enforces the password policy and confirmation.
- [ ] Password confirmation accepts the correct password and rejects an incorrect password.
- [ ] Email verification accepts a valid signed link and rejects a changed ID/hash.
- [ ] Public self-registration is unavailable.
- [ ] Session cookies are not exposed in URLs or page source and sensitive pages are not cached after logout.

## 3. Role and hub access matrix

Test both the visible menu/button and direct URL/API access. Hidden UI alone is not a passing result.

### Admin

- [ ] Can access all authorized hubs, configuration, staff accounts, catalog, products, allocations, inventory workflows, verification queues, logs, and reports.
- [ ] Can create/edit/toggle staff and reset a staff password without displaying the new password in logs or responses.
- [ ] Can perform admin-only destructive product actions where the workflow permits them.

### Inventory Staff

- [ ] Can view/manage inventory, allocations, catalog assignment, imports/exports, verification queues, transaction logs, and staff logs.
- [ ] Cannot access admin-only staff management or admin-only destructive actions by URL/API.
- [ ] Can work only within intended Head Office/all-hub rules and designated sales channels.
- [ ] Does not see duplicate branch-to-branch activity in the Inventory Transaction Log when it belongs in Staff Logs.

### Sales Associate

- [ ] Single-branch user is locked to the assigned branch in sales, returns, products, and transfers.
- [ ] Multi-branch user can select only designated branches and cannot access any other branch by changing a URL, form ID, or API parameter.
- [ ] Can use permitted Walk-In/branch sales, branch Return Items, and branch transfer request workflows.
- [ ] Cannot use configuration, stock allocation, product import/export, verification queues, or Inventory Transaction Logs by direct URL/API.

### Sales/Marketing Staff

- [ ] Sees and records only assigned sales channels; a single assigned channel is locked.
- [ ] A user with multiple channels can switch only among assigned channels.
- [ ] A user with no assigned channels sees no channel data and cannot submit a channel sale.
- [ ] Cannot access inventory-only, transaction-log, staff-log, configuration, or queue-review screens by URL/API.

### Common access controls

- [ ] Inactive Store Hubs are absent from operational sidebars/selectors but remain visible in Configuration for reactivation.
- [ ] Cross-hub record IDs cannot be opened, downloaded, updated, approved, rejected, or deleted by an unauthorized user.
- [ ] A user cannot read or mark another user's notification as read.
- [ ] Private attachments require authentication and the correct role/hub/channel ownership.
- [ ] Form tampering with role, hub, channel, status, price, quantity, or record ID is rejected server-side.

## 4. Navigation, shared layout, and profile

- [ ] Sidebar items match the signed-in role and no dead or duplicate navigation item appears.
- [ ] Pending verification and Fully Booked badges show the correct counts and update after processing.
- [ ] Current page, selected hub, selected channel, filters, and pagination state are clear.
- [ ] Success, warning, validation, and failure messages appear once, are readable, and do not expose stack traces.
- [ ] Notification dropdown opens, refreshes, and links to the correct destination.
- [ ] Profile page loads; name/email changes save and persist after a new login.
- [ ] Changing to a new email triggers the intended verification behavior; keeping the same email does not reset verification.
- [ ] Account deletion requires the correct password and follows the approved business policy.
- [ ] Removed Team Messages pages/API routes return not found and no obsolete presence/message UI remains.

## 5. Configuration and master data

- [ ] Admin and Inventory Staff can open Configuration; other roles receive 403.
- [ ] Create and edit a Store Hub with unique name/code and correct Head Office flag.
- [ ] Duplicate or invalid hub data is rejected without partial changes.
- [ ] Toggle a hub inactive and confirm it disappears from operational selectors without deleting history.
- [ ] Reactivate the hub and confirm it returns to operational selectors.
- [ ] Store Hub delete action remains unavailable and a direct delete request is blocked.
- [ ] Inventory Staff does not see Store Hub action controls reserved for Admin.
- [ ] Create, toggle, and delete a Unit Type; referenced/in-use records are protected appropriately.
- [ ] Create a brand, retail department, and retail group; duplicate/blank values are rejected.
- [ ] Deleting referenced brands/departments/groups is blocked or handled without orphaning products.
- [ ] Configuration changes are reflected in product forms and are logged where required.

## 6. Staff account administration

- [ ] Staff forms offer only Admin, Inventory Staff, Sales Associate, and Sales/Marketing Staff roles.
- [ ] Submitting a custom/unknown role is rejected server-side.
- [ ] Create each staff role with valid name, unique email/username, mobile number, Employee ID, hub, and channel assignment.
- [ ] Employee ID is stored/displayed in uppercase.
- [ ] Duplicate email, username, or Employee ID is rejected with a field-specific message.
- [ ] Edit role, active status, hub assignment, multiple branch assignments, and channel assignments; changes take effect on the next request/login.
- [ ] Fully Booked can be assigned to Sales/Marketing Staff but not to Inventory Staff.
- [ ] Admin password reset requires a strong confirmed password and is unavailable to non-admins.
- [ ] Toggling a user inactive blocks new access while retaining historical authorship and logs.
- [ ] Deleting or changing a user does not delete their sales, inventory movements, notifications, or audit history.

## 7. Shared catalog and branch product records

- [ ] Shared Catalog opens only for Admin/Inventory Staff in a Head Office context.
- [ ] Catalog search is case-insensitive across Item ID, barcode, name, description, and supported metadata.
- [ ] Item IDs sort naturally/numerically, including values such as 2, 10, and leading-zero identifiers.
- [ ] Create/assign a catalog item to one branch; shared metadata copies while branch stock starts at zero.
- [ ] Assign the entire catalog to a branch; existing branch stock/prices remain unchanged and rerunning is idempotent.
- [ ] Catalog assignment reports created/skipped counts and produces an itemized staff log.
- [ ] Shared metadata updates are visible across assigned branches without merging their independent stock/prices.
- [ ] Branch product edits do not leak stock or branch-specific price changes to another hub.
- [ ] Inactive products are hidden from new sales/searches but remain visible in historical transactions.
- [ ] Product list and AJAX search paginate and do not embed/load the entire large catalog into the page.
- [ ] Unassigned staff see no products; branch staff search cannot return another branch's products.
- [ ] Bulk delete and direct delete enforce admin-only rules and protect referenced products/history.

## 8. Product CSV/XLSX import, export, and queue processing

- [ ] Import/export controls are visible only to Admin and Inventory Staff; direct routes/API return 403 for sales roles.
- [ ] Dedicated `product-files` queue worker receives a submitted import/export job.
- [ ] Live import status shows queued, processing, completed/failed, row counts, and actionable errors.
- [ ] Valid import creates/updates the intended hub records only.
- [ ] Blank stock imports as zero where specified.
- [ ] Direct branch import preserves shared catalog metadata and stock in other branches.
- [ ] Invalid file type, header, quantity, identifier, duplicate/conflicting row, or scientific notation rejects the whole import before writes.
- [ ] A failed job rolls back product and catalog changes; retry does not duplicate or partially reapply rows.
- [ ] Large import completes without timing out or loading all rows into browser memory.
- [ ] Export is an immutable snapshot and matches the products/hub/filter selected at request time.
- [ ] Item IDs and barcodes retain leading zeros and long digits in CSV and Excel.
- [ ] CSV correctly preserves commas, quotes, BOM, and multiline descriptions.
- [ ] Imported/exported filenames are meaningful and contain the correct file extension.
- [ ] Legacy request details/downloads remain accessible only to authorized users and paginate correctly.
- [ ] Review rejection requires a reason and leaves inventory unchanged; an approved request cannot be applied twice.
- [ ] Import/export completion, failure, approval, and rejection create the correct notifications and staff logs.

## 9. Product stock display and inventory accounting rules

- [ ] Product pages show Good stock, Damaged stock, and Total Physical = Good + Damaged.
- [ ] Damaged units remain visible in physical totals but are excluded from all allocatable/sellable Good capacity.
- [ ] Zero, negative, decimal, and excessively large stock inputs are validated correctly.
- [ ] Adding Good stock changes Good and Total Physical by the exact quantity and creates a traceable movement.
- [ ] Adding Damaged stock changes Damaged and Total Physical without increasing channel capacity.
- [ ] Every stock-changing operation is atomic: one invalid item prevents all rows in the request from changing.
- [ ] Concurrent attempts cannot oversell, over-transfer, over-return, or over-allocate the same units.
- [ ] Retrying/refreshing a completed submission does not duplicate the movement.
- [ ] The saved product balance, transaction detail, staff log, dashboard, and report all reconcile to the same quantity.

## 10. Stock allocation

- [ ] Only Admin/Inventory Staff assigned to a Head Office context can open/update Stock Allocation.
- [ ] Non-Head Office hubs cannot be opened or updated by changing the hub parameter.
- [ ] Multiple Head Offices show the intended hub selector; single scope defaults correctly.
- [ ] Allocation list contains only the correct Head Office products.
- [ ] Allocate Good stock across Online, Wholesale, Shopee, Lazada, and TikTok within capacity.
- [ ] Total allocated never exceeds Good stock; Damaged stock does not increase capacity.
- [ ] Reducing an allocation cannot create a negative channel balance or invalidate already committed stock.
- [ ] Current allocation is displayed as the live remaining balance; historical Sold quantities are informational and are not subtracted a second time.
- [ ] Walk-In availability equals unallocated Good stock and cannot consume units reserved for another channel.
- [ ] Sales deduct only the originating channel's live allocation (or Walk-In unallocated capacity).
- [ ] Good returns restore the correct originating balance according to the channel return rules; Damaged returns do not restore sellable allocation.
- [ ] Date-range sold-item filters return only products sold in the selected period and preserve pagination/filter state.

## 11. Dashboards

- [ ] Admin All Stores dashboard compares Head Office and branch sales using the correct scope.
- [ ] Default hub follows the user's designation; hub selector hides when only one hub is accessible.
- [ ] Changing hub/channel/date filters refreshes all widgets consistently and preserves valid selections.
- [ ] Sales channel selector locks for one assignment, offers only allowed channels for multiple assignments, and shows none for no assignment.
- [ ] Main totals exclude Shopee, Lazada, and TikTok where the dashboard design requires exclusion.
- [ ] Walk-In and Online year-over-year charts compare the correct periods.
- [ ] Sold count and top products subtract returns and do not count replacement units as new sales.
- [ ] Wholesale totals exclude unpaid sales and count only collected value for partial payments.
- [ ] Inventory alerts show correct low/zero stock for the selected branch and paginate ten rows while retaining filters.
- [ ] Slow-moving products include only in-stock products and paginate correctly.
- [ ] Selected marketplace layout shows Sales Channels and Payment Overview once without duplicate/blank panels.
- [ ] Non-Head Office dashboard displays branch sales snapshot, payment cards, inventory alerts, and Return Items action.
- [ ] Dashboard totals reconcile with the underlying report and individual transaction records for the same filters.

## 12. Common sales entry and inventory verification

- [ ] Product search returns only active products in the selected hub and displays Good sellable stock, not Damaged stock.
- [ ] Duplicate product rows, zero/negative/decimal invalid quantity, damaged-only quantity, and unknown product ID are rejected.
- [ ] Order number preview uses the correct channel prefix/format and remains unique under concurrent requests.
- [ ] New customer and existing customer paths save/select the correct customer without cross-hub data leakage.
- [ ] Discounts, shipping, taxes/fees, subtotal, total, and amount due calculate and round consistently in UI and server data.
- [ ] Required supporting attachments accept approved file types/sizes and reject unsafe or oversized files.
- [ ] Non-cash payments require proof; Cash and COD follow their channel-specific proof exemptions.
- [ ] Dated/Post-Dated Check requires bank, check number, and check date.
- [ ] "Other" payment method requires its custom label.
- [ ] Submitting a sale creates one pending verification record and does not deduct stock yet.
- [ ] Pending queue shows correct hub/channel/customer/order/items/payment/attachment data and an order slip where supported.
- [ ] Inventory Staff can confirm a valid order from the intended cross-hub scope; stock deducts once and a sale/log/notification is created.
- [ ] Inventory Staff can reject with a reason; no stock changes and the submitter sees the rejected order and notification.
- [ ] Confirming an order with insufficient stock rejects the whole order without partial item deductions.
- [ ] A second confirm/reject attempt cannot process an already finalized order.
- [ ] Approved marketplace orders appear only on the correct channel's order page.
- [ ] Results count appears bottom-left and numeric pagination bottom-right where designed.

## 13. Walk-In sales

- [ ] Branch Sales Associate sees only Walk-In availability for their branch.
- [ ] Head Office Walk-In uses unallocated Good stock and cannot consume allocated marketplace/Online/Wholesale units.
- [ ] Existing-customer lookup and new-customer entry format and save names correctly.
- [ ] Walk-In reference/order number uses the intended format.
- [ ] Cash sale succeeds without proof; each configured non-cash method requires and preserves proof.
- [ ] Order slip is available during verification and remains available after approval.
- [ ] Confirmation deducts the exact unallocated Good quantity and leaves channel allocations unchanged.
- [ ] Walk-In sold items remain separate from Online sold reporting.
- [ ] Branch Walk-In report shows daily total sales, transactions, discounts, purchased items, date, and intended payment information.
- [ ] Purchased-item count subtracts returns and counts approved replacement quantities correctly.

## 14. Online sales

- [ ] Online sale uses only Online allocation and deducts it once after verification.
- [ ] Required customer, delivery, payment, bank/custom method, proof, and order details validate correctly.
- [ ] Online report summaries and formulas match individual orders and payment records.
- [ ] Delivery/status edits persist only for authorized roles and create the intended audit trail.
- [ ] PNG preview/export contains the intended report content and excludes screen-only controls.

## 15. Wholesale sales and payments

- [ ] Wholesale sale uses only Wholesale allocation and preserves quotation/order attachments.
- [ ] Paid, partial, and unpaid statuses save the correct paid/collected amount.
- [ ] Paid/partial orders appear in Return Items; unpaid orders do not.
- [ ] Wholesale report filters Open and Completed transactions correctly.
- [ ] Total Sales counts only collected paid/partial amounts, not unpaid receivables.
- [ ] Withholding, shipping, discount, balance due, and replacement charges reconcile to transaction totals.

## 16. Shopee and Lazada sales

- [ ] User can record only their assigned marketplace channel and cannot change the channel in the request.
- [ ] Blank invoice number generates a unique invoice number; entered valid invoice is preserved.
- [ ] Channel-specific payment methods validate; Other requires a description.
- [ ] Confirmation deducts only the correct Shopee or Lazada live allocation.
- [ ] Approval notification opens the selected marketplace's Approved Orders page.
- [ ] Order list never includes the other marketplace or an unauthorized staff member's channel data.
- [ ] Marketplace returns page lists only orders/items with a recorded return.

## 17. TikTok sales and settlement

- [ ] TikTok customer lookup finds existing customers by name and stays within authorized scope.
- [ ] TikTok sale deducts only TikTok allocation after confirmation.
- [ ] TikTok financial values and settlement/payout formula match the source fields.
- [ ] Missing payout is not silently estimated.
- [ ] Authorized status/payout/delivery edits persist and unauthorized edits return 403.
- [ ] Partial return leaves permitted payout and delivery fields editable.
- [ ] TikTok Return Items remains accessible from the Hub dashboard to designated staff.
- [ ] Removed/unsupported TikTok sales-report route is unavailable to Sales/Marketing Staff where intended.

## 18. Fully Booked workflow

- [ ] Fully Booked is offered only to designated staff and does not appear as a general sidebar module.
- [ ] Entry point from Inventory Transaction Logs and pending badges show the correct unprocessed count.
- [ ] Product search returns only matching active store products and available permitted stock.
- [ ] "Other Fully Booked Store" requires a specified store name.
- [ ] Designated staff can submit the request and required attachment; unauthorized staff cannot submit.
- [ ] Attachment preview/download is private to Inventory Staff/Admin and the authorized submitter as designed.
- [ ] Inventory Staff can review, reject with reason, or pull out; submitter receives the correct notification.
- [ ] Pull-out deducts physical Good stock and the intended Online allocation exactly once.
- [ ] Rejected request does not deduct stock and is visible in the correct rejected list.
- [ ] Completed pull-out creates a returnable sales/order reference.
- [ ] Fully Booked tracking paginates six per page and displays correct state/history.
- [ ] Fully Booked return is visible to designated staff, links back to its source attachment, restores physical stock per condition, and never creates an automatic refund.

## 19. Head Office-to-Branch stock transfer

- [ ] Transfer form defaults to the correct Head Office source/destination behavior.
- [ ] Only active permitted source/destination hubs appear and source cannot equal destination.
- [ ] Product worksheet preserves Item ID/barcode as Excel-safe text.
- [ ] Valid Good and Damaged quantities move to the destination condition without changing the system-wide physical total.
- [ ] Insufficient, negative, duplicate, invalid-product, and mixed valid/invalid submissions fail atomically.
- [ ] Transfer generates a unique document/reference, success popup, transaction detail, and audit record.
- [ ] Branch-to-Head Office direction works when allowed and applies the same reconciliation rules.

## 20. Branch-to-Branch transfer review

- [ ] Sales Associate source branch is locked when only one branch is designated; multi-branch user sees only designated sources.
- [ ] A submitted request remains Pending and does not move stock before review.
- [ ] Review queue paginates ten transfer documents per page and shows document/reference, source, destination, requester, and items.
- [ ] Requester cannot approve their own transfer; unauthorized role receives 403.
- [ ] Approval rechecks current source stock and fails atomically if stock changed after submission.
- [ ] Approval moves each item's Good/Damaged stock once and keeps total system physical stock unchanged.
- [ ] Rejection requires a reason and changes no stock.
- [ ] Reviewer/requester/destination staff receive the correct notifications and links.
- [ ] Branch transfer activity appears in Staff Logs with search/pagination, while the role-specific Transaction Log view avoids duplicate workflow representation.

## 21. Restock / Added Items

- [ ] Restock form uses compact item rows and an automatically generated unique reference.
- [ ] Excel-only bulk controls and workbook metadata/columns match the Restock form.
- [ ] Valid Good and Damaged restock quantities increase the correct balances.
- [ ] Blank, zero, negative, invalid, duplicate, or excessive rows are rejected/skipped according to the documented rule without partial corruption.
- [ ] One submission appears as one grouped Transaction Log row with naturally sorted item details.
- [ ] Success redirects to Transaction Logs and displays the intended popup.

## 22. Sponsor / Workshop and event pull-out

- [ ] Authorized built-in roles can open the workflow; unauthorized/direct access is rejected.
- [ ] Reference number is generated automatically and uniquely.
- [ ] Event worksheet includes document metadata and separate Good/Damaged stock columns.
- [ ] Exported selected items retain the chosen condition and can be reimported.
- [ ] Valid pull-out consumes Good and Damaged stock separately and creates grouped logs.
- [ ] Invalid or over-stock request fails atomically.
- [ ] Event return restores Good and Damaged quantities to their original conditions and rejects over-return.
- [ ] Confirmed pull-out creates one Event Inventory ledger without deducting stock a second time when viewed or tracked.
- [ ] Event Inventory compares Actual Released, Sold, Returned Good, Returned Damaged, Remaining, and Missing quantities per product.
- [ ] Event sale creates a linked sales/audit record, deducts only the Event balance, and leaves Head Office physical stock and marketplace allocations unchanged.
- [ ] Event Return Items limits returnable quantity to `Actual Released - Event Sold - Previously Returned`.
- [ ] Reconcile & Close records unresolved remaining quantities as missing, locks further Event sales/returns, and preserves the audit history.
- [ ] Event Inventory routes are restricted to Admin and Inventory Staff in both the UI and direct requests.
- [ ] Event Transaction Logs are available as an internal Event Inventory tab and display Event Pull-Out, Event Sale, Return, and Missing activity.
- [ ] Event Transaction Logs can be filtered by Store Hub, Event, activity, date range, and reference/product search.
- [ ] Event Transaction Logs combine repeated product IDs into one product line with Good/Damaged quantity breakdowns.
- [ ] Event Transaction Logs use compact summary rows and show the consolidated products, inventory effect, sale image attachments, and audit metadata through View Details.
- [ ] Record Event Sale opens as a channel-sales-style popup with a visible generated order number, customer details, per-row Good/Damaged selection, searchable product rows, availability, price, line discount, additional discount, totals, and remarks; Payment Status and Amount Paid are not shown.
- [ ] One Event Sale can contain both Good and Damaged rows, including the same product in both conditions, while deducting each Event balance separately without another Head Office deduction.
- [ ] Customer names are saved in title case, and Event Sale contact numbers accept only valid Philippine mobile numbers stored as `+639XXXXXXXXX`.
- [ ] Record Event Sale is vertically scrollable and keeps its header/footer controls usable while reviewing a long form.
- [ ] Event Sale separates Order Slip Image, up to five Item Images, and Proof of Payment; every JPG/PNG/WebP file is limited to 2 MB and saved under the correct category.
- [ ] Clicking an attachment preview opens an in-page image viewer; multiple Item Images support previous/next buttons and keyboard arrow navigation without opening another tab.
- [ ] Event payment methods are limited to Cash, GCash, BDO, BPI, PayMaya, and Others; selecting Others requires and saves a specified payment method.
- [ ] Event Sale Remarks are fixed-size, cannot be manually resized, and reject input longer than 150 characters in both the browser and server.
- [ ] Order Slip, Item Images, and Proof of Payment cards use equal-width responsive columns; Order Item labels align with their fields, the condition selector remains readable, and Regular Price is shown as a read-only peso amount sourced from the product catalog.
- [ ] Large workbook import matches products in bounded batches and preserves identifiers.

## 23. National Inventory and National Pull-out

- [ ] National Inventory is visible only to Head Office Admin/Inventory Staff.
- [ ] National products are separate from store products and sort naturally by Item ID.
- [ ] Valid National CSV import/export preserves exact identifiers and requested columns.
- [ ] Invalid National CSV is rejected before any write; empty export contains headers only.
- [ ] Head Office staff can edit/toggle National products; only Admin can delete, with no unintended store mutation.
- [ ] National Pull-out form/CSV/Excel worksheet contains only the approved document and product columns/layout.
- [ ] Imported workbook populates document and item values accurately.
- [ ] Valid pull-out deducts Actual Quantity, creates a unique PO/reference, and appears in Transaction Logs.
- [ ] Duplicate PO, invalid row, or insufficient quantity rejects the entire pull-out.
- [ ] Exported saved pull-out matches the on-screen transaction.
- [ ] National bookstore return by PO restores only the original National Inventory items and rejects excess/duplicate return.

## 24. Return Items common rules

- [ ] Return page is available only to Admin, Inventory Staff, and authorized Sales Associates.
- [ ] Store selector is locked for a single-branch associate and limited to designated hubs for a multi-branch associate.
- [ ] Non-Head Office return channel is locked to Walk-In where required.
- [ ] Return Date limits customer/order lookup to sales on that date.
- [ ] Order lookup loads the exact original items, sold quantities, prior returned quantities, price/discount, channel, and reference.
- [ ] Return quantity cannot be zero, negative, fractional where not allowed, exceed sold quantity, or exceed remaining returnable quantity.
- [ ] At least one returned quantity is required; blank worksheet rows do not create movements.
- [ ] Each returned unit requires an explicit Good or Damaged condition.
- [ ] Good return restores the correct sellable physical/channel balance for the original channel's rule.
- [ ] Damaged return increases only Damaged physical stock and never allocatable/sellable capacity.
- [ ] Repeated submission cannot restock or refund the same quantity twice.
- [ ] Return saves one grouped history/log entry, correct user/date/reference, and remains searchable.
- [ ] Return notification opens the correct channel-specific returns page.

### Refund rules

- [ ] Walk-In/eligible automatic refund is calculated only for items and quantities actually returned, using the discounted unit value.
- [ ] Shopee, Lazada, TikTok, Fully Booked, and partial Wholesale returns do not create an automatic refund.
- [ ] Fully Booked return forces refund amount to zero even if a crafted request submits another amount.
- [ ] Refund-only TikTok update does not restore stock; receiving the return restores stock only once.
- [ ] Reports and dashboard subtract returned units and show refund cost exactly once.

## 25. Exchanges and replacements

- [ ] Supported channel requires the full eligible return credit before an exchange and can exchange into multiple products.
- [ ] Shopee and Lazada cannot use the unsupported exchange endpoint.
- [ ] Replacement quantity cannot exceed received return quantity.
- [ ] Replacement product search uses the live balance for the original channel (Walk-In uses unallocated physical Good stock).
- [ ] Online/Wholesale/TikTok replacement deducts the replacement channel allocation and restores/deducts original stock only once.
- [ ] Walk-In replacement is blocked until Inventory records the return where required.
- [ ] Lower-value, equal-value, and higher-value exchanges calculate credit, revised balance, shipping, and additional payment correctly.
- [ ] Non-cash additional payment requires proof; check/bank/Other methods require their dependent fields.
- [ ] Approved replacement updates the original report/order without inflating sold-item dashboard totals.
- [ ] Rejecting replacement changes no inventory and sends a clear rejection notification.
- [ ] Replacement order slip and proof attachments are preserved and accessible only to authorized users.

## 26. Reports and exports

- [ ] Each role sees only the intended report menu and direct report URL access follows the same rule.
- [ ] Date, hub, channel, status, customer/order, and other supported filters apply consistently to cards, table, preview, and export.
- [ ] All Channels branch report excludes marketplace channels where designed and shows only branch Walk-In orders.
- [ ] New Customer badge appears consistently in every supported channel report.
- [ ] View buttons open the correct transaction details and attachments.
- [ ] Online payment group totals map GCash, PayMaya, BDO, BPI, Dated Check, Post-Dated Check, and Other/custom values correctly.
- [ ] TikTok and Online financial formulas match transaction-level data.
- [ ] Wholesale Open/Completed, paid/partial/unpaid, collected totals, returns, and replacements reconcile.
- [ ] PNG preview/export has correct title, dates, totals, rows, currency format, and no hidden buttons/modals/duplicate content.
- [ ] Large report pagination keeps filters and total/result placement.
- [ ] Report totals equal a manual calculation from the displayed transaction rows and the database control totals.

## 27. Inventory Transaction Logs

- [ ] Only Admin/Inventory Staff can open the logs; other roles receive 403.
- [ ] Log filters include only role-appropriate transaction types and retain values across pagination.
- [ ] Merged ordinary, Fully Booked, and National Pull-out rows sort by business date, then event timestamp, then a stable key, newest first.
- [ ] Date/Time shown for synthetic Fully Booked/National rows matches the source event, not the page-load time.
- [ ] Logs paginate 20 grouped display rows and do not split one transaction across pages.
- [ ] Grouped item details sort naturally by Item ID and show correct quantities/conditions.
- [ ] Sold order detail uses compact metadata and links to the preserved order slip where allowed.
- [ ] Search by reference/document/order/product/user returns the correct records without leaking another hub.
- [ ] Historical records remain readable after product/user/hub deactivation.

## 28. Staff Logs and auditability

- [ ] Admin, Inventory Staff, and authorized Sales Associates can view the intended staff logs; Sales/Marketing Staff cannot.
- [ ] Product import/export and catalog assignment create itemized logs with actor, hub, counts, and affected items.
- [ ] Inventory verification creates a stock-deduction log linked to the order/reference.
- [ ] Branch transfer submission/review records requester, reviewer, document number, source, destination, items, and outcome.
- [ ] Log filters/search/pagination retain state and return no unauthorized hub activity.
- [ ] Audit records cannot be edited/deleted through the UI or ordinary endpoints.
- [ ] Timestamps use the configured Asia/Manila display expectation and can be correlated with the transaction.

## 29. Notifications

- [ ] Notification feed/count contains only the signed-in user's records and updates after marking one/all as read.
- [ ] Mark All as Read uses POST and cannot be triggered cross-user.
- [ ] Inventory confirmation/rejection, replacement approval/rejection, branch transfer, Fully Booked, return, and product-file events use clear text and the intended icon/color.
- [ ] Marketplace approval opens Approved Orders; return notification opens Shopee/Lazada, TikTok, Fully Booked, or branch return destination as appropriate.
- [ ] Sales staff opening an inventory-only queue notification see a friendly restriction message, not a 403/500 loop.
- [ ] Old notification links handle missing/deactivated related records gracefully.
- [ ] Email/database notification delivery failure does not roll back an already committed inventory transaction unexpectedly.

## 30. Private API and server-side protection

- [ ] Every private API endpoint requires authentication.
- [ ] `/api/me` returns only the signed-in user's intended permissions and accessible hubs.
- [ ] Product APIs sort naturally, paginate, and restrict results to allowed hubs.
- [ ] Product search cannot reveal inactive/unauthorized hub stock or another channel's protected data.
- [ ] Import-status API is restricted to Inventory roles and the permitted hub.
- [ ] Notification list/read/read-all APIs operate only on the signed-in user's notifications.
- [ ] Invalid IDs return 404 and unauthorized valid IDs return 403 without sensitive record details.
- [ ] State-changing endpoints enforce CSRF, HTTP method, validation, authorization, and idempotency/state checks.
- [ ] Uploaded files are MIME/size validated, stored outside unsafe execution paths, and served with safe headers.
- [ ] SQL/script/HTML payloads in search, names, descriptions, references, and filenames are escaped and do not execute.
- [ ] Validation errors do not expose SQL, filesystem paths, secrets, stack traces, or attachment storage keys.

## 31. Bulk workbook and identifier integrity

- [ ] Transfer, Restock, Sponsor/Workshop, Return, and National workflows expose only their supported CSV/XLSX controls.
- [ ] Downloaded workbook contains the correct workflow metadata, required columns, stock columns, and protected text formatting.
- [ ] Reimport preserves leading-zero Item IDs/barcodes and long numeric identifiers exactly.
- [ ] Duplicate, missing, ambiguous, conflicting, invalid-condition, and invalid-quantity rows produce precise errors.
- [ ] Blank quantity rows are ignored safely and do not become zero-value movements.
- [ ] A stock error identifies/highlights the matching product row for correction.
- [ ] 1,500+ row file completes within the agreed limit with bounded memory and one atomic outcome.

## 32. UI, responsive behavior, accessibility, and browser compatibility

- [ ] Test primary workflows at 360 px, 768 px, 1024 px, and 1440 px widths.
- [ ] Sidebar/menu, filters, cards, tables, dialogs, and pagination do not overlap, clip, or create inaccessible horizontal content.
- [ ] Large TikTok/marketplace orders keep items and editors inside the order window.
- [ ] Keyboard users can reach, operate, and close menus, modals, tabs, file pickers, and submit controls with a visible focus state.
- [ ] Every input has a meaningful label; required fields and validation are announced/readable without color alone.
- [ ] Icons/buttons have tooltips or accessible names and destructive actions are clearly distinguished.
- [ ] Text, badges, alerts, disabled controls, and selected states have readable contrast.
- [ ] Loading/disabled state prevents accidental double-click submission and provides progress for queued/large operations.
- [ ] Empty states, no search results, server validation, session expiry, network failure, and 403/404/500 pages are usable and clear.
- [ ] Chrome and Edge complete the critical path; file downloads/previews and PNG capture work in supported browsers.
- [ ] Currency, dates, times, quantities, and Asia/Manila timezone display consistently.

## 33. Performance, reliability, and concurrency

- [ ] Dashboard, product list, search, pending queue, logs, and reports meet the agreed response-time target with production-sized data.
- [ ] Product/hub lists use pagination/bounded loading and do not issue an excessive query count.
- [ ] Two users confirming the last available unit result in one success and one clean failure—never negative stock.
- [ ] Two reviewers processing the same sale/transfer/replacement cannot apply it twice.
- [ ] Simultaneous import and sale/transfer either serialize safely or fail without partial data.
- [ ] Browser refresh, Back, double-click, retry after timeout, and network reconnect do not duplicate sales, payments, returns, or stock movements.
- [ ] A queue-worker crash leaves a retryable job and no partial catalog/inventory update.
- [ ] Database rollback restores every related table when a multi-item operation fails midway.
- [ ] Logs and user messages contain enough correlation data to diagnose a failed request without exposing secrets.

## 34. History retention, backup, restore, and operations

- [ ] `php artisan history:prune --dry-run` reports only eligible read notifications and finalized old file requests.
- [ ] Nonpositive retention values are rejected.
- [ ] Actual pruning retains unread notifications, pending/processing requests, sales, payments, returns, replacements, staff logs, and inventory movements.
- [ ] Weekly scheduler runs pruning without overlap and records success/failure.
- [ ] Database backup and matching `storage/app/private` and `storage/app/public` backup complete from the same recovery point.
- [ ] Backup is encrypted, access-controlled, stored off-host, checksummed, and covered by retention policy.
- [ ] Restore drill uses the original `APP_KEY` and restores database plus uploaded files into a non-production environment.
- [ ] After restore, migrations, `inventory:health`, login, product lookup, stock balances, reports, and private attachment access pass.
- [ ] Restored control totals match the pre-backup inventory, sales, payments, returns, and audit counts.
- [ ] Deployment restarts queue workers; a newly submitted product-file job is processed by the replacement worker.

## 35. Automated regression and build gate

Run these from the repository root and attach the complete outputs.

- [ ] `composer test` passes.
- [ ] `npm test` passes.
- [ ] `npm run build` passes with no missing asset or fatal warning.
- [ ] `php artisan view:cache` passes after Blade/template changes.
- [ ] `php artisan route:list` completes and all controller routes resolve.
- [ ] `php artisan migrate:status` shows the expected applied migrations.
- [ ] Focused tests for every changed workflow pass before the full suite.
- [ ] Any unrelated/pre-existing failure is documented with exact command, failing test, reason, owner, and release decision.

## 36. Final end-to-end reconciliation scenario

Use one uniquely named product and record every value in a reconciliation sheet.

- [ ] Add Good and Damaged stock at Head Office and confirm Total Physical.
- [ ] Allocate controlled Good quantities to Online, Wholesale, Shopee, Lazada, and TikTok.
- [ ] Transfer stock to a branch and confirm source/destination/system-wide totals.
- [ ] Complete one verified Walk-In sale and one verified sale in each assigned channel.
- [ ] Reject one pending sale and confirm no stock change.
- [ ] Complete a Fully Booked request/pull-out.
- [ ] Record one Good return and one Damaged return from eligible orders.
- [ ] Complete one replacement/exchange with any required additional payment proof.
- [ ] Complete and return a Sponsor/Workshop pull-out.
- [ ] Complete a National Pull-out and PO-based National return.
- [ ] Reconcile product balances, all channel allocations, sold quantities, return quantities, replacement quantities, and Total Physical.
- [ ] Reconcile sale totals, collected amounts, discounts, shipping, refunds, outstanding amounts, and report/dashboard totals.
- [ ] Confirm every action appears once in the appropriate transaction/staff log and every intended recipient has one correct notification.

## 37. Release sign-off

- [ ] No open Critical or High defect remains.
- [ ] Medium/Low defects have an owner, accepted workaround/risk, and target fix date.
- [ ] Role/access matrix is signed off by the business owner.
- [ ] Inventory and financial reconciliation is signed off by the responsible owner.
- [ ] Import/export, queue recovery, backup, and restore evidence is attached.
- [ ] Production smoke-test plan and rollback owner are documented.
- [ ] Final release decision is recorded below.

### Sign-off table

| Area | Name | Decision | Date | Notes / defect references |
|---|---|---|---|---|
| Business / Operations | | | | |
| Inventory | | | | |
| Sales / Finance | | | | |
| QA | | | | |
| Technical / Deployment | | | | |

