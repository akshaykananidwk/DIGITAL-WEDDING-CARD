<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Str;
use App\Repositories\InvitationDataRepository;
use App\Repositories\InvitationMusicRepository;
use App\Repositories\InvitationPhotoRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\InvitationSectionRepository;
use App\Repositories\RsvpRepository;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserRepository;

/**
 * Invitation lifecycle: create from a template, save wizard steps, publish,
 * duplicate and delete.
 *
 * Field values are validated against the template's own field definitions, so
 * a template can be added in the admin panel and immediately have a correct,
 * validated builder form with no code change.
 */
final class InvitationService
{
    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository(),
        private readonly InvitationDataRepository $data = new InvitationDataRepository(),
        private readonly InvitationSectionRepository $sections = new InvitationSectionRepository(),
        private readonly InvitationPhotoRepository $photos = new InvitationPhotoRepository(),
        private readonly InvitationMusicRepository $music = new InvitationMusicRepository(),
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly TemplateFieldRepository $fields = new TemplateFieldRepository(),
        private readonly SlugService $slugs = new SlugService(),
        private readonly TemplateEngine $engine = new TemplateEngine()
    ) {
    }

    /** Sections every invitation understands, with their default state. */
    public const DEFAULT_SECTIONS = [
        'hero'      => true,
        'names'     => true,
        'blessing'  => true,
        'events'    => true,
        'countdown' => true,
        'venue'     => true,
        'map'       => true,
        'family'    => true,
        'gallery'   => true,
        'rsvp'      => true,
        'contact'   => true,
        'share'     => true,
        'qr'        => false,
        'music'     => true,
    ];

    /**
     * Start a new invitation from a template.
     *
     * The invitation is created as a draft, pre-filled with the template's
     * demo data so step 6 (preview) already shows something beautiful.
     */
    public function createFromTemplate(int $userId, int $templateId, string $title = ''): array
    {
        $template = $this->templates->definition($templateId);
        if ($template === null || (int) $template['is_active'] !== 1) {
            throw HttpException::notFound('That template is not available.');
        }

        $this->assertWithinPlanLimit($userId);

        $title = trim($title) !== '' ? trim($title) : (string) $template['name'];
        $title = mb_substr($title, 0, 180);

        return $this->invitations->db()->transaction(function () use ($userId, $template, $title, $templateId): array {
            $slug = $this->slugs->forTitle($title);
            $shortCode = $this->slugs->shortCode();

            $id = $this->invitations->create([
                'user_id'        => $userId,
                'template_id'    => $templateId,
                'category_id'    => $template['category_id'],
                'subcategory_id' => $template['subcategory_id'],
                'title'          => $title,
                'slug'           => $slug,
                'short_code'     => $shortCode,
                'language'       => (string) ($template['language'] === 'multi' ? \App\Core\Lang::locale() : $template['language']),
                'status'         => 'draft',
                'wizard_step'    => 3,
                'settings'       => $this->defaultSettings(),
                'theme_overrides' => [],
                'timezone'       => (string) Config::get('app.timezone', 'Asia/Kolkata'),
            ]);

            $starter = $this->engine->starterValues($template);
            if ($starter !== []) {
                $this->data->saveMany($id, $starter);
            }

            $this->sections->saveMany($id, $this->defaultSectionRows($template));

            $this->templates->incrementUses($templateId);
            (new UserRepository())->incrementInvitationCount($userId);

            AuditService::instance()->log('invitation.create', 'invitation', $id, 'Created from template ' . $template['code']);

            $invitation = $this->invitations->find($id);
            if ($invitation === null) {
                throw new \RuntimeException('The invitation could not be created.');
            }
            return $invitation;
        });
    }

    /** @return array<string,mixed> */
    private function defaultSettings(): array
    {
        return [
            'show_countdown'  => true,
            'show_rsvp'       => true,
            'show_gallery'    => true,
            'show_share'      => true,
            'show_qr'         => false,
            'music_autoplay'  => false,
            'skip_animation'  => false,
            'watermark'       => FeatureFlagService::instance()->enabled('watermark'),
        ];
    }

    /** @return array<string,array{is_visible:bool,sort_order:int}> */
    private function defaultSectionRows(array $template): array
    {
        $rows = [];
        $order = 0;
        foreach (self::DEFAULT_SECTIONS as $key => $visible) {
            $supported = match ($key) {
                'music'     => (int) ($template['supports_music'] ?? 1) === 1,
                'gallery'   => (int) ($template['supports_gallery'] ?? 1) === 1,
                'countdown' => (int) ($template['supports_countdown'] ?? 1) === 1,
                'rsvp'      => (int) ($template['supports_rsvp'] ?? 1) === 1,
                'map'       => (int) ($template['supports_map'] ?? 1) === 1,
                default     => true,
            };
            $rows[$key] = [
                'is_visible' => $visible && $supported,
                'sort_order' => $order += 10,
            ];
        }
        return $rows;
    }

    /**
     * Save the content step.
     *
     * @param array<string,mixed> $input raw request data
     * @return array{errors:array<string,array<int,string>>,saved:int}
     */
    public function saveContent(array $invitation, array $input): array
    {
        $templateId = (int) $invitation['template_id'];
        $definitions = $this->fields->keyed($templateId);

        $rules = [];
        $labels = [];
        $values = [];

        foreach ($definitions as $key => $field) {
            if ((int) $field['is_editable'] !== 1) {
                continue;
            }
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $rules[$key] = $this->rulesFor($field);
            $labels[$key] = (string) $field['label'];
            $values[$key] = $this->normaliseValue($field, $input[$key]);
        }

        if ($rules !== []) {
            $validator = \App\Core\Validator::make($values, $rules, $labels);
            if ($validator->fails()) {
                return ['errors' => $validator->errors(), 'saved' => 0];
            }
        }

        if ($values !== []) {
            $this->data->saveMany((int) $invitation['id'], $values);
        }

        $this->syncEventDateTime((int) $invitation['id'], $values, $definitions);
        $this->refreshTitle($invitation, $values);

        return ['errors' => [], 'saved' => count($values)];
    }

    /** Translate a template field definition into validator rules. */
    private function rulesFor(array $field): string
    {
        $rules = [];
        $rules[] = (int) $field['is_required'] === 1 ? 'required' : 'nullable';

        $rules[] = match ((string) $field['type']) {
            'date'        => 'date',
            'time'        => 'time',
            'datetime'    => 'datetime',
            'number'      => 'numeric',
            'phone'       => 'phone',
            'email'       => 'email',
            'url', 'location' => 'url',
            'color'       => 'hex_color',
            'checkbox'    => 'boolean',
            'gallery', 'multiselect' => 'array',
            default       => 'string',
        };

        if (in_array((string) $field['type'], ['text', 'textarea', 'richtext'], true)) {
            $rules[] = 'safe_text';
        }
        if ((int) ($field['max_length'] ?? 0) > 0) {
            $rules[] = 'max:' . (int) $field['max_length'];
        } elseif (in_array((string) $field['type'], ['text', 'phone', 'url', 'location'], true)) {
            $rules[] = 'max:255';
        } elseif (in_array((string) $field['type'], ['textarea', 'richtext'], true)) {
            $rules[] = 'max:4000';
        }
        if ((string) $field['type'] === 'select' && is_array($field['options'] ?? null) && $field['options'] !== []) {
            $allowed = [];
            foreach ($field['options'] as $option) {
                $allowed[] = is_array($option) ? (string) ($option['value'] ?? '') : (string) $option;
            }
            $allowed = array_filter($allowed);
            if ($allowed !== []) {
                $rules[] = 'in:' . implode(',', $allowed);
            }
        }
        // A field can carry extra rules authored in the admin panel.
        $extra = trim((string) ($field['validation'] ?? ''));
        if ($extra !== '') {
            $rules[] = $extra;
        }

        return implode('|', array_unique($rules));
    }

    private function normaliseValue(array $field, mixed $value): mixed
    {
        $type = (string) $field['type'];

        if (in_array($type, ['gallery', 'multiselect'], true)) {
            return is_array($value) ? array_values(array_map('strval', $value)) : [];
        }
        if ($type === 'checkbox') {
            return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
        }
        if (!is_scalar($value)) {
            return is_array($value) ? $value : '';
        }

        $value = trim((string) $value);

        return match ($type) {
            'phone' => Str::phone($value),
            'date'  => $this->normaliseDate($value),
            'time'  => $this->normaliseTime($value),
            'color' => strtoupper($value),
            default => $value,
        };
    }

    private function normaliseDate(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? $value : date('Y-m-d', $timestamp);
    }

    private function normaliseTime(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime('1970-01-01 ' . $value);
        return $timestamp === false ? $value : date('H:i:s', $timestamp);
    }

    /**
     * Keep invitations.event_date/time/at in step with whichever field the
     * template uses as its primary date. The countdown and the "upcoming
     * events" cron read those columns, so they must not drift.
     */
    private function syncEventDateTime(int $invitationId, array $values, array $definitions): void
    {
        $dateKeys = ['wedding_date', 'event_date', 'function_date', 'ceremony_date', 'opening_date', 'birthday_date'];
        $timeKeys = ['wedding_time', 'event_time', 'function_time', 'ceremony_time', 'opening_time', 'birthday_time'];

        $date = null;
        foreach ($dateKeys as $key) {
            if (isset($values[$key]) && $values[$key] !== '') {
                $date = (string) $values[$key];
                break;
            }
        }
        if ($date === null) {
            // Fall back to the first date field the template declares.
            foreach ($definitions as $key => $field) {
                if ((string) $field['type'] === 'date' && isset($values[$key]) && $values[$key] !== '') {
                    $date = (string) $values[$key];
                    break;
                }
            }
        }
        if ($date === null) {
            return;
        }

        $time = '00:00:00';
        foreach ($timeKeys as $key) {
            if (isset($values[$key]) && $values[$key] !== '') {
                $time = (string) $values[$key];
                break;
            }
        }
        if ($time === '00:00:00') {
            $stored = $this->data->get($invitationId, 'wedding_time') ?? $this->data->get($invitationId, 'event_time');
            if (is_string($stored) && $stored !== '') {
                $time = $stored;
            }
        }

        $timestamp = strtotime($date . ' ' . $time);
        if ($timestamp === false) {
            return;
        }

        $this->invitations->update($invitationId, [
            'event_date' => date('Y-m-d', $timestamp),
            'event_time' => date('H:i:s', $timestamp),
            'event_at'   => date('Y-m-d H:i:s', $timestamp),
        ]);
    }

    /** Derive a friendlier title once the names are known. */
    private function refreshTitle(array $invitation, array $values): void
    {
        if ((string) $invitation['status'] === 'published') {
            return; // a published invitation keeps the title its guests saw
        }
        $groom = trim((string) ($values['groom_name'] ?? ''));
        $bride = trim((string) ($values['bride_name'] ?? ''));
        $celebrant = trim((string) ($values['celebrant_name'] ?? $values['name'] ?? $values['business_name'] ?? ''));

        $title = null;
        if ($groom !== '' && $bride !== '') {
            $title = $groom . ' weds ' . $bride;
        } elseif ($celebrant !== '') {
            $title = $celebrant;
        }
        if ($title === null) {
            return;
        }
        $title = mb_substr($title, 0, 180);
        if ($title === (string) $invitation['title']) {
            return;
        }
        $this->invitations->update((int) $invitation['id'], ['title' => $title]);
    }

    /**
     * Save the design/customisation step.
     *
     * @param array<string,mixed> $overrides
     */
    public function saveDesign(array $invitation, array $overrides, array $sections, array $settings): void
    {
        $allowed = $this->engine->allowedOverrides();
        $clean = [];
        foreach ($overrides as $key => $value) {
            if (isset($allowed[$key]) && $allowed[$key]($value)) {
                $clean[$key] = $value;
            }
        }

        $existingSettings = is_array($invitation['settings'] ?? null) ? $invitation['settings'] : [];
        $booleanSettings = [
            'show_countdown', 'show_rsvp', 'show_gallery', 'show_share', 'show_qr',
            'music_autoplay', 'skip_animation', 'watermark',
        ];
        foreach ($booleanSettings as $key) {
            if (array_key_exists($key, $settings)) {
                $existingSettings[$key] = in_array((string) $settings[$key], ['1', 'true', 'on', 'yes'], true);
            }
        }

        $this->invitations->update((int) $invitation['id'], [
            'theme_overrides' => $clean,
            'settings'        => $existingSettings,
        ]);

        if ($sections !== []) {
            $rows = [];
            $order = 0;
            foreach ($sections as $key => $visible) {
                if (!array_key_exists($key, self::DEFAULT_SECTIONS)) {
                    continue;
                }
                $rows[$key] = [
                    'is_visible' => in_array((string) $visible, ['1', 'true', 'on', 'yes'], true),
                    'sort_order' => $order += 10,
                ];
            }
            if ($rows !== []) {
                $this->sections->saveMany((int) $invitation['id'], $rows);
            }
        }

        AuditService::instance()->log('invitation.design', 'invitation', (int) $invitation['id']);
    }

    /** Publish, generating the QR code and verifying the required fields. */
    public function publish(array $invitation): array
    {
        $missing = $this->missingRequiredFields($invitation);
        if ($missing !== []) {
            return ['ok' => false, 'missing' => $missing];
        }

        $this->invitations->publish((int) $invitation['id']);
        $this->invitations->update((int) $invitation['id'], ['wizard_step' => 8]);

        // Pre-generate the QR so the share step is instant.
        try {
            (new QrService())->forInvitation(array_merge($invitation, ['status' => 'published']), true);
        } catch (\Throwable $e) {
            Logger::warning('QR pre-generation failed: ' . $e->getMessage());
        }

        Cache::forget('invite:' . $invitation['slug']);
        AuditService::instance()->log('invitation.publish', 'invitation', (int) $invitation['id']);

        return ['ok' => true, 'missing' => []];
    }

    public function unpublish(array $invitation): void
    {
        $this->invitations->unpublish((int) $invitation['id']);
        Cache::forget('invite:' . $invitation['slug']);
        AuditService::instance()->log('invitation.unpublish', 'invitation', (int) $invitation['id']);
    }

    /** @return array<int,string> labels of required fields that are still empty */
    public function missingRequiredFields(array $invitation): array
    {
        $values = $this->data->forInvitation((int) $invitation['id']);
        $missing = [];
        foreach ($this->fields->forTemplate((int) $invitation['template_id']) as $field) {
            if ((int) $field['is_required'] !== 1 || (int) $field['is_visible'] !== 1) {
                continue;
            }
            $key = (string) $field['field_key'];
            $value = $values[$key] ?? null;
            $empty = $value === null || $value === '' || (is_array($value) && $value === []);
            if ($empty) {
                $missing[] = (string) $field['label'];
            }
        }
        return $missing;
    }

    /** Change the public link, keeping it unique. */
    public function changeSlug(array $invitation, ?string $desired = null): string
    {
        $slug = $desired === null || trim($desired) === ''
            ? $this->slugs->forTitle((string) $invitation['title'], (int) $invitation['id'])
            : $this->slugs->fromUserInput($desired, (int) $invitation['id']);

        $this->invitations->update((int) $invitation['id'], ['slug' => $slug]);
        Cache::forget('invite:' . $invitation['slug']);

        // The QR points at the URL, so it has to be rebuilt.
        (new QrService())->forgetCache((int) $invitation['id']);
        AuditService::instance()->log(
            'invitation.slug',
            'invitation',
            (int) $invitation['id'],
            'Link changed to ' . $slug
        );

        return $slug;
    }

    public function regenerateShortCode(array $invitation): string
    {
        $code = $this->slugs->shortCode();
        $this->invitations->update((int) $invitation['id'], ['short_code' => $code]);
        (new QrService())->forgetCache((int) $invitation['id']);
        return $code;
    }

    /** Duplicate an invitation with all its content, as a fresh draft. */
    public function duplicate(array $invitation): array
    {
        $this->assertWithinPlanLimit((int) $invitation['user_id']);

        return $this->invitations->db()->transaction(function () use ($invitation): array {
            $title = mb_substr((string) $invitation['title'] . ' (copy)', 0, 180);
            $copy = $invitation;
            unset($copy['id'], $copy['created_at'], $copy['updated_at'], $copy['deleted_at']);
            $copy['title'] = $title;
            $copy['slug'] = $this->slugs->forTitle($title);
            $copy['short_code'] = $this->slugs->shortCode();
            $copy['status'] = 'draft';
            $copy['published_at'] = null;
            $copy['last_viewed_at'] = null;
            $copy['qr_path'] = null;
            foreach (['view_count', 'unique_view_count', 'share_count', 'download_count', 'qr_scan_count', 'rsvp_count'] as $counter) {
                $copy[$counter] = 0;
            }

            $newId = $this->invitations->create($copy);
            $this->data->copy((int) $invitation['id'], $newId);

            $sections = [];
            foreach ($this->sections->forInvitation((int) $invitation['id']) as $key => $section) {
                $sections[$key] = [
                    'is_visible' => (int) $section['is_visible'] === 1,
                    'sort_order' => (int) $section['sort_order'],
                    'title'      => $section['title'],
                ];
            }
            if ($sections !== []) {
                $this->sections->saveMany($newId, $sections);
            }

            // Photos are referenced, not re-uploaded: the same files, new rows.
            foreach ($this->photos->forInvitation((int) $invitation['id']) as $photo) {
                unset($photo['id'], $photo['created_at'], $photo['updated_at']);
                $photo['invitation_id'] = $newId;
                $this->photos->create($photo);
            }
            $track = $this->music->forInvitation((int) $invitation['id']);
            if ($track !== null) {
                unset($track['id'], $track['created_at'], $track['updated_at']);
                $track['invitation_id'] = $newId;
                $this->music->create($track);
            }

            (new UserRepository())->incrementInvitationCount((int) $invitation['user_id']);
            AuditService::instance()->log('invitation.duplicate', 'invitation', $newId, 'Copied from #' . $invitation['id']);

            $created = $this->invitations->find($newId);
            if ($created === null) {
                throw new \RuntimeException('The copy could not be created.');
            }
            return $created;
        });
    }

    /** Soft delete, keeping analytics history for the owner's dashboard. */
    public function delete(array $invitation): void
    {
        $this->invitations->delete((int) $invitation['id']);
        Cache::forget('invite:' . $invitation['slug']);
        (new UserRepository())->incrementInvitationCount((int) $invitation['user_id'], -1);
        AuditService::instance()->log('invitation.delete', 'invitation', (int) $invitation['id']);
    }

    /** Permanently remove an invitation and its uploaded files (GDPR). */
    public function purge(array $invitation): void
    {
        $invitationId = (int) $invitation['id'];
        $media = new MediaService();

        foreach ($this->photos->forInvitation($invitationId) as $photo) {
            $media->deleteFiles([
                (string) $photo['path'],
                (string) ($photo['thumb_path'] ?? ''),
                (string) ($photo['webp_path'] ?? ''),
            ]);
        }
        $track = $this->music->forInvitation($invitationId);
        if ($track !== null && (string) ($track['source'] ?? '') === 'upload') {
            $media->deleteFiles([(string) $track['path']]);
        }
        if (!empty($invitation['qr_path'])) {
            $media->deleteFiles([(string) $invitation['qr_path']]);
        }

        // Child rows go with the parent via ON DELETE CASCADE.
        $this->invitations->forceDelete($invitationId);
        AuditService::instance()->log('invitation.purge', 'invitation', $invitationId);
    }

    /** Owner-scoped fetch used by every builder action. */
    public function findOwnedOrFail(int $id): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw HttpException::unauthorized();
        }
        $invitation = $this->invitations->findOwned($id, $userId);
        if ($invitation === null) {
            // Administrators managing content still need access.
            $invitation = Auth::can('invitations.manage_all') ? $this->invitations->find($id) : null;
        }
        if ($invitation === null) {
            throw HttpException::notFound('Invitation not found.');
        }
        return $invitation;
    }

    public function advanceStep(array $invitation, int $step): void
    {
        $step = max(1, min(8, $step));
        if ($step > (int) $invitation['wizard_step']) {
            $this->invitations->update((int) $invitation['id'], ['wizard_step' => $step]);
        }
    }

    /** Free plan is unlimited today; the check exists for future plans. */
    private function assertWithinPlanLimit(int $userId): void
    {
        $user = (new UserRepository())->find($userId);
        if ($user === null) {
            throw HttpException::forbidden();
        }
        $limits = $this->planLimits($user);
        $max = (int) ($limits['max_invitations'] ?? 0);
        if ($max <= 0) {
            return; // unlimited
        }
        $current = $this->invitations->count(['user_id' => $userId]);
        if ($current >= $max) {
            throw new HttpException(
                403,
                'You have reached the limit of ' . $max . ' invitations for your plan.'
            );
        }
    }

    /** @return array<string,mixed> */
    public function planLimits(array $user): array
    {
        $planId = $user['plan_id'] ?? null;
        if ($planId === null) {
            return [];
        }
        $plan = $this->invitations->db()->first(
            'SELECT limits FROM ' . $this->invitations->db()->wrap($this->invitations->db()->table('plans'))
            . ' WHERE id = :id',
            ['id' => (int) $planId]
        );
        if ($plan === null || !is_string($plan['limits'] ?? null)) {
            return [];
        }
        $decoded = json_decode((string) $plan['limits'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string,mixed> summary shown on the share step */
    public function shareSummary(array $invitation): array
    {
        $share = new ShareService();
        return [
            'public_url'   => \App\Core\Url::invite((string) $invitation['slug']),
            'short_url'    => \App\Core\Url::shortInvite((string) $invitation['short_code']),
            'whatsapp'     => $share->whatsappUrl($invitation),
            'facebook'     => $share->facebookUrl($invitation),
            'telegram'     => $share->telegramUrl($invitation),
            'x'            => $share->xUrl($invitation),
            'email'        => $share->emailUrl($invitation),
            'message'      => $share->message($invitation),
            'qr_png'       => \App\Core\Url::to('invite/' . $invitation['slug'] . '/qr.png'),
            'qr_svg'       => \App\Core\Url::to('invite/' . $invitation['slug'] . '/qr.svg'),
            'pdf'          => \App\Core\Url::to('invite/' . $invitation['slug'] . '/pdf'),
            'rsvp_count'   => (new RsvpRepository())->summary((int) $invitation['id']),
        ];
    }
}
