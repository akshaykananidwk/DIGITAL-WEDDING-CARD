<?php

declare(strict_types=1);

namespace App\Seeds;

use App\Core\Auth;
use App\Services\InvitationService;
use App\Services\SlugService;

/**
 * Optional demo content.
 *
 * Creates one published sample invitation owned by the administrator so a
 * fresh install has something real to look at: a working public page, a QR
 * code, a PDF and a few analytics rows.
 *
 * No fake credentials are created - the demo invitation belongs to the
 * administrator account the installer just made.
 */
final class DemoSeeder extends Seeder
{
    public function __construct(private readonly int $ownerId, ?\App\Core\Database $db = null)
    {
        parent::__construct($db);
    }

    public function run(): void
    {
        if (!$this->isEmpty('invitations')) {
            $this->note('Demo invitation skipped: invitations already exist.');
            return;
        }

        $template = $this->db->first(
            'SELECT * FROM ' . $this->db->wrap($this->db->table('templates')) . "
             WHERE code = 'KANK-001' AND is_active = 1 LIMIT 1"
        );
        if ($template === null) {
            $template = $this->db->first(
                'SELECT * FROM ' . $this->db->wrap($this->db->table('templates')) . '
                 WHERE is_active = 1 ORDER BY id ASC LIMIT 1'
            );
        }
        if ($template === null) {
            $this->note('Demo invitation skipped: no templates are available.');
            return;
        }

        $slugs = new SlugService();
        $nextYear = (int) date('Y') + 1;
        $eventAt = $nextYear . '-12-25 11:30:00';

        $invitationId = $this->db->insert('invitations', [
            'user_id'        => $this->ownerId,
            'template_id'    => (int) $template['id'],
            'category_id'    => $template['category_id'],
            'subcategory_id' => $template['subcategory_id'],
            'title'          => 'Rahul weds Priya',
            'slug'           => $slugs->forTitle('Rahul weds Priya'),
            'short_code'     => $slugs->shortCode(),
            'event_type'     => 'wedding',
            'event_date'     => $nextYear . '-12-25',
            'event_time'     => '11:30:00',
            'event_at'       => $eventAt,
            'timezone'       => 'Asia/Kolkata',
            'language'       => 'en',
            'status'         => 'published',
            'wizard_step'    => 8,
            'theme_overrides' => json_encode([], JSON_UNESCAPED_UNICODE),
            'settings'       => json_encode([
                'show_countdown' => true,
                'show_rsvp'      => true,
                'show_gallery'   => true,
                'show_share'     => true,
                'show_qr'        => true,
                'music_autoplay' => false,
                'skip_animation' => false,
                'watermark'      => false,
            ], JSON_UNESCAPED_UNICODE),
            'meta_title'     => 'Rahul weds Priya - Wedding Invitation',
            'meta_description' => 'You are warmly invited to the wedding of Rahul and Priya on 25 December '
                . $nextYear . ' at Shreeji Party Plot, Dwarka.',
            'published_at'   => $this->now(),
            'created_at'     => $this->now(),
            'updated_at'     => $this->now(),
        ]);

        $values = [
            'invocation'     => '॥ શુભ લગ્ન ॥',
            'groom_name'     => 'Rahul',
            'bride_name'     => 'Priya',
            'groom_parents'  => 'Shri Mahesh & Smt. Nisha Patel',
            'bride_parents'  => 'Shri Kiran & Smt. Hetal Shah',
            'wedding_date'   => $nextYear . '-12-25',
            'wedding_time'   => '11:30:00',
            'mehndi_date'    => $nextYear . '-12-23',
            'mehndi_time'    => '16:00:00',
            'sangeet_date'   => $nextYear . '-12-24',
            'sangeet_time'   => '19:00:00',
            'reception_date' => $nextYear . '-12-25',
            'reception_time' => '20:00:00',
            'venue'          => 'Shreeji Party Plot',
            'venue_address'  => 'Near Dwarkadhish Temple, Dwarka, Gujarat 361335',
            'maps_url'       => 'https://www.google.com/maps/search/?api=1&query=Dwarkadhish+Temple+Dwarka',
            'custom_message' => 'With the blessings of Lord Krishna and the elders of our families, '
                . 'we joyfully invite you to share in the wedding celebrations of our children. '
                . 'Your presence will bless the couple and complete our happiness.',
            'welcome_message' => 'Together with our families, we invite you to celebrate with us',
            'family_names'   => "Patel Family\nShah Family\nMehta Family",
            'rsvp_name'      => 'Mahesh Patel',
            'rsvp_phone'     => '9876543210',
            'whatsapp_number' => '9876543210',
        ];

        $rows = [];
        foreach ($values as $key => $value) {
            $rows[] = [
                'invitation_id' => $invitationId,
                'field_key'     => $key,
                'value'         => $value,
                'value_json'    => null,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ];
        }
        $this->db->insertMany('invitation_data', $rows);

        // Section visibility, matching the builder's defaults.
        $sectionRows = [];
        $order = 0;
        foreach (InvitationService::DEFAULT_SECTIONS as $key => $visible) {
            $order += 10;
            $sectionRows[] = [
                'invitation_id' => $invitationId,
                'section_key'   => $key,
                'title'         => null,
                'is_visible'    => $visible ? 1 : 0,
                'sort_order'    => $order,
                'config'        => null,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ];
        }
        $this->db->insertMany('invitation_sections', $sectionRows);

        // A few RSVP responses so the dashboard is not empty.
        $this->db->insertMany('rsvp', [
            [
                'invitation_id' => $invitationId,
                'name' => 'Amit Trivedi', 'phone' => '9812345670', 'email' => null,
                'response' => 'yes', 'guests' => 4,
                'message' => 'Congratulations! We will all be there.',
                'visitor_hash' => substr(hash('sha256', 'demo-1'), 0, 32),
                'ip_hash' => null, 'is_read' => 0,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ],
            [
                'invitation_id' => $invitationId,
                'name' => 'Sneha Joshi', 'phone' => '9823456781', 'email' => null,
                'response' => 'maybe', 'guests' => 2,
                'message' => 'Will confirm closer to the date.',
                'visitor_hash' => substr(hash('sha256', 'demo-2'), 0, 32),
                'ip_hash' => null, 'is_read' => 0,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ],
            [
                'invitation_id' => $invitationId,
                'name' => 'Rakesh Desai', 'phone' => '9834567892', 'email' => null,
                'response' => 'no', 'guests' => 0,
                'message' => 'Travelling that week - our blessings to the couple.',
                'visitor_hash' => substr(hash('sha256', 'demo-3'), 0, 32),
                'ip_hash' => null, 'is_read' => 1,
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ],
        ]);

        // Analytics for the last fortnight, so the charts have a shape.
        $dailyRows = [];
        for ($day = 13; $day >= 0; $day--) {
            $date = date('Y-m-d', strtotime('-' . $day . ' days'));
            $views = random_int(4, 40);
            $dailyRows[] = [
                'invitation_id' => $invitationId,
                'stat_date'     => $date,
                'views'         => $views,
                'unique_views'  => (int) round($views * 0.7),
                'shares'        => random_int(0, 5),
                'downloads'     => random_int(0, 3),
                'qr_scans'      => random_int(0, 4),
                'rsvps'         => $day === 3 ? 2 : ($day === 7 ? 1 : 0),
                'devices'       => null,
                'created_at'    => $this->now(),
                'updated_at'    => $this->now(),
            ];
        }
        $this->db->insertMany('invitation_daily_stats', $dailyRows);

        $totals = [
            'views' => 0, 'unique' => 0, 'shares' => 0, 'downloads' => 0, 'qr' => 0,
        ];
        foreach ($dailyRows as $row) {
            $totals['views'] += $row['views'];
            $totals['unique'] += $row['unique_views'];
            $totals['shares'] += $row['shares'];
            $totals['downloads'] += $row['downloads'];
            $totals['qr'] += $row['qr_scans'];
        }

        $this->db->update('invitations', [
            'view_count'        => $totals['views'],
            'unique_view_count' => $totals['unique'],
            'share_count'       => $totals['shares'],
            'download_count'    => $totals['downloads'],
            'qr_scan_count'     => $totals['qr'],
            'rsvp_count'        => 3,
            'last_viewed_at'    => $this->now(),
        ], ['id' => $invitationId]);

        $this->db->increment('users', 'invitation_count', ['id' => $this->ownerId]);
        $this->db->increment('templates', 'use_count', ['id' => (int) $template['id']]);

        $this->note('Demo invitation created with RSVP responses and two weeks of analytics.');
    }
}
