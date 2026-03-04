
# Part E: Sections 9-10 (Build Approach + Dependencies) + close HTML
f = open("C:/Users/sinet/AI Guy/eprocurement-spec.html", "a", encoding="utf-8")

f.write("""
<div class="wrap pb" id="s9">
<h1 class="sec">9. Build Approach, File Structure &amp; Phases</h1>

<h2 class="sub">How I Would Build It</h2>
<p>The plugin follows the <strong>WordPress Plugin Boilerplate</strong> pattern &mdash; fully Object-Oriented PHP. Cloud storage SDKs (Google, Microsoft, Dropbox, AWS) are managed via <strong>Composer</strong>. All frontend interactivity is handled through <strong>WordPress REST API endpoints</strong> consumed by vanilla JavaScript. No React/Vue required.</p>

<div class="cl ok"><strong>Why Composer?</strong> The cloud storage SDKs (Google API Client, Microsoft Graph, Dropbox SDK, AWS SDK) are industry-standard PHP packages best managed through Composer. The plugin ships with a <code>vendor/</code> directory so the end-user does not need to run Composer manually. Everything else uses core WordPress APIs.</div>

<h2 class="sub">Plugin File Structure</h2>
<div class="ft">
<span class="d">eprocurement/</span><br>
&nbsp;&nbsp;&#9500; <span class="fi">eprocurement.php</span> <span class="cm">&mdash; Main plugin file: header, constants, autoload</span><br>
&nbsp;&nbsp;&#9500; <span class="fi">uninstall.php</span> <span class="cm">&mdash; Clean up tables and options on uninstall</span><br>
&nbsp;&nbsp;&#9500; <span class="d">includes/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-activator.php</span> <span class="cm">&mdash; register_activation_hook handler</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-deactivator.php</span> <span class="cm">&mdash; register_deactivation_hook handler</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-roles.php</span> <span class="cm">&mdash; Custom role definitions and capability maps</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-database.php</span> <span class="cm">&mdash; dbDelta schema, shared query helpers</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-documents.php</span> <span class="cm">&mdash; Bid CRUD, status transitions, metadata</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-contact-persons.php</span> <span class="cm">&mdash; Contact person records linked to WP users</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-messaging.php</span> <span class="cm">&mdash; Thread/message creation, visibility rules</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-bidder.php</span> <span class="cm">&mdash; Registration, email verification token logic</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-downloads.php</span> <span class="cm">&mdash; Secure download endpoint + audit log writes</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-notifications.php</span> <span class="cm">&mdash; All wp_mail() calls, email templates</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-storage-interface.php</span> <span class="cm">&mdash; Abstract: upload(), get_download_url(), delete()</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="d">storage/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-google-drive.php</span> <span class="cm">&mdash; Google Drive (OAuth 2.0)</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-onedrive.php</span> <span class="cm">&mdash; OneDrive / Microsoft Graph (OAuth 2.0)</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-dropbox.php</span> <span class="cm">&mdash; Dropbox API v2 (OAuth 2.0)</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">class-s3.php</span> <span class="cm">&mdash; AWS S3 / DigitalOcean Spaces / Backblaze B2</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-compliance-docs.php</span> <span class="cm">&mdash; Static compliance document library CRUD</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">class-rest-api.php</span> <span class="cm">&mdash; /wp-json/eprocurement/v1/ endpoints</span><br>
&nbsp;&nbsp;&#9500; <span class="d">admin/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-admin.php</span> <span class="cm">&mdash; Admin menu registration, asset enqueue</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="d">partials/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">dashboard.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">bid-list.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">bid-edit.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">contact-persons.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">messages.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">bidders.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">compliance-docs.php</span> <span class="cm">&mdash; Compliance document library management</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">settings.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">admin.js</span> <span class="cm">&mdash; Admin-side interactions (file upload, messaging)</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">admin.css</span><br>
&nbsp;&nbsp;&#9500; <span class="d">public/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">class-public.php</span> <span class="cm">&mdash; Shortcode handler, frontend asset enqueue</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="d">partials/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">tender-listing.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">tender-detail.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">bidder-register.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">bidder-login.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">bidder-dashboard.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">compliance-docs.php</span> <span class="cm">&mdash; Public compliance documents page</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">frontend.js</span> <span class="cm">&mdash; Listing filters, query form modal, AJAX calls</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">frontend.css</span> <span class="cm">&mdash; Responsive, mobile-first styles</span><br>
&nbsp;&nbsp;&#9500; <span class="d">templates/email/</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">verification.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9500; <span class="fi">new-query.php</span><br>
&nbsp;&nbsp;&#9474;&nbsp;&nbsp;&#9492; <span class="fi">new-reply.php</span><br>
&nbsp;&nbsp;&#9492; <span class="d">languages/</span> <span class="cm">&mdash; .pot file for translation</span>
</div>

<h2 class="sub">Build Phases</h2>
<div class="phase">
  <div class="ph"><div class="pn">1</div> Foundation (Week 1)</div>
  <div class="pb2"><ul>
    <li>Plugin scaffolding: constants, autoloader, activation/deactivation hooks</li>
    <li>Database schema: create all 7 tables via dbDelta on activation</li>
    <li>Custom roles: SCM Manager, SCM Official, Unit Manager, Subscriber/Bidder</li>
    <li>Admin menu registration and empty page shells</li>
  </ul></div>
</div>
<div class="phase">
  <div class="ph"><div class="pn">2</div> Bid &amp; Document Management (Week 1-2)</div>
  <div class="pb2"><ul>
    <li>Bid CRUD (create, read, update, delete) in admin</li>
    <li>All bid metadata fields including Bid Number (unique validation)</li>
    <li>Status workflow (Draft &rarr; Published &rarr; Open &rarr; Closed | Cancelled | Archived)</li>
    <li>Multi-provider cloud storage: Storage Interface + Google Drive, OneDrive, Dropbox, S3 implementations</li>
    <li>OAuth 2.0 flow for Google Drive, OneDrive, Dropbox (token storage + auto-refresh)</li>
    <li>Cloud storage settings page: provider selection, credential entry, connection test button</li>
    <li>Supporting document upload to cloud storage, junction table, labels</li>
    <li>Compliance Documents library: admin CRUD, cloud upload, customisable section title</li>
    <li>Contact person management page and assignment to bids</li>
  </ul></div>
</div>
<div class="phase">
  <div class="ph"><div class="pn">3</div> Bidder Registration &amp; Frontend (Week 2)</div>
  <div class="pb2"><ul>
    <li>Bidder registration form with company profile fields</li>
    <li>Email verification token generation, email send, token validation endpoint</li>
    <li>Frontend shortcode [eprocurement] with router (listing / detail / register / dashboard)</li>
    <li>Public tender listing page with search and status filter</li>
    <li>Bid detail page: metadata, contact cards with click-to-call, document download list</li>
    <li>Secure download endpoint: verify login, log download, redirect to file</li>
  </ul></div>
</div>
<div class="phase">
  <div class="ph"><div class="pn">4</div> Messaging &amp; Q&amp;A System (Week 3)</div>
  <div class="pb2"><ul>
    <li>Query form modal with contact selector, visibility toggle, and file attachment (5MB max, cloud-stored)</li>
    <li>Thread + message creation, auto-generated subject line, attachment support on replies</li>
    <li>Rich text editor for bid description with inline image paste/upload (images stored on cloud)</li>
    <li>Public Q&amp;A display on bid detail page (all registered bidders)</li>
    <li>Bidder dashboard: My Queries tab with thread view and reply</li>
    <li>Admin messaging inbox with thread list, message view, and reply form</li>
    <li>Email notifications for all messaging events via wp_mail()</li>
  </ul></div>
</div>
<div class="phase">
  <div class="ph"><div class="pn">5</div> Polish, Security &amp; Testing (Week 3-4)</div>
  <div class="pb2"><ul>
    <li>Full capability checks on every AJAX handler and REST endpoint</li>
    <li>Nonce verification on all forms</li>
    <li>Input sanitization audit across all user-facing inputs</li>
    <li>Responsive CSS for frontend (mobile-first)</li>
    <li>Admin settings page: SMTP test, notification toggles, frontend page assignments</li>
    <li>Download log export (CSV) for audit purposes</li>
    <li>WordPress coding standards (PHPCS) pass</li>
  </ul></div>
</div>
</div>
""")

# S10
f.write("""
<div class="wrap pb" id="s10">
<h1 class="sec">10. Dependencies &amp; Deployment Requirements</h1>

<h2 class="sub">What I Need From You (Before Build Starts)</h2>
<table>
<tr><th>Item</th><th>Why Needed</th></tr>
<tr><td>WordPress site URL and admin access</td><td>To install and test the plugin on a staging environment</td></tr>
<tr><td>Organisation name and logo</td><td>For branding the frontend portal and email templates</td></tr>
<tr><td>SMTP email credentials</td><td>wp_mail() must be configured to send verification and notification emails reliably</td></tr>
<tr><td>Cloud storage credentials (one provider)</td><td><strong>Google Drive:</strong> Google Cloud project with Drive API enabled + OAuth Client ID/Secret<br><strong>OneDrive:</strong> Azure App Registration with Graph API Files.ReadWrite scope<br><strong>Dropbox:</strong> Dropbox App with files.content.write scope<br><strong>S3:</strong> Bucket name, region, access key, secret key</td></tr>
<tr><td>Agreed frontend page slug</td><td>The WordPress page where [eprocurement] shortcode is placed (e.g., /tenders/)</td></tr>
<tr><td>Sample bid data (2-3 examples)</td><td>For testing the full workflow end-to-end during development</td></tr>
</table>

<h2 class="sub">Server &amp; Software Requirements</h2>
<table>
<tr><th>Requirement</th><th>Minimum</th><th>Recommended</th></tr>
<tr><td>WordPress</td><td>6.0</td><td>6.4+</td></tr>
<tr><td>PHP</td><td>8.0</td><td>8.2+</td></tr>
<tr><td>MySQL / MariaDB</td><td>MySQL 5.7 / MariaDB 10.3</td><td>MySQL 8.0+</td></tr>
<tr><td>SMTP Plugin</td><td>WP Mail SMTP (free)</td><td>WP Mail SMTP Pro or FluentSMTP</td></tr>
<tr><td>Max upload size (php.ini)</td><td>20MB</td><td>50MB+ (for large bid documents)</td></tr>
<tr><td>Cloud storage account</td><td>Required (one of 4)</td><td>Google Drive, OneDrive, Dropbox, or S3-compatible bucket</td></tr>
<tr><td>Composer (PHP dependency manager)</td><td>Required</td><td>Installs Google API Client, Microsoft Graph SDK, Dropbox SDK, AWS SDK</td></tr>
<tr><td>SSL Certificate</td><td>Required</td><td>Required (login + file downloads)</td></tr>
</table>

<h2 class="sub">What Is NOT Needed</h2>
<table>
<tr><th>Item</th><th>Why Not Needed</th></tr>
<tr><td>Node.js / npm build process</td><td>Frontend uses vanilla JS; no bundler required</td></tr>
<tr><td>React or Vue</td><td>Plugin JavaScript is lightweight enough for vanilla JS + REST API</td></tr>
<tr><td>Manual OAuth implementation</td><td>Plugin handles OAuth internally for Google/OneDrive/Dropbox. Admin clicks &ldquo;Connect&rdquo; in settings, completes OAuth, and the token is stored and auto-refreshed. No external OAuth plugins needed.</td></tr>
<tr><td>Page builder (Elementor, Divi)</td><td>Plugin renders its own frontend via shortcode. No page builder dependency.</td></tr>
<tr><td>WooCommerce</td><td>No e-commerce functionality is required</td></tr>
</table>

<h2 class="sub">Security Checklist (Built-in)</h2>
<table>
<tr><th>Security Measure</th><th>Implementation</th></tr>
<tr><td>Nonce verification</td><td>All forms and AJAX calls include wp_create_nonce() / check_ajax_referer()</td></tr>
<tr><td>Capability checks</td><td>Every AJAX handler and REST endpoint calls current_user_can() before processing</td></tr>
<tr><td>Input sanitization</td><td>sanitize_text_field(), sanitize_email(), absint(), wp_kses_post() throughout</td></tr>
<tr><td>Prepared SQL statements</td><td>All database queries use $wpdb-&gt;prepare() or $wpdb insert/update methods with format arrays</td></tr>
<tr><td>Output escaping</td><td>esc_html(), esc_attr(), esc_url() on all rendered output</td></tr>
<tr><td>Secure downloads</td><td>Files served through a PHP endpoint that checks login and logs access, not direct URL</td></tr>
<tr><td>Email verification</td><td>One-time token, deleted after use, 48-hour expiry</td></tr>
<tr><td>Role isolation</td><td>Each role has only the capabilities it needs, nothing more</td></tr>
</table>

<div style="margin-top:40px;padding:20px;background:#1e3a5f;color:#fff;border-radius:8px;text-align:center">
  <div style="font-size:16px;font-weight:700;margin-bottom:8px">eProcurement Plugin &mdash; Technical Specification v1.0</div>
  <div style="opacity:.7;font-size:12px">Prepared February 2026 &bull; Pre-Build Review Document</div>
  <div style="opacity:.5;font-size:11px;margin-top:6px">To print this document as PDF: File &rarr; Print &rarr; Save as PDF (set margins to Default, enable Background Graphics)</div>
</div>
</div>

</body>
</html>
""")
f.close()
print("Part E done - sections 9-10 + closing HTML written. File complete.")
