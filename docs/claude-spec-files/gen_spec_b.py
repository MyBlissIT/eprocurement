
# Part B: Sections 1-5 (Exec Summary, Decisions, Architecture, Roles, DB Schema)
f = open("C:/Users/sinet/AI Guy/eprocurement-spec.html", "a", encoding="utf-8")

# S1
f.write("""
<div class="wrap pb" id="s1">
<h1 class="sec">1. Executive Summary</h1>
<p>The <strong>eProcurement Plugin</strong> is a WordPress plugin functioning as a <strong>mini-CRM for procurement processes</strong>. It manages the full lifecycle of a tender/bid notice from publication to closure, providing a structured communication channel between procurement officials and prospective bidders. It is <em>not</em> a bid submission system &mdash; bidders communicate and download documents only.</p>
<h2 class="sub">Core Capabilities</h2>
<table>
<tr><th>Function</th><th>Who</th><th>Description</th></tr>
<tr><td>Publish Bid Notices</td><td>SCM Manager / Official</td><td>Create tender records with bid number, dates, metadata, contacts, and status</td></tr>
<tr><td>Attach Supporting Documents</td><td>SCM Manager / Official</td><td>Multiple files per bid (specs, BOQ, addenda) via WordPress Media Library</td></tr>
<tr><td>Download Documents</td><td>Any visitor (guest or registered)</td><td>Document downloads are open to everyone &mdash; no login required. All downloads are logged (with user_id if logged in, IP address if guest).</td></tr>
<tr><td>Send Queries (Private)</td><td>Registered Bidders</td><td>Direct query to SCM or Technical contact &mdash; only sender and contact see it</td></tr>
<tr><td>Post Public Q&amp;A</td><td>Registered Bidders</td><td>Bidder marks query Public &mdash; all registered bidders see the Q&amp;A on the bid page</td></tr>
<tr><td>Reply to Queries</td><td>Unit Manager / SCM Staff</td><td>Assigned contacts respond through the plugin inbox; email notification sent to bidder</td></tr>
<tr><td>Audit Trail</td><td>SCM Manager</td><td>Full log of all downloads and conversations per bid, exportable</td></tr>
</table>
<div class="cl ok"><strong>Design Principle:</strong> This is a communication and document distribution tool. Bidders interact with the process but never submit bid responses through the plugin. All formal submissions happen offline per procurement procedure.</div>
</div>
""")

# S2
f.write("""
<div class="wrap pb" id="s2">
<h1 class="sec">2. Confirmed Design Decisions</h1>
<table>
<tr><th>#</th><th>Topic</th><th>Decision</th><th>Implication</th></tr>
<tr><td>1</td><td>Conversation Visibility</td><td>Bidder chooses <strong>Public (Q&amp;A)</strong> or <strong>Private</strong> per query</td><td>Public threads visible to all registered bidders on the bid detail page. Private threads visible only to sender and assigned contact.</td></tr>
<tr><td>2</td><td>Account Activation</td><td><strong>Email verification</strong> required &mdash; no manual admin approval</td><td>Plugin sends a verification link on registration. Account inactive until clicked. Self-service, automated.</td></tr>
<tr><td>3</td><td>Registration Gate</td><td>Registration required only to <strong>send queries / interact</strong>. Downloads are <strong>open to all guests</strong>.</td><td>Tender listing, detail pages, and document downloads are fully public &mdash; no login needed. Registration and email verification are only enforced when a visitor tries to send a query or post a public Q&amp;A.</td></tr>
<tr><td>4</td><td>Unit Manager Replies</td><td>Unit Managers <strong>can reply</strong> to queries</td><td>Unit Manager role has an inbox view and reply capability but cannot manage documents or bidder accounts.</td></tr>
<tr><td>5</td><td>Publish / Close Rights</td><td>SCM Manager, SCM Official, WordPress Admin, and WordPress Editor</td><td>Custom plugin capabilities mapped to these four roles. No other roles can publish or close bids.</td></tr>
<tr><td>6</td><td>Document Versioning</td><td><strong>No versioning</strong> &mdash; files are additive only</td><td>Supporting documents are added to a bid, never replaced. Each file has an optional label. Old files are never deleted by the system.</td></tr>
<tr><td>7</td><td>Supporting Documents</td><td>Each bid can have <strong>multiple supporting files</strong></td><td>A junction table links bid to media library uploads. Each file has a sort order and display label (e.g., &ldquo;Bill of Quantities&rdquo;, &ldquo;Site Map&rdquo;).</td></tr>
</table>
</div>
""")

# S3
f.write("""
<div class="wrap pb" id="s3">
<h1 class="sec">3. Plugin Architecture Overview</h1>
<h2 class="sub">Query Lifecycle Flow</h2>
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;background:#f7f9fc;padding:16px;border-radius:6px;border:1px solid #ddd;margin-bottom:14px">
  <div style="background:#fff;border:2px solid #2980b9;border-radius:4px;padding:6px 10px;font-size:11px;color:#1e3a5f;font-weight:600;text-align:center">Bidder views<br>Bid Detail</div>
  <span style="color:#2980b9;font-size:18px">&rarr;</span>
  <div style="background:#fff;border:2px solid #2980b9;border-radius:4px;padding:6px 10px;font-size:11px;color:#1e3a5f;font-weight:600;text-align:center">Clicks<br>Send Query</div>
  <span style="color:#2980b9;font-size:18px">&rarr;</span>
  <div style="background:#fff;border:2px solid #2980b9;border-radius:4px;padding:6px 10px;font-size:11px;color:#1e3a5f;font-weight:600;text-align:center">Chooses contact<br>+ Public/Private</div>
  <span style="color:#2980b9;font-size:18px">&rarr;</span>
  <div style="background:#fff;border:2px solid #2980b9;border-radius:4px;padding:6px 10px;font-size:11px;color:#1e3a5f;font-weight:600;text-align:center">Thread created<br>in DB</div>
  <span style="color:#2980b9;font-size:18px">&rarr;</span>
  <div style="background:#fff;border:2px solid #2980b9;border-radius:4px;padding:6px 10px;font-size:11px;color:#1e3a5f;font-weight:600;text-align:center">Email notification<br>to contact</div>
  <span style="color:#2980b9;font-size:18px">&rarr;</span>
  <div style="background:#fff;border:2px solid #27ae60;border-radius:4px;padding:6px 10px;font-size:11px;color:#27ae60;font-weight:600;text-align:center">Contact replies<br>via plugin</div>
  <span style="color:#27ae60;font-size:18px">&rarr;</span>
  <div style="background:#fff;border:2px solid #27ae60;border-radius:4px;padding:6px 10px;font-size:11px;color:#27ae60;font-weight:600;text-align:center">Bidder gets<br>email + sees reply</div>
</div>
<h2 class="sub">Core Classes</h2>
<table>
<tr><th>Class</th><th>File</th><th>Responsibility</th></tr>
<tr><td><code>Eprocurement_Activator</code></td><td>class-activator.php</td><td>DB tables, roles, default options on plugin activation</td></tr>
<tr><td><code>Eprocurement_Roles</code></td><td>class-roles.php</td><td>Defines 4 custom WP roles and maps capabilities</td></tr>
<tr><td><code>Eprocurement_Database</code></td><td>class-database.php</td><td>Shared query methods, dbDelta schema management</td></tr>
<tr><td><code>Eprocurement_Documents</code></td><td>class-documents.php</td><td>CRUD for bids, metadata, status transitions, supporting file management</td></tr>
<tr><td><code>Eprocurement_Contact_Persons</code></td><td>class-contact-persons.php</td><td>SCM and Technical contacts linked to bid records</td></tr>
<tr><td><code>Eprocurement_Messaging</code></td><td>class-messaging.php</td><td>Thread creation, message storage, public/private visibility rules</td></tr>
<tr><td><code>Eprocurement_Bidder</code></td><td>class-bidder.php</td><td>Registration, email verification token, extended bidder profiles</td></tr>
<tr><td><code>Eprocurement_Downloads</code></td><td>class-downloads.php</td><td>Logs every download with user, document, timestamp, IP</td></tr>
<tr><td><code>Eprocurement_Storage_Interface</code></td><td>class-storage-interface.php</td><td>Abstract class: upload(), get_download_url(), delete()</td></tr>
<tr><td><code>Eprocurement_Google_Drive</code></td><td>storage/class-google-drive.php</td><td>Google Drive implementation (OAuth 2.0, Google API Client)</td></tr>
<tr><td><code>Eprocurement_OneDrive</code></td><td>storage/class-onedrive.php</td><td>OneDrive implementation (OAuth 2.0, Microsoft Graph API)</td></tr>
<tr><td><code>Eprocurement_Dropbox</code></td><td>storage/class-dropbox.php</td><td>Dropbox implementation (OAuth 2.0, Dropbox API v2)</td></tr>
<tr><td><code>Eprocurement_S3_Storage</code></td><td>storage/class-s3.php</td><td>S3-compatible implementation (AWS SDK, pre-signed URLs)</td></tr>
<tr><td><code>Eprocurement_Notifications</code></td><td>class-notifications.php</td><td>All wp_mail() notification emails for every system event</td></tr>
<tr><td><code>Eprocurement_REST_API</code></td><td>class-rest-api.php</td><td>Registers /wp-json/eprocurement/v1/ endpoints for frontend</td></tr>
<tr><td><code>Eprocurement_Admin</code></td><td>admin/class-admin.php</td><td>WP admin menus, admin asset enqueue, admin page rendering</td></tr>
<tr><td><code>Eprocurement_Public</code></td><td>public/class-public.php</td><td>Shortcode [eprocurement], frontend assets, public page rendering</td></tr>
</table>
</div>
""")

# S4 - ROLES
f.write("""
<div class="wrap pb" id="s4">
<h1 class="sec">4. User Roles &amp; Capabilities Matrix</h1>
<div class="cl"><strong>Note on WP Admin &amp; Editor:</strong> WordPress Super Admin and Editor inherit publish/close rights for bids alongside the custom roles below. The Super Admin retains full plugin management rights.</div>
<table>
<tr>
  <th>Capability</th>
  <th style="text-align:center">Super Admin</th>
  <th style="text-align:center">SCM Manager</th>
  <th style="text-align:center">SCM Official</th>
  <th style="text-align:center">Unit Manager</th>
  <th style="text-align:center">Subscriber (Bidder)</th>
</tr>
<tr><td><strong>Access WP Admin area</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Install / delete plugin</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Manage plugin settings</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Create / edit bid documents</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Publish / close bids</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Upload supporting documents</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Manage contact persons</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>View all query threads</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Reply to queries (inbox)</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>View bidder accounts</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>View download audit log</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td></tr>
<tr><td><strong>Register on frontend</strong></td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&#10003;</td></tr>
<tr><td><strong>Download bid documents</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003; (no login needed)</td></tr>
<tr><td><strong>Send queries to contacts</strong></td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&#10003; (verified)</td></tr>
<tr><td><strong>Mark query Public / Private</strong></td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&mdash;</td><td style="text-align:center">&#10003;</td></tr>
<tr><td><strong>View public Q&amp;A on bids</strong></td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003;</td><td style="text-align:center">&#10003; (registered)</td></tr>
</table>
</div>
""")

# S5 - DB SCHEMA
f.write("""
<div class="wrap pb" id="s5">
<h1 class="sec">5. Database Schema (10 Tables)</h1>
<div class="cl"><strong>Convention:</strong> All tables use the WordPress table prefix (e.g., <code>wp_eproc_</code>). IDs are <code>BIGINT(20) UNSIGNED</code>. Foreign keys use <span class="fk">FK</span> notation. <span class="pk">PK</span> = Primary Key. <span class="uq">UQ</span> = Unique.</div>
""")

tables = [
    ("wp_eproc_documents", "Core bid/tender records", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("bid_number","VARCHAR(100)","Unique bid reference e.g. RFQ/2025/001","uq"),
        ("title","VARCHAR(255)","Official tender title",""),
        ("description","LONGTEXT","Full description / scope of work (supports inline JPEG/PNG images &mdash; rich text editor)",""),
        ("status","ENUM","draft | published | open | closed | cancelled | archived",""),
        ("scm_contact_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_contact_persons","fk"),
        ("technical_contact_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_contact_persons (nullable)","fk"),
        ("opening_date","DATETIME","Date tender opens",""),
        ("briefing_date","DATETIME","Compulsory/optional briefing (nullable)",""),
        ("closing_date","DATETIME","Submission deadline (display only)",""),
        ("created_by","BIGINT UNSIGNED","FK &rarr; wp_users","fk"),
        ("created_at","DATETIME","AUTO",""),
        ("updated_at","DATETIME","AUTO on update",""),
    ]),
    ("wp_eproc_contact_persons", "SCM and Technical contact profiles", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("user_id","BIGINT UNSIGNED","FK &rarr; wp_users (nullable &mdash; must be WP user to reply)","fk"),
        ("type","ENUM","scm | technical",""),
        ("name","VARCHAR(255)","Full name",""),
        ("phone","VARCHAR(50)","Click-to-call number",""),
        ("email","VARCHAR(255)","Direct email address",""),
        ("department","VARCHAR(255)","Department or unit (nullable)",""),
        ("created_at","DATETIME","AUTO",""),
    ]),
    ("wp_eproc_supporting_docs", "Files attached to a bid (cloud-stored)", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("document_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_documents","fk"),
        ("file_name","VARCHAR(255)","Original filename as uploaded",""),
        ("file_size","BIGINT UNSIGNED","File size in bytes",""),
        ("file_type","VARCHAR(100)","MIME type (application/pdf, etc.)",""),
        ("cloud_provider","VARCHAR(50)","google_drive | onedrive | dropbox | s3",""),
        ("cloud_key","VARCHAR(500)","Provider file ID or S3 object key",""),
        ("cloud_url","VARCHAR(500)","Full URL or path in cloud storage",""),
        ("label","VARCHAR(255)","Display label e.g. &ldquo;Bill of Quantities&rdquo;",""),
        ("sort_order","INT","Display order on bid page",""),
        ("uploaded_by","BIGINT UNSIGNED","FK &rarr; wp_users","fk"),
        ("created_at","DATETIME","AUTO",""),
    ]),
    ("wp_eproc_compliance_docs", "Static compliance document library (cloud-stored)", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("file_name","VARCHAR(255)","Original filename",""),
        ("file_size","BIGINT UNSIGNED","File size in bytes",""),
        ("file_type","VARCHAR(100)","MIME type",""),
        ("cloud_provider","VARCHAR(50)","google_drive | onedrive | dropbox | s3",""),
        ("cloud_key","VARCHAR(500)","Provider file ID or S3 object key",""),
        ("cloud_url","VARCHAR(500)","Full cloud URL",""),
        ("label","VARCHAR(255)","Display label e.g. &ldquo;BBBEE Declaration Form&rdquo;",""),
        ("description","TEXT","Optional short description of the document",""),
        ("sort_order","INT","Display order on compliance page",""),
        ("uploaded_by","BIGINT UNSIGNED","FK &rarr; wp_users","fk"),
        ("created_at","DATETIME","AUTO",""),
    ]),
    ("wp_eproc_threads", "Query / conversation threads per bid", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("document_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_documents","fk"),
        ("bidder_id","BIGINT UNSIGNED","FK &rarr; wp_users (Subscriber)","fk"),
        ("contact_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_contact_persons","fk"),
        ("subject","VARCHAR(255)","Auto: Query: [Bid Number] &mdash; [Title]",""),
        ("visibility","ENUM","private | public",""),
        ("status","ENUM","open | resolved | closed",""),
        ("created_at","DATETIME","AUTO",""),
        ("updated_at","DATETIME","AUTO on update",""),
    ]),
    ("wp_eproc_messages", "Individual messages within a thread", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("thread_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_threads","fk"),
        ("sender_id","BIGINT UNSIGNED","FK &rarr; wp_users","fk"),
        ("message","LONGTEXT","Message body (sanitized HTML allowed)",""),
        ("is_read","TINYINT(1)","0 = unread, 1 = read",""),
        ("created_at","DATETIME","AUTO",""),
    ]),
    ("wp_eproc_message_attachments", "Files attached to query/reply messages (cloud-stored, max 5MB)", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("message_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_messages","fk"),
        ("file_name","VARCHAR(255)","Original filename",""),
        ("file_size","BIGINT UNSIGNED","File size in bytes (max 5242880 = 5MB)",""),
        ("file_type","VARCHAR(100)","MIME type",""),
        ("cloud_provider","VARCHAR(50)","google_drive | onedrive | dropbox | s3",""),
        ("cloud_key","VARCHAR(500)","Provider file ID or S3 object key",""),
        ("cloud_url","VARCHAR(500)","Full cloud URL",""),
        ("created_at","DATETIME","AUTO",""),
    ]),
    ("wp_eproc_downloads", "Audit log of every file download", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("document_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_documents","fk"),
        ("supporting_doc_id","BIGINT UNSIGNED","FK &rarr; wp_eproc_supporting_docs (nullable)","fk"),
        ("user_id","BIGINT UNSIGNED","FK &rarr; wp_users (nullable &mdash; NULL for guest downloads)","fk"),
        ("ip_address","VARCHAR(45)","IPv4 or IPv6 address",""),
        ("user_agent","VARCHAR(255)","Browser user-agent string (guest identification)",""),
        ("downloaded_at","DATETIME","AUTO",""),
    ]),
    ("wp_eproc_bidder_profiles", "Extended profile for Subscriber/Bidder accounts", [
        ("id","BIGINT UNSIGNED","PK AUTO_INCREMENT","pk"),
        ("user_id","BIGINT UNSIGNED","FK &rarr; wp_users","fk"),
        ("company_name","VARCHAR(255)","Bidding entity name",""),
        ("company_reg","VARCHAR(100)","Registration number (nullable)",""),
        ("phone","VARCHAR(50)","Contact number",""),
        ("verified","TINYINT(1)","0 = pending, 1 = email verified",""),
        ("verification_token","VARCHAR(255)","One-time email verification token",""),
        ("created_at","DATETIME","AUTO",""),
    ]),
]

for tname, tdesc, cols in tables:
    f.write(f'<div class="db"><div class="db-h"><span style="font-family:monospace">{tname}</span><span>{tdesc}</span></div>\n')
    for col in cols:
        cname, ctype, cnote, badge = col
        badge_html = ""
        if badge == "pk": badge_html = '<span class="pk">PK</span>'
        elif badge == "fk": badge_html = '<span class="fk">FK</span>'
        elif badge == "uq": badge_html = '<span class="uq">UQ</span>'
        f.write(f'<div class="db-r"><div class="cn">{cname}{badge_html}</div><div class="ct">{ctype}</div><div class="cno">{cnote}</div></div>\n')
    f.write('</div>\n')

f.write("</div>\n")
f.close()
print("Part B done - sections 1-5 written.")
