<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Repositories\UserRepository;

final class LocaleController extends Controller
{
    public function switch(Request $request): Response
    {
        $locale = (string) $request->param('locale');
        if (array_key_exists($locale, Lang::available())) {
            Lang::setLocale($locale);
            // Remember the choice on the account, so it follows the user.
            $userId = Auth::id();
            if ($userId !== null) {
                (new UserRepository())->update($userId, ['locale' => $locale]);
                Auth::refresh();
            }
        }

        $referer = $request->referer();
        return Response::redirect($referer !== '' ? Url::safeRedirect($referer, '/') : Url::to('/'));
    }
}
