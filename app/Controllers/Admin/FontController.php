<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;
use App\Repositories\FontRepository;
use App\Services\AuditService;
use App\Services\MediaService;

final class FontController extends AdminController
{
    public function __construct(
        private readonly FontRepository $fonts = new FontRepository()
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->admin('admin.fonts', 'Fonts', [
            'fonts'       => $this->fonts->all('script ASC, name ASC'),
            'pdfCapable'  => $this->fonts->pdfCapable(),
        ]);
    }

    public function store(Request $request): Response
    {
        $file = $request->file('font');
        if ($file === null) {
            return $this->respond($request, false, 'Please choose a .ttf or .otf file.', 'admin/fonts');
        }

        try {
            $stored = (new MediaService())->storeFont($file);
        } catch (\Throwable $e) {
            return $this->respond($request, false, $e->getMessage(), 'admin/fonts');
        }

        $name = mb_substr(trim(strip_tags((string) $request->input('name', ''))), 0, 120)
            ?: $stored['family'];
        $family = preg_replace('/[^A-Za-z0-9 \-]/', '', (string) $request->input('family', $stored['family'])) ?: $stored['family'];
        $script = in_array($request->input('script'), ['latin', 'gujarati', 'devanagari', 'multi'], true)
            ? (string) $request->input('script')
            : 'latin';

        $slug = Str::slug($name) ?: 'font-' . strtolower(Str::shortCode(5));
        $candidate = $slug;
        $i = 1;
        while ($this->fonts->exists('slug', $candidate)) {
            $candidate = $slug . '-' . (++$i);
        }

        // Only a TrueType (glyf) font can be embedded by the built-in PDF
        // engine; an OTF/CFF font still works in the browser.
        $pdfCapable = str_ends_with($stored['path'], '.ttf');

        $id = $this->fonts->create([
            'name'         => $name,
            'slug'         => $candidate,
            'family'       => $family,
            'source'       => 'uploaded',
            'file_path'    => $stored['path'],
            'weight'       => preg_replace('/[^0-9]/', '', (string) $request->input('weight', '400')) ?: '400',
            'style'        => $request->input('style') === 'italic' ? 'italic' : 'normal',
            'script'       => $script,
            'category'     => preg_replace('/[^a-z]/', '', (string) $request->input('category', 'sans')) ?: 'sans',
            'preview_text' => mb_substr(trim(strip_tags((string) $request->input('preview_text', ''))), 0, 191) ?: null,
            'pdf_capable'  => $pdfCapable ? 1 : 0,
            'is_active'    => 1,
            'is_default'   => 0,
            'sort_order'   => 500,
        ]);

        $this->fonts->flushCache();
        \App\Core\Cache::forget('css:fontfaces');
        AuditService::instance()->log('admin.font.create', 'font', $id, $name);

        return $this->respond(
            $request,
            true,
            'Font added.' . ($pdfCapable ? '' : ' Note: OpenType/CFF fonts work on screen but not in the built-in PDF engine.'),
            'admin/fonts'
        );
    }

    public function update(Request $request): Response
    {
        $id = $request->int('id');
        $font = $this->fonts->find($id);
        if ($font === null) {
            throw HttpException::notFound();
        }

        $update = [
            'name'       => mb_substr(trim(strip_tags((string) $request->input('name', (string) $font['name']))), 0, 120),
            'is_active'  => $request->bool('is_active') ? 1 : 0,
            'sort_order' => max(0, min(9999, $request->int('sort_order', (int) $font['sort_order']))),
        ];
        if ($request->bool('is_default')) {
            // Only one default per script.
            $this->fonts->updateWhere(['script' => (string) $font['script']], ['is_default' => 0]);
            $update['is_default'] = 1;
        }

        $this->fonts->update($id, $update);
        $this->fonts->flushCache();
        \App\Core\Cache::forget('css:fontfaces');
        AuditService::instance()->log('admin.font.update', 'font', $id, (string) $font['name']);

        return $this->respond($request, true, 'Font updated.', 'admin/fonts');
    }

    public function destroy(Request $request): Response
    {
        $id = $request->int('id');
        $font = $this->fonts->find($id);
        if ($font === null) {
            throw HttpException::notFound();
        }
        if ((string) $font['source'] === 'bundled') {
            return $this->respond(
                $request,
                false,
                'Bundled fonts cannot be deleted - deactivate them instead.',
                'admin/fonts'
            );
        }

        $db = $this->fonts->db();
        $inUse = (int) $db->value(
            'SELECT COUNT(*) FROM ' . $db->wrap($db->table('templates'))
            . ' WHERE deleted_at IS NULL AND (font_heading = :a OR font_body = :b)',
            ['a' => (string) $font['family'], 'b' => (string) $font['family']],
            0
        );
        if ($inUse > 0 && !$request->bool('force')) {
            return $this->respond(
                $request,
                false,
                $inUse . ' template(s) use this font. Confirm to remove it anyway.',
                'admin/fonts'
            );
        }

        $path = (string) ($font['file_path'] ?? '');
        if ($path !== '' && str_starts_with($path, 'fonts/')) {
            (new MediaService())->deleteFiles([$path]);
        }
        $this->fonts->forceDelete($id);
        $this->fonts->flushCache();
        \App\Core\Cache::forget('css:fontfaces');
        AuditService::instance()->log('admin.font.delete', 'font', $id, (string) $font['name']);

        return $this->respond($request, true, 'Font removed.', 'admin/fonts');
    }
}
