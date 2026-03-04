<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HelpController extends Controller
{
    public function show(Request $request, ?string $page = null)
    {
        $page = $page ?: 'user';

        $allowed = [
            'user'  => [
                'title' => 'Help',
                'path'  => $this->packageRootPath('docs/help-user.md'),
            ],
            'admin' => [
                'title' => 'Admin docs',
                'path'  => $this->packageRootPath('docs/help-admin.md'),
            ],
        ];

        if (! array_key_exists($page, $allowed)) {
            abort(404);
        }

        if ($page === 'admin') {
            $sfpUser = $this->currentSfpUser($request);
            if (! $sfpUser || ! $sfpUser->isAdmin()) {
                abort(403);
            }
        }

        $meta = $allowed[$page];
        $md = @file_get_contents($meta['path']);
        if ($md === false) {
            abort(404);
        }

        $html = method_exists(Str::class, 'markdown')
            ? Str::markdown($md, ['html_input' => 'strip', 'allow_unsafe_links' => false])
            : nl2br(e($md));

        $view = $request->boolean('popup') ? 'sfp::staff.help.popup' : 'sfp::staff.help.show';

        return view($view, [
            'title' => $meta['title'],
            'page'  => $page,
            'html'  => $html,
        ]);
    }

    private function packageRootPath(string $relative): string
    {
        return dirname(__DIR__, 4) . '/' . ltrim($relative, '/');
    }
}

