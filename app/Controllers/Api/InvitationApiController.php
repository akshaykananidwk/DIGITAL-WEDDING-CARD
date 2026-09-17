<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\InvitationDataRepository;
use App\Repositories\InvitationRepository;
use App\Services\InvitationService;
use App\Services\TemplateEngine;

final class InvitationApiController extends Controller
{
    public function __construct(
        private readonly InvitationRepository $invitations = new InvitationRepository(),
        private readonly InvitationService $service = new InvitationService(),
        private readonly InvitationDataRepository $data = new InvitationDataRepository(),
        private readonly TemplateEngine $engine = new TemplateEngine()
    ) {
    }

    public function index(Request $request): Response
    {
        $result = $this->invitations->paginateForUser(
            (int) Auth::id(),
            [
                'status' => (string) $request->query('status', ''),
                'q'      => mb_substr((string) $request->query('q', ''), 0, 80),
            ],
            max(1, $request->int('page', 1)),
            max(1, min(50, $request->int('per_page', 20)))
        );

        return $this->success(
            array_map([$this, 'transform'], $result['rows']),
            '',
            [
                'page'     => $result['page'],
                'pages'    => $result['pages'],
                'total'    => $result['total'],
                'per_page' => $result['per_page'],
            ]
        );
    }

    public function show(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $payload = $this->transform($invitation);
        $payload['content'] = $this->data->forInvitation((int) $invitation['id']);
        $payload['missing_fields'] = $this->service->missingRequiredFields($invitation);

        return $this->success($payload);
    }

    public function store(Request $request): Response
    {
        $templateId = $request->int('template_id');
        if ($templateId <= 0) {
            return $this->error('template_id is required.', 422);
        }

        $invitation = $this->service->createFromTemplate(
            (int) Auth::id(),
            $templateId,
            mb_substr((string) $request->input('title', ''), 0, 180)
        );

        $content = $request->array('content');
        if ($content !== []) {
            $result = $this->service->saveContent($invitation, $content);
            if ($result['errors'] !== []) {
                return $this->error('The invitation was created but some fields were rejected.', 422, $result['errors']);
            }
            $invitation = $this->invitations->find((int) $invitation['id']) ?? $invitation;
        }

        return $this->success($this->transform($invitation), 'Invitation created.');
    }

    public function update(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));

        $content = $request->array('content');
        if ($content !== []) {
            $result = $this->service->saveContent($invitation, $content);
            if ($result['errors'] !== []) {
                return $this->error('Please correct the highlighted fields.', 422, $result['errors']);
            }
        }

        $theme = $request->array('theme');
        $sections = $request->array('sections');
        $settings = $request->array('settings');
        if ($theme !== [] || $sections !== [] || $settings !== []) {
            $this->service->saveDesign($invitation, $theme, $sections, $settings);
        }

        if ($request->filled('title')) {
            $this->invitations->update((int) $invitation['id'], [
                'title' => mb_substr((string) $request->input('title'), 0, 180),
            ]);
        }

        $fresh = $this->invitations->find((int) $invitation['id']);
        return $this->success($this->transform($fresh ?? $invitation), 'Invitation updated.');
    }

    public function publish(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $result = $this->service->publish($invitation);

        if (!$result['ok']) {
            return $this->error('Some required fields are still empty.', 422, ['missing' => $result['missing']]);
        }

        $fresh = $this->invitations->find((int) $invitation['id']);
        return $this->success($this->transform($fresh ?? $invitation), 'Invitation published.');
    }

    public function destroy(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));
        $this->service->delete($invitation);
        return $this->success(null, 'Invitation deleted.');
    }

    /** Server-rendered HTML for a live preview, without saving anything. */
    public function preview(Request $request): Response
    {
        $invitation = $this->service->findOwnedOrFail($request->int('id'));

        $overrides = $request->array('content');
        $themeOverrides = $request->array('theme');
        if ($themeOverrides !== []) {
            $invitation['theme_overrides'] = array_merge(
                is_array($invitation['theme_overrides']) ? $invitation['theme_overrides'] : [],
                $themeOverrides
            );
        }

        $context = $this->engine->context($invitation, $overrides !== [] ? $overrides : null, true);

        return $this->success([
            'html'   => $this->engine->render($invitation, $overrides !== [] ? $overrides : null, true),
            'styles' => $this->engine->styles($context),
            'css_variables' => $context->cssVariables(),
        ]);
    }

    /** @return array<string,mixed> */
    private function transform(array $invitation): array
    {
        return [
            'id'          => (int) $invitation['id'],
            'title'       => (string) $invitation['title'],
            'slug'        => (string) $invitation['slug'],
            'short_code'  => (string) $invitation['short_code'],
            'status'      => (string) $invitation['status'],
            'step'        => (int) $invitation['wizard_step'],
            'template_id' => (int) $invitation['template_id'],
            'language'    => (string) $invitation['language'],
            'event_at'    => $invitation['event_at'],
            'public_url'  => Url::invite((string) $invitation['slug']),
            'short_url'   => Url::shortInvite((string) $invitation['short_code']),
            'pdf_url'     => Url::to('invite/' . $invitation['slug'] . '/pdf'),
            'qr_url'      => Url::to('invite/' . $invitation['slug'] . '/qr.png'),
            'stats'       => [
                'views'     => (int) $invitation['view_count'],
                'unique'    => (int) $invitation['unique_view_count'],
                'shares'    => (int) $invitation['share_count'],
                'downloads' => (int) $invitation['download_count'],
                'rsvps'     => (int) $invitation['rsvp_count'],
            ],
            'created_at' => $invitation['created_at'],
            'updated_at' => $invitation['updated_at'],
        ];
    }
}
