<?php

namespace App\Http\Middleware;

use App\Models\Form;
use App\Services\PublicFormCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-30 publishing controls: a published form whose owner ticked "Allow embedding"
 * may be framed by other sites. Only the public form route carries this middleware,
 * and only an owner-set flag opens it — everything else on the host keeps the
 * fleet's SAMEORIGIN framing. Enforced CSP frame-ancestors is the governing header;
 * SecurityHeaders sees it and leaves X-Frame-Options off this response.
 */
class AllowFormEmbedding
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $slug = (string) $request->route('slug');

        if ($slug === '' || $request->boolean('preview')) {
            return $response;
        }

        // Read through the same cache the component fills while rendering this very
        // request, so a cached public view stays at zero form queries. A miss here
        // (the page 404ed) simply leaves the default framing in place.
        try {
            $form = app(PublicFormCache::class)->remember($slug, false, function () use ($slug): Form {
                /** @var Form */
                return Form::withoutTeamScope()
                    ->where('slug', $slug)
                    ->whereNull('archived_at')
                    ->where('is_published', true)
                    ->with(['fields' => fn ($fields) => $fields->orderBy('order')])
                    ->firstOrFail();
            });
        } catch (\Throwable) {
            return $response;
        }

        if ((bool) data_get($form->settings, 'allow_embed', false)) {
            $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }
}
