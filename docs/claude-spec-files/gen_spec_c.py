
# Part C: Section 6 (Feature Specs) + Frontend Wireframes (Section 7)
f = open("C:/Users/sinet/AI Guy/eprocurement-spec.html", "a", encoding="utf-8")

# S6
f.write("""
<div class="wrap pb" id="s6">
<h1 class="sec">6. Feature Specifications</h1>

<h2 class="sub">6.1 Bid Document Management</h2>
<p>Each bid record contains: Bid Number (unique), Title, Description, Status, SCM Contact, Technical Contact (optional), Opening Date, Briefing Date, Closing Date. Status transitions follow a defined workflow.</p>
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin:10px 0">
  <div style="background:#888;color:#fff;padding:4px 12px;border-radius:3px;font-size:11px;font-weight:700">DRAFT</div>
  <span style="color:#aaa">&rarr;</span>
  <div style="background:#2980b9;color:#fff;padding:4px 12px;border-radius:3px;font-size:11px;font-weight:700">PUBLISHED</div>
  <span style="color:#aaa">&rarr;</span>
  <div style="background:#27ae60;color:#fff;padding:4px 12px;border-radius:3px;font-size:11px;font-weight:700">OPEN</div>
  <span style="color:#aaa">&rarr;</span>
  <div style="background:#e74c3c;color:#fff;padding:4px 12px;border-radius:3px;font-size:11px;font-weight:700">CLOSED</div>
</div>
<div class="cl warn"><strong>Cancelled</strong> is also a valid terminal status. An SCM Manager or Official can cancel a bid at any stage before Closed.</div>
<div class="cl"><strong>Closed Bid Retention:</strong> An admin setting controls how long closed bids remain visible: <code>Auto-remove closed bids after ___ days (leave blank = keep forever)</code>. A daily WP-Cron job checks for expired closed bids and soft-deletes them (sets status to <code>archived</code>). Archived bids are hidden from the frontend but remain in the database for audit purposes.</div>

<h2 class="sub">6.2 Supporting Documents per Bid (Cloud Storage &mdash; Multi-Provider)</h2>
<p>Each bid can have unlimited supporting files. Files are uploaded through the plugin admin and <strong>stored on external cloud storage</strong>. The local WordPress server is never used for permanent file storage &mdash; this prevents server overload and keeps page load times fast.</p>

<div style="font-size:11px;font-weight:700;color:#1e3a5f;margin:10px 0 6px">Supported Cloud Storage Providers</div>
<table>
<tr><th>Provider</th><th>Auth Method</th><th>How Downloads Work</th><th>Setup Complexity</th></tr>
<tr><td><strong>Google Drive</strong></td><td>OAuth 2.0 (Google Cloud Console)</td><td>Plugin generates a time-limited shareable link; file served from Google</td><td>Medium &mdash; requires Google Cloud project + OAuth consent screen</td></tr>
<tr><td><strong>Microsoft OneDrive</strong></td><td>OAuth 2.0 (Microsoft Azure / Graph API)</td><td>Plugin generates a sharing link via Graph API; file served from OneDrive</td><td>Medium &mdash; requires Azure App Registration</td></tr>
<tr><td><strong>Dropbox</strong></td><td>OAuth 2.0 (Dropbox App Console)</td><td>Plugin generates a temporary download link via Dropbox API</td><td>Medium &mdash; requires Dropbox App</td></tr>
<tr><td><strong>S3-Compatible</strong> (AWS S3, DigitalOcean Spaces, Backblaze B2, MinIO)</td><td>Access Key + Secret Key</td><td>Plugin generates a time-limited <strong>pre-signed URL</strong>; file served directly from bucket</td><td>Simple &mdash; key-based, no OAuth</td></tr>
</table>

<div style="font-size:11px;font-weight:700;color:#1e3a5f;margin:14px 0 6px">Upload &amp; Download Flow</div>
<table>
<tr><th>Step</th><th>What Happens</th></tr>
<tr><td>Upload</td><td>Admin uploads file via the plugin. File is sent to the active cloud provider. A <code>cloud_provider</code>, <code>cloud_key</code> (file identifier/path), and <code>cloud_url</code> are stored in the database.</td></tr>
<tr><td>Display</td><td>Bid detail page shows file label, size, and a Download button. No file is stored on the WordPress server.</td></tr>
<tr><td>Download</td><td>Plugin requests a <strong>time-limited download URL</strong> from the active provider, logs the download event, then redirects the user. File is served directly from the cloud &mdash; zero load on WordPress.</td></tr>
<tr><td>Configuration</td><td>Admin settings page: select provider, enter credentials (OAuth or API keys). All credentials stored as <strong>encrypted WordPress options</strong>. OAuth tokens auto-refresh.</td></tr>
</table>

<div style="font-size:11px;font-weight:700;color:#1e3a5f;margin:14px 0 6px">Storage Abstraction Architecture</div>
<p>The plugin uses a <strong>Storage Interface</strong> pattern &mdash; one abstract class defining <code>upload()</code>, <code>get_download_url()</code>, and <code>delete()</code> methods, with 4 concrete provider classes. The admin selects a provider in Settings; the rest of the plugin is provider-agnostic.</p>
<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;background:#f7f9fc;padding:14px;border-radius:6px;border:1px solid #ddd;margin:8px 0">
  <div style="background:#1e3a5f;color:#fff;padding:6px 12px;border-radius:4px;font-size:11px;font-weight:600;text-align:center">Eprocurement_Storage_Interface<br><span style="font-size:9px;font-weight:400">upload() | get_download_url() | delete()</span></div>
  <span style="color:#2980b9;font-size:18px">&larr;</span>
  <div style="display:flex;flex-direction:column;gap:4px">
    <div style="background:#4285f4;color:#fff;padding:3px 10px;border-radius:3px;font-size:10px">Google_Drive_Storage</div>
    <div style="background:#0078d4;color:#fff;padding:3px 10px;border-radius:3px;font-size:10px">OneDrive_Storage</div>
    <div style="background:#0061fe;color:#fff;padding:3px 10px;border-radius:3px;font-size:10px">Dropbox_Storage</div>
    <div style="background:#ff9900;color:#fff;padding:3px 10px;border-radius:3px;font-size:10px">S3_Compatible_Storage</div>
  </div>
</div>
<div class="cl warn"><strong>Important:</strong> Only one provider is active at a time. If the admin switches providers, existing files remain on the old provider. A migration tool is out of scope for v1 but can be added later. The <code>cloud_provider</code> field on each file record tracks which provider stores it, so mixed-provider scenarios work correctly.</div>

<h2 class="sub">6.2.1 Compliance Documents (Static Library)</h2>
<p>Separate from bid-specific files, the plugin provides a <strong>Compliance Documents</strong> section accessible from the frontend navigation bar. This is a library of standard compliance documents (e.g., BBBEE forms, tax clearance templates, declaration forms) that apply to all bids. Admins upload and manage these once.</p>
<table>
<tr><th>Feature</th><th>Detail</th></tr>
<tr><td>Section title</td><td>Admin can customise the display title (default: &ldquo;Compliance Documents&rdquo;). Can be renamed to e.g. &ldquo;Standard Forms&rdquo;, &ldquo;Required Documents&rdquo;, etc.</td></tr>
<tr><td>Upload</td><td>Admin uploads files via the same cloud storage pipeline. Each file has a label and optional description.</td></tr>
<tr><td>Frontend display</td><td>A dedicated page linked in the main navigation: list of documents with label, description, and download button.</td></tr>
<tr><td>Access</td><td>Publicly downloadable (same as bid documents &mdash; no login required).</td></tr>
<tr><td>Management</td><td>SCM Manager and SCM Official can add, reorder, and remove compliance documents via WP Admin &rarr; eProcurement &rarr; Compliance Documents.</td></tr>
</table>

<h2 class="sub">6.3 Contact Persons (SCM + Technical)</h2>
<p>Each bid can have one SCM contact and one Technical contact. Contacts must be linked to a WordPress user account so they can log in and reply to queries. Their profile cards are displayed on the bid detail page showing: Name, Role type, Click-to-call phone number, and a Query button that opens a query form pre-addressed to that contact.</p>

<h2 class="sub">6.4 Bidder Registration &amp; Email Verification</h2>
<div class="cl ok"><strong>Key rule:</strong> Document downloads are open to everyone &mdash; no login required. Registration and email verification are only required to <strong>send queries</strong> or <strong>post public Q&amp;A</strong>.</div>
<table>
<tr><th>Step</th><th>Action</th><th>System Behaviour</th></tr>
<tr><td>1</td><td>Guest visits bid detail and downloads files</td><td>Files are available without login. Download is logged with IP address (user_id = NULL).</td></tr>
<tr><td>2</td><td>Guest clicks &ldquo;Send Query&rdquo; or &ldquo;Ask Public Question&rdquo;</td><td>System prompts: &ldquo;Please register or log in to send queries.&rdquo;</td></tr>
<tr><td>3</td><td>Bidder fills registration form</td><td>Form captures: Name, Company, Reg No., Phone, Email, Password</td></tr>
<tr><td>4</td><td>Form submitted</td><td>Account created with status <code>unverified</code>. Verification token generated and stored.</td></tr>
<tr><td>5</td><td>Verification email sent</td><td>wp_mail() sends email with unique link: <code>/tenders/verify/?token=xxxx</code></td></tr>
<tr><td>6</td><td>Bidder clicks link</td><td>Token matched, account set to <code>verified</code>, bidder redirected to dashboard</td></tr>
<tr><td>7</td><td>Bidder tries to query before verifying</td><td>System shows: &ldquo;Please verify your email address before sending queries.&rdquo;</td></tr>
</table>

<h2 class="sub">6.5 Query &amp; Messaging System</h2>
<p>A <strong>thread</strong> is created per query. One bidder, one contact person, one bid. The subject is auto-generated: <strong>Query: [Bid Number] &mdash; [Bid Title]</strong>. The bidder selects <strong>Public</strong> or <strong>Private</strong> visibility before submitting.</p>
<div class="cl ok"><strong>Attachments:</strong> Both queries and replies support file attachments up to <strong>5MB per file</strong>. Supported types: PDF, DOCX, XLSX, JPG, PNG. Files are uploaded to cloud storage (same S3 bucket). Email notifications include the attachment or a download link.</div>
<div class="cl"><strong>Rich Description:</strong> The bid description field uses a rich text editor (WordPress TinyMCE/Block Editor) that supports <strong>inline JPEG/PNG images</strong>. Admins can paste screenshots directly into the description, which are uploaded to cloud storage and displayed inline on the bid detail page. This enables visual specifications, diagrams, and annotated screenshots to accompany bid text.</div>
<table>
<tr><th>Visibility</th><th>Who Can See It</th><th>Where It Appears</th></tr>
<tr><td><strong>Private</strong></td><td>Only the querying bidder and the assigned contact person</td><td>Bidder dashboard inbox + Admin messaging inbox only</td></tr>
<tr><td><strong>Public (Q&amp;A)</strong></td><td>All registered (verified) bidders + all staff</td><td>Bid detail page Q&amp;A section + Admin inbox</td></tr>
</table>
<div class="cl"><strong>Email notifications:</strong> (1) Contact person notified when new query arrives. (2) Bidder notified when reply is posted. (3) If thread is public, no mass notification to other bidders &mdash; they see it when they visit the bid page.</div>

<h2 class="sub">6.6 Download Audit Log</h2>
<p>Every file download records: user_id, document_id, supporting_doc_id (which specific file), IP address, and timestamp. SCM Manager can view and export this log per bid. This satisfies procurement audit requirements without building a complex document tracking system.</p>

<h2 class="sub">6.7 Email Notification Events</h2>
<table>
<tr><th>Event</th><th>Recipient</th><th>Subject Template</th></tr>
<tr><td>New bidder registration</td><td>Bidder</td><td>Verify your eProcurement account</td></tr>
<tr><td>New query submitted</td><td>Assigned contact person</td><td>New Query: [Bid Number] &mdash; [Title]</td></tr>
<tr><td>Reply posted to query</td><td>Bidder who sent query</td><td>Reply to your query: [Bid Number]</td></tr>
<tr><td>New bid published</td><td>All verified bidders (optional)</td><td>New Tender Published: [Bid Number] &mdash; [Title]</td></tr>
<tr><td>Bid status changed</td><td>SCM Manager (admin digest)</td><td>Bid Status Update: [Bid Number] is now [Status]</td></tr>
</table>
</div>
""")

# S7 - FRONTEND WIREFRAMES
f.write("""
<div class="wrap pb" id="s7">
<h1 class="sec">7. Frontend UI Wireframes</h1>
<p>The frontend is accessible to the public at a WordPress page using the <code>[eprocurement]</code> shortcode. The plugin renders different views based on the URL path and user login state.</p>
""")

# WF1 - Public Tender Listing
f.write("""
<h2 class="sub">Wireframe 1 &mdash; Public Tender Listing Page (Guest &amp; Logged-in)</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span>
    <span class="dot" style="background:#f39c12"></span>
    <span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">https://yoursite.com/tenders/</span>
  </div>
  <div class="wf-lbl">PUBLIC PAGE &mdash; No login required to view listings</div>
  <div class="wf-body">
    <div class="wf-nav">
      <span class="logo">&#128196; eProcurement Portal</span>
      <a href="#">Tenders</a>
      <a href="#">Compliance Documents</a>
      <a href="#">How to Register</a>
      <span class="nbtn">Login</span>
      <span class="nbtn g">Register</span>
    </div>
    <div class="hero">
      <h3>Active Tenders &amp; Bid Opportunities</h3>
      <p>Browse open tenders. Register a free account to download documents and send queries to procurement officials.</p>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:10px">
      <input class="inp" style="margin:0;flex:1" placeholder="&#128269; Search by title, bid number or keyword...">
      <select style="border:1px solid #ccc;border-radius:3px;padding:4px 8px;font-size:10px;background:#fff">
        <option>All Statuses</option><option>Open</option><option>Upcoming</option><option>Closed</option>
      </select>
      <span class="wbtn bb">Filter</span>
    </div>
    <div class="g3">
      <div class="card">
        <span class="tag tg">OPEN</span>
        <h4>RFQ/2025/047 &mdash; Supply of Office Furniture</h4>
        <p>Supply and delivery of ergonomic office furniture for Head Office.</p>
        <div style="font-size:9px;color:#999;border-top:1px solid #eee;padding-top:5px;margin-top:5px">
          Closing: 28 Feb 2025 &bull; SCM: J. Mokoena &bull; 3 docs
        </div>
        <span class="wbtn bb">View Details</span>
      </div>
      <div class="card">
        <span class="tag tb">UPCOMING</span>
        <h4>BID/2025/012 &mdash; IT Infrastructure Upgrade</h4>
        <p>Provision of network hardware, servers, and installation services.</p>
        <div style="font-size:9px;color:#999;border-top:1px solid #eee;padding-top:5px;margin-top:5px">
          Opens: 05 Mar 2025 &bull; SCM: T. Dlamini &bull; 5 docs
        </div>
        <span class="wbtn bgr">View Details</span>
      </div>
      <div class="card">
        <span class="tag tr">CLOSED</span>
        <h4>RFQ/2025/031 &mdash; Security Services</h4>
        <p>Provision of armed guarding services for all company premises.</p>
        <div style="font-size:9px;color:#999;border-top:1px solid #eee;padding-top:5px;margin-top:5px">
          Closed: 10 Jan 2025 &bull; SCM: N. Sithole &bull; 2 docs
        </div>
        <span class="wbtn bgr">View Details</span>
      </div>
    </div>
    <div style="text-align:center;font-size:10px;color:#888;margin-top:6px">Showing 3 of 18 tenders &mdash; <a href="#" style="color:#2980b9">Load more</a></div>
  </div>
</div>
""")

# WF2 - Bid Detail Page
f.write("""
<h2 class="sub">Wireframe 2 &mdash; Bid Detail Page (Guest or Logged-in)</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span>
    <span class="dot" style="background:#f39c12"></span>
    <span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">https://yoursite.com/tenders/rfq-2025-047/</span>
  </div>
  <div class="wf-lbl">BID DETAIL &mdash; Downloads open to all. Query buttons require login.</div>
  <div class="wf-body">
    <div class="wf-nav">
      <span class="logo">&#128196; eProcurement Portal</span>
      <a href="#">&#8592; All Tenders</a>
      <span style="margin-left:auto;font-size:10px;color:#a8c8e8">Logged in: ABC Company (Pty) Ltd</span>
      <span class="nbtn">My Dashboard</span>
    </div>
    <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:12px;margin-bottom:8px">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:8px">
        <div>
          <span class="tag tg" style="font-size:10px">OPEN</span>
          <h3 style="font-size:15px;color:#1e3a5f;margin-top:4px">Supply of Office Furniture</h3>
          <div style="font-size:10px;color:#888;margin-top:3px">Bid Number: <strong>RFQ/2025/047</strong></div>
        </div>
        <div style="font-size:10px;color:#555;text-align:right;line-height:2">
          <div>Opening: <strong>01 Feb 2025</strong></div>
          <div>Briefing: <strong>10 Feb 2025</strong></div>
          <div>Closing: <strong style="color:#e74c3c">28 Feb 2025</strong></div>
        </div>
      </div>
      <p style="font-size:11px;color:#444">Supply and delivery of ergonomic office furniture including chairs, desks, and storage units for the Head Office building. All items must comply with SABS standards.</p>
    </div>
    <div class="g21">
      <div>
        <div style="font-size:10px;font-weight:700;color:#1e3a5f;margin-bottom:6px;text-transform:uppercase">&#128462; Supporting Documents</div>
        <div class="card" style="margin-bottom:6px">
          <table class="wt">
            <tr><th>#</th><th>Document</th><th>Size</th><th>Action</th></tr>
            <tr><td>1</td><td>Bid Specification Document</td><td>2.3 MB</td><td><span class="wbtn bb" style="padding:2px 8px;font-size:9px">&#8659; Download</span></td></tr>
            <tr><td>2</td><td>Bill of Quantities</td><td>540 KB</td><td><span class="wbtn bb" style="padding:2px 8px;font-size:9px">&#8659; Download</span></td></tr>
            <tr><td>3</td><td>Site Visit Register Form</td><td>180 KB</td><td><span class="wbtn bb" style="padding:2px 8px;font-size:9px">&#8659; Download</span></td></tr>
          </table>
        </div>
        <div style="font-size:10px;font-weight:700;color:#1e3a5f;margin:10px 0 6px;text-transform:uppercase">&#128172; Public Q&amp;A</div>
        <div class="card">
          <div style="border:1px solid #ddd;border-radius:4px;padding:8px;margin-bottom:6px;background:#fafafa">
            <div style="font-size:10px;font-weight:700;color:#1e3a5f">Q: Are imported furniture items acceptable?</div>
            <div style="font-size:9px;color:#888;margin-bottom:4px">Asked by: Registered Bidder &bull; 12 Feb 2025</div>
            <div style="font-size:10px;color:#27ae60;font-weight:700">A: Yes, provided SABS equivalent certification is submitted with the bid.</div>
            <div style="font-size:9px;color:#888">Answered by: J. Mokoena (SCM) &bull; 14 Feb 2025</div>
          </div>
          <span class="wbtn bb">Ask a Public Question</span>
          <span class="wbtn" style="background:#8e44ad">Send Private Query</span>
        </div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:#1e3a5f;margin-bottom:6px;text-transform:uppercase">&#128100; Contact Persons</div>
        <div class="cc" style="margin-bottom:8px">
          <span class="ctype scm">SCM Contact</span>
          <div class="cname">Jabu Mokoena</div>
          <div class="cd">
            &#128222; <a href="tel:+27110000001">011 000 0001</a><br>
            &#9993; <a href="#">Send Query</a>
          </div>
        </div>
        <div class="cc">
          <span class="ctype tech">Technical Contact</span>
          <div class="cname">Sipho Ndlovu</div>
          <div class="cd">
            &#128222; <a href="tel:+27110000002">011 000 0002</a><br>
            &#9993; <a href="#">Send Query</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
""")

# WF3 - Query Form Modal
f.write("""
<h2 class="sub">Wireframe 3 &mdash; Send Query Form (Modal / Inline)</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">Modal overlay on bid detail page</span>
  </div>
  <div class="wf-lbl">QUERY FORM &mdash; Appears when bidder clicks "Send Query" or "Ask a Public Question"</div>
  <div class="wf-body" style="max-width:520px;margin:0 auto">
    <div style="background:#fff;border:2px solid #2980b9;border-radius:6px;padding:16px">
      <div style="font-size:13px;font-weight:700;color:#1e3a5f;margin-bottom:12px;border-bottom:1px solid #eee;padding-bottom:8px">
        &#9993; Send Query &mdash; RFQ/2025/047
      </div>
      <span class="lbl">To (Contact Person)</span>
      <select style="border:1px solid #ccc;border-radius:3px;padding:4px 8px;font-size:10px;background:#fff;width:100%;margin-bottom:8px">
        <option>Jabu Mokoena &mdash; SCM Contact</option>
        <option>Sipho Ndlovu &mdash; Technical Contact</option>
      </select>
      <span class="lbl">Subject (auto-filled)</span>
      <input class="inp" value="Query: RFQ/2025/047 &mdash; Supply of Office Furniture" readonly style="background:#f5f5f5">
      <span class="lbl">Your Message</span>
      <textarea style="border:1px solid #ccc;border-radius:3px;padding:6px 8px;font-size:10px;background:#fff;width:100%;height:70px;display:block;margin-bottom:8px" placeholder="Type your question here..."></textarea>
      <span class="lbl">Attach File (optional, max 5MB)</span>
      <div style="border:1px dashed #2980b9;border-radius:3px;padding:8px;text-align:center;background:#f7fbff;margin-bottom:8px;font-size:10px;color:#2980b9;cursor:pointer">
        &#128206; Click to attach a file (PDF, DOCX, XLSX, JPG, PNG)
      </div>
      <span class="lbl">Visibility</span>
      <div style="display:flex;gap:16px;margin-bottom:10px;padding:8px;background:#f7f9fc;border-radius:4px;border:1px solid #ddd">
        <label style="font-size:10px;display:flex;align-items:center;gap:5px;cursor:pointer">
          <input type="radio" name="vis" checked> <strong style="color:#8e44ad">Private</strong> &mdash; Only you and the contact can see this
        </label>
        <label style="font-size:10px;display:flex;align-items:center;gap:5px;cursor:pointer">
          <input type="radio" name="vis"> <strong style="color:#2980b9">Public Q&amp;A</strong> &mdash; All registered bidders can see the answer
        </label>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px">
        <span class="wbtn bgr">Cancel</span>
        <span class="wbtn bb">Send Query</span>
      </div>
    </div>
  </div>
</div>
""")

# WF4 - Registration
f.write("""
<h2 class="sub">Wireframe 4 &mdash; Bidder Registration Page</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">https://yoursite.com/tenders/register/</span>
  </div>
  <div class="wf-lbl">BIDDER REGISTRATION &mdash; Public page, no login required</div>
  <div class="wf-body" style="max-width:560px;margin:0 auto">
    <div style="background:#fff;border:1px solid #ddd;border-radius:6px;padding:18px">
      <h3 style="font-size:14px;color:#1e3a5f;margin-bottom:4px">Create Your Bidder Account</h3>
      <p style="font-size:10px;color:#888;margin-bottom:14px">Register to download documents and send queries to procurement officials.</p>
      <div class="g2">
        <div><span class="lbl">First Name *</span><input class="inp" placeholder="e.g. Thabo"></div>
        <div><span class="lbl">Last Name *</span><input class="inp" placeholder="e.g. Nkosi"></div>
      </div>
      <span class="lbl">Company / Organisation Name *</span>
      <input class="inp" placeholder="e.g. ABC Construction (Pty) Ltd">
      <span class="lbl">Company Registration Number</span>
      <input class="inp" placeholder="e.g. 2010/123456/07">
      <span class="lbl">Phone Number *</span>
      <input class="inp" placeholder="e.g. 011 000 0000">
      <span class="lbl">Email Address * (used for login &amp; verification)</span>
      <input class="inp" placeholder="e.g. thabo@abcconstruction.co.za">
      <div class="g2">
        <div><span class="lbl">Password *</span><input class="inp" type="password" placeholder="Min 8 characters"></div>
        <div><span class="lbl">Confirm Password *</span><input class="inp" type="password" placeholder="Repeat password"></div>
      </div>
      <div style="background:#fef9ec;border:1px solid #f39c12;border-radius:3px;padding:8px;font-size:10px;color:#666;margin-bottom:10px">
        &#128274; A verification email will be sent to your address. You must verify before downloading documents or sending queries.
      </div>
      <span class="wbtn bb" style="width:100%;text-align:center;display:block;padding:7px">Create Account &amp; Send Verification Email</span>
      <div style="text-align:center;margin-top:8px;font-size:10px;color:#888">Already have an account? <a href="#" style="color:#2980b9">Login here</a></div>
    </div>
  </div>
</div>
""")

# WF5 - Bidder Dashboard
f.write("""
<h2 class="sub">Wireframe 5 &mdash; Bidder Dashboard (My Account)</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">https://yoursite.com/tenders/my-account/</span>
  </div>
  <div class="wf-lbl">BIDDER DASHBOARD &mdash; Logged-in verified bidder only</div>
  <div class="wf-body">
    <div class="wf-nav">
      <span class="logo">&#128196; eProcurement Portal</span>
      <a href="#">All Tenders</a>
      <span style="margin-left:auto;font-size:10px;color:#a8c8e8">ABC Company (Pty) Ltd</span>
      <span class="nbtn" style="background:#e74c3c">Logout</span>
    </div>
    <div class="tabs">
      <div class="tab active">My Queries</div>
      <div class="tab">My Downloads</div>
      <div class="tab">My Profile</div>
    </div>
    <div class="g21">
      <div>
        <div class="mi act">
          <div style="display:flex;justify-content:space-between">
            <span class="snd">RFQ/2025/047 &mdash; Office Furniture</span>
            <span class="ppub">PUBLIC</span>
          </div>
          <div class="prv">Query to: Jabu Mokoena (SCM) &bull; 12 Feb 2025 &bull; <span style="color:#27ae60">1 reply</span></div>
        </div>
        <div class="mi">
          <div style="display:flex;justify-content:space-between">
            <span class="snd">BID/2025/012 &mdash; IT Infrastructure</span>
            <span class="ppriv">PRIVATE</span>
          </div>
          <div class="prv">Query to: Sipho Ndlovu (Technical) &bull; 08 Feb 2025 &bull; <span style="color:#e74c3c">Awaiting reply</span></div>
        </div>
      </div>
      <div>
        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px">
          <div style="font-size:11px;font-weight:700;color:#1e3a5f;border-bottom:1px solid #eee;padding-bottom:6px;margin-bottom:8px">
            Thread: RFQ/2025/047 &mdash; Office Furniture
            <span class="ppub" style="margin-left:6px">PUBLIC</span>
          </div>
          <div class="bub">
            <div class="who">You &bull; 12 Feb 2025</div>
            Are imported furniture items acceptable if they meet SABS equivalent standards?
          </div>
          <div class="bub rep">
            <div class="who">Jabu Mokoena (SCM) &bull; 14 Feb 2025</div>
            Yes, SABS equivalent certification is acceptable. Include it in your submission.
          </div>
          <div style="display:flex;gap:6px;margin-top:8px">
            <textarea style="border:1px solid #ccc;border-radius:3px;padding:5px;font-size:10px;flex:1;height:40px" placeholder="Reply to this thread..."></textarea>
            <span class="wbtn bb" style="align-self:flex-end">Send</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
""")

f.write("</div>\n")
f.close()
print("Part C done - section 6 + 5 frontend wireframes written.")
