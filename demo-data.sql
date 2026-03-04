-- =============================================
-- eProcurement Demo Data
-- Run: wp db query < demo-data.sql
-- =============================================

-- =============================================
-- TENDERS (DOCUMENTS)
-- =============================================

-- Tender 1: CLOSED - Office Furniture (past dates)
INSERT INTO HIL_eproc_documents (bid_number, title, description, status, category, scm_contact_id, technical_contact_id, opening_date, briefing_date, closing_date, created_by) VALUES
('RFQ/2025/001', 'Supply and Delivery of Office Furniture',
'<h3>Background</h3><p>The Municipality invites suitably qualified and experienced service providers to supply and deliver office furniture for the newly refurbished administrative building at 45 Nelson Mandela Drive, East London.</p><h3>Scope of Work</h3><ul><li>Supply of 120 ergonomic office chairs (mesh back, adjustable height)</li><li>Supply of 80 height-adjustable desks (1400mm x 700mm)</li><li>Supply of 40 four-drawer filing cabinets (lockable)</li><li>Supply of 20 boardroom chairs (executive leather)</li><li>Delivery and assembly at specified locations within the building</li></ul><h3>Requirements</h3><p>Bidders must be registered on the Central Supplier Database (CSD) and have a valid Tax Clearance Certificate. BBBEE Level 1-4 contributors will receive preference points.</p><h3>Evaluation Criteria</h3><p>Price: 80 points | BBBEE: 20 points (80/20 preference point system)</p>',
'closed', 'bid', 1, 3, '2025-01-15 08:00:00', '2025-01-22 10:00:00', '2025-02-14 12:00:00', 2);

-- Tender 2: OPEN - IT Network Infrastructure (future closing)
INSERT INTO HIL_eproc_documents (bid_number, title, description, status, category, scm_contact_id, technical_contact_id, opening_date, briefing_date, closing_date, created_by) VALUES
('RFQ/2025/002', 'IT Network Infrastructure Upgrade and Cabling',
'<h3>Background</h3><p>The Municipality seeks proposals from qualified ICT service providers for the upgrade of its local area network (LAN) infrastructure across three municipal buildings in the Buffalo City Metropolitan area.</p><h3>Scope of Work</h3><ul><li>Supply and installation of Cat6A structured cabling (estimated 15,000m)</li><li>Installation of 24-port managed switches (Cisco or equivalent) at each floor</li><li>Configuration of VLANs, QoS policies, and network segmentation</li><li>Supply and installation of 30 x wireless access points (Wi-Fi 6)</li><li>Core switch upgrade to 10Gbps backbone</li><li>12-month post-installation maintenance and support</li></ul><h3>Requirements</h3><p>Bidders must hold a valid CIDB grading of 5EP or higher. Key personnel must have CCNA/CCNP certifications. Minimum 5 years experience in enterprise networking projects.</p><h3>Evaluation</h3><p>Functionality: 60% | Price: 30% | BBBEE: 10%</p>',
'open', 'bid', 1, 3, '2026-02-20 08:00:00', '2026-03-10 10:00:00', '2026-04-15 12:00:00', 2);

-- Tender 3: OPEN - Cleaning Services (future closing)
INSERT INTO HIL_eproc_documents (bid_number, title, description, status, category, scm_contact_id, opening_date, briefing_date, closing_date, created_by) VALUES
('RFQ/2025/003', 'Professional Cleaning Services — Annual Contract',
'<h3>Background</h3><p>The Municipality invites bids from experienced cleaning companies for the provision of daily cleaning services at four municipal facilities for a period of 36 months.</p><h3>Scope of Work</h3><ul><li>Daily cleaning of office spaces, ablution facilities, and common areas</li><li>Weekly deep cleaning of carpets and upholstery</li><li>Monthly window cleaning (interior and exterior)</li><li>Supply of all cleaning materials, equipment, and consumables</li><li>Waste management and recycling services</li><li>Pest control services (quarterly)</li></ul><h3>Facilities</h3><p>1. Main Admin Building — 4,500 sqm<br>2. Community Hall — 1,200 sqm<br>3. Library — 800 sqm<br>4. Parks Depot — 600 sqm</p><h3>Requirements</h3><p>Minimum 3 years experience. Valid UIF, COIDA, and SARS compliance. Staff must undergo security vetting.</p><h3>Evaluation</h3><p>Price: 90 points | BBBEE: 10 points (90/10 preference point system)</p>',
'open', 'bid', 2, '2026-03-01 08:00:00', '2026-03-15 09:30:00', '2026-04-30 12:00:00', 3);

-- Tender 4: DRAFT - Security Services (not yet published)
INSERT INTO HIL_eproc_documents (bid_number, title, description, status, category, scm_contact_id, technical_contact_id, opening_date, closing_date, created_by) VALUES
('RFQ/2025/004', 'Physical Security and Access Control Services',
'<h3>Background</h3><p>The Municipality requires the services of a reputable security company to provide 24-hour physical guarding, access control, and CCTV monitoring at municipal facilities.</p><h3>Scope of Work</h3><ul><li>24/7 physical guarding at 6 municipal sites (minimum Grade C officers)</li><li>Armed response capability with 15-minute response time</li><li>Access control management using biometric systems</li><li>CCTV monitoring from central control room</li><li>Monthly incident reports and risk assessments</li><li>Contract period: 36 months with option to extend by 12 months</li></ul><h3>Requirements</h3><p>PSIRA Grade A registration required. Minimum R10 million professional indemnity insurance. Minimum 5 years experience in government sector security.</p><h3>Evaluation</h3><p>Functionality: 70% | Price: 20% | BBBEE: 10%</p>',
'draft', 'bid', 1, 3, '2026-04-01 08:00:00', '2026-05-15 12:00:00', 2);

-- Tender 5: OPEN - Fleet Management (closing soon)
INSERT INTO HIL_eproc_documents (bid_number, title, description, status, category, scm_contact_id, opening_date, briefing_date, closing_date, created_by) VALUES
('RFQ/2025/005', 'Municipal Fleet Management and Maintenance Services',
'<h3>Background</h3><p>The Municipality is seeking a qualified service provider for the comprehensive management and maintenance of its vehicle fleet consisting of 85 vehicles ranging from light delivery vehicles to heavy-duty trucks and specialized equipment.</p><h3>Scope of Work</h3><ul><li>Scheduled and unscheduled vehicle maintenance</li><li>Fleet tracking and telematics system installation and management</li><li>Fuel management and reporting</li><li>Driver training and road safety programmes</li><li>24-hour roadside assistance</li><li>Monthly fleet utilization and cost reports</li></ul><h3>Fleet Composition</h3><p>35 x Light Delivery Vehicles (LDV)<br>20 x Sedans/SUVs<br>15 x Medium trucks (4-8 ton)<br>10 x Heavy-duty trucks and TLBs<br>5 x Specialized vehicles (water tankers, refuse compactors)</p><h3>Requirements</h3><p>RMI accredited workshop. Minimum 10 years fleet management experience. Valid SARS Tax Clearance. BBBEE Level 1-3 preferred.</p><h3>Evaluation</h3><p>Functionality: 60% | Price: 30% | BBBEE: 10%</p>',
'open', 'bid', 2, '2026-02-15 08:00:00', '2026-02-28 10:00:00', '2026-03-14 12:00:00', 3);

-- =============================================
-- BIDDER PROFILES
-- =============================================
INSERT INTO HIL_eproc_bidder_profiles (user_id, company_name, company_reg, phone, verified, notify_replies) VALUES
(5, 'Mzansi Building Supplies (Pty) Ltd', '2018/456789/07', '043 722 1234', 1, 1),
(6, 'Ikwezi IT Solutions',                '2020/112233/07', '041 585 6789', 1, 1),
(7, 'Naidoo & Associates Consulting',     '2019/334455/07', '040 635 4321', 1, 1);

-- =============================================
-- COMPLIANCE DOCUMENTS
-- =============================================
INSERT INTO HIL_eproc_compliance_docs (file_name, file_size, file_type, cloud_provider, cloud_key, cloud_url, label, description, sort_order, uploaded_by) VALUES
('SBD1_Invitation_to_Bid.pdf', 245760, 'application/pdf', 'local', 'scm/SBD1_Invitation_to_Bid.pdf', '#', 'SBD 1 — Invitation to Bid', 'Standard Bidding Document 1: Official invitation to bid form. Must be completed and signed by the bidder.', 1, 2),
('SBD4_Declaration_of_Interest.pdf', 189440, 'application/pdf', 'local', 'scm/SBD4_Declaration_of_Interest.pdf', '#', 'SBD 4 — Declaration of Interest', 'Declaration of interest form. Bidders must declare any conflict of interest with the Municipality or its officials.', 2, 2),
('SBD6_Preference_Points_Claim.pdf', 312320, 'application/pdf', 'local', 'scm/SBD6_Preference_Points_Claim.pdf', '#', 'SBD 6.1 — Preference Points Claim Form', 'BBBEE preference points claim form as per the Preferential Procurement Policy Framework Act (PPPFA).', 3, 2),
('SBD8_Declaration_Bidders_Past.pdf', 204800, 'application/pdf', 'local', 'scm/SBD8_Declaration_Bidders_Past.pdf', '#', 'SBD 8 — Declaration of Bidder''s Past SCM Practices', 'Declaration regarding past supply chain management practices, abuse, and restrictions.', 4, 2),
('SBD9_Certificate_Independent_Bid.pdf', 163840, 'application/pdf', 'local', 'scm/SBD9_Certificate_Independent_Bid.pdf', '#', 'SBD 9 — Certificate of Independent Bid Determination', 'Certificate confirming that the bid was determined independently without collusion.', 5, 2),
('General_Conditions_of_Contract.pdf', 524288, 'application/pdf', 'local', 'scm/General_Conditions_of_Contract.pdf', '#', 'General Conditions of Contract (GCC)', 'Standard general conditions applicable to all municipal contracts and purchase orders.', 6, 2);

-- =============================================
-- SUPPORTING DOCUMENTS (attached to tenders)
-- =============================================

-- Tender 1 (RFQ/2025/001) - Office Furniture
INSERT INTO HIL_eproc_supporting_docs (document_id, file_name, file_size, file_type, cloud_provider, cloud_key, cloud_url, label, sort_order, uploaded_by) VALUES
(1, 'RFQ2025001_Terms_of_Reference.pdf', 456700, 'application/pdf', 'local', 'tenders/1/RFQ2025001_TOR.pdf', '#', 'Terms of Reference', 1, 2),
(1, 'RFQ2025001_Bill_of_Quantities.xlsx', 89200, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'local', 'tenders/1/RFQ2025001_BOQ.xlsx', '#', 'Bill of Quantities', 2, 2),
(1, 'RFQ2025001_Floor_Plan.pdf', 1245000, 'application/pdf', 'local', 'tenders/1/RFQ2025001_FloorPlan.pdf', '#', 'Building Floor Plan', 3, 2);

-- Tender 2 (RFQ/2025/002) - IT Network
INSERT INTO HIL_eproc_supporting_docs (document_id, file_name, file_size, file_type, cloud_provider, cloud_key, cloud_url, label, sort_order, uploaded_by) VALUES
(2, 'RFQ2025002_Technical_Specifications.pdf', 678900, 'application/pdf', 'local', 'tenders/2/RFQ2025002_TechSpec.pdf', '#', 'Technical Specifications', 1, 2),
(2, 'RFQ2025002_Network_Diagram.pdf', 234500, 'application/pdf', 'local', 'tenders/2/RFQ2025002_NetworkDiagram.pdf', '#', 'Current Network Diagram', 2, 2),
(2, 'RFQ2025002_Site_Survey_Report.pdf', 890100, 'application/pdf', 'local', 'tenders/2/RFQ2025002_SiteSurvey.pdf', '#', 'Site Survey Report', 3, 2),
(2, 'RFQ2025002_Bill_of_Quantities.xlsx', 102400, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'local', 'tenders/2/RFQ2025002_BOQ.xlsx', '#', 'Bill of Quantities', 4, 2);

-- Tender 3 (RFQ/2025/003) - Cleaning
INSERT INTO HIL_eproc_supporting_docs (document_id, file_name, file_size, file_type, cloud_provider, cloud_key, cloud_url, label, sort_order, uploaded_by) VALUES
(3, 'RFQ2025003_Scope_of_Work.pdf', 345600, 'application/pdf', 'local', 'tenders/3/RFQ2025003_SOW.pdf', '#', 'Scope of Work', 1, 3),
(3, 'RFQ2025003_Site_Layout_Plans.pdf', 567800, 'application/pdf', 'local', 'tenders/3/RFQ2025003_SiteLayouts.pdf', '#', 'Site Layout Plans', 2, 3);

-- Tender 5 (RFQ/2025/005) - Fleet Management
INSERT INTO HIL_eproc_supporting_docs (document_id, file_name, file_size, file_type, cloud_provider, cloud_key, cloud_url, label, sort_order, uploaded_by) VALUES
(5, 'RFQ2025005_Fleet_Register.xlsx', 145600, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'local', 'tenders/5/RFQ2025005_FleetRegister.xlsx', '#', 'Current Fleet Register', 1, 3),
(5, 'RFQ2025005_Service_Level_Agreement.pdf', 423500, 'application/pdf', 'local', 'tenders/5/RFQ2025005_SLA.pdf', '#', 'Draft Service Level Agreement', 2, 3),
(5, 'RFQ2025005_Workshop_Requirements.pdf', 289700, 'application/pdf', 'local', 'tenders/5/RFQ2025005_Workshop.pdf', '#', 'Workshop Minimum Requirements', 3, 3);

-- =============================================
-- QUERY THREADS
-- =============================================

-- Thread 1: Bidder 5 asks about Tender 2 (IT Network) - Public
INSERT INTO HIL_eproc_threads (document_id, bidder_id, contact_id, subject, visibility, status) VALUES
(2, 5, 1, 'Query: RFQ/2025/002 — Clarification on Switch Specifications', 'public', 'resolved');

-- Thread 2: Bidder 6 asks about Tender 2 (IT Network) - Private
INSERT INTO HIL_eproc_threads (document_id, bidder_id, contact_id, subject, visibility, status) VALUES
(2, 6, 3, 'Query: RFQ/2025/002 — Site Access for Pre-Bid Survey', 'private', 'open');

-- Thread 3: Bidder 7 asks about Tender 3 (Cleaning) - Public
INSERT INTO HIL_eproc_threads (document_id, bidder_id, contact_id, subject, visibility, status) VALUES
(3, 7, 2, 'Query: RFQ/2025/003 — Staffing Requirements Clarification', 'public', 'open');

-- Thread 4: Bidder 5 asks about Tender 5 (Fleet) - Public
INSERT INTO HIL_eproc_threads (document_id, bidder_id, contact_id, subject, visibility, status) VALUES
(5, 5, 2, 'Query: RFQ/2025/005 — Vehicle Age and Condition', 'public', 'open');

-- =============================================
-- MESSAGES IN THREADS
-- =============================================

-- Thread 1 messages (resolved)
INSERT INTO HIL_eproc_messages (thread_id, sender_id, message, is_read, created_at) VALUES
(1, 5, 'Good day,\n\nWith reference to the Technical Specifications document, page 12, item 3.4 — the specification calls for \"24-port managed switches (Cisco or equivalent)\".\n\nCould you please clarify:\n1. Will the Municipality accept Juniper EX2300 series as an equivalent?\n2. Must the switches support PoE+ (802.3at) or is standard PoE (802.3af) sufficient for the access points?\n\nThank you for your assistance.\n\nRegards,\nMzansi Building Supplies', 1, '2026-02-25 09:15:00'),
(1, 2, 'Dear Bidder,\n\nThank you for your query.\n\n1. Yes, the Juniper EX2300 series is acceptable as an equivalent, provided it meets the minimum specifications outlined in section 3.4 of the Technical Specifications document.\n2. PoE+ (802.3at) is required as the specified Wi-Fi 6 access points draw up to 25.5W.\n\nPlease ensure your pricing reflects PoE+ capable switches.\n\nRegards,\nThandi Nkosi\nSCM Manager', 1, '2026-02-26 14:30:00'),
(1, 5, 'Thank you for the clarification. We will price accordingly.\n\nRegards,\nMzansi Building Supplies', 1, '2026-02-26 16:45:00');

-- Thread 2 messages (open)
INSERT INTO HIL_eproc_messages (thread_id, sender_id, message, is_read, created_at) VALUES
(2, 6, 'Good day,\n\nWe would like to request access to the three municipal buildings mentioned in the tender for a pre-bid site survey. This would allow us to accurately assess the current cabling infrastructure and provide a more precise quotation.\n\nCould you please advise on:\n1. The process for arranging site access\n2. Available dates for the survey\n3. Whether security clearance is required\n\nKind regards,\nLerato Mokoena\nIkwezi IT Solutions', 1, '2026-03-01 11:20:00'),
(2, 4, 'Good day Ms Mokoena,\n\nThank you for your enquiry. Site visits can be arranged through the Infrastructure department.\n\nPlease note that a compulsory briefing session is scheduled for 10 March 2026 at 10:00, during which a guided site tour will be conducted at all three buildings. All prospective bidders are encouraged to attend.\n\nIf you require an additional independent site visit after the briefing, please submit a written request to this thread and we will arrange accordingly.\n\nRegards,\nSipho Dlamini\nInfrastructure & Facilities', 0, '2026-03-02 08:45:00');

-- Thread 3 messages (open)
INSERT INTO HIL_eproc_messages (thread_id, sender_id, message, is_read, created_at) VALUES
(3, 7, 'Good day,\n\nRegarding the cleaning services tender, we require clarification on the following:\n\n1. What is the minimum number of cleaning staff required per facility?\n2. Must the cleaning staff be permanently employed by the service provider, or can contract workers be used?\n3. Is the service provider required to provide uniforms and PPE?\n\nThank you,\nThabo Naidoo\nNaidoo & Associates', 0, '2026-03-03 10:00:00');

-- Thread 4 messages (open)
INSERT INTO HIL_eproc_messages (thread_id, sender_id, message, is_read, created_at) VALUES
(4, 5, 'Good day,\n\nWe have reviewed the Fleet Register and would appreciate clarity on the following:\n\n1. What is the average age of the vehicles in the fleet?\n2. Are there any vehicles currently out of service that will require immediate attention?\n3. Will the Municipality consider a fleet replacement programme as part of this contract?\n\nRegards,\nMzansi Building Supplies', 0, '2026-03-03 14:30:00');

-- =============================================
-- DOWNLOAD AUDIT LOG (sample entries for closed tender)
-- =============================================
INSERT INTO HIL_eproc_downloads (document_id, supporting_doc_id, user_id, ip_address, user_agent, downloaded_at) VALUES
(1, 1, 5, '41.13.252.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0', '2025-01-20 09:12:33'),
(1, 2, 5, '41.13.252.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0', '2025-01-20 09:13:45'),
(1, 1, 6, '105.186.44.210', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', '2025-01-21 14:22:10'),
(1, 2, 6, '105.186.44.210', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', '2025-01-21 14:23:01'),
(1, 3, 6, '105.186.44.210', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15', '2025-01-21 14:24:55'),
(1, 1, 7, '196.21.88.15', 'Mozilla/5.0 (Linux; Android 13) Chrome/122.0.0.0 Mobile', '2025-01-25 08:05:20'),
(2, 1, 5, '41.13.252.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0', '2026-02-22 10:30:00'),
(2, 4, 5, '41.13.252.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0', '2026-02-22 10:31:15'),
(2, 1, 7, '196.21.88.15', 'Mozilla/5.0 (Linux; Android 13) Chrome/122.0.0.0 Mobile', '2026-02-24 16:40:00');
