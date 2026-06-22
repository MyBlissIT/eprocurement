
# Part A: Open file and write CSS + head + cover + TOC
f = open("C:/Users/sinet/AI Guy/eprocurement-spec.html", "w", encoding="utf-8")

CSS = """
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#222;background:#fff;line-height:1.6}
@media print{.pb{page-break-before:always}body{font-size:11px}}
.wrap{max-width:960px;margin:0 auto;padding:40px 32px}
.cover{min-height:100vh;display:flex;flex-direction:column;justify-content:center;align-items:center;
  text-align:center;background:linear-gradient(135deg,#1e3a5f 0%,#2980b9 100%);color:#fff;padding:60px}
.cover h1{font-size:38px;font-weight:700;margin-bottom:12px}
.cover h2{font-size:18px;font-weight:400;opacity:.85;margin-bottom:28px}
.cover .meta{font-size:13px;opacity:.7;line-height:2.2}
.cbadge{display:inline-block;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
  padding:4px 16px;border-radius:20px;font-size:12px;margin-bottom:28px}
h1.sec{font-size:22px;color:#1e3a5f;border-bottom:3px solid #2980b9;padding-bottom:8px;margin:40px 0 18px}
h2.sub{font-size:15px;color:#1e3a5f;margin:24px 0 10px;padding-left:10px;border-left:4px solid #2980b9}
p{margin-bottom:10px;color:#333}
code{font-family:monospace;background:#f0f0f0;padding:1px 5px;border-radius:3px;font-size:11px;color:#c0392b}
table{width:100%;border-collapse:collapse;margin:14px 0;font-size:12px}
th{background:#1e3a5f;color:#fff;padding:8px 10px;text-align:left;font-weight:600}
td{padding:7px 10px;border-bottom:1px solid #e0e0e0;vertical-align:top}
tr:nth-child(even) td{background:#f7f9fc}
.cl{border-left:4px solid #2980b9;background:#eef6fb;padding:12px 16px;margin:14px 0;border-radius:0 6px 6px 0;font-size:12px}
.cl.ok{border-color:#27ae60;background:#eafaf1}
.cl.warn{border-color:#f39c12;background:#fef9ec}
.toc{background:#f7f9fc;border:1px solid #d0dce8;border-radius:6px;padding:20px 24px;margin:20px 0}
.toc h3{color:#1e3a5f;margin-bottom:12px;font-size:15px}
.toc ol{padding-left:20px;line-height:2.4;font-size:13px}
.toc a{color:#2980b9;text-decoration:none}
/* DB */
.db{border:2px solid #1e3a5f;border-radius:6px;overflow:hidden;margin-bottom:18px}
.db-h{background:#1e3a5f;color:#fff;padding:7px 14px;font-weight:700;font-size:12px;display:flex;justify-content:space-between}
.db-h span{font-size:10px;font-weight:400;opacity:.7}
.db-r{display:grid;grid-template-columns:185px 130px 1fr;padding:5px 12px;border-bottom:1px solid #e0e8f0;font-size:11px}
.db-r:nth-child(even){background:#f7f9fc}
.cn{font-weight:600;color:#1e3a5f;font-family:monospace}
.ct{color:#c0392b;font-family:monospace;font-size:10px}
.cno{color:#666;font-size:10px}
.pk{font-size:9px;background:#f39c12;color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px}
.fk{font-size:9px;background:#8e44ad;color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px}
.uq{font-size:9px;background:#27ae60;color:#fff;padding:1px 5px;border-radius:4px;margin-left:4px}
/* WIREFRAMES */
.wf{background:#e8eaed;border:2px solid #aaa;border-radius:8px;overflow:hidden;margin:18px 0;font-size:11px}
.wf-bar{background:#333;color:#fff;padding:5px 12px;display:flex;align-items:center;gap:8px}
.dot{width:11px;height:11px;border-radius:50%;display:inline-block}
.wf-url{background:#fff;border-radius:10px;padding:2px 12px;font-size:10px;color:#555;flex:1;max-width:340px;margin:0 auto}
.wf-lbl{background:#1e3a5f;color:#fff;padding:4px 14px;font-size:10px;font-weight:700;letter-spacing:.5px}
.wf-body{padding:10px}
.wf-nav{background:#1e3a5f;color:#fff;padding:7px 12px;display:flex;align-items:center;gap:14px;font-size:10px;margin-bottom:8px;border-radius:4px}
.wf-nav .logo{font-weight:700;font-size:12px;margin-right:auto}
.wf-nav a{color:#a8c8e8;text-decoration:none;font-size:10px}
.nbtn{background:#2980b9;padding:3px 10px;border-radius:3px;color:#fff;font-size:10px}
.nbtn.g{background:#27ae60}
.hero{background:#2980b9;color:#fff;padding:16px;border-radius:4px;margin-bottom:10px;text-align:center}
.hero h3{font-size:15px;margin-bottom:3px}
.hero p{font-size:10px;opacity:.85;margin:0}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px}
.g21{display:grid;grid-template-columns:2fr 1fr;gap:10px}
.g12{display:grid;grid-template-columns:1fr 2fr;gap:10px}
.card{background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px}
.card h4{font-size:11px;color:#1e3a5f;margin-bottom:4px;font-weight:700}
.card p{font-size:10px;color:#666;margin:0 0 3px}
.tag{display:inline-block;padding:1px 7px;border-radius:10px;font-size:9px;font-weight:700;color:#fff;margin-bottom:4px}
.tg{background:#27ae60}.tr{background:#e74c3c}.to{background:#f39c12}.tb{background:#2980b9}.tp{background:#8e44ad}
.wbtn{display:inline-block;padding:3px 10px;border-radius:3px;font-size:10px;color:#fff;cursor:pointer;margin-top:5px}
.bb{background:#2980b9}.bg{background:#27ae60}.br{background:#e74c3c}.bgr{background:#888}
.inp{border:1px solid #ccc;border-radius:3px;padding:4px 8px;font-size:10px;background:#fff;width:100%;display:block;margin-bottom:6px}
.lbl{font-size:10px;color:#555;font-weight:600;margin-bottom:2px;display:block}
.sta{background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px;text-align:center}
.sta .num{font-size:22px;font-weight:700;color:#2980b9}
.sta .lbl2{font-size:9px;color:#888;text-transform:uppercase}
.wt{width:100%;border-collapse:collapse}
.wt th{background:#f0f2f5;color:#333;padding:5px 8px;border:1px solid #ddd;font-weight:600;font-size:10px;text-align:left}
.wt td{padding:5px 8px;border:1px solid #ddd;color:#444;font-size:10px}
.wt tr:nth-child(even) td{background:#fafafa}
.tabs{display:flex;border-bottom:2px solid #2980b9;margin-bottom:8px}
.tab{padding:5px 14px;font-size:10px;color:#666;border-radius:4px 4px 0 0;cursor:pointer}
.tab.active{background:#2980b9;color:#fff}
.mi{padding:7px 10px;border-bottom:1px solid #eee;background:#fff;font-size:10px;cursor:pointer}
.mi.act{background:#eef6fb;border-left:3px solid #2980b9}
.mi .snd{font-weight:700;color:#1e3a5f;font-size:10px}
.mi .prv{color:#888;font-size:9px}
.pill{display:inline-block;padding:1px 6px;border-radius:10px;font-size:9px;font-weight:700}
.ppub{background:#2980b9;color:#fff}.ppriv{background:#8e44ad;color:#fff}
.bub{background:#f0f0f0;border-radius:8px;padding:7px 10px;margin-bottom:6px;font-size:10px;max-width:80%}
.bub.rep{background:#dbeeff;margin-left:auto;text-align:right}
.bub .who{font-size:9px;color:#888;margin-bottom:2px}
.cc{background:#fff;border:1px solid #ddd;border-radius:4px;padding:9px}
.cc .ctype{display:inline-block;padding:1px 7px;border-radius:10px;font-size:9px;color:#fff;font-weight:700;margin-bottom:4px}
.scm{background:#1e3a5f}.tech{background:#8e44ad}
.cc .cname{font-weight:700;font-size:11px;color:#1e3a5f;margin-bottom:3px}
.cc .cd{font-size:9px;color:#555;line-height:1.9}
.cc .cd a{color:#2980b9}
.sb{background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px}
.sb h5{font-size:11px;color:#1e3a5f;font-weight:700;margin-bottom:7px;border-bottom:1px solid #eee;padding-bottom:4px}
/* BUILD */
.phase{border:1px solid #ddd;border-radius:6px;margin-bottom:12px;overflow:hidden}
.ph{background:#1e3a5f;color:#fff;padding:8px 14px;font-weight:700;font-size:12px;display:flex;align-items:center;gap:10px}
.pn{background:#2980b9;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0}
.pb2{padding:10px 14px;font-size:12px}
.pb2 li{margin-bottom:3px;color:#444}
.ft{font-family:monospace;font-size:11px;background:#f7f7f7;border:1px solid #ddd;border-radius:4px;padding:14px;line-height:2}
.ft .d{color:#1e3a5f;font-weight:700}
.ft .fi{color:#444}
.ft .cm{color:#27ae60}
"""

f.write("<!DOCTYPE html>\n<html lang='en'>\n<head>\n<meta charset='UTF-8'>\n")
f.write("<meta name='viewport' content='width=device-width,initial-scale=1.0'>\n")
f.write("<title>eProcurement Plugin - Technical Specification</title>\n")
f.write(f"<style>{CSS}</style>\n</head>\n<body>\n")

# COVER
f.write("""
<div class="cover">
  <div class="cbadge">INTERNAL DOCUMENT &mdash; PRE-BUILD REVIEW</div>
  <h1>eProcurement Plugin</h1>
  <h2>Technical Specification &amp; UI Wireframe Document</h2>
  <div style="width:80px;height:3px;background:rgba(255,255,255,.35);margin:18px auto 24px"></div>
  <div class="meta">
    Version: 1.0 &nbsp;&bull;&nbsp; Date: February 2026<br>
    Status: Approved for Development<br><br>
    <strong>Purpose:</strong> Full architecture, database schema, roles,<br>
    feature specifications &amp; UI wireframes for review before build.
  </div>
</div>
""")

# TOC
f.write("""
<div class="wrap pb">
  <div class="toc">
    <h3>&#128221; Table of Contents</h3>
    <ol>
      <li><a href="#s1">Executive Summary</a></li>
      <li><a href="#s2">Confirmed Design Decisions</a></li>
      <li><a href="#s3">Plugin Architecture Overview</a></li>
      <li><a href="#s4">User Roles &amp; Capabilities Matrix</a></li>
      <li><a href="#s5">Database Schema (10 Tables)</a></li>
      <li><a href="#s6">Feature Specifications</a></li>
      <li><a href="#s7">Frontend UI Wireframes (5 Screens)</a></li>
      <li><a href="#s8">Backend / Admin UI Wireframes (5 Screens)</a></li>
      <li><a href="#s9">Build Approach, File Structure &amp; Phases</a></li>
      <li><a href="#s10">Dependencies &amp; Deployment Requirements</a></li>
    </ol>
  </div>
</div>
""")

f.close()
print("Part A done - head, CSS, cover, TOC written.")
