
# Part D: Section 8 - Backend / Admin Wireframes
f = open("C:/Users/sinet/AI Guy/eprocurement-spec.html", "a", encoding="utf-8")

f.write("""
<div class="wrap pb" id="s8">
<h1 class="sec">8. Backend / Admin UI Wireframes</h1>
<p>All admin screens are accessible via <strong>WordPress Admin &rarr; eProcurement</strong> menu. SCM Manager, SCM Official, and Unit Manager each see only the menu items their role permits.</p>
""")

# WF6 - Admin Dashboard
f.write("""
<h2 class="sub">Wireframe 6 &mdash; Admin Dashboard</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">wp-admin/admin.php?page=eprocurement-dashboard</span>
  </div>
  <div class="wf-lbl">ADMIN DASHBOARD &mdash; SCM Manager / Super Admin view</div>
  <div class="wf-body">
    <div style="display:flex;gap:12px">
      <div style="width:160px;flex-shrink:0;background:#f7f9fc;border-right:1px solid #ddd;padding:10px;font-size:10px;min-height:300px">
        <div style="font-weight:700;color:#888;font-size:9px;text-transform:uppercase;margin-bottom:8px">eProcurement</div>
        <div style="background:#2980b9;color:#fff;padding:4px 8px;border-radius:3px;margin-bottom:4px">&#128202; Dashboard</div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444">&#128462; All Bids</div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444">&#43; Add New Bid</div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444">&#128100; Contact Persons</div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444;display:flex;justify-content:space-between">&#128172; Messages <span style="background:#e74c3c;color:#fff;border-radius:10px;padding:0 6px;font-size:9px">4</span></div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444">&#128101; Bidders</div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444">&#128196; Download Log</div>
        <div style="padding:4px 8px;margin-bottom:4px;color:#444">&#128203; Compliance Docs</div>
        <div style="padding:4px 8px;color:#444">&#9881; Settings</div>
      </div>
      <div style="flex:1">
        <h3 style="font-size:13px;color:#1e3a5f;margin-bottom:10px">Dashboard Overview</h3>
        <div class="g3" style="margin-bottom:12px">
          <div class="sta"><div class="num">18</div><div class="lbl2">Total Bids</div></div>
          <div class="sta"><div class="num" style="color:#27ae60">6</div><div class="lbl2">Open Bids</div></div>
          <div class="sta"><div class="num" style="color:#e74c3c">4</div><div class="lbl2">Unread Queries</div></div>
        </div>
        <div class="g2" style="margin-bottom:10px">
          <div class="sta"><div class="num" style="color:#8e44ad">143</div><div class="lbl2">Registered Bidders</div></div>
          <div class="sta"><div class="num" style="color:#f39c12">317</div><div class="lbl2">Downloads This Month</div></div>
        </div>
        <div style="font-size:11px;font-weight:700;color:#1e3a5f;margin-bottom:6px">Recent Activity</div>
        <table class="wt">
          <tr><th>Time</th><th>Event</th><th>Bid</th><th>User</th></tr>
          <tr><td>10 min ago</td><td>New query submitted</td><td>RFQ/2025/047</td><td>abc@company.co.za</td></tr>
          <tr><td>1 hr ago</td><td>Document downloaded</td><td>BID/2025/012</td><td>xyz@firm.co.za</td></tr>
          <tr><td>2 hrs ago</td><td>Bid published</td><td>RFQ/2025/051</td><td>J. Mokoena (SCM)</td></tr>
          <tr><td>Yesterday</td><td>New bidder registered</td><td>&mdash;</td><td>newbidder@mail.com</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>
""")

# WF7 - Add/Edit Bid
f.write("""
<h2 class="sub">Wireframe 7 &mdash; Add / Edit Bid Document</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">wp-admin/admin.php?page=eprocurement-bid-edit</span>
  </div>
  <div class="wf-lbl">ADD / EDIT BID &mdash; SCM Manager &amp; Official only</div>
  <div class="wf-body">
    <div class="g21">
      <div>
        <h3 style="font-size:12px;color:#1e3a5f;margin-bottom:10px">Bid Information</h3>
        <span class="lbl">Bid Number * <span style="font-weight:400;color:#888">(must be unique)</span></span>
        <input class="inp" placeholder="e.g. RFQ/2025/051">
        <span class="lbl">Bid Title *</span>
        <input class="inp" placeholder="e.g. Supply of Cleaning Materials">
        <span class="lbl">Description / Scope of Work *</span>
        <textarea style="border:1px solid #ccc;border-radius:3px;padding:6px;font-size:10px;width:100%;height:70px;margin-bottom:6px"></textarea>
        <div class="g3">
          <div><span class="lbl">Opening Date</span><input class="inp" type="date"></div>
          <div><span class="lbl">Briefing Date</span><input class="inp" type="date"></div>
          <div><span class="lbl">Closing Date *</span><input class="inp" type="date"></div>
        </div>
        <span class="lbl">Status</span>
        <select style="border:1px solid #ccc;border-radius:3px;padding:4px 8px;font-size:10px;background:#fff;width:100%;margin-bottom:8px">
          <option>Draft</option><option>Published</option><option>Open</option><option>Closed</option><option>Cancelled</option>
        </select>
        <div class="g2">
          <div>
            <span class="lbl">SCM Contact *</span>
            <select style="border:1px solid #ccc;border-radius:3px;padding:4px 8px;font-size:10px;background:#fff;width:100%;margin-bottom:6px">
              <option>-- Select SCM Contact --</option>
              <option>Jabu Mokoena</option>
              <option>Thandi Sithole</option>
            </select>
          </div>
          <div>
            <span class="lbl">Technical Contact</span>
            <select style="border:1px solid #ccc;border-radius:3px;padding:4px 8px;font-size:10px;background:#fff;width:100%;margin-bottom:6px">
              <option>-- Optional --</option>
              <option>Sipho Ndlovu</option>
              <option>Ravi Pillay</option>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:6px;margin-top:6px">
          <span class="wbtn bb">Save Draft</span>
          <span class="wbtn bg">Publish Bid</span>
          <span class="wbtn bgr" style="margin-left:auto">Delete</span>
        </div>
      </div>
      <div>
        <h3 style="font-size:12px;color:#1e3a5f;margin-bottom:10px">Supporting Documents</h3>
        <div style="border:2px dashed #2980b9;border-radius:4px;padding:14px;text-align:center;background:#f7fbff;margin-bottom:8px;font-size:10px;color:#2980b9;cursor:pointer">
          &#128196; Click to upload or drag files here<br>
          <span style="font-size:9px;color:#888">PDF, DOCX, XLSX, ZIP &mdash; Max 20MB per file</span>
        </div>
        <table class="wt">
          <tr><th>Label</th><th>File</th><th>Size</th><th></th></tr>
          <tr>
            <td><input style="border:1px solid #ccc;border-radius:2px;padding:2px 5px;font-size:9px;width:100%" value="Bid Specification"></td>
            <td style="font-size:9px">bid-spec-rfq2025047.pdf</td>
            <td style="font-size:9px">2.3 MB</td>
            <td><span class="wbtn br" style="padding:1px 6px;font-size:9px">&#215;</span></td>
          </tr>
          <tr>
            <td><input style="border:1px solid #ccc;border-radius:2px;padding:2px 5px;font-size:9px;width:100%" value="Bill of Quantities"></td>
            <td style="font-size:9px">boq-rfq2025047.xlsx</td>
            <td style="font-size:9px">540 KB</td>
            <td><span class="wbtn br" style="padding:1px 6px;font-size:9px">&#215;</span></td>
          </tr>
        </table>
        <p style="font-size:9px;color:#888;margin-top:6px">&#9432; Files are stored in the WordPress Media Library. Drag rows to reorder display.</p>
      </div>
    </div>
  </div>
</div>
""")

# WF8 - Messaging Inbox
f.write("""
<h2 class="sub">Wireframe 8 &mdash; Admin Messaging Inbox</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">wp-admin/admin.php?page=eprocurement-messages</span>
  </div>
  <div class="wf-lbl">MESSAGING INBOX &mdash; SCM Manager, SCM Official, Unit Manager</div>
  <div class="wf-body">
    <div style="display:flex;gap:10px">
      <div style="width:230px;flex-shrink:0">
        <div style="display:flex;gap:4px;margin-bottom:8px">
          <input class="inp" style="margin:0" placeholder="&#128269; Search queries...">
        </div>
        <div class="tabs" style="margin-bottom:6px">
          <div class="tab active" style="font-size:9px;padding:4px 8px">All (12)</div>
          <div class="tab" style="font-size:9px;padding:4px 8px">Unread (4)</div>
          <div class="tab" style="font-size:9px;padding:4px 8px">Public</div>
        </div>
        <div class="mi act">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="snd">ABC Company</span>
            <span class="ppub">PUBLIC</span>
          </div>
          <div style="font-weight:700;color:#1e3a5f;font-size:10px">RFQ/2025/047 &mdash; Office Furniture</div>
          <div class="prv">Imported items acceptable?</div>
        </div>
        <div class="mi">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="snd">XYZ Firm</span>
            <span class="ppriv">PRIVATE</span>
          </div>
          <div style="font-weight:700;color:#1e3a5f;font-size:10px">BID/2025/012 &mdash; IT Infrastructure</div>
          <div class="prv">What are the SLA requirements?</div>
        </div>
        <div class="mi">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span class="snd">DEF Suppliers</span>
            <span class="ppub">PUBLIC</span>
          </div>
          <div style="font-weight:700;color:#1e3a5f;font-size:10px">RFQ/2025/047 &mdash; Office Furniture</div>
          <div class="prv">Site visit compulsory?</div>
        </div>
      </div>
      <div style="flex:1">
        <div style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px">
          <div style="border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:10px">
            <div style="font-size:12px;font-weight:700;color:#1e3a5f">Query: RFQ/2025/047 &mdash; Supply of Office Furniture</div>
            <div style="font-size:10px;color:#888">From: ABC Company (abc@company.co.za) &bull; 12 Feb 2025 &bull; <span class="ppub">PUBLIC Q&amp;A</span></div>
          </div>
          <div class="bub">
            <div class="who">ABC Company &bull; 12 Feb 2025</div>
            Good day. Are imported furniture items acceptable if they meet SABS equivalent standards?
          </div>
          <div class="bub rep">
            <div class="who">Jabu Mokoena (SCM) &bull; 14 Feb 2025</div>
            Yes, SABS equivalent certification is acceptable. Please include it with your submission.
          </div>
          <div style="margin-top:10px;border-top:1px solid #eee;padding-top:10px">
            <span class="lbl">Reply as: Jabu Mokoena (SCM Contact)</span>
            <textarea style="border:1px solid #ccc;border-radius:3px;padding:6px;font-size:10px;width:100%;height:55px;margin-bottom:6px" placeholder="Type your reply here..."></textarea>
            <div style="display:flex;gap:6px;align-items:center">
              <span class="wbtn bb">Send Reply</span>
              <span class="wbtn bgr">Mark Resolved</span>
              <div style="margin-left:auto;font-size:9px;color:#888">&#9432; Reply will email the bidder and update the thread.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
""")

# WF9 - Contact Persons Admin
f.write("""
<h2 class="sub">Wireframe 9 &mdash; Contact Persons Management</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">wp-admin/admin.php?page=eprocurement-contacts</span>
  </div>
  <div class="wf-lbl">CONTACT PERSONS &mdash; SCM Manager &amp; SCM Official</div>
  <div class="wf-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h3 style="font-size:13px;color:#1e3a5f">Contact Persons Directory</h3>
      <span class="wbtn bb">&#43; Add Contact Person</span>
    </div>
    <table class="wt">
      <tr><th>Name</th><th>Type</th><th>Phone</th><th>Email</th><th>Department</th><th>WP User</th><th>Actions</th></tr>
      <tr>
        <td><strong>Jabu Mokoena</strong></td>
        <td><span class="tag tb" style="font-size:9px">SCM</span></td>
        <td><a href="tel:+27110000001" style="color:#2980b9;font-size:10px">011 000 0001</a></td>
        <td style="font-size:10px">j.mokoena@org.co.za</td>
        <td style="font-size:10px">Supply Chain</td>
        <td style="font-size:10px"><span class="tag tg" style="font-size:8px">Linked</span></td>
        <td><span class="wbtn bb" style="padding:2px 8px;font-size:9px">Edit</span></td>
      </tr>
      <tr>
        <td><strong>Sipho Ndlovu</strong></td>
        <td><span class="tag tp" style="font-size:9px">Technical</span></td>
        <td><a href="tel:+27110000002" style="color:#2980b9;font-size:10px">011 000 0002</a></td>
        <td style="font-size:10px">s.ndlovu@org.co.za</td>
        <td style="font-size:10px">IT Department</td>
        <td style="font-size:10px"><span class="tag tg" style="font-size:8px">Linked</span></td>
        <td><span class="wbtn bb" style="padding:2px 8px;font-size:9px">Edit</span></td>
      </tr>
      <tr>
        <td><strong>Ravi Pillay</strong></td>
        <td><span class="tag tp" style="font-size:9px">Technical</span></td>
        <td><a href="tel:+27110000003" style="color:#2980b9;font-size:10px">011 000 0003</a></td>
        <td style="font-size:10px">r.pillay@org.co.za</td>
        <td style="font-size:10px">Engineering</td>
        <td style="font-size:10px"><span class="tag to" style="font-size:8px">Not linked</span></td>
        <td><span class="wbtn bb" style="padding:2px 8px;font-size:9px">Edit</span> <span class="wbtn bgr" style="padding:2px 8px;font-size:9px">Link WP User</span></td>
      </tr>
    </table>
    <p style="font-size:10px;color:#888;margin-top:8px">&#9432; Contact persons must be linked to a WordPress user account to reply to queries through the plugin inbox.</p>
  </div>
</div>
""")

# WF10 - Bidder Management
f.write("""
<h2 class="sub">Wireframe 10 &mdash; Bidder / Subscriber Management</h2>
<div class="wf">
  <div class="wf-bar">
    <span class="dot" style="background:#e74c3c"></span><span class="dot" style="background:#f39c12"></span><span class="dot" style="background:#27ae60"></span>
    <span class="wf-url">wp-admin/admin.php?page=eprocurement-bidders</span>
  </div>
  <div class="wf-lbl">BIDDER MANAGEMENT &mdash; SCM Manager only</div>
  <div class="wf-body">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h3 style="font-size:13px;color:#1e3a5f">Registered Bidders (143)</h3>
      <div style="display:flex;gap:6px">
        <input class="inp" style="margin:0;width:180px" placeholder="&#128269; Search bidder...">
        <span class="wbtn bb">Export CSV</span>
      </div>
    </div>
    <table class="wt">
      <tr><th>Company</th><th>Contact Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Registered</th><th>Downloads</th><th>Queries</th></tr>
      <tr>
        <td><strong>ABC Company (Pty) Ltd</strong></td>
        <td>Thabo Nkosi</td>
        <td style="font-size:10px">thabo@abc.co.za</td>
        <td style="font-size:10px">011 000 0001</td>
        <td><span class="tag tg" style="font-size:9px">Verified</span></td>
        <td style="font-size:10px">01 Feb 2025</td>
        <td style="text-align:center">7</td>
        <td style="text-align:center">3</td>
      </tr>
      <tr>
        <td><strong>XYZ Firm CC</strong></td>
        <td>Priya Govender</td>
        <td style="font-size:10px">priya@xyz.co.za</td>
        <td style="font-size:10px">031 000 0002</td>
        <td><span class="tag tg" style="font-size:9px">Verified</span></td>
        <td style="font-size:10px">03 Feb 2025</td>
        <td style="text-align:center">4</td>
        <td style="text-align:center">1</td>
      </tr>
      <tr>
        <td><strong>New Bidder Ltd</strong></td>
        <td>John Smith</td>
        <td style="font-size:10px">john@newbidder.co.za</td>
        <td style="font-size:10px">021 000 0003</td>
        <td><span class="tag to" style="font-size:9px">Unverified</span></td>
        <td style="font-size:10px">18 Feb 2025</td>
        <td style="text-align:center">0</td>
        <td style="text-align:center">0</td>
      </tr>
    </table>
  </div>
</div>
""")

f.write("</div>\n")
f.close()
print("Part D done - 5 backend wireframes written.")
