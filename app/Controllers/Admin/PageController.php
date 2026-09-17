<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Core\ValidationException;
use App\Repositories\PageRepository;
use App\Services\AuditService;
use App\Services\SitemapService;
use App\Services\TemplateEngine;

final class PageController extends AdminController
{
    public function __construct(
        private readonly PageRepository $pages = new PageRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.pages.index', 'Pages', [
            'pages' => $this->pages->all('sort_order ASC, title ASC'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->admin('admin.pages.form', 'New page', ['page' => null]);
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $slug = $this->uniqueSlug((string) ($data['slug'] ?? $data['title']));
        $id = $this->pages->create($this->payload($request, $data) + ['slug' => $slug]);

        (new SitemapService())->flush();
        AuditService::instance()->log('admin.page.create', 'page', $id, (string) $data['title']);

        return $this->respond($request, true, 'Page created.', 'admin/pages');
    }

    public function edit(Request $request): Response
    {
        $page = $this->pages->find($request->int('id'));
        if ($page === null) {
            throw HttpException::notFound();
        }
        return $this->admin('admin.pages.form', 'Edit page', ['page' => $page]);
    }

    public function update(Request $request): Response
    {
        $id = $request->int('id');
        $page = $this->pages->find($id);
        if ($page === null) {
            throw HttpException::notFound();
        }

        try {
            $data = $this->validate($request, $this->rules());
        } catch (ValidationException $e) {
            return $this->validationResponse($e, $request);
        }

        $update = $this->payload($request, $data);
        if (isset($data['slug']) && Str::slug((string) $data['slug']) !== (string) $page['slug']) {
            $update['slug'] = $this->uniqueSlug((string) $data['slug'], $id);
        }

        $this->pages->update($id, $update);
        (new SitemapService())->flush();
        AuditService::instance()->logChanges('admin.page.update', 'page', $id, $page, $update);

        return $this->respond($request, true, 'Page updated.', 'admin/pages');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->int('id');
        $page = $this->pages->find($id);
        if ($page === null) {
            throw HttpException::notFound();
        }
        // Privacy and Terms are linked from the footer and the registration
        // form, so deleting them needs an explicit confirmation.
        if (in_array((string) $page['slug'], ['privacy', 'terms'], true) && !$request->bool('force')) {
            return $this->respond(
                $request,
                false,
                'This page is linked from registration and the footer. Confirm to delete it anyway.',
                'admin/pages'
            );
        }

        $this->pages->delete($id);
        (new SitemapService())->flush();
        AuditService::instance()->log('admin.page.delete', 'page', $id, (string) $page['title']);

        return $this->respond($request, true, 'Page deleted.', 'admin/pages');
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'title'   => 'required|string|min:2|max:191|no_html',
            'slug'    => 'nullable|string|max:191',
            'content' => 'nullable|string|max:200000',
            'excerpt' => 'nullable|string|max:500|no_html',
            'meta_title' => 'nullable|string|max:191|no_html',
            'meta_description' => 'nullable|string|max:300|no_html',
            'status'  => 'required|in:draft,published',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'locale'  => 'nullable|locale',
        ];
    }

    /** @return array<string,mixed> */
    private function payload(Request $request, array $data): array
    {
        return [
            'title'   => (string) $data['title'],
            // Page bodies are HTML written by an administrator; sanitise so a
            // compromised admin account cannot plant a script on every page.
            'content' => (new TemplateEngine())->sanitiseHtml((string) ($data['content'] ?? '')),
            'excerpt' => $data['excerpt'] ?? null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'status'  => (string) $data['status'],
            'show_in_footer' => $request->bool('show_in_footer') ? 1 : 0,
            'show_in_header' => $request->bool('show_in_header') ? 1 : 0,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'locale'  => (string) ($data['locale'] ?? 'en'),
            'updated_by' => Auth::id(),
        ];
    }

    private function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'page-' . strtolower(Str::shortCode(5));
        $candidate = $slug;
        $i = 1;
        while ($this->pages->exists('slug', $candidate, $ignoreId)) {
            $candidate = $slug . '-' . (++$i);
        }
        return $candidate;
    }
}
