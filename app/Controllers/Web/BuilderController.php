<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\CategoryRepository;
use App\Repositories\InvitationMusicRepository;
use App\Repositories\InvitationPhotoRepository;
use App\Repositories\MediaRepository;
use App\Repositories\SubcategoryRepository;
use App\Repositories\TemplateFieldRepository;
use App\Repositories\TemplateRepository;
use App\Services\AiService;
use App\Services\InvitationService;
use App\Services\MediaService;
use App\Services\SeoService;
use App\Services\TemplateEngine;
use App\Services\TemplateRecommenderService;

/**
 * The invitation builder.
 *
 * Eight steps, but only three of them need any thought from the user:
 * choose a template, fill in the details, publish. Everything else has a
 * working default, and the live preview updates as they type.
 */
final class BuilderController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitations = new InvitationService(),
        private readonly TemplateRepository $templates = new TemplateRepository(),
        private readonly TemplateFieldRepository $fields = new TemplateFieldRepository(),
        private readonly InvitationPhotoRepository $photos = new InvitationPhotoRepository(),
        private readonly InvitationMusicRepository $music = new InvitationMusicRepository(),
        private readonly TemplateEngine $engine = new TemplateEngine()
    ) {
    }

    // ------------------------------------------------------------------
    //  Step 1 & 2: category and template
    // ------------------------------------------------------------------

    public function start(Request $request): Response
    {
        return $this->view('builder.start', [
            'seo'        => SeoService::make()->title(Lang::get('builder.step_category'))->noindex(),
            'categories' => (new CategoryRepository())->tree(),
            'aiEnabled'  => (new AiService())->isEnabledForUsers(),
        ]);
    }

    public function chooseTemplate(Request $request): Response
    {
        $categories = new CategoryRepository();
        $subcategories = new SubcategoryRepository();

        $categorySlug = (string) $request->query('category', '');
        $subSlug = (string) $request->query('subcategory', '');

        $category = $categorySlug !== '' ? $categories->findBySlug($categorySlug) : null;
        $subcategory = $subSlug !== '' ? $subcategories->findBySlug($subSlug) : null;

        $filters = [
            'active'      => true,
            'category'    => $category === null ? 0 : (int) $category['id'],
            'subcategory' => $subcategory === null ? 0 : (int) $subcategory['id'],
            'q'           => mb_substr((string) $request->query('q', ''), 0, 80),
            'language'    => in_array($request->query('language'), ['en', 'gu', 'hi'], true)
                ? (string) $request->query('language')
                : '',
            'sort'        => 'popular',
        ];

        $result = $this->templates->search($filters, max(1, $request->int('page', 1)), 24);

        $query = array_filter([
            'category'    => $categorySlug,
            'subcategory' => $subSlug,
            'q'           => $filters['q'],
            'language'    => $filters['language'],
        ]);

        return $this->view('builder.templates', [
            'seo'         => SeoService::make()->title(Lang::get('builder.step_template'))->noindex(),
            'templates'   => $result['rows'],
            'pagination'  => $this->paginationMeta($result, Url::to('create/templates'), $query),
            'category'    => $category,
            'subcategory' => $subcategory,
            'subcategories' => $category === null ? [] : $subcategories->forCategory((int) $category['id']),
            'categories'  => $categories->tree(),
            'filters'     => $filters,
            'total'       => $result['total'],
            'aiEnabled'   => (new AiService())->isEnabledForUsers(),
        ]);
    }

    /** AI/rule-based template suggestions for the brief the user typed. */
    public function suggest(Request $request): Response
    {
        $brief = [
            'event_type'  => mb_substr((string) $request->input('event_type', ''), 0, 80),
            'theme'       => mb_substr((string) $request->input('theme', ''), 0, 80),
            'language'    => in_array($request->input('language'), ['en', 'gu', 'hi'], true)
                ? (string) $request->input('language')
                : Lang::locale(),
            'style'       => mb_substr((string) $request->input('style', ''), 0, 40),
            'color'       => (string) $request->input('color', ''),
            'notes'       => mb_substr((string) $request->input('notes', ''), 0, 500),
        ];

        if (($slug = (string) $request->input('subcategory', '')) !== '') {
            $subcategory = (new SubcategoryRepository())->findBySlug($slug);
            if ($subcategory !== null) {
                $brief['subcategory_id'] = (int) $subcategory['id'];
                $brief['category_id'] = (int) $subcategory['category_id'];
            }
        }

        $result = (new TemplateRecommenderService())->recommend($brief, 9);

        if ($request->expectsJson()) {
            return $this->success([
                'templates' => array_map(static fn (array $template): array => [
                    'id'        => (int) $template['id'],
                    'name'      => (string) $template['name'],
                    'slug'      => (string) $template['slug'],
                    'code'      => (string) $template['code'],
                    'color'     => (string) $template['color_primary'],
                    'type'      => (string) $template['type'],
                    'preview'   => Url::to('templates/' . $template['slug'] . '/preview'),
                    'score'     => (int) ($template['match_score'] ?? 0),
                ], $result['templates']),
                'source' => $result['source'],
                'reason' => $result['reason'],
            ]);
        }

        return $this->view('builder.templates', [
            'seo'        => SeoService::make()->title(Lang::get('templates.ai_suggested'))->noindex(),
            'templates'  => $result['templates'],
            'pagination' => null,
            'category'   => null,
            'subcategory' => null,
            'subcategories' => [],
            'categories' => (new CategoryRepository())->tree(),
            'filters'    => [],
            'total'      => count($result['templates']),
            'suggestion' => $result,
            'aiEnabled'  => (new AiService())->isEnabledForUsers(),
        ]);
    }

    public function create(Request $request): Response
    {
        $templateId = $request->int('template_id');
        if ($templateId <= 0) {
            $this->flash('danger', 'Please choose a template first.');
            return $this->redirect('/create');
        }

        $invitation = $this->invitations->createFromTemplate(
            (int) Auth::id(),
            $templateId,
            mb_substr((string) $request->input('title', ''), 0, 180)
        );

        if ($request->expectsJson()) {
            return $this->success([
                'id'       => (int) $invitation['id'],
                'redirect' => Url::to('builder/' . $invitation['id']),
            ], 'Invitation created.');
        }

        return $this->redirect('builder/' . $invitation['id']);
    }

    // ------------------------------------------------------------------
    //  Steps 3-7: the wizard
    // ------------------------------------------------------------------

    public function edit(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $templateId = (int) $invitation['template_id'];

        $step = max(3, min(8, $request->int('step', max(3, (int) $invitation['wizard_step']))));
        $context = $this->engine->context($invitation);

        return $this->view('builder.edit', [
            'seo'        => SeoService::make()->title($this->stepTitle($step))->noindex(),
            'invitation' => $invitation,
            'template'   => $this->templates->find($templateId),
            'step'       => $step,
            'fieldsBySection' => $this->fields->bySection($templateId),
            'sectionLabels'   => \App\Seeds\FieldPresets::SECTIONS,
            'values'     => $context->all(),
            'sections'   => (new \App\Repositories\InvitationSectionRepository())->forInvitation((int) $invitation['id']),
            'photos'     => $this->photos->forInvitation((int) $invitation['id']),
            'music'      => $this->music->forInvitation((int) $invitation['id']),
            'musicLibrary' => (new MediaRepository())->library('audio', 24),
            'fonts'      => (new \App\Repositories\FontRepository())->options(),
            'palettes'   => \App\Seeds\ThemePalettes::all(),
            'theme'      => $this->engine->resolveTheme((array) $this->templates->find($templateId), $invitation),
            'missing'    => $this->invitations->missingRequiredFields($invitation),
            'aiEnabled'  => (new AiService())->isEnabledForUsers(),
            'maxPhotos'  => (int) config('uploads.max_photos', 30),
            'share'      => $step >= 8 ? $this->invitations->shareSummary($invitation) : null,
        ]);
    }

    private function stepTitle(int $step): string
    {
        return match ($step) {
            3 => Lang::get('builder.step_details'),
            4 => Lang::get('builder.step_photos'),
            5 => Lang::get('builder.step_design'),
            6 => Lang::get('builder.step_preview'),
            7 => Lang::get('builder.step_publish'),
            8 => Lang::get('builder.step_share'),
            default => Lang::get('builder.step_details'),
        };
    }

    public function saveContent(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));

        $input = $request->array('fields');
        $result = $this->invitations->saveContent($invitation, $input);

        if ($result['errors'] !== []) {
            if ($request->expectsJson()) {
                return $this->error('Please correct the highlighted fields.', 422, $result['errors']);
            }
            return $this->back($result['errors'], ['fields' => $input]);
        }

        $this->invitations->advanceStep($invitation, 4);

        if ($request->expectsJson()) {
            return $this->success(['saved' => $result['saved']], Lang::get('builder.saved'));
        }

        $this->flash('success', Lang::get('builder.saved'));
        return $this->redirect('builder/' . $invitation['id'] . '?step=' . $request->int('next', 4));
    }

    public function saveDesign(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));

        $this->invitations->saveDesign(
            $invitation,
            $request->array('theme'),
            $request->array('sections'),
            $request->array('settings')
        );
        $this->invitations->advanceStep($invitation, 6);

        if ($request->expectsJson()) {
            return $this->success(null, Lang::get('builder.saved'));
        }
        $this->flash('success', Lang::get('builder.saved'));
        return $this->redirect('builder/' . $invitation['id'] . '?step=6');
    }

    // ------------------------------------------------------------------
    //  Photos and music
    // ------------------------------------------------------------------

    public function uploadPhotos(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $invitationId = (int) $invitation['id'];

        $files = $request->fileList('photos');
        if ($files === []) {
            return $request->expectsJson()
                ? $this->error('Please choose at least one photo.', 422)
                : $this->back(['photos' => ['Please choose at least one photo.']]);
        }

        $maxPhotos = (int) config('uploads.max_photos', 30);
        $existing = $this->photos->countForInvitation($invitationId);
        $role = $request->input('role') === 'hero' ? 'hero' : 'gallery';

        $media = new MediaService();
        $uploaded = [];
        $errors = [];

        foreach ($files as $file) {
            if ($role === 'gallery' && ($existing + count($uploaded)) >= $maxPhotos) {
                $errors[] = 'Only ' . $maxPhotos . ' photos are allowed per invitation.';
                break;
            }
            try {
                $stored = $media->storeImage($file, 'photos');
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
                continue;
            }

            // A hero photo replaces the previous one.
            if ($role === 'hero') {
                foreach ($this->photos->forInvitation($invitationId, 'hero') as $old) {
                    $media->deleteFiles([
                        (string) $old['path'],
                        (string) ($old['thumb_path'] ?? ''),
                        (string) ($old['webp_path'] ?? ''),
                    ]);
                    $this->photos->forceDelete((int) $old['id']);
                }
            }

            $photoId = $this->photos->create([
                'invitation_id' => $invitationId,
                'path'          => $stored['path'],
                'thumb_path'    => $stored['thumb_path'],
                'webp_path'     => $stored['webp_path'],
                'caption'       => null,
                'role'          => $role,
                'width'         => $stored['width'],
                'height'        => $stored['height'],
                'size'          => $stored['size'],
                'sort_order'    => $this->photos->nextSortOrder($invitationId),
            ]);

            (new \App\Repositories\UserRepository())->addStorageUsed((int) Auth::id(), $stored['size']);

            $uploaded[] = [
                'id'    => $photoId,
                'url'   => Url::upload($stored['path']),
                'thumb' => $stored['thumb_path'] === null ? Url::upload($stored['path']) : Url::upload($stored['thumb_path']),
                'role'  => $role,
            ];
        }

        $this->invitations->advanceStep($invitation, 5);

        if ($request->expectsJson()) {
            return $uploaded === []
                ? $this->error(implode(' ', $errors) ?: 'No photos were uploaded.', 422)
                : $this->success(['photos' => $uploaded, 'errors' => $errors], count($uploaded) . ' photo(s) uploaded.');
        }

        if ($errors !== []) {
            $this->flash('warning', implode(' ', $errors));
        }
        if ($uploaded !== []) {
            $this->flash('success', count($uploaded) . ' photo(s) uploaded.');
        }
        return $this->redirect('builder/' . $invitationId . '?step=4');
    }

    public function reorderPhotos(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $order = array_map('intval', $request->array('order'));
        if ($order !== []) {
            $this->photos->reorder((int) $invitation['id'], $order);
        }
        return $request->expectsJson()
            ? $this->success(null, 'Order saved.')
            : $this->redirect('builder/' . $invitation['id'] . '?step=4');
    }

    public function deletePhoto(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $photo = $this->photos->findOwned($request->int('photoId'), (int) $invitation['id']);
        if ($photo === null) {
            throw HttpException::notFound('That photo no longer exists.');
        }

        (new MediaService())->deleteFiles([
            (string) $photo['path'],
            (string) ($photo['thumb_path'] ?? ''),
            (string) ($photo['webp_path'] ?? ''),
        ]);
        $this->photos->forceDelete((int) $photo['id']);
        (new \App\Repositories\UserRepository())->addStorageUsed((int) Auth::id(), -(int) $photo['size']);

        return $request->expectsJson()
            ? $this->success(null, 'Photo removed.')
            : $this->redirect('builder/' . $invitation['id'] . '?step=4');
    }

    public function saveMusic(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $invitationId = (int) $invitation['id'];

        $file = $request->file('music');
        $libraryId = $request->int('library_id');

        if ($file !== null) {
            try {
                $stored = (new MediaService())->storeAudio($file, 'music');
            } catch (\Throwable $e) {
                return $request->expectsJson()
                    ? $this->error($e->getMessage(), 422)
                    : $this->back(['music' => [$e->getMessage()]]);
            }
            $this->music->replace($invitationId, [
                'path'       => $stored['path'],
                'title'      => $stored['original_name'],
                'source'     => 'upload',
                'autoplay'   => $request->bool('autoplay') ? 1 : 0,
                'loop_track' => 1,
                'volume'     => max(0, min(100, $request->int('volume', 70))),
                'size'       => $stored['size'],
            ]);
            (new \App\Repositories\UserRepository())->addStorageUsed((int) Auth::id(), $stored['size']);
        } elseif ($libraryId > 0) {
            $track = (new MediaRepository())->find($libraryId);
            if ($track === null || (string) $track['kind'] !== 'audio') {
                return $this->error('That track is not available.', 422);
            }
            $this->music->replace($invitationId, [
                'path'       => (string) $track['path'],
                'title'      => (string) ($track['title'] ?: $track['original_name']),
                'source'     => 'library',
                'autoplay'   => $request->bool('autoplay') ? 1 : 0,
                'loop_track' => 1,
                'volume'     => max(0, min(100, $request->int('volume', 70))),
                'size'       => (int) $track['size'],
            ]);
        } else {
            return $request->expectsJson()
                ? $this->error('Choose a track or upload an MP3.', 422)
                : $this->back(['music' => ['Choose a track or upload an MP3.']]);
        }

        if ($request->expectsJson()) {
            return $this->success(null, 'Music saved.');
        }
        $this->flash('success', 'Music saved.');
        return $this->redirect('builder/' . $invitationId . '?step=4');
    }

    public function deleteMusic(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $track = $this->music->forInvitation((int) $invitation['id']);
        if ($track !== null) {
            if ((string) ($track['source'] ?? '') === 'upload') {
                (new MediaService())->deleteFiles([(string) $track['path']]);
            }
            $this->music->deleteForInvitation((int) $invitation['id']);
        }
        return $request->expectsJson()
            ? $this->success(null, 'Music removed.')
            : $this->redirect('builder/' . $invitation['id'] . '?step=4');
    }

    // ------------------------------------------------------------------
    //  Preview, publish, share
    // ------------------------------------------------------------------

    /** Rendered inside the builder's preview iframe. */
    public function preview(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));

        // Unsaved values may be posted in for a live preview.
        $overrides = $request->array('values');
        $themeOverrides = $request->array('theme');
        if ($themeOverrides !== []) {
            $invitation['theme_overrides'] = array_merge(
                is_array($invitation['theme_overrides']) ? $invitation['theme_overrides'] : [],
                $themeOverrides
            );
        }

        $context = $this->engine->context($invitation, $overrides !== [] ? $overrides : null, true);

        return $this->view('invite.shell', [
            'c'         => $context,
            'body'      => $this->engine->render($invitation, $overrides !== [] ? $overrides : null, true),
            'styles'    => $this->engine->styles($context),
            'scripts'   => $this->engine->scripts($context),
            'seo'       => SeoService::make()->title((string) $invitation['title'])->noindex(),
            'isPreview' => true,
            'liveEdit'  => true,
        ]);
    }

    public function publish(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $result = $this->invitations->publish($invitation);

        if (!$result['ok']) {
            $message = Lang::get('builder.missing_fields', ['fields' => implode(', ', $result['missing'])]);
            if ($request->expectsJson()) {
                return $this->error($message, 422, ['missing' => $result['missing']]);
            }
            $this->flash('warning', $message);
            return $this->redirect('builder/' . $invitation['id'] . '?step=3');
        }

        if ($request->expectsJson()) {
            return $this->success([
                'redirect' => Url::to('builder/' . $invitation['id'] . '/share'),
                'url'      => Url::invite((string) $invitation['slug']),
            ], Lang::get('builder.published'));
        }

        $this->flash('success', Lang::get('builder.published'));
        return $this->redirect('builder/' . $invitation['id'] . '/share');
    }

    public function unpublish(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $this->invitations->unpublish($invitation);

        if ($request->expectsJson()) {
            return $this->success(null, 'Invitation unpublished.');
        }
        $this->flash('info', 'Your invitation is no longer public.');
        return $this->redirect('invitations');
    }

    public function share(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));

        return $this->view('builder.share', [
            'seo'        => SeoService::make()->title(Lang::get('builder.step_share'))->noindex(),
            'invitation' => $invitation,
            'share'      => $this->invitations->shareSummary($invitation),
            'step'       => 8,
        ]);
    }

    public function changeSlug(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));

        try {
            $slug = $this->invitations->changeSlug($invitation, (string) $request->input('slug', ''));
        } catch (\InvalidArgumentException $e) {
            return $request->expectsJson()
                ? $this->error($e->getMessage(), 422)
                : $this->back(['slug' => [$e->getMessage()]]);
        }

        if ($request->expectsJson()) {
            return $this->success(['slug' => $slug, 'url' => Url::invite($slug)], 'Link updated.');
        }
        $this->flash('success', 'Your invitation link is now ' . Url::invite($slug));
        return $this->redirect('builder/' . $invitation['id'] . '/share');
    }

    public function duplicate(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $copy = $this->invitations->duplicate($invitation);

        if ($request->expectsJson()) {
            return $this->success([
                'id'       => (int) $copy['id'],
                'redirect' => Url::to('builder/' . $copy['id']),
            ], 'Copy created.');
        }
        $this->flash('success', 'A copy has been created as a draft.');
        return $this->redirect('builder/' . $copy['id']);
    }

    public function destroy(Request $request): Response
    {
        $invitation = $this->invitations->findOwnedOrFail($request->int('id'));
        $this->invitations->delete($invitation);

        if ($request->expectsJson()) {
            return $this->success(['redirect' => Url::to('invitations')], 'Invitation deleted.');
        }
        $this->flash('success', 'Invitation deleted.');
        return $this->redirect('invitations');
    }
}
