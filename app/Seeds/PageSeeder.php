<?php

declare(strict_types=1);

namespace App\Seeds;

/**
 * Starter content pages.
 *
 * Privacy and Terms exist from the first minute because the platform starts
 * collecting RSVP data as soon as someone publishes an invitation. They are
 * written as honest, editable drafts, not legal advice, and every page is
 * editable in Admin → Pages.
 */
final class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Privacy Policy',
                'slug'  => 'privacy',
                'excerpt' => 'What we collect, why, and how to have it deleted.',
                'content' => $this->privacy(),
                'sort_order' => 10,
            ],
            [
                'title' => 'Terms of Use',
                'slug'  => 'terms',
                'excerpt' => 'The simple rules for using this service.',
                'content' => $this->terms(),
                'sort_order' => 20,
            ],
            [
                'title' => 'Help & FAQ',
                'slug'  => 'help',
                'excerpt' => 'How to create, share and manage your invitation.',
                'content' => $this->help(),
                'sort_order' => 30,
            ],
            [
                'title' => 'About',
                'slug'  => 'about',
                'excerpt' => 'Why this platform exists.',
                'content' => $this->about(),
                'sort_order' => 40,
            ],
        ];

        $added = 0;
        foreach ($pages as $page) {
            $exists = (int) $this->db->value(
                'SELECT COUNT(*) FROM ' . $this->db->wrap($this->db->table('pages')) . ' WHERE slug = :slug',
                ['slug' => $page['slug']],
                0
            ) > 0;
            if ($exists) {
                continue;
            }
            $this->db->insert('pages', [
                'title'       => $page['title'],
                'slug'        => $page['slug'],
                'content'     => $page['content'],
                'excerpt'     => $page['excerpt'],
                'meta_title'  => $page['title'],
                'meta_description' => $page['excerpt'],
                'status'      => 'published',
                'show_in_footer' => 1,
                'show_in_header' => $page['slug'] === 'help' ? 1 : 0,
                'sort_order'  => $page['sort_order'],
                'locale'      => 'en',
                'created_at'  => $this->now(),
                'updated_at'  => $this->now(),
            ]);
            $added++;
        }
        $this->note($added . ' page(s) added.');
    }

    private function privacy(): string
    {
        return <<<'HTML'
<p><em>This is a starting draft. Review it with your own legal advisor before launch,
and edit it in Admin &rarr; Pages.</em></p>

<h2>What we collect</h2>
<p>We deliberately collect as little as possible.</p>
<ul>
  <li><strong>Your account:</strong> name, email address and (optionally) a mobile number,
      so you can sign in and we can send a password reset.</li>
  <li><strong>Your invitations:</strong> the names, dates, venues, photos and music you enter.
      This is your content. You can edit or delete it at any time.</li>
  <li><strong>RSVP responses:</strong> the name, contact details and message a guest chooses
      to submit through your invitation. These are shown only to you.</li>
  <li><strong>Invitation analytics:</strong> a count of views, shares and downloads, plus a
      coarse device type and browser family.</li>
</ul>

<h2>What we do not collect</h2>
<ul>
  <li>We do <strong>not</strong> store your guests' IP addresses. To count unique visitors we
      store a one-way salted hash that cannot be reversed into an address, and that is
      different for every invitation.</li>
  <li>We do <strong>not</strong> set advertising or tracking cookies, and we do not load
      third-party analytics scripts on invitation pages.</li>
  <li>We do <strong>not</strong> sell or share your data or your guests' data.</li>
</ul>

<h2>Cookies</h2>
<p>We use one essential cookie to keep you signed in, and an optional "remember me" cookie
if you ask us to. Invitation pages set no cookies at all.</p>

<h2>Your photos and music</h2>
<p>Files you upload are stored on our server so they can be shown on your invitation.
Deleting an invitation deletes its files. Please only upload material you have the right
to use.</p>

<h2>Deleting your data</h2>
<p>You can delete any invitation from <strong>My Invitations</strong>, and delete your whole
account from <strong>Profile &rarr; Delete account</strong>. Deleting your account removes your
invitations, uploaded files and RSVP responses. Backups are rotated and the data disappears
from them as older backups are pruned.</p>

<h2>Security</h2>
<p>Passwords are stored as salted hashes and never in readable form. Traffic is served over
HTTPS. Administrative actions are recorded in an audit log.</p>

<h2>Contact</h2>
<p>For any privacy question, or to ask for your data to be exported or erased, contact us
using the details in the footer.</p>
HTML;
    }

    private function terms(): string
    {
        return <<<'HTML'
<p><em>This is a starting draft. Review it with your own legal advisor before launch.</em></p>

<h2>Using the service</h2>
<p>All the main features are free. Create an account, choose a template, fill in your details
and share your invitation link. You keep ownership of everything you write and upload.</p>

<h2>Your responsibilities</h2>
<ul>
  <li>Give accurate event information &mdash; your guests rely on it.</li>
  <li>Only upload photos, music and text you have the right to use.</li>
  <li>Do not use the service to send spam, or to publish unlawful, hateful or misleading
      content.</li>
  <li>Do not attempt to break, overload or probe the platform, or access another user's
      invitations.</li>
</ul>

<h2>Guest data</h2>
<p>If you enable RSVP, you become responsible for the guest details you collect. Use them only
to organise your event.</p>

<h2>Availability</h2>
<p>We work to keep the service running and take regular backups, but it is provided
&ldquo;as is&rdquo; without a guarantee of uninterrupted availability. Please keep your own copy of
important details, and download the PDF of your invitation.</p>

<h2>Suspension</h2>
<p>We may suspend an account that breaks these terms. Where we can, we will tell you why first.</p>

<h2>Changes</h2>
<p>If we change these terms materially, we will note the date of the change on this page.</p>
HTML;
    }

    private function help(): string
    {
        return <<<'HTML'
<h2>Creating your first invitation</h2>
<ol>
  <li><strong>Choose a category</strong> &mdash; wedding, pooja, opening, birthday and more.</li>
  <li><strong>Pick a template.</strong> Use the filters for language, style and colour, or let
      the assistant suggest designs for your occasion.</li>
  <li><strong>Fill in the details.</strong> Only the fields marked required must be completed.
      Everything else is optional and simply hidden if you leave it blank.</li>
  <li><strong>Add photos</strong> and, if you like, background music.</li>
  <li><strong>Customise</strong> the colours and fonts, and switch off any section you do not
      need.</li>
  <li><strong>Preview</strong> on both phone and desktop.</li>
  <li><strong>Publish</strong> &mdash; you get a permanent link and a QR code.</li>
  <li><strong>Share</strong> on WhatsApp with one tap.</li>
</ol>

<h2>Frequently asked questions</h2>

<h3>Can I edit an invitation after sharing it?</h3>
<p>Yes. Edit it any time; the link stays the same and guests always see the current version.</p>

<h3>Can I change the link?</h3>
<p>Yes, from <strong>Share &rarr; Change link</strong>. The old link stops working, so change it
before you share widely.</p>

<h3>Will Gujarati and Hindi text look right?</h3>
<p>Yes. The platform ships with proper Unicode fonts for both scripts, on screen and in the
PDF.</p>

<h3>How do I see who is coming?</h3>
<p>Open your invitation and choose <strong>RSVP</strong>. You can also export the list as CSV.</p>

<h3>Does the music play automatically?</h3>
<p>Only where the browser allows it. Phones usually require a tap first, which is why every
invitation has a visible play button. Guests can always mute it.</p>

<h3>Can I print the invitation?</h3>
<p>Yes. Download the PDF &mdash; it is A4 print-ready and includes the QR code. There is also a
phone-sized version for sharing as a file.</p>

<h3>Is my invitation private?</h3>
<p>Anyone with the link can open it, and invitations are kept out of search engines by default.
You can also protect an invitation with a passphrase.</p>
HTML;
    }

    private function about(): string
    {
        return <<<'HTML'
<h2>Why this exists</h2>
<p>A printed kankotri is beautiful, but it takes days to design, costs money for every copy,
and cannot tell you who is coming. A digital invitation can be ready in minutes, reach every
relative on WhatsApp, hold the whole programme, show the venue on a map, count down to the day
and collect RSVPs &mdash; while still looking like a card a family would be proud to send.</p>

<h2>Built for Indian celebrations</h2>
<p>Everything here is designed around how Indian &mdash; and especially Gujarati &mdash; families
actually celebrate: multi-day functions with haldi, mehndi, sangeet and garba; family names that
matter; Gujarati, Hindi and English side by side; and sharing that happens on WhatsApp.</p>

<h2>Free</h2>
<p>Every feature is free: unlimited invitations, all templates, PDF export, QR codes, RSVP and
analytics.</p>
HTML;
    }
}
