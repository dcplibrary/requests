<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Renders in-app help pages (patron and staff documentation from package markdown).
 */
class HelpController extends Controller
{
    /**
     * Render a markdown help page as HTML (patron `user` or staff `admin`).
     *
     * @param  Request  $request  When `popup=1`, uses the popup layout
     * @param  string|null  $page  `user`, `admin`, or default `user`
     * @return \Illuminate\Contracts\View\View
     */
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
            $staffUser = $this->currentStaffUser($request);
            if (! $staffUser || ! $staffUser->isAdmin()) {
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

        $view = $request->boolean('popup') ? 'requests::staff.help.popup' : 'requests::staff.help.show';

        return view($view, [
            'title' => $meta['title'],
            'page'  => $page,
            'html'  => $html,
        ]);
    }

    /**
     * @param  string  $relative  Path relative to the package root (e.g. `docs/help-user.md`)
     */
    private function packageRootPath(string $relative): string
    {
        return dirname(__DIR__, 4) . '/' . ltrim($relative, '/');
    }
}

