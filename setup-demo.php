<?php
/**
 * DEMO Tenant Setup Script
 *
 * Creates a complete demo environment with:
 * - Branding configuration for "DEMO" tenant
 * - Users for each role (SCM Manager, SCM Official, Unit Manager, Bidder)
 * - Sample bids (open, closed, draft)
 * - Contact persons
 * - Sample queries and threads
 * - Compliance documents
 */
require_once('/var/www/html/wp-load.php');

echo "╔══════════════════════════════════════════╗\n";
echo "║   eProcurement DEMO Tenant Setup         ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

// ── 1. Configure DEMO Branding ──
echo "1. Setting up DEMO branding...\n";

update_option('eprocurement_brand_name', 'eProcurement Demo');
update_option('eprocurement_brand_url', 'https://demo.eprocurement.co.za');
update_option('eprocurement_support_email', 'demo@eprocurement.co.za');
update_option('eprocurement_login_title', 'Demo Portal');
update_option('eprocurement_brand_tagline', 'Experience the full eProcurement system');

// Use default MyBliss colors for demo (maroon/navy)
// Tenants can override in Settings > Branding

echo "   [DONE] Branding configured\n\n";

// ── 2. Create DEMO Users ──
echo "2. Creating DEMO users...\n";

$demo_users = [
    [
        'login'    => 'demo.scm.manager',
        'email'    => 'scm.manager@demo.eprocurement.co.za',
        'pass'     => 'DemoManager2026!',
        'display'  => 'Sarah Nkosi',
        'first'    => 'Sarah',
        'last'     => 'Nkosi',
        'role'     => 'eprocurement_scm_manager',
        'label'    => 'SCM Manager',
    ],
    [
        'login'    => 'demo.scm.official',
        'email'    => 'scm.official@demo.eprocurement.co.za',
        'pass'     => 'DemoOfficial2026!',
        'display'  => 'Thabo Molefe',
        'first'    => 'Thabo',
        'last'     => 'Molefe',
        'role'     => 'eprocurement_scm_official',
        'label'    => 'SCM Official',
    ],
    [
        'login'    => 'demo.unit.manager',
        'email'    => 'unit.manager@demo.eprocurement.co.za',
        'pass'     => 'DemoUnit2026!',
        'display'  => 'Lerato Dlamini',
        'first'    => 'Lerato',
        'last'     => 'Dlamini',
        'role'     => 'eprocurement_unit_manager',
        'label'    => 'Unit Manager',
    ],
    [
        'login'    => 'demo.bidder',
        'email'    => 'bidder@demo.eprocurement.co.za',
        'pass'     => 'DemoBidder2026!',
        'display'  => 'James van der Merwe',
        'first'    => 'James',
        'last'     => 'van der Merwe',
        'role'     => 'eprocurement_subscriber',
        'label'    => 'Bidder',
    ],
];

$user_ids = [];

foreach ($demo_users as $u) {
    $existing = get_user_by('login', $u['login']);
    if ($existing) {
        $user_ids[$u['role']] = $existing->ID;
        echo "   [EXISTS] {$u['label']}: {$u['login']} (ID: {$existing->ID})\n";
        // Ensure correct role
        $existing->set_role($u['role']);
        continue;
    }

    $user_id = wp_insert_user([
        'user_login'   => $u['login'],
        'user_email'   => $u['email'],
        'user_pass'    => $u['pass'],
        'display_name' => $u['display'],
        'first_name'   => $u['first'],
        'last_name'    => $u['last'],
        'role'         => $u['role'],
    ]);

    if (is_wp_error($user_id)) {
        echo "   [ERROR] {$u['label']}: " . $user_id->get_error_message() . "\n";
        continue;
    }

    $user_ids[$u['role']] = $user_id;
    echo "   [CREATED] {$u['label']}: {$u['login']} / {$u['pass']} (ID: {$user_id})\n";

    // If bidder, create profile
    if ($u['role'] === 'eprocurement_subscriber') {
        global $wpdb;
        $bp_table = $wpdb->prefix . EPROC_TABLE_PREFIX . 'bidder_profiles';
        $wpdb->replace($bp_table, [
            'user_id'         => $user_id,
            'company_name'    => 'Demo Construction (Pty) Ltd',
            'company_reg'     => '2020/123456/07',
            'phone'           => '011-555-0100',
            'verified'        => 1,
            'notify_replies'  => 1,
        ]);
        echo "   [CREATED] Bidder profile: Demo Construction (Pty) Ltd, verified=1\n";
    }
}
echo "\n";

// ── 3. Create Contact Persons ──
echo "3. Creating contact persons...\n";

global $wpdb;
$cp_table = Eprocurement_Database::table('contact_persons');

$contacts = [
    [
        'type'       => 'scm',
        'name'       => 'Sarah Nkosi',
        'email'      => 'scm.manager@demo.eprocurement.co.za',
        'phone'      => '012-555-0001',
        'department' => 'Supply Chain Management',
        'user_id'    => $user_ids['eprocurement_scm_manager'] ?? null,
    ],
    [
        'type'       => 'technical',
        'name'       => 'Thabo Molefe',
        'email'      => 'scm.official@demo.eprocurement.co.za',
        'phone'      => '012-555-0002',
        'department' => 'Technical Services',
        'user_id'    => $user_ids['eprocurement_scm_official'] ?? null,
    ],
    [
        'type'       => 'scm',
        'name'       => 'Lerato Dlamini',
        'email'      => 'unit.manager@demo.eprocurement.co.za',
        'phone'      => '012-555-0003',
        'department' => 'Finance',
        'user_id'    => $user_ids['eprocurement_unit_manager'] ?? null,
    ],
];

$contact_ids = [];
foreach ($contacts as $c) {
    // Check if exists
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$cp_table} WHERE email = %s", $c['email']
    ));
    if ($existing) {
        $contact_ids[] = $existing;
        echo "   [EXISTS] {$c['name']} ({$c['type']})\n";
        continue;
    }

    $wpdb->insert($cp_table, $c);
    $id = $wpdb->insert_id;
    $contact_ids[] = $id;
    echo "   [CREATED] {$c['name']} ({$c['type']}, ID: {$id})\n";
}
echo "\n";

// ── 4. Create Sample Bids ──
echo "4. Creating sample bids...\n";

$doc_table = Eprocurement_Database::table('documents');
$scm_id = $contact_ids[0] ?? 1;
$tech_id = $contact_ids[1] ?? 2;
$creator = $user_ids['eprocurement_scm_manager'] ?? 1;

$bids = [
    [
        'bid_number'       => 'DEMO/BID/2026/001',
        'title'            => 'Supply and Delivery of Office Furniture',
        'description'      => 'The Department invites suitably qualified service providers to submit quotations for the supply and delivery of office furniture including desks, chairs, filing cabinets, and boardroom tables. Items must comply with SABS standards and be delivered within 30 days of order confirmation.',
        'category'         => 'bid',
        'status'           => 'open',
        'opening_date'     => date('Y-m-d H:i:s', strtotime('-5 days')),
        'briefing_date'    => date('Y-m-d H:i:s', strtotime('+5 days')),
        'closing_date'     => date('Y-m-d H:i:s', strtotime('+25 days')),
        'scm_contact_id'   => $scm_id,
        'technical_contact_id' => $tech_id,
        'created_by'       => $creator,
    ],
    [
        'bid_number'       => 'DEMO/BID/2026/002',
        'title'            => 'IT Infrastructure Maintenance Services',
        'description'      => 'A three-year contract for the maintenance and support of the Department\'s IT infrastructure, including servers, networking equipment, desktop computers, and printers. The successful bidder must provide 24/7 support with a 4-hour response time SLA.',
        'category'         => 'bid',
        'status'           => 'open',
        'opening_date'     => date('Y-m-d H:i:s', strtotime('-3 days')),
        'closing_date'     => date('Y-m-d H:i:s', strtotime('+30 days')),
        'scm_contact_id'   => $scm_id,
        'technical_contact_id' => $tech_id,
        'created_by'       => $creator,
    ],
    [
        'bid_number'       => 'DEMO/BID/2026/003',
        'title'            => 'Security Guard Services',
        'description'      => 'Provision of security guard services at six departmental offices. Requirements include armed response, access control, CCTV monitoring, and incident reporting. Bidders must be registered with PSIRA and hold valid Grade A certification.',
        'category'         => 'bid',
        'status'           => 'closed',
        'opening_date'     => date('Y-m-d H:i:s', strtotime('-60 days')),
        'closing_date'     => date('Y-m-d H:i:s', strtotime('-15 days')),
        'scm_contact_id'   => $scm_id,
        'technical_contact_id' => $tech_id,
        'created_by'       => $creator,
    ],
    [
        'bid_number'       => 'DEMO/BID/2026/004',
        'title'            => 'Cleaning and Hygiene Services',
        'description'      => 'Daily cleaning and hygiene services for all departmental buildings including washroom supplies, waste management, and deep cleaning. Contract period: 24 months with option to extend.',
        'category'         => 'bid',
        'status'           => 'draft',
        'closing_date'     => date('Y-m-d H:i:s', strtotime('+45 days')),
        'scm_contact_id'   => $scm_id,
        'created_by'       => $creator,
    ],
    [
        'bid_number'       => 'DEMO/BR/2026/001',
        'title'            => 'Compulsory Briefing Session — Office Furniture',
        'description'      => 'All prospective bidders for DEMO/BID/2026/001 must attend this compulsory briefing session. Venue: Conference Room A, Ground Floor.',
        'category'         => 'briefing_register',
        'status'           => 'draft',
        'created_by'       => $creator,
    ],
    [
        'bid_number'       => 'DEMO/CR/2026/001',
        'title'            => 'Closing Register — Security Guard Services',
        'description'      => 'Record of all bids received for Security Guard Services tender DEMO/BID/2026/003.',
        'category'         => 'closing_register',
        'status'           => 'draft',
        'created_by'       => $creator,
    ],
];

$bid_ids = [];
foreach ($bids as $b) {
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$doc_table} WHERE bid_number = %s", $b['bid_number']
    ));
    if ($existing) {
        $bid_ids[] = $existing;
        echo "   [EXISTS] {$b['bid_number']}: {$b['title']}\n";
        continue;
    }

    $b['created_at'] = current_time('mysql');
    $wpdb->insert($doc_table, $b);
    $id = $wpdb->insert_id;
    $bid_ids[] = $id;
    echo "   [CREATED] {$b['bid_number']}: {$b['title']} (ID: {$id}, status: {$b['status']})\n";
}
echo "\n";

// ── 5. Create Sample Threads/Queries ──
echo "5. Creating sample Q&A threads...\n";

$thread_table = Eprocurement_Database::table('threads');
$msg_table = Eprocurement_Database::table('messages');
$bidder_id = $user_ids['eprocurement_subscriber'] ?? 2;
$open_bid_id = $bid_ids[0] ?? 1;

// Check if threads already exist for this bidder
$existing_threads = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$thread_table} WHERE bidder_id = %d", $bidder_id
));

if ($existing_threads > 0) {
    echo "   [EXISTS] {$existing_threads} threads already exist for demo bidder\n";
} else {
    // Thread 1: Public query
    $wpdb->insert($thread_table, [
        'document_id' => $open_bid_id,
        'bidder_id'   => $bidder_id,
        'contact_id'  => $scm_id,
        'subject'     => 'Query: DEMO/BID/2026/001 — Supply and Delivery of Office Furniture',
        'visibility'  => 'public',
        'status'      => 'open',
        'created_at'  => current_time('mysql'),
    ]);
    $thread1 = $wpdb->insert_id;

    // Message 1: Bidder's query
    $wpdb->insert($msg_table, [
        'thread_id'  => $thread1,
        'sender_id'  => $bidder_id,
        'message'    => 'Good day. Could you please clarify the delivery timeframe? The bid document states 30 days, but does this include weekends and public holidays?',
        'is_read'    => 1,
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
    ]);

    // Message 2: Staff reply
    $staff_id = $user_ids['eprocurement_scm_manager'] ?? 1;
    $wpdb->insert($msg_table, [
        'thread_id'  => $thread1,
        'sender_id'  => $staff_id,
        'message'    => 'Good day. Thank you for your query. The 30-day delivery period refers to 30 calendar days from the date of the purchase order. Weekends and public holidays are included in this count. Please ensure your quotation accounts for this timeline.',
        'is_read'    => 0,
        'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
    ]);

    echo "   [CREATED] Thread 1 (public): Delivery timeframe query + staff reply\n";

    // Thread 2: Private query
    $wpdb->insert($thread_table, [
        'document_id' => $open_bid_id,
        'bidder_id'   => $bidder_id,
        'contact_id'  => $tech_id,
        'subject'     => 'Query: DEMO/BID/2026/001 — Supply and Delivery of Office Furniture',
        'visibility'  => 'private',
        'status'      => 'open',
        'created_at'  => current_time('mysql'),
    ]);
    $thread2 = $wpdb->insert_id;

    $wpdb->insert($msg_table, [
        'thread_id'  => $thread2,
        'sender_id'  => $bidder_id,
        'message'    => 'Hi, we are a Level 1 B-BBEE contributor. Can we submit our BBBEE certificate as part of the bid, and will this be considered for preferential points?',
        'is_read'    => 0,
        'created_at' => current_time('mysql'),
    ]);

    echo "   [CREATED] Thread 2 (private): B-BBEE query (awaiting reply)\n";
}
echo "\n";

// ── 6. Enable All Settings ──
echo "6. Configuring DEMO settings...\n";

// Enable all bid categories
update_option('eprocurement_category_briefing_register', '1');
update_option('eprocurement_category_closing_register', '1');
update_option('eprocurement_category_appointments', '1');
echo "   [DONE] All bid categories enabled\n";

// Enable all notifications
$notifications = [
    'new_bid_notify_bidders' => true,
    'query_notify_contact'   => true,
    'reply_notify_bidder'    => true,
    'status_change_notify'   => true,
];
update_option('eprocurement_notification_settings', wp_json_encode($notifications));
echo "   [DONE] All notifications enabled\n";

// Set retention days
update_option('eprocurement_closed_bid_retention_days', '90');
echo "   [DONE] Closed bid retention: 90 days\n";

// Set page heading
update_option('eprocurement_bid_heading', 'eProcurement Demo Portal');
echo "   [DONE] Page heading: eProcurement Demo Portal\n";

echo "\n";

// ── 7. Summary ──
echo "╔══════════════════════════════════════════╗\n";
echo "║   DEMO Tenant Setup Complete!            ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

echo "DEMO LOGIN CREDENTIALS:\n";
echo "┌───────────────────┬────────────────────────┬────────────────────┐\n";
echo "│ Role              │ Username               │ Password           │\n";
echo "├───────────────────┼────────────────────────┼────────────────────┤\n";
echo "│ Super Admin       │ admin                  │ admin123           │\n";
echo "│ SCM Manager       │ demo.scm.manager       │ DemoManager2026!   │\n";
echo "│ SCM Official      │ demo.scm.official      │ DemoOfficial2026!  │\n";
echo "│ Unit Manager      │ demo.unit.manager      │ DemoUnit2026!      │\n";
echo "│ Bidder            │ demo.bidder             │ DemoBidder2026!    │\n";
echo "└───────────────────┴────────────────────────┴────────────────────┘\n\n";

echo "DEMO URLS:\n";
echo "  Frontend:    http://localhost:8190/tenders/\n";
echo "  Admin:       http://localhost:8190/wp-admin/\n";
echo "  Login:       http://localhost:8190/tenders/login/\n";
echo "  Register:    http://localhost:8190/tenders/register/\n";
echo "  Mailpit:     http://localhost:8191/\n\n";

echo "DEMO DATA:\n";
echo "  Open bids:      2 (Office Furniture, IT Infrastructure)\n";
echo "  Closed bids:    1 (Security Guard Services)\n";
echo "  Draft bids:     1 (Cleaning and Hygiene)\n";
echo "  Categories:     1 Briefing Register, 1 Closing Register\n";
echo "  Contacts:       3 (SCM, Technical, Finance)\n";
echo "  Q&A Threads:    2 (1 public, 1 private)\n";
echo "  Notifications:  All enabled\n\n";
