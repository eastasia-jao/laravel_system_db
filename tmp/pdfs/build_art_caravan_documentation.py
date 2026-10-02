from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle, KeepTogether
from reportlab.pdfbase.pdfmetrics import stringWidth

OUT = 'output/pdf/Art_Caravan_PH_Inventory_Management_System_Documentation.pdf'
NAVY = colors.HexColor('#14213D')
TEAL = colors.HexColor('#007C83')
GOLD = colors.HexColor('#E9B44C')
INK = colors.HexColor('#253246')
MUTED = colors.HexColor('#65758B')
PALE = colors.HexColor('#F2F7F8')

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(name='CoverKicker', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=11, leading=14, textColor=GOLD, alignment=TA_CENTER, spaceAfter=18))
styles.add(ParagraphStyle(name='CoverTitle', parent=styles['Title'], fontName='Helvetica-Bold', fontSize=29, leading=35, textColor=NAVY, alignment=TA_CENTER, spaceAfter=10))
styles.add(ParagraphStyle(name='CoverSub', parent=styles['Normal'], fontName='Helvetica', fontSize=13, leading=19, textColor=INK, alignment=TA_CENTER))
styles.add(ParagraphStyle(name='Section', parent=styles['Heading1'], fontName='Helvetica-Bold', fontSize=18, leading=23, textColor=NAVY, spaceBefore=14, spaceAfter=9, keepWithNext=True))
styles.add(ParagraphStyle(name='Subsection', parent=styles['Heading2'], fontName='Helvetica-Bold', fontSize=12.5, leading=16, textColor=TEAL, spaceBefore=10, spaceAfter=5, keepWithNext=True))
styles.add(ParagraphStyle(name='Body2', parent=styles['BodyText'], fontName='Helvetica', fontSize=9.6, leading=14.2, textColor=INK, spaceAfter=7))
styles.add(ParagraphStyle(name='Small', parent=styles['BodyText'], fontName='Helvetica', fontSize=8.4, leading=11.4, textColor=INK, spaceAfter=4))
styles.add(ParagraphStyle(name='Bullet2', parent=styles['BodyText'], fontName='Helvetica', fontSize=9.4, leading=13.5, textColor=INK, leftIndent=14, firstLineIndent=-8, spaceAfter=3))
styles.add(ParagraphStyle(name='TableH', parent=styles['BodyText'], fontName='Helvetica-Bold', fontSize=8.1, leading=10, textColor=colors.white))
styles.add(ParagraphStyle(name='TableC', parent=styles['BodyText'], fontName='Helvetica', fontSize=7.8, leading=10.1, textColor=INK))

def P(text, style='Body2'):
    return Paragraph(text, styles[style])

def bullet(text):
    return P('&bull; ' + text, 'Bullet2')

def section(title):
    return P(title, 'Section')

def subsection(title):
    return P(title, 'Subsection')

def table(headers, rows, widths):
    data = [[P(h, 'TableH') for h in headers]] + [[P(c, 'TableC') for c in row] for row in rows]
    t = Table(data, colWidths=widths, repeatRows=1, hAlign='LEFT')
    t.setStyle(TableStyle([
        ('BACKGROUND', (0,0), (-1,0), NAVY), ('TEXTCOLOR', (0,0), (-1,0), colors.white),
        ('BACKGROUND', (0,1), (-1,-1), colors.white), ('ROWBACKGROUNDS', (0,1), (-1,-1), [colors.white, PALE]),
        ('GRID', (0,0), (-1,-1), 0.35, colors.HexColor('#D6E0E6')),
        ('VALIGN', (0,0), (-1,-1), 'TOP'), ('LEFTPADDING', (0,0), (-1,-1), 6), ('RIGHTPADDING', (0,0), (-1,-1), 6),
        ('TOPPADDING', (0,0), (-1,-1), 5), ('BOTTOMPADDING', (0,0), (-1,-1), 5),
    ]))
    return t

def footer(canvas, doc):
    if doc.page == 1: return
    canvas.saveState()
    canvas.setStrokeColor(colors.HexColor('#D5DFE6'))
    canvas.line(18*mm, 14*mm, 192*mm, 14*mm)
    canvas.setFont('Helvetica', 7.5)
    canvas.setFillColor(MUTED)
    canvas.drawString(18*mm, 9.5*mm, 'Art Caravan PH Inventory Management System | System Documentation')
    canvas.drawRightString(192*mm, 9.5*mm, f'Page {doc.page}')
    canvas.restoreState()

doc = SimpleDocTemplate(OUT, pagesize=A4, leftMargin=18*mm, rightMargin=18*mm, topMargin=17*mm, bottomMargin=20*mm, title='Art Caravan PH Inventory Management System Documentation', author='Jao Lacatan')
story = []

# Cover
story += [Spacer(1, 42*mm), P('SYSTEM DOCUMENTATION', 'CoverKicker'), P('Art Caravan PH', 'CoverTitle'), P('Inventory Management System', 'CoverTitle'), Spacer(1, 8*mm), P('Professional Technical and Operations Documentation', 'CoverSub'), Spacer(1, 26*mm)]
cover = Table([[P('<b>Prepared by</b><br/>Jao Lacatan', 'Body2'), P('<b>Document version</b><br/>1.0', 'Body2'), P('<b>Prepared</b><br/>October 2026', 'Body2')]], colWidths=[52*mm]*3)
cover.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),PALE),('BOX',(0,0),(-1,-1),0.8,TEAL),('VALIGN',(0,0),(-1,-1),'MIDDLE'),('LEFTPADDING',(0,0),(-1,-1),9),('TOPPADDING',(0,0),(-1,-1),9),('BOTTOMPADDING',(0,0),(-1,-1),9)]))
story += [cover, Spacer(1, 16*mm), P('Confidential operational reference for authorized Art Caravan PH personnel.', 'CoverSub'), PageBreak()]

story += [section('Document Control'), P('This document describes the intended business use, operational workflows, access controls, and deployment requirements of the Art Caravan PH Inventory Management System. It is written for management, inventory personnel, sales personnel, and technical administrators.'),
table(['Field','Details'], [['System name','Art Caravan PH Inventory Management System'], ['Author','Jao Lacatan'], ['Platform','Laravel 12 web application with PHP 8.2+ and a supported SQL database'], ['Primary purpose','Centralize catalog, inventory, sales, transfers, returns, approvals, reporting, and staff activity tracking.'], ['Audience','Administrators, inventory staff, sales associates, sales and marketing staff, and technical operators.']], [40*mm, 116*mm]), Spacer(1, 10),
section('Executive Summary'), P('The system provides Art Caravan PH with a controlled, multi-store environment for managing products and stock across store hubs and sales channels. It supports day-to-day selling, stock replenishment, allocations, branch transfers, returns, payment evidence, transaction review, and reporting while preserving a traceable audit trail.'),
P('The application is designed around separation of duties. Operational screens are limited by role, and routes are protected by server-side authorization gates. This reduces the risk of unauthorized inventory movements, status changes, and access to sensitive reports.'),
section('Contents'),
table(['Section','Coverage'], [['1','System Scope and Objectives'],['2','User Roles and Access Control'],['3','Core Modules'],['4','Operational Workflows'],['5','Business Rules and Controls'],['6','Reports, Notifications, and Audit Trail'],['7','Technical Architecture'],['8','Deployment, Backup, and Operations'],['9','User Acceptance and Go-Live Checklist']], [22*mm, 134*mm]), PageBreak()]

story += [section('1. System Scope and Objectives'),
P('The Art Caravan PH Inventory Management System is a browser-based management solution for stock-driven retail and channel sales operations. It consolidates information that would otherwise be distributed across spreadsheets, messaging, paper sales slips, and separate branch records.'),
subsection('Primary objectives'),
bullet('<b>Maintain stock integrity.</b> Record stock additions, allocations, transfers, returns, adjustments, and sales through controlled workflows.'),
bullet('<b>Support multi-store operations.</b> Assign staff to store hubs and ensure branch activity is scoped to the user’s permitted location and sales channels.'),
bullet('<b>Improve accountability.</b> Capture staff activity logs, transaction records, approvals, order references, and uploaded payment or quotation evidence.'),
bullet('<b>Provide operational visibility.</b> Deliver dashboards and sales reporting for Walk-In, online, marketplace, wholesale, and related sales activities.'),
subsection('Supported sales and fulfillment contexts'),
table(['Context','System support'], [['Walk-In','Branch transactions, payment records, returns, replacements, daily branch reporting.'], ['Marketplace / Online','Multi-channel recording and allocation-aware stock handling for channels such as Shopee, Lazada, TikTok, and online orders.'], ['Wholesale','Wholesale reporting, payment and replacement workflows, and status management.'], ['Fully Booked / Special orders','Order tracking, attachment access, review, and pull-out controls.'], ['Sponsorship / Workshop','Dedicated inventory transaction path for approved operational use.']], [48*mm, 108*mm]), PageBreak()]

story += [section('2. User Roles and Access Control'), P('All protected functions require authentication. Permissions are enforced on the server through Laravel authorization gates; hiding a menu item alone is not relied upon as a security control.'),
table(['Role','Typical responsibilities','Key restrictions'], [
['Administrator','System configuration, users, shared catalog, inventory, stock allocation, approvals, reports, and full oversight.','Only authorized administrators receive full-access functions such as user management and destructive master-data actions.'],
['Inventory Staff','Product and stock maintenance, catalog assignment, allocation, transfer review, inventory verification, and transaction logs.','Cannot assume all sales-status or executive report controls.'],
['Sales Associate','Channel sales, permitted branch transfers and returns, approved branch reports, and permitted customer lookup.','Limited to assigned hub and approved sales channels; no broad inventory administration.'],
['Sales and Marketing Staff','Sales recording, product viewing, selected reports, and sales-status actions.','Does not receive general inventory-management authority.'],
], [27*mm, 65*mm, 64*mm]), Spacer(1,8),
subsection('Access control principles'), bullet('Users have a role, account status, assigned hub, and where applicable an approved list of sales channels.'), bullet('Role checks apply to pages and direct routes, preventing users from bypassing navigation restrictions with a copied URL.'), bullet('Sales associates are prevented from crossing into unauthorized branches; an unassigned associate should not receive branch products.'), bullet('Notification links are role-aware so users are directed only to information they are allowed to view.') , PageBreak()]

story += [section('3. Core Modules'),
table(['Module','Purpose','Main outputs'], [
['Configuration and Master Data','Manage store hubs, unit types, brands, departments, retail groups, and related configuration.','Standardized operational master data.'],
['User Management','Create, update, activate, or deactivate users; assign roles, hubs, and channels.','Controlled user access and accountability.'],
['Product Catalog','Maintain shared products and assign catalog items to branches.','Branch-ready product availability.'],
['Inventory and Allocation','View product stock, add stock, allocate to channels, and monitor inventory transactions.','Physical stock and channel allocation balances.'],
['Sales Processing','Record Walk-In and multi-channel sales; create pending sales where review is required.','Sales references, order slips, payment evidence, and status tracking.'],
['Transfers and Returns','Create restocks, stock transfers, branch transfers, and returns.','Movement history, transfer document references, and return history.'],
['Reports','Produce sales and operational reports, including branch-focused Walk-In reporting.','Daily totals, discounts, payment method summaries, item counts, and transaction detail.'],
['Files and Notifications','Import/export product files through approval workflow; notify users about workflow events.','Auditable requests, downloads, and targeted alerts.'],
['Activity Logs','Record staff and transaction activity for review and reconciliation.','Searchable accountability trail.'],
], [36*mm, 68*mm, 52*mm]), PageBreak()]

story += [section('4. Operational Workflows'),
subsection('4.1 Product and stock setup'), P('An administrator or inventory staff member configures master data, creates or maintains products, and makes catalog items available to the correct store hubs. Stock additions are recorded through the inventory workflow, providing an auditable starting point before items are allocated or sold.'),
subsection('4.2 Channel allocation'), P('Physical inventory and channel allocations are intentionally distinct. Allocation reserves available stock for a named sales channel without losing the physical-stock context. Teams should allocate stock before using channel sales flows and regularly reconcile allocation balances with product stock.'),
subsection('4.3 Sales processing and approval'), P('Sales personnel record transactions through the appropriate Walk-In or multi-channel workflow. Depending on the transaction type, a sale may enter a pending queue for inventory verification. Authorized staff can review, confirm, or reject pending sales. Rejected sales remain traceable to the originating hub and user.'),
subsection('4.4 Payment evidence'), P('The system supports payment method information, payment references, and uploaded proof where the selected method requires it. Cash and COD are ordinarily treated differently from non-cash methods. Staff should attach clear, readable evidence before submitting non-cash transactions and avoid storing sensitive payment-card data in free-text fields.'),
subsection('4.5 Stock transfers'), P('Inventory transfers move stock between approved operational locations. Branch transfer requests generate a transfer document reference and proceed through review. Submission, approval, and rejection events are communicated to appropriate users through notifications and remain visible in relevant activity history.'),
subsection('4.6 Returns and replacements'), P('Returns are created against the original sale using sales reference and date-aware lookup. The operator records returned quantity, condition, and refund data. Good, damaged, and refund-only outcomes affect stock differently; replacements also follow an approval-aware process to avoid premature stock or financial changes.'), PageBreak()]

story += [section('5. Business Rules and Controls'),
table(['Rule','Operational meaning'], [
['Physical stock versus allocation','Physical stock and channel allocation balances are separate records. Reconciliation should consider both, especially for non-Walk-In channels.'],
['Good return','A good return restores physical stock. For a non-Walk-In sale, it also restores the allocation associated with the original sales channel.'],
['Damaged return','A damaged return restores physical stock only; it is not returned to a sellable channel allocation.'],
['Refund-only return','A refund-only outcome does not restore physical stock or channel allocation.'],
['Replacement approval','Pending replacements do not change stock or totals. Approval returns the original item, deducts the replacement item, records movements, and recalculates the affected transaction.'],
['Branch scope','A user’s hub assignment determines which branch context the user may access. The model uses one primary hub assignment per user.'],
['File workflow','Product import and export requests are submitted and reviewed through a controlled request process; the file worker performs queued processing.'],
['Auditability','Staff actions, sales, payment records, returns, replacements, and inventory movements are retained for reconciliation and historical review.'],
], [48*mm,108*mm]), Spacer(1,8),
P('<b>Example stock sequence:</b> Allocate 10 units to a channel; sell 1 unit, leaving 9 allocated; return the original item in good condition, restoring the allocation to 10; issue a replacement, reducing it to 9. This sequence ensures the replacement is not treated as extra inventory.'), PageBreak()]

story += [section('6. Reports, Notifications, and Audit Trail'),
subsection('Reporting'), P('Sales reporting is available through authorized report screens and is tailored to channel and hub context. Branch Walk-In reporting uses branch-focused daily labels and metrics, including daily transactions, daily total sales, daily total discounts, purchased-item totals, payment method detail, and the relevant date. Head Office behavior remains distinct from branch reporting where required.'),
subsection('Notifications'), P('The notification center records workflow alerts such as product-file events, transfer submission, approval, and rejection. Users can mark notifications as read, view paginated history, and follow authorized links to the associated workflow. Notification retention is configured so read notifications are eventually removed while unread items remain available.'),
subsection('Audit and reconciliation'), P('Inventory transactions and staff activity logs provide the operational evidence needed to investigate discrepancies. Daily reconciliation should compare physical counts, recorded movements, allocated channel stock, approved sales, pending sales, returns, and transfer status. Investigate exceptions before submitting manual stock corrections.'),
subsection('Recommended daily close'), bullet('Review pending sales and unresolved transfer or replacement requests.'), bullet('Confirm non-cash payment proofs and transaction references are present.'), bullet('Compare channel allocation balances with expected sales and approved returns.'), bullet('Review transaction logs for duplicate, rejected, or unusual movements.'), bullet('Export or retain management reports according to the company’s record-retention policy.'), PageBreak()]

story += [section('7. Technical Architecture'),
table(['Layer','Implementation'], [['Application','Laravel 12 application written in PHP 8.2 or later.'], ['User interface','Server-rendered web application with Vite-managed front-end assets.'], ['Authentication','Laravel authentication with role- and gate-based authorization.'], ['Database','SQLite for local/test use; supported production database such as MySQL.'], ['Background work','Dedicated inventory queue connection; product-file work uses the product-files queue.'], ['File storage','Laravel storage for private/public attachments such as payment proofs and related files.'], ['Testing','PHP feature tests use isolated in-memory SQLite; front-end build and test tooling is available through npm.']], [43*mm,113*mm]),
subsection('Operational services'), P('Production requires a web server, database, application storage, a supervised queue worker, and a scheduler. The queue worker must be restarted after deployment. The scheduler should run Laravel scheduled tasks every minute so retention and operational jobs can execute.'),
subsection('Important limitations'), P('Automated tests are valuable but do not replace staging validation on the production database engine, concurrent-user testing, or real branch pilot verification. Broad regression failures or workflows changed after a focused test require follow-up testing before declaring end-to-end readiness.'), PageBreak()]

story += [section('8. Deployment, Backup, and Operations'),
subsection('Production deployment checklist'), bullet('Configure production environment values, a unique application key, HTTPS, and APP_DEBUG=false.'), bullet('Install PHP and Node dependencies, build assets, and run reviewed database migrations in a low-traffic deployment window.'), bullet('Ensure storage and bootstrap cache directories are writable by the application account.'), bullet('Start a supervised worker for the inventory connection and product-files queue; verify it restarts after host or deployment events.'), bullet('Run the inventory health check and review failed jobs before retrying any file import or approval process.'),
subsection('Backup and recovery'), P('Back up the database and application-upload directories together. Include private and public storage, protect backup encryption keys and database credentials, keep copies off-host, apply access restrictions, and periodically perform a restoration drill in a non-production environment. The database backup alone is insufficient when payment proofs, attachments, or files are stored separately.'),
subsection('Data retention'), P('Read notifications and completed/rejected product-file request artifacts are subject to retention rules. Transactional business records such as sales, payments, returns, replacements, staff activity, and inventory movements require a deliberate archive strategy. Retention periods should be confirmed with company management and professional accounting or legal advisers.'), PageBreak()]

story += [section('9. User Acceptance and Go-Live Checklist'), P('Before production launch, complete a controlled pilot using representative data and real user roles. Record issues, assign owners, and resolve critical discrepancies before expanding access to more branches or channels.'),
table(['Area','Acceptance check'], [['Access','Each role can use only its approved screens and is denied direct access to restricted routes.'], ['Catalog','Products, brands, units, departments, groups, and branch assignments display correctly.'], ['Inventory','Add-stock, allocation, restock, transfer, and logs reconcile to expected balances.'], ['Sales','Walk-In, online/channel, and pending-sale workflows produce correct order references and statuses.'], ['Payments','Required proof and references are captured for applicable payment methods.'], ['Returns','Good, damaged, refund-only, and replacement cases apply the correct stock effects.'], ['Reports','Branch and Head Office report labels, dates, totals, discounts, payment methods, and item counts are correct.'], ['Operations','Queue worker, scheduler, backups, and restore procedure have been verified.'], ['Security','HTTPS, production environment settings, access status, and backup access controls are confirmed.']], [38*mm,118*mm]), Spacer(1,12),
section('Appendix: Support Runbook'), P('For day-to-day assistance, begin with the user role, store hub, sales reference or transfer document reference, time of incident, and screenshots where available. Review the related transaction log, staff activity log, notification, and sales/return records before attempting a correction. Preserve evidence and use authorized workflows; avoid direct database edits except through an approved technical recovery procedure.'),
P('Document prepared by <b>Jao Lacatan</b> for Art Caravan PH. Version 1.0 - October 2026.', 'Small')]

doc.build(story, onFirstPage=footer, onLaterPages=footer)
print(OUT)
